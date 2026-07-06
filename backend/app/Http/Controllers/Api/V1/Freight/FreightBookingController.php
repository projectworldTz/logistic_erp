<?php

namespace App\Http\Controllers\Api\V1\Freight;

use App\Http\Controllers\Controller;
use App\Http\Requests\Freight\StoreFreightBookingRequest;
use App\Http\Requests\Freight\UpdateFreightBookingRequest;
use App\Http\Resources\FreightBookingResource;
use App\Models\FreightBooking;
use Illuminate\Http\Request;

class FreightBookingController extends Controller
{
    public function index(Request $request)
    {
        return FreightBookingResource::collection(
            FreightBooking::query()
                ->with(['customer', 'assignedTo'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreFreightBookingRequest $request)
    {
        $freightBooking = FreightBooking::query()->create($request->validated())->refresh();

        return new FreightBookingResource($freightBooking->load(['customer', 'assignedTo']));
    }

    public function show(FreightBooking $freightBooking)
    {
        return new FreightBookingResource($freightBooking->load(['customer', 'assignedTo']));
    }

    public function update(UpdateFreightBookingRequest $request, FreightBooking $freightBooking)
    {
        $freightBooking->update($request->validated());

        return new FreightBookingResource($freightBooking->load(['customer', 'assignedTo']));
    }

    public function destroy(FreightBooking $freightBooking)
    {
        $freightBooking->delete();

        return response()->json(status: 204);
    }
}
