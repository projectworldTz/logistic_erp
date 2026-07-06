<?php

namespace Database\Seeders;

use App\Models\LandingContentSection;
use Illuminate\Database\Seeder;

class LandingContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('landing_content', []) as $key => $content) {
            LandingContentSection::query()->updateOrCreate(
                ['key' => $key],
                ['content' => $content],
            );
        }
    }
}
