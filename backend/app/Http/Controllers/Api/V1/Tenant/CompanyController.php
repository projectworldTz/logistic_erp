<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateCompanyRequest;
use App\Http\Requests\Tenant\UploadCompanyLogoRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\Uploads\CompanyLogoUploadService;

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

    public function uploadLogo(UploadCompanyLogoRequest $request, CompanyLogoUploadService $service)
    {
        $company = Company::query()->firstOrFail();

        $path = $service->store($request->file('logo'), $company->logo_path);
        $company->update(['logo_path' => $path]);

        return new CompanyResource($company);
    }
}
