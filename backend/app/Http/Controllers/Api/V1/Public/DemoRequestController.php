<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\DemoRequestRequest;
use Illuminate\Support\Facades\Log;

class DemoRequestController extends Controller
{
    /**
     * Accept a "book a demo" submission. Logged for now (MAIL_MAILER=log);
     * wiring to a real mailer/CRM lead is a follow-up pass.
     */
    public function store(DemoRequestRequest $request)
    {
        Log::info('Demo request submission', $request->validated());

        return response()->json(['message' => 'Demo request received — we will reach out shortly.'], 201);
    }
}
