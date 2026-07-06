<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\LandingContentSection;

class LandingContentController extends Controller
{
    /**
     * Public, unauthenticated landing page content, keyed by section.
     */
    public function index()
    {
        return response()->json([
            'data' => LandingContentSection::query()->get()->keyBy('key')->map(fn ($section) => $section->content),
        ]);
    }
}
