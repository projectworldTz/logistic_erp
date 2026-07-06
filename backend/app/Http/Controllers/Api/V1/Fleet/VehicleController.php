<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreVehicleRequest;
use App\Http\Requests\Fleet\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        return VehicleResource::collection(
            Vehicle::query()
                ->with(['branch', 'assignedDriver'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreVehicleRequest $request)
    {
        $vehicle = Vehicle::query()->create($request->validated())->refresh();

        return new VehicleResource($vehicle->load(['branch', 'assignedDriver']));
    }

    public function show(Vehicle $vehicle)
    {
        return new VehicleResource($vehicle->load(['branch', 'assignedDriver']));
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {
        $vehicle->update($request->validated());

        return new VehicleResource($vehicle->load(['branch', 'assignedDriver']));
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();

        return response()->json(status: 204);
    }
}
