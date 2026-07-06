<?php

namespace App\Http\Controllers\Api\V1\Warehouse;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\StoreWarehouseItemRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseItemRequest;
use App\Http\Resources\WarehouseItemResource;
use App\Models\WarehouseItem;
use Illuminate\Http\Request;

class WarehouseItemController extends Controller
{
    public function index(Request $request)
    {
        return WarehouseItemResource::collection(
            WarehouseItem::query()
                ->with(['customer', 'branch'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreWarehouseItemRequest $request)
    {
        $warehouseItem = WarehouseItem::query()->create($request->validated())->refresh();

        return new WarehouseItemResource($warehouseItem->load(['customer', 'branch']));
    }

    public function show(WarehouseItem $warehouseItem)
    {
        return new WarehouseItemResource($warehouseItem->load(['customer', 'branch']));
    }

    public function update(UpdateWarehouseItemRequest $request, WarehouseItem $warehouseItem)
    {
        $warehouseItem->update($request->validated());

        return new WarehouseItemResource($warehouseItem->load(['customer', 'branch']));
    }

    public function destroy(WarehouseItem $warehouseItem)
    {
        $warehouseItem->delete();

        return response()->json(status: 204);
    }
}
