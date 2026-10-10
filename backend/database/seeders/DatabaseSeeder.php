<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
        ]);

        User::factory()->create([
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+251911000001',
            'type' => 'buyer',
        ]);
    }
}
