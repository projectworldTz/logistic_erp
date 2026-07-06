<?php

namespace Database\Factories;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        return [
            'registration_number' => strtoupper(fake()->bothify('K??###?')),
            'vehicle_type' => fake()->randomElement(VehicleType::cases())->value,
            'status' => VehicleStatus::Active->value,
        ];
    }
}
