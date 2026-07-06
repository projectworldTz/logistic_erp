<?php

namespace App\Http\Controllers\Api\V1\Clearing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clearing\StoreClearingFileRequest;
use App\Http\Requests\Clearing\UpdateClearingFileRequest;
use App\Http\Resources\ClearingFileResource;
use App\Models\ClearingFile;
use Illuminate\Http\Request;

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
}
