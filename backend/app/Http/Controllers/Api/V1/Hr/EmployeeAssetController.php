<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Enums\EmployeeAssetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ReturnEmployeeAssetRequest;
use App\Http\Requests\Hr\StoreEmployeeAssetRequest;
use App\Http\Resources\EmployeeAssetResource;
use App\Models\EmployeeAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeAssetController extends Controller
{
    private const WITH = ['employee'];

    public function index(Request $request)
    {
        return EmployeeAssetResource::collection(
            EmployeeAsset::query()
                ->with(self::WITH)
                ->when($request->query('employee_id'), fn ($query, $id) => $query->where('employee_id', $id))
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->latest('assigned_date')
                ->paginate(20)
        );
    }

    public function store(StoreEmployeeAssetRequest $request)
    {
        $asset = EmployeeAsset::query()->create([
            ...$request->validated(),
            'status' => EmployeeAssetStatus::Assigned,
            'created_by' => Auth::id(),
        ])->refresh();

        return new EmployeeAssetResource($asset->load(self::WITH));
    }

    public function show(EmployeeAsset $employeeAsset)
    {
        return new EmployeeAssetResource($employeeAsset->load(self::WITH));
    }

    public function returnAsset(ReturnEmployeeAssetRequest $request, EmployeeAsset $employeeAsset)
    {
        abort_if($employeeAsset->status !== EmployeeAssetStatus::Assigned, 409, 'Only assigned assets can be returned.');

        $employeeAsset->update($request->validated());

        return new EmployeeAssetResource($employeeAsset->fresh()->load(self::WITH));
    }

    public function destroy(EmployeeAsset $employeeAsset)
    {
        $employeeAsset->delete();

        return response()->json(status: 204);
    }
}
