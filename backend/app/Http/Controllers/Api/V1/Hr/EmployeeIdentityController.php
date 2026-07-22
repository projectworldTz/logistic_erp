<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\StoreIdentityVerificationRequest;
use App\Http\Resources\EmployeeIdentityVerificationResource;
use App\Models\Employee;
use App\Services\Identity\Data\IdentityVerificationRequestData;
use App\Services\Identity\Exceptions\IdentityProviderUnavailableException;
use App\Services\Identity\Exceptions\IdentityRateLimitException;
use App\Services\Identity\IdentityVerificationService;
use Illuminate\Support\Facades\Auth;

class EmployeeIdentityController extends Controller
{
    public function index(Employee $employee)
    {
        return EmployeeIdentityVerificationResource::collection(
            $employee->identityVerifications()->with(['requestedBy', 'confirmedBy', 'rejectedBy'])->latest()->get()
        );
    }

    /**
     * Re-run identity verification for an employee who already exists —
     * e.g. their document expired, or a previous check needs to be
     * refreshed. Creates a new verification record already linked to the
     * employee; still requires the normal confirm step before it takes
     * effect on identity_verified/identity_verification_status.
     */
    public function resync(StoreIdentityVerificationRequest $request, Employee $employee, IdentityVerificationService $service)
    {
        $data = new IdentityVerificationRequestData(
            documentType: \App\Enums\IdentityDocumentType::from($request->validated('document_type')),
            identityNumber: $request->validated('identity_number'),
            countryCode: strtoupper($request->validated('country_code')),
            dateOfBirth: $request->validated('date_of_birth'),
            phoneNumber: $request->validated('phone_number'),
        );

        try {
            $verification = $service->verify(Auth::user(), $employee->tenant_id, $data, $employee->id);
        } catch (IdentityRateLimitException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        } catch (IdentityProviderUnavailableException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $employee->forceFill(['identity_last_synced_at' => now()])->save();

        return new EmployeeIdentityVerificationResource($verification);
    }
}
