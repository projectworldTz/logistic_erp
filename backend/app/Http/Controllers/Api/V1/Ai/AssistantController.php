<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AssistantChatRequest;
use App\Services\Ai\AiAssistantService;

class AssistantController extends Controller
{
    public function chat(AssistantChatRequest $request, AiAssistantService $service)
    {
        abort_unless($service->configured(), 503, 'The AI assistant is not configured. Set ANTHROPIC_API_KEY.');

        $reply = $service->chat($request->validated('history', []), $request->validated('message'));

        return response()->json(['reply' => $reply]);
    }
}
