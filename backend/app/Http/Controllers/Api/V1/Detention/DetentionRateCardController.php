<?php

namespace App\Http\Controllers\Api\V1\Detention;

use App\Http\Controllers\Controller;
use App\Http\Requests\Detention\StoreDetentionRateCardRequest;
use App\Http\Requests\Detention\UpdateDetentionRateCardRequest;
use App\Http\Resources\DetentionRateCardResource;
use App\Models\Company;
use App\Models\DetentionRateCard;
use Illuminate\Support\Facades\DB;

class DetentionRateCardController extends Controller
{
    public function index()
    {
        return DetentionRateCardResource::collection(
            DetentionRateCard::query()->with('tiers')->latest()->get()
        );
    }

    public function store(StoreDetentionRateCardRequest $request)
    {
        $data = $request->validated();
        $tiers = $data['tiers'];
        unset($data['tiers']);
        $data['currency'] ??= Company::query()->value('currency') ?? 'TZS';

        $rateCard = DB::transaction(function () use ($data, $tiers) {
            $rateCard = DetentionRateCard::query()->create($data);
            $this->syncTiers($rateCard, $tiers);

            return $rateCard;
        });

        return new DetentionRateCardResource($rateCard->load('tiers'));
    }

    public function show(DetentionRateCard $rateCard)
    {
        return new DetentionRateCardResource($rateCard->load('tiers'));
    }

    public function update(UpdateDetentionRateCardRequest $request, DetentionRateCard $rateCard)
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

        return new DetentionRateCardResource($rateCard->load('tiers'));
    }

    public function destroy(DetentionRateCard $rateCard)
    {
        $rateCard->delete();

        return response()->json(status: 204);
    }

    private function syncTiers(DetentionRateCard $rateCard, array $tiers): void
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
