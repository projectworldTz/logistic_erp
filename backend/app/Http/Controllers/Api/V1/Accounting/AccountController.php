<?php

namespace App\Http\Controllers\Api\V1\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreAccountRequest;
use App\Http\Requests\Accounting\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        return AccountResource::collection(
            Account::query()
                ->when($request->query('type'), fn ($query, $type) => $query->where('type', $type))
                ->orderBy('code')
                ->paginate(50)
        );
    }

    public function store(StoreAccountRequest $request)
    {
        $account = Account::query()->create($request->validated())->refresh();

        return new AccountResource($account);
    }

    public function show(Account $account)
    {
        return new AccountResource($account);
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $account->update($request->validated());

        return new AccountResource($account);
    }

    public function destroy(Account $account)
    {
        abort_if($account->journalEntryLines()->exists(), 409, 'Cannot delete an account that has journal entry lines.');

        $account->delete();

        return response()->json(status: 204);
    }
}
