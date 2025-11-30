<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'tillNumber' => fake()->unique()->numerify('######'),
            'daily_target' => 10000,
            'weekly_target' => 70000,
            'monthly_target' => 300000,
            'yearly_target' => 3600000,
        ];
    }
}
