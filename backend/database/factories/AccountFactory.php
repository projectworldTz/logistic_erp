<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('#####'),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(AccountType::cases())->value,
        ];
    }
}
