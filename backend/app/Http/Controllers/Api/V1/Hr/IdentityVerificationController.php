<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\RetryIdentityVerificationRequest;
use App\Http\Requests\Identity\StoreIdentityVerificationRequest;
use App\Http\Resources\EmployeeIdentityVerificationResource;
use App\Models\EmployeeIdentityVerification;
use App\Services\Identity\Data\IdentityVerificationRequestData;
use App\Services\Identity\Exceptions\IdentityProviderUnavailableException;
use App\Services\Identity\Exceptions\IdentityRateLimitException;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Support\Facades\Auth;

class IdentityVerificationController extends Controller
{
    public function store(StoreIdentityVerificationRequest $request, IdentityVerificationService $service)
    {
        try {
            $verification = $service->verify(
                Auth::user(),
                Auth::user()->tenant_id,
                $this->requestData($request),
            );
        } catch (IdentityRateLimitException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        } catch (IdentityProviderUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return new EmployeeIdentityVerificationResource($verification);
    }

    public function show(EmployeeIdentityVerification $verification)
    {
        return new EmployeeIdentityVerificationResource(
            $verification->load(['requestedBy', 'confirmedBy', 'rejectedBy'])
        );
    }

    public function confirm(EmployeeIdentityVerification $verification, IdentityVerificationService $service)
    {
        return new EmployeeIdentityVerificationResource($service->confirm($verification, Auth::user()));
    }

    public function reject(EmployeeIdentityVerification $verification, IdentityVerificationService $service)
    {
        return new EmployeeIdentityVerificationResource($service->reject($verification, Auth::user()));
    }

    public function retry(RetryIdentityVerificationRequest $request, EmployeeIdentityVerification $verification, IdentityVerificationService $service)
    {
        try {
            $verification = $service->retry($verification, $this->requestData($request));
        } catch (IdentityRateLimitException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        } catch (IdentityProviderUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return new EmployeeIdentityVerificationResource($verification);
    }

    private function requestData($request): IdentityVerificationRequestData
    {
        return new IdentityVerificationRequestData(
            documentType: \App\Enums\IdentityDocumentType::from($request->validated('document_type')),
            identityNumber: $request->validated('identity_number'),
            countryCode: strtoupper($request->validated('country_code')),
            dateOfBirth: $request->validated('date_of_birth'),
            phoneNumber: $request->validated('phone_number'),
        );
    }
}
