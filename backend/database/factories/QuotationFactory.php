<?php

namespace Database\Factories;

use App\Enums\QuotationDirection;
use App\Enums\QuotationStatus;
use App\Enums\TransportMode;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100, 5000);
        $tax = round($subtotal * 0.16, 2);

        return [
            'direction' => fake()->randomElement(QuotationDirection::cases())->value,
            'mode' => fake()->randomElement(TransportMode::cases())->value,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
            'status' => QuotationStatus::Draft->value,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $subtotal + $tax,
            'currency' => 'USD',
        ];
    }
}
