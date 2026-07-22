<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreVehicleLogRequest;
use App\Http\Resources\VehicleLogResource;
use App\Models\Company;
use App\Models\Vehicle;
use App\Models\VehicleLog;

class VehicleLogController extends Controller
{
    public function index(Vehicle $vehicle)
    {
        return VehicleLogResource::collection(
            $vehicle->logs()->with(['driver', 'creator'])->paginate(20)
        );
    }

    public function store(StoreVehicleLogRequest $request, Vehicle $vehicle)
    {
        $data = $request->validated();
        if (! empty($data['cost']) && empty($data['currency'])) {
            $data['currency'] = Company::query()->value('currency') ?? 'TZS';
        }

        $log = $vehicle->logs()->create([
            ...$data,
            'created_by' => $request->user()->id,
        ])->refresh();

        return new VehicleLogResource($log->load(['driver', 'creator']));
    }

    public function destroy(Vehicle $vehicle, VehicleLog $log)
    {
        abort_unless($log->vehicle_id === $vehicle->id, 404);

        $log->delete();

        return response()->json(status: 204);
    }
}
