<?php

namespace Database\Factories;

use App\Enums\DocumentCategory;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'category' => DocumentCategory::Other->value,
            'file_name' => fake()->word().'.pdf',
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(1000, 500000),
            'mime_type' => 'application/pdf',
        ];
    }
}
