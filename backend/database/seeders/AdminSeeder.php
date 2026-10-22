<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate([
            'email' => 'admin@delalabekise.com',
        ], [
            'full_name' => 'System Admin',
            'phone' => '+251900000000',
            'password' => Hash::make('admin123'),
            'type' => 'admin',
            'status' => 'active',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);
    }
}
