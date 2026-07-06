<?php

namespace Database\Factories;

use App\Enums\ShipmentDirection;
use App\Enums\ShipmentStatus;
use App\Enums\TransportMode;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'direction' => fake()->randomElement(ShipmentDirection::cases())->value,
            'mode' => fake()->randomElement(TransportMode::cases())->value,
            'origin_port' => fake()->city(),
            'destination_port' => fake()->city(),
            'status' => ShipmentStatus::Booked->value,
        ];
    }
}
