<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePublicHolidayRequest;
use App\Http\Resources\PublicHolidayResource;
use App\Models\PublicHoliday;
use Illuminate\Http\Request;

class PublicHolidayController extends Controller
{
    public function index(Request $request)
    {
        return PublicHolidayResource::collection(
            PublicHoliday::query()
                ->with('branch')
                ->when($request->query('year'), fn ($query, $year) => $query->whereYear('date', $year))
                ->orderBy('date')
                ->get()
        );
    }

    public function store(StorePublicHolidayRequest $request)
    {
        $holiday = PublicHoliday::query()->create($request->validated())->refresh();

        return new PublicHolidayResource($holiday->load('branch'));
    }

    public function destroy(PublicHoliday $publicHoliday)
    {
        $publicHoliday->delete();

        return response()->json(status: 204);
    }
}
