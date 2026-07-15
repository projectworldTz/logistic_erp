<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\ClearingFile;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;

class ReleaseOrderVerificationController extends Controller
{
    /**
     * Public, unauthenticated release-order verification by QR token — a
     * warehouse or port gate can confirm a printed release order is
     * genuine without needing tenant credentials. Deliberately excludes
     * the customer's identity and any financial figures, mirroring the
     * same minimal-exposure convention as shipment tracking.
     */
    public function show(string $token): JsonResponse
    {
        $clearingFile = ClearingFile::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('release_order_token', $token)
            ->first();

        abort_if(! $clearingFile, 404);

        return response()->json([
            'data' => [
                'reference_no' => $clearingFile->reference_no,
                'release_order_number' => $clearingFile->release_order_number,
                'status' => $clearingFile->status,
                'assessment_status' => $clearingFile->assessment_status,
                'customs_office' => $clearingFile->customs_office,
                'cleared_date' => $clearingFile->cleared_date,
            ],
        ]);
    }
}
