<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreCandidateRequest;
use App\Http\Resources\CandidateResource;
use App\Models\Candidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CandidateController extends Controller
{
    public function index(Request $request)
    {
        return CandidateResource::collection(
            Candidate::query()
                ->when($request->query('search'), fn ($query, $search) => $query->where(fn ($q) => $q
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")))
                ->latest()
                ->paginate(20)
        );
    }

    public function store(StoreCandidateRequest $request)
    {
        $candidate = Candidate::query()->create([
            ...$request->validated(),
            'created_by' => Auth::id(),
        ])->refresh();

        return new CandidateResource($candidate);
    }

    public function show(Candidate $candidate)
    {
        return new CandidateResource($candidate->load('applications.vacancy'));
    }

    public function update(StoreCandidateRequest $request, Candidate $candidate)
    {
        $candidate->update($request->validated());

        return new CandidateResource($candidate->fresh());
    }

    public function destroy(Candidate $candidate)
    {
        abort_if($candidate->applications()->exists(), 409, 'This candidate already has applications and cannot be deleted.');

        $candidate->delete();

        return response()->json(status: 204);
    }
}
