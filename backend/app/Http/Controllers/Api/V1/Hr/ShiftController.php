<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreShiftRequest;
use App\Http\Requests\Hr\UpdateShiftRequest;
use App\Http\Resources\ShiftResource;
use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        return ShiftResource::collection(
            Shift::query()
                ->with(['branch', 'department'])
                ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreShiftRequest $request)
    {
        $shift = Shift::query()->create($request->validated())->refresh();

        return new ShiftResource($shift->load(['branch', 'department']));
    }

    public function show(Shift $shift)
    {
        return new ShiftResource($shift->load(['branch', 'department']));
    }

    public function update(UpdateShiftRequest $request, Shift $shift)
    {
        $shift->update($request->validated());

        return new ShiftResource($shift->load(['branch', 'department']));
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();

        return response()->json(status: 204);
    }
}
