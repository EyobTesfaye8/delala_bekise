<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('purges unverified accounts older than 72 hours with zero jobs', function () {
    // 1. Account unverified and 73 hours old with NO jobs -> should be purged
    $oldUnverified = User::factory()->create([
        'status' => 'unverified',
        'created_at' => now()->subHours(73),
    ]);

    // 2. Account unverified but only 24 hours old -> should NOT be purged
    $recentUnverified = User::factory()->create([
        'status' => 'unverified',
        'created_at' => now()->subHours(24),
    ]);

    // 3. Account active and 80 hours old -> should NOT be purged
    $activeUser = User::factory()->create([
        'status' => 'active',
        'created_at' => now()->subHours(80),
    ]);

    $this->artisan('delala:purge-unverified')
        ->assertSuccessful();

    $this->assertDatabaseMissing('users', ['id' => $oldUnverified->id]);
    $this->assertDatabaseHas('users', ['id' => $recentUnverified->id]);
    $this->assertDatabaseHas('users', ['id' => $activeUser->id]);
});
