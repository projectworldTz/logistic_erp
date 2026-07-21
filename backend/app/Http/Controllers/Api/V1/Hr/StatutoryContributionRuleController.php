<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreStatutoryContributionRuleRequest;
use App\Http\Resources\StatutoryContributionRuleResource;
use App\Models\StatutoryContributionRule;
use App\Models\StatutoryRuleSet;

class StatutoryContributionRuleController extends Controller
{
    public function store(StoreStatutoryContributionRuleRequest $request, StatutoryRuleSet $statutoryRuleSet)
    {
        $rule = $statutoryRuleSet->contributionRules()->create([
            ...$request->validated(),
            'tenant_id' => $statutoryRuleSet->tenant_id,
        ])->refresh();

        return new StatutoryContributionRuleResource($rule);
    }

    public function update(StoreStatutoryContributionRuleRequest $request, StatutoryRuleSet $statutoryRuleSet, StatutoryContributionRule $contributionRule)
    {
        $contributionRule->update($request->validated());

        return new StatutoryContributionRuleResource($contributionRule);
    }

    public function destroy(StatutoryRuleSet $statutoryRuleSet, StatutoryContributionRule $contributionRule)
    {
        $contributionRule->delete();

        return response()->json(status: 204);
    }
}
