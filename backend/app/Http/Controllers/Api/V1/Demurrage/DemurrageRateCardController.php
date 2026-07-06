<?php

namespace App\Http\Controllers\Api\V1\Demurrage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Demurrage\StoreDemurrageRateCardRequest;
use App\Http\Requests\Demurrage\UpdateDemurrageRateCardRequest;
use App\Http\Resources\DemurrageRateCardResource;
use App\Models\DemurrageRateCard;
use Illuminate\Support\Facades\DB;

class DemurrageRateCardController extends Controller
{
    public function index()
    {
        return DemurrageRateCardResource::collection(
            DemurrageRateCard::query()->with('tiers')->latest()->get()
        );
    }

    public function store(StoreDemurrageRateCardRequest $request)
    {
        $data = $request->validated();
        $tiers = $data['tiers'];
        unset($data['tiers']);

        $rateCard = DB::transaction(function () use ($data, $tiers) {
            $rateCard = DemurrageRateCard::query()->create($data);
            $this->syncTiers($rateCard, $tiers);

            return $rateCard;
        });

        return new DemurrageRateCardResource($rateCard->load('tiers'));
    }

    public function show(DemurrageRateCard $rateCard)
    {
        return new DemurrageRateCardResource($rateCard->load('tiers'));
    }

    public function update(UpdateDemurrageRateCardRequest $request, DemurrageRateCard $rateCard)
    {
        $data = $request->validated();
        $tiers = $data['tiers'] ?? null;
        unset($data['tiers']);

        DB::transaction(function () use ($rateCard, $data, $tiers) {
            $rateCard->update($data);

            if ($tiers !== null) {
                $rateCard->tiers()->delete();
                $this->syncTiers($rateCard, $tiers);
            }
        });

        return new DemurrageRateCardResource($rateCard->load('tiers'));
    }

    public function destroy(DemurrageRateCard $rateCard)
    {
        $rateCard->delete();

        return response()->json(status: 204);
    }

    private function syncTiers(DemurrageRateCard $rateCard, array $tiers): void
    {
        foreach ($tiers as $index => $tier) {
            $rateCard->tiers()->create([
                'position' => $index + 1,
                'from_day' => $tier['from_day'],
                'to_day' => $tier['to_day'] ?? null,
                'daily_rate' => $tier['daily_rate'],
            ]);
        }
    }
}
