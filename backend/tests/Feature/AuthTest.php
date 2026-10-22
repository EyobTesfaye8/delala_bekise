<?php

use App\Jobs\SendSmsOtpJob;
use App\Models\User;
use App\Notifications\VerifyEmailMagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('FR-1.1: validates Ethiopian phone formats and rejects invalid numbers', function () {
    // Invalid phone
    $response = $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0812345678', // Not starting with 09 or 07
        'g-recaptcha-response' => 'test_token',
    ]);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);

    // Valid Ethiopian phone (09 format)
    $response09 = $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911223344',
        'g-recaptcha-response' => 'test_token',
    ]);
    $response09->assertStatus(200);

    // Valid Ethiopian phone (07 format)
    $response07 = $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0711223344',
        'g-recaptcha-response' => 'test_token',
    ]);
    $response07->assertStatus(200);
});

it('FR-1.1: generates OTP and dispatches SMS job', function () {
    Queue::fake();

    $response = $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911000000',
        'g-recaptcha-response' => 'test_token',
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'OTP sent successfully']);

    Queue::assertPushed(SendSmsOtpJob::class);

    $this->assertDatabaseHas('otp_codes', [
        'phone' => '+251911000000',
    ]);
});

it('FR-1.1: verifies valid OTP and returns 24-hour guest Sanctum token', function () {
    $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911000000',
        'g-recaptcha-response' => 'test_token',
    ]);

    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '0911000000',
        'code' => '123456',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'phone', 'type', 'guest_uuid'],
        ]);

    $this->assertDatabaseHas('users', [
        'phone' => '+251911000000',
        'type' => 'guest',
    ]);
});

it('FR-1.2: recovers guest session with same user ID when re-authenticating same phone', function () {
    // First login
    $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911333444',
        'g-recaptcha-response' => 'test_token',
    ]);
    $firstRes = $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '0911333444',
        'code' => '123456',
    ]);
    $userId = $firstRes->json('user.id');
    $guestUuid = $firstRes->json('user.guest_uuid');

    // Second login with same phone
    $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911333444',
        'g-recaptcha-response' => 'test_token',
    ]);
    $secondRes = $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '0911333444',
        'code' => '123456',
    ]);

    expect($secondRes->json('user.id'))->toBe($userId);
    expect($secondRes->json('user.guest_uuid'))->toBe($guestUuid);
    expect(User::where('phone', '+251911333444')->count())->toBe(1);
});

it('FR-1.3: direct registration creates unverified account and dispatches OTP & email verification', function () {
    Notification::fake();
    Queue::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Sara Haile',
        'phone' => '0912345678',
        'email' => 'sara@example.com',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('user.status', 'unverified')
        ->assertJsonPath('user.type', 'buyer');

    $user = User::where('email', 'sara@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->phone)->toBe('+251912345678');
    expect($user->status)->toBe('unverified');

    // Notification and queue dispatched
    Notification::assertSentTo($user, VerifyEmailMagicLink::class);
    Queue::assertPushed(SendSmsOtpJob::class);
});

it('FR-1.3: verifies email magic link and activates user when phone is verified', function () {
    $user = User::factory()->create([
        'status' => 'unverified',
        'email_verified_at' => null,
        'phone_verified_at' => now(), // phone already verified via OTP
    ]);

    $signedUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
    );

    $response = $this->getJson($signedUrl);
    $response->assertStatus(200)
        ->assertJson(['message' => 'Email verified successfully!']);

    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeTrue();
    expect($user->status)->toBe('active');
});

it('FR-1.4: migrates guest to registered buyer in-place', function () {
    $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911777888',
        'g-recaptcha-response' => 'test_token',
    ]);
    $verifyRes = $this->postJson('/api/v1/auth/verify-otp', ['phone' => '0911777888', 'code' => '123456']);
    $token = $verifyRes->json('token');
    $guestId = $verifyRes->json('user.id');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/migrate', [
            'full_name' => 'Dawit Bekele',
            'email' => 'dawit@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Account upgraded successfully']);

    $user = User::find($guestId);
    expect($user->type)->toBe('buyer');
    expect($user->email)->toBe('dawit@example.com');
    expect($user->full_name)->toBe('Dawit Bekele');
});

it('FR-1.5: returns 409 Conflict EMAIL_ALREADY_REGISTERED if guest tries to migrate to existing email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911999000',
        'g-recaptcha-response' => 'test_token',
    ]);
    $verifyRes = $this->postJson('/api/v1/auth/verify-otp', ['phone' => '0911999000', 'code' => '123456']);
    $token = $verifyRes->json('token');

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/migrate', [
            'full_name' => 'Imposter',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $response->assertStatus(409)
        ->assertJson(['error_code' => 'EMAIL_ALREADY_REGISTERED']);
});

it('NFR-SEC-2: locks account for 15 minutes after 5 consecutive failed login attempts', function () {
    $user = User::factory()->create([
        'email' => 'lockout_test@example.com',
        'password' => bcrypt('correct_password'),
    ]);

    for ($i = 1; $i <= 5; $i++) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'lockout_test@example.com',
            'password' => 'wrong_pass',
        ]);
        $response->assertStatus(422);
    }

    $user->refresh();
    expect($user->isLocked())->toBeTrue();
    expect($user->login_attempt_count)->toBe(5);

    // 6th attempt is blocked with 423
    $response6 = $this->postJson('/api/v1/auth/login', [
        'email' => 'lockout_test@example.com',
        'password' => 'correct_password',
    ]);
    $response6->assertStatus(423)
        ->assertJson(['error_code' => 'ACCOUNT_LOCKED']);
});
