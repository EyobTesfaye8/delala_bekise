<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['type' => 'delala']);
});

it('SRS §9.1: rejects unauthenticated requests to media presign', function () {
    $response = $this->postJson('/api/v1/media/presign', [
        'file_type' => 'kyc_id',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 102400,
    ]);

    $response->assertStatus(401);
});

it('SRS §9.1: validates file_type, mime_type and file size constraints', function () {
    // 1. Invalid file_type
    $response = $this->actingAs($this->user)->postJson('/api/v1/media/presign', [
        'file_type' => 'invalid_type',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 102400,
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors(['file_type']);

    // 2. File size exceeds 5MB
    $response = $this->actingAs($this->user)->postJson('/api/v1/media/presign', [
        'file_type' => 'kyc_id',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 6000000, // 6MB > 5MB limit
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors(['file_size_bytes']);

    // 3. PDF is NOT allowed for proposal images
    $response = $this->actingAs($this->user)->postJson('/api/v1/media/presign', [
        'file_type' => 'proposal_image',
        'mime_type' => 'application/pdf',
        'file_size_bytes' => 102400,
    ]);
    $response->assertStatus(422)->assertJsonValidationErrors(['mime_type']);

    // 4. PDF IS allowed for KYC ID documents
    $response = $this->actingAs($this->user)->postJson('/api/v1/media/presign', [
        'file_type' => 'kyc_id',
        'mime_type' => 'application/pdf',
        'file_size_bytes' => 102400,
    ]);
    $response->assertStatus(200);
});

it('SRS §9.1: returns pre-signed S3 PUT URL and secure s3_key slot', function () {
    $response = $this->actingAs($this->user)->postJson('/api/v1/media/presign', [
        'file_type' => 'kyc_id',
        'mime_type' => 'image/jpeg',
        'file_size_bytes' => 204800,
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'upload_url',
            's3_key',
            'expires_in',
            'headers' => ['Content-Type'],
        ]);

    expect($response->json('s3_key'))->toStartWith("kyc/{$this->user->id}/");
    expect($response->json('s3_key'))->toEndWith('.jpg');
    expect($response->json('expires_in'))->toBe(600);
});
