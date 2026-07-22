<?php

namespace App\Services\Identity;

use App\Contracts\IdentityVerificationProvider;
use App\Enums\IdentityVerificationStatus;
use App\Events\Identity\EmployeeIdentityConfirmed;
use App\Events\Identity\EmployeeIdentityRejected;
use App\Events\Identity\IdentityVerificationFailed;
use App\Events\Identity\IdentityVerificationRequested;
use App\Events\Identity\IdentityVerificationSucceeded;
use App\Models\Employee;
use App\Models\EmployeeIdentityVerification;
use App\Models\User;
use App\Services\Identity\Data\IdentityVerificationRequestData;
use App\Services\Identity\Data\VerificationResult;
use App\Services\Identity\Exceptions\IdentityProviderUnavailableException;
use App\Services\Identity\Exceptions\IdentityRateLimitException;
use App\Services\Identity\Exceptions\IdentityVerificationException;
use App\Support\Identity\IdentityNumberHasher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use RuntimeException;

class IdentityVerificationService
{
    /**
     * How long the raw (encrypted, cache-only, never in a DB column) identity
     * number stays available after a verification for applyToEmployee() to
     * pick up — long enough for HR to confirm and finish the registration
     * form in one sitting, short enough that it doesn't linger.
     */
    private const RAW_NUMBER_TTL_MINUTES = 60;

    public function __construct(private readonly IdentityVerificationProvider $provider) {}

    /**
     * Submit a fresh verification request. Throws IdentityRateLimitException
     * or IdentityProviderUnavailableException for infrastructure-level
     * failures the caller should surface as 429/503; every other outcome
     * (not found, inactive, expired, mismatch, ...) is recorded and
     * returned on the verification itself rather than thrown, since those
     * are normal business outcomes the UI needs to render, not errors.
     */
    public function verify(User $actor, int $tenantId, IdentityVerificationRequestData $request, ?int $employeeId = null): EmployeeIdentityVerification
    {
        $this->throttle($actor->id);

        $hash = IdentityNumberHasher::hash($request->identityNumber, $request->documentType->value, $tenantId);
        $masked = IdentityNumberHasher::mask($request->identityNumber);

        $verification = EmployeeIdentityVerification::query()->create([
            'employee_id' => $employeeId,
            'identity_document_type' => $request->documentType->value,
            'identity_number_hash' => $hash,
            'identity_number_masked' => $masked,
            'identity_country_code' => $request->countryCode,
            'provider' => $this->provider->key(),
            'verification_status' => IdentityVerificationStatus::Pending->value,
            'requested_by' => $actor->id,
            'requested_at' => now(),
            'request_metadata' => [
                'country_code' => $request->countryCode,
                'has_date_of_birth' => $request->dateOfBirth !== null,
                'has_phone_number' => $request->phoneNumber !== null,
            ],
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);

        $this->cacheRawNumber($verification->id, $request->identityNumber);

        event(new IdentityVerificationRequested($verification));

        return $this->resolveProviderResult($verification, $request);
    }

    /**
     * Re-attempt an existing verification with a freshly supplied
     * identity number (never persisted in plaintext, so the caller must
     * resend it). Rejects if the resupplied number doesn't hash to the
     * same identity — a "retry" must be the same person, not a
     * substitution.
     */
    public function retry(EmployeeIdentityVerification $verification, IdentityVerificationRequestData $request): EmployeeIdentityVerification
    {
        if (in_array($verification->verification_status, [IdentityVerificationStatus::Verified, IdentityVerificationStatus::Rejected], true)) {
            throw new RuntimeException('This verification has already been resolved and cannot be retried.');
        }

        $hash = IdentityNumberHasher::hash($request->identityNumber, $request->documentType->value, $verification->tenant_id);

        if (! hash_equals($verification->identity_number_hash, $hash)) {
            throw new RuntimeException('The resupplied identity number does not match the original request.');
        }

        $this->throttle($verification->requested_by);

        $verification->update([
            'verification_status' => IdentityVerificationStatus::Pending->value,
            'result_code' => null,
            'result_message' => null,
            'failure_reason' => null,
        ]);

        $this->cacheRawNumber($verification->id, $request->identityNumber);

        return $this->resolveProviderResult($verification, $request);
    }

    public function confirm(EmployeeIdentityVerification $verification, User $actor): EmployeeIdentityVerification
    {
        if ($verification->verification_status !== IdentityVerificationStatus::Verified) {
            throw new RuntimeException('Only a successfully verified identity can be confirmed.');
        }

        $verification->update([
            'confirmed_by' => $actor->id,
            'confirmed_at' => now(),
        ]);

        event(new EmployeeIdentityConfirmed($verification, $actor));

        return $verification->fresh();
    }

    public function reject(EmployeeIdentityVerification $verification, User $actor): EmployeeIdentityVerification
    {
        if ($verification->confirmed_at !== null) {
            throw new RuntimeException('A confirmed identity cannot be rejected — submit an override instead.');
        }

        $verification->update([
            'verification_status' => IdentityVerificationStatus::Rejected->value,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
        ]);

        event(new EmployeeIdentityRejected($verification, $actor));

        return $verification->fresh();
    }

    /**
     * Stamp a confirmed verification's outcome onto the employee record
     * and link the two together. Called once the employee is actually
     * created (or on resync for an existing employee) — verification and
     * employee creation are deliberately separate steps.
     */
    public function applyToEmployee(EmployeeIdentityVerification $verification, Employee $employee): void
    {
        if ($verification->tenant_id !== $employee->tenant_id) {
            throw new RuntimeException('This verification does not belong to the same tenant as the employee.');
        }

        if ($verification->confirmed_at === null) {
            throw new RuntimeException('Only a confirmed identity verification can be applied to an employee.');
        }

        if ($verification->employee_id !== null && $verification->employee_id !== $employee->id) {
            throw new RuntimeException('This verification is already linked to a different employee.');
        }

        $rawNumber = $this->consumeRawNumber($verification->id);

        $employee->forceFill([
            'identity_document_type' => $verification->identity_document_type->value,
            'identity_number' => $rawNumber,
            'identity_country_code' => $verification->identity_country_code,
            'identity_verification_status' => IdentityVerificationStatus::Verified->value,
            'identity_verified' => true,
            'identity_verified_at' => $verification->confirmed_at,
            'identity_verified_by' => $verification->confirmed_by,
            'identity_provider' => $verification->provider,
            'identity_reference' => $verification->provider_reference,
            'identity_last_synced_at' => now(),
        ])->save();

        $verification->update(['employee_id' => $employee->id]);
    }

    private function cacheRawNumber(int $verificationId, string $identityNumber): void
    {
        Cache::put(
            "identity-verification-raw:{$verificationId}",
            Crypt::encryptString($identityNumber),
            now()->addMinutes(self::RAW_NUMBER_TTL_MINUTES),
        );
    }

    /**
     * Reads and immediately forgets the cached raw number — it's only ever
     * meant to be applied to an employee once.
     */
    private function consumeRawNumber(int $verificationId): ?string
    {
        $key = "identity-verification-raw:{$verificationId}";
        $encrypted = Cache::get($key);
        Cache::forget($key);

        if (! $encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return null;
        }
    }

    private function resolveProviderResult(EmployeeIdentityVerification $verification, IdentityVerificationRequestData $request): EmployeeIdentityVerification
    {
        try {
            $result = $this->provider->verify($request);
        } catch (IdentityVerificationException $e) {
            $verification->update([
                'verification_status' => $e->status()->value,
                'result_message' => $e->getMessage(),
                'failure_reason' => $e->getMessage(),
                'responded_at' => now(),
            ]);

            event(new IdentityVerificationFailed($verification, $e->getMessage()));

            if ($e instanceof IdentityRateLimitException || $e instanceof IdentityProviderUnavailableException) {
                throw $e;
            }

            return $verification;
        }

        return $this->applyResult($verification, $result);
    }

    private function applyResult(EmployeeIdentityVerification $verification, VerificationResult $result): EmployeeIdentityVerification
    {
        $verification->update([
            'verification_status' => $result->status->value,
            'provider_reference' => $result->reference,
            'response_metadata' => $result->toArray(),
            'responded_at' => now(),
        ]);

        if ($result->verified) {
            event(new IdentityVerificationSucceeded($verification));
        } else {
            event(new IdentityVerificationFailed($verification, $result->message ?? 'Verification failed.'));
        }

        return $verification;
    }

    private function throttle(int $userId): void
    {
        $key = "identity-verify:{$userId}";
        $maxAttempts = (int) config('identity.rate_limit.max_attempts', 5);
        $decayMinutes = (int) config('identity.rate_limit.decay_minutes', 10);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new IdentityRateLimitException;
        }

        RateLimiter::hit($key, $decayMinutes * 60);
    }
}
