<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'stock' => 100,
            'buying_price' => 50,
            'price' => 100,
            'branch_id' => \App\Models\Branch::factory(),
        ];
    }
}
