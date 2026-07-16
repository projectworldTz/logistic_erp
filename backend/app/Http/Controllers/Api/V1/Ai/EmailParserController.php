<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ParseEmailRequest;
use App\Services\Ai\AiEmailParserService;

class EmailParserController extends Controller
{
    public function parse(ParseEmailRequest $request, AiEmailParserService $service)
    {
        abort_unless($service->configured(), 503, 'The AI email parser is not configured. Set ANTHROPIC_API_KEY.');

        return response()->json(['data' => $service->parse($request->validated('email_text'))]);
    }
}
