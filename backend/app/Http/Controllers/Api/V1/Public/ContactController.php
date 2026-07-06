<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ContactRequest;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    /**
     * Accept a contact-form submission. Logged for now (MAIL_MAILER=log);
     * wiring to a real mailer/CRM lead is a follow-up pass.
     */
    public function store(ContactRequest $request)
    {
        Log::info('Contact form submission', $request->validated());

        return response()->json(['message' => 'Thanks — we will be in touch shortly.'], 201);
    }
}
