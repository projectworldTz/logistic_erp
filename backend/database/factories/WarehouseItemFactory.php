<?php

namespace Database\Factories;

use App\Enums\WarehouseItemStatus;
use App\Models\WarehouseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarehouseItem>
 */
class WarehouseItemFactory extends Factory
{
    protected $model = WarehouseItem::class;

    public function definition(): array
    {
        return [
            'description' => fake()->words(3, true),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'unit' => 'pcs',
            'status' => WarehouseItemStatus::Received->value,
        ];
    }
}
