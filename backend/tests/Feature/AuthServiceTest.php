<?php

use App\Models\User;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendSmsOtpJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requests an OTP successfully', function () {
    Queue::fake();

    $response = $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911000000',
    ]);

    $response->assertStatus(200)
             ->assertJson(['message' => 'OTP sent successfully']);

    Queue::assertPushed(SendSmsOtpJob::class);
    $this->assertDatabaseHas('otp_codes', [
        'phone' => '0911000000',
    ]);
});

it('verifies valid OTP and returns token', function () {
    // Generate valid OTP
    $this->postJson('/api/v1/auth/send-otp', [
        'phone' => '0911000000',
    ]);
    
    // In local env, code is always 123456 as per AuthService
    $response = $this->postJson('/api/v1/auth/verify-otp', [
        'phone' => '0911000000',
        'code' => '123456',
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure(['token', 'user' => ['id', 'phone', 'type', 'guest_uuid']]);
             
    $this->assertDatabaseHas('users', [
        'phone' => '0911000000',
        'type' => 'guest',
    ]);
});

it('migrates guest to registered user', function () {
    // First, login to get a guest user token
    $this->postJson('/api/v1/auth/send-otp', ['phone' => '0911000000']);
    $verifyRes = $this->postJson('/api/v1/auth/verify-otp', ['phone' => '0911000000', 'code' => '123456']);
    $token = $verifyRes->json('token');

    // Migrate
    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/v1/auth/migrate', [
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $response->assertStatus(200)
             ->assertJson(['message' => 'Account upgraded successfully']);
             
    $this->assertDatabaseHas('users', [
        'phone' => '0911000000',
        'full_name' => 'John Doe',
        'email' => 'john@example.com',
        'type' => 'buyer',
    ]);
});
