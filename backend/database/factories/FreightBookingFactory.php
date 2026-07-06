<?php

namespace Database\Factories;

use App\Enums\FreightDirection;
use App\Enums\FreightStatus;
use App\Enums\TransportMode;
use App\Models\FreightBooking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FreightBooking>
 */
class FreightBookingFactory extends Factory
{
    protected $model = FreightBooking::class;

    public function definition(): array
    {
        return [
            'direction' => fake()->randomElement(FreightDirection::cases())->value,
            'mode' => fake()->randomElement(TransportMode::cases())->value,
            'carrier' => fake()->company(),
            'origin_port' => fake()->city(),
            'destination_port' => fake()->city(),
            'status' => FreightStatus::Booked->value,
        ];
    }
}
