<?php

use App\Jobs\SendSmsOtpJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('requests an OTP successfully with reCAPTCHA', function () {
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

it('verifies valid OTP and returns token', function () {
    // Generate valid OTP
    $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911000000',
        'g-recaptcha-response' => 'test_token',
    ]);

    // In local/testing env, code is always 123456 as per AuthService
    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '0911000000',
        'code' => '123456',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'user' => ['id', 'phone', 'type', 'guest_uuid']]);

    $this->assertDatabaseHas('users', [
        'phone' => '+251911000000',
        'type' => 'guest',
    ]);
});

it('migrates guest to registered user', function () {
    // First, login to get a guest user token
    $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911000000',
        'g-recaptcha-response' => 'test_token',
    ]);
    $verifyRes = $this->postJson('/api/v1/auth/verify-otp', ['phone' => '0911000000', 'code' => '123456']);
    $token = $verifyRes->json('token');

    // Migrate
    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/migrate', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Account upgraded successfully']);

    $this->assertDatabaseHas('users', [
        'phone' => '+251911000000',
        'full_name' => 'John Doe',
        'email' => 'john@example.com',
        'type' => 'buyer',
    ]);
});

it('aborts guest migration with 409 Conflict if email is already taken', function () {
    // Create an existing buyer with the email
    User::factory()->create(['email' => 'existing@example.com']);

    // Login as a guest
    $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911000001',
        'g-recaptcha-response' => 'test_token',
    ]);
    $verifyRes = $this->postJson('/api/v1/auth/verify-otp', ['phone' => '0911000001', 'code' => '123456']);
    $token = $verifyRes->json('token');

    // Attempt migration with conflicting email
    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/migrate', [
            'full_name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $response->assertStatus(409)
        ->assertJson(['error_code' => 'EMAIL_ALREADY_REGISTERED']);
});

it('locks account for 15 minutes after 5 consecutive failed login attempts', function () {
    $user = User::factory()->create([
        'email' => 'lockout@example.com',
        'password' => bcrypt('correct_password'),
    ]);

    for ($i = 1; $i <= 4; $i++) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'lockout@example.com',
            'password' => 'wrong_password',
        ]);
        $response->assertStatus(422);
    }

    // 5th failed attempt triggers lock
    $response5 = $this->postJson('/api/v1/auth/login', [
        'email' => 'lockout@example.com',
        'password' => 'wrong_password',
    ]);
    $response5->assertStatus(422);

    $user->refresh();
    expect($user->isLocked())->toBeTrue();
    expect($user->login_attempt_count)->toBe(5);

    // 6th attempt is blocked with 423 Locked
    $response6 = $this->postJson('/api/v1/auth/login', [
        'email' => 'lockout@example.com',
        'password' => 'correct_password',
    ]);
    $response6->assertStatus(423)
        ->assertJson(['error_code' => 'ACCOUNT_LOCKED']);
});
