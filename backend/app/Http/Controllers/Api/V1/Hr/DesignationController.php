<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreDesignationRequest;
use App\Http\Requests\Hr\UpdateDesignationRequest;
use App\Http\Resources\DesignationResource;
use App\Models\Designation;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function index(Request $request)
    {
        return DesignationResource::collection(
            Designation::query()
                ->withCount('employees')
                ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
                ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreDesignationRequest $request)
    {
        $designation = Designation::query()->create($request->validated())->refresh();

        return new DesignationResource($designation);
    }

    public function show(Designation $designation)
    {
        return new DesignationResource($designation->loadCount('employees'));
    }

    public function update(UpdateDesignationRequest $request, Designation $designation)
    {
        $designation->update($request->validated());

        return new DesignationResource($designation);
    }

    public function destroy(Designation $designation)
    {
        $designation->delete();

        return response()->json(status: 204);
    }
}
