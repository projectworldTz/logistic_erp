<?php

namespace App\Http\Controllers\Api\V1\Containers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Containers\StoreContainerRequest;
use App\Http\Requests\Containers\UpdateContainerRequest;
use App\Http\Resources\ContainerResource;
use App\Models\Container;
use Illuminate\Http\Request;

class ContainerController extends Controller
{
    public function index(Request $request)
    {
        return ContainerResource::collection(
            Container::query()
                ->with(['customer'])
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreContainerRequest $request)
    {
        $container = Container::query()->create($request->validated())->refresh();

        return new ContainerResource($container->load(['customer']));
    }

    public function show(Container $container)
    {
        return new ContainerResource($container->load(['customer']));
    }

    public function update(UpdateContainerRequest $request, Container $container)
    {
        $container->update($request->validated());

        return new ContainerResource($container->load(['customer']));
    }

    public function destroy(Container $container)
    {
        $container->delete();

        return response()->json(status: 204);
    }
}
