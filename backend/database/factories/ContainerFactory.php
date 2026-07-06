<?php

namespace Database\Factories;

use App\Enums\ContainerStatus;
use App\Enums\ContainerType;
use App\Models\Container;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Container>
 */
class ContainerFactory extends Factory
{
    protected $model = Container::class;

    public function definition(): array
    {
        return [
            'container_number' => strtoupper(fake()->bothify('????#######')),
            'container_type' => fake()->randomElement(ContainerType::cases())->value,
            'status' => ContainerStatus::AtPort->value,
        ];
    }
}
