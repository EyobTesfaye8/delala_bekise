<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\DelalaJob;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProposalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_id' => DelalaJob::factory(),
            'delala_id' => User::factory(),
            'status' => 'pending', // state machine
        ];
    }
}
