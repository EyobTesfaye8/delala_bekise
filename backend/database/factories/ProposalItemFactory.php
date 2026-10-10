<?php

namespace Database\Factories;

use App\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProposalItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'item_name' => fake()->word(),
            'description' => fake()->sentence(),
            'commission_amount_santim' => 50000,
            'status' => 'pending', // state machine
        ];
    }
}
