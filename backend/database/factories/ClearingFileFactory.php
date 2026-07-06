<?php

namespace Database\Factories;

use App\Enums\ClearingDirection;
use App\Enums\ClearingStatus;
use App\Enums\TransportMode;
use App\Models\ClearingFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClearingFile>
 */
class ClearingFileFactory extends Factory
{
    protected $model = ClearingFile::class;

    public function definition(): array
    {
        return [
            'direction' => fake()->randomElement(ClearingDirection::cases())->value,
            'mode' => fake()->randomElement(TransportMode::cases())->value,
            'port_of_loading' => fake()->city(),
            'port_of_discharge' => fake()->city(),
            'bl_awb_number' => strtoupper(fake()->bothify('BL######')),
            'status' => ClearingStatus::Pending->value,
        ];
    }
}
