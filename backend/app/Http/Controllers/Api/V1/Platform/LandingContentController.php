<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateLandingContentSectionRequest;
use App\Http\Requests\Platform\UploadLandingImageRequest;
use App\Http\Resources\LandingContentSectionResource;
use App\Models\LandingContentSection;
use App\Services\Audit\AuditLogger;
use App\Services\Uploads\LandingImageUploadService;
use Illuminate\Support\Facades\Storage;

class LandingContentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly LandingImageUploadService $landingImageUploadService,
    ) {}

    public function index()
    {
        return LandingContentSectionResource::collection(
            LandingContentSection::query()->orderBy('key')->get()
        );
    }

    public function update(UpdateLandingContentSectionRequest $request, string $key)
    {
        $section = LandingContentSection::query()->where('key', $key)->firstOrFail();

        $section->update(['content' => $request->validated()['content']]);

        $this->auditLogger->log(
            action: 'landing_content.updated',
            auditable: $section,
            newValues: ['key' => $section->key],
            tenantId: null,
        );

        return new LandingContentSectionResource($section);
    }

    public function uploadImage(UploadLandingImageRequest $request)
    {
        $path = $this->landingImageUploadService->store(
            $request->file('image'),
            $request->input('purpose', 'hero'),
        );

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }
}
