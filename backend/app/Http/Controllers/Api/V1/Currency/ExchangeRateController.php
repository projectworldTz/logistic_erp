<?php

namespace App\Http\Controllers\Api\V1\Currency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Currency\ConvertCurrencyRequest;
use App\Http\Requests\Currency\StoreExchangeRateRequest;
use App\Http\Resources\ExchangeRateResource;
use App\Models\ExchangeRate;
use App\Services\Currency\CurrencyConversionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ExchangeRateController extends Controller
{
    public function index()
    {
        return ExchangeRateResource::collection(
            ExchangeRate::query()
                ->with('creator')
                ->orderByDesc('rate_date')
                ->paginate(20)
        );
    }

    /**
     * Re-entering a rate for the same pair and date updates it in place —
     * a same-day correction, not a new history entry.
     */
    public function store(StoreExchangeRateRequest $request)
    {
        $data = $request->validated();
        $baseCurrency = strtoupper($data['base_currency']);
        $quoteCurrency = strtoupper($data['quote_currency']);

        // The model's `date` cast stores rate_date with a full datetime
        // format, so an exact-string match in updateOrCreate()'s search
        // array would never hit — look the existing row up with
        // whereDate() instead, matching the same gotcha documented for
        // other date-cast columns in this codebase.
        $rate = ExchangeRate::query()
            ->where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency)
            ->whereDate('rate_date', $data['rate_date'])
            ->first() ?? new ExchangeRate([
                'base_currency' => $baseCurrency,
                'quote_currency' => $quoteCurrency,
                'rate_date' => $data['rate_date'],
            ]);

        $rate->rate = $data['rate'];
        $rate->created_by = Auth::id();
        $rate->save();

        return new ExchangeRateResource($rate->load('creator'));
    }

    public function destroy(ExchangeRate $exchangeRate)
    {
        $exchangeRate->delete();

        return response()->json(status: 204);
    }

    public function convert(ConvertCurrencyRequest $request, CurrencyConversionService $service)
    {
        $data = $request->validated();
        $asOf = isset($data['date']) ? Carbon::parse($data['date']) : null;

        $converted = $service->convert((float) $data['amount'], $data['from'], $data['to'], $asOf);

        if ($converted === null) {
            return response()->json([
                'message' => "No exchange rate found for {$data['from']} to {$data['to']}.",
            ], 422);
        }

        return response()->json([
            'amount' => (float) $data['amount'],
            'from' => strtoupper($data['from']),
            'to' => strtoupper($data['to']),
            'converted_amount' => $converted,
            'rate' => $service->resolveRate($data['from'], $data['to'], $asOf),
        ]);
    }
}
