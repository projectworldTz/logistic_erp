<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreStatutoryRuleSetRequest;
use App\Http\Requests\Hr\UpdateStatutoryRuleSetRequest;
use App\Http\Resources\StatutoryRuleSetResource;
use App\Models\StatutoryRuleSet;

class StatutoryRuleSetController extends Controller
{
    private const WITH = ['taxBands', 'contributionRules'];

    public function index()
    {
        return StatutoryRuleSetResource::collection(
            StatutoryRuleSet::query()->with(self::WITH)->orderBy('name')->get()
        );
    }

    public function store(StoreStatutoryRuleSetRequest $request)
    {
        $ruleSet = StatutoryRuleSet::query()->create($request->validated())->refresh();

        return new StatutoryRuleSetResource($ruleSet->load(self::WITH));
    }

    public function show(StatutoryRuleSet $statutoryRuleSet)
    {
        return new StatutoryRuleSetResource($statutoryRuleSet->load(self::WITH));
    }

    public function update(UpdateStatutoryRuleSetRequest $request, StatutoryRuleSet $statutoryRuleSet)
    {
        $statutoryRuleSet->update($request->validated());

        return new StatutoryRuleSetResource($statutoryRuleSet->load(self::WITH));
    }

    public function destroy(StatutoryRuleSet $statutoryRuleSet)
    {
        $statutoryRuleSet->delete();

        return response()->json(status: 204);
    }
}
