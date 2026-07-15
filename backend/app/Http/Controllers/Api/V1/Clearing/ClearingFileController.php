<?php

namespace App\Http\Controllers\Api\V1\Clearing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clearing\StoreClearingFileRequest;
use App\Http\Requests\Clearing\UpdateClearingFileRequest;
use App\Http\Resources\ClearingFileResource;
use App\Models\ClearingFile;
use App\Services\Tracking\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClearingFileController extends Controller
{
    public function index(Request $request)
    {
        return ClearingFileResource::collection(
            ClearingFile::query()
                ->with(['customer', 'assignedTo'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreClearingFileRequest $request)
    {
        $clearingFile = ClearingFile::query()->create($request->validated())->refresh();

        return new ClearingFileResource($clearingFile->load(['customer', 'assignedTo']));
    }

    public function show(ClearingFile $clearingFile)
    {
        return new ClearingFileResource($clearingFile->load(['customer', 'assignedTo']));
    }

    public function update(UpdateClearingFileRequest $request, ClearingFile $clearingFile)
    {
        $clearingFile->update($request->validated());

        return new ClearingFileResource($clearingFile->load(['customer', 'assignedTo']));
    }

    public function destroy(ClearingFile $clearingFile)
    {
        $clearingFile->delete();

        return response()->json(status: 204);
    }

    /**
     * SVG QR code linking to the public release-order verification page.
     * Only meaningful once a release order has actually been issued; the
     * verification token is generated lazily on first request rather than
     * whenever release_order_number is set, since nothing else needs it.
     */
    public function releaseOrderQr(ClearingFile $clearingFile, QrCodeService $service)
    {
        abort_if(! $clearingFile->release_order_number, 404, 'No release order has been issued for this file yet.');

        if (! $clearingFile->release_order_token) {
            $clearingFile->release_order_token = (string) Str::uuid();
            $clearingFile->save();
        }

        $url = rtrim(config('app.frontend_url'), '/')."/verify/release-order/{$clearingFile->release_order_token}";

        return response($service->svg($url), 200)->header('Content-Type', 'image/svg+xml');
    }
}
