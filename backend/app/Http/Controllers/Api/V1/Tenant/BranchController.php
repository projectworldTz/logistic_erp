<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;

class BranchController extends Controller
{
    public function index()
    {
        return BranchResource::collection(Branch::query()->get());
    }
}
