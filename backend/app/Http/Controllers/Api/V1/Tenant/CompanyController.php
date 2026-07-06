<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;

class CompanyController extends Controller
{
    public function show()
    {
        return new CompanyResource(Company::query()->firstOrFail());
    }

    public function update(UpdateCompanyRequest $request)
    {
        $company = Company::query()->firstOrFail();
        $company->update($request->validated());

        return new CompanyResource($company);
    }
}
