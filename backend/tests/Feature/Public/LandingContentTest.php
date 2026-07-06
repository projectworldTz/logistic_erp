<?php

namespace Tests\Feature\Public;

use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
    }

    public function test_public_can_fetch_landing_content_with_seeded_defaults(): void
    {
        $this->getJson('/api/v1/landing-content')
            ->assertOk()
            ->assertJsonPath('data.hero.headline', config('landing_content.hero.headline'))
            ->assertJsonStructure([
                'data' => ['hero', 'about', 'features', 'industries', 'testimonials', 'faqs'],
            ]);
    }
}
