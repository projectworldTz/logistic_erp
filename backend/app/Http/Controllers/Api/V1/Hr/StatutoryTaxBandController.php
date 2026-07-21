<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreStatutoryTaxBandRequest;
use App\Http\Resources\StatutoryTaxBandResource;
use App\Models\StatutoryRuleSet;
use App\Models\StatutoryTaxBand;

class StatutoryTaxBandController extends Controller
{
    public function store(StoreStatutoryTaxBandRequest $request, StatutoryRuleSet $statutoryRuleSet)
    {
        $band = $statutoryRuleSet->taxBands()->create([
            ...$request->validated(),
            'tenant_id' => $statutoryRuleSet->tenant_id,
        ])->refresh();

        return new StatutoryTaxBandResource($band);
    }

    public function update(StoreStatutoryTaxBandRequest $request, StatutoryRuleSet $statutoryRuleSet, StatutoryTaxBand $taxBand)
    {
        $taxBand->update($request->validated());

        return new StatutoryTaxBandResource($taxBand);
    }

    public function destroy(StatutoryRuleSet $statutoryRuleSet, StatutoryTaxBand $taxBand)
    {
        $taxBand->delete();

        return response()->json(status: 204);
    }
}
