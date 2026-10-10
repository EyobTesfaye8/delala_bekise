<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class DelalaJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => 'open', // state machine
            'budget_min' => 500,
            'budget_max' => 1000,
        ];
    }
}
