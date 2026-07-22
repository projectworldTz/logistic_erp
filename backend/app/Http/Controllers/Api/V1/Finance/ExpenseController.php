<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ExpenseStatus;
use App\Enums\WorkflowSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RejectExpenseRequest;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Requests\Finance\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Company;
use App\Models\Expense;
use App\Services\Finance\ExpenseService;
use App\Services\Workflow\ApprovalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    private const WITH = ['customer', 'shipment', 'creator', 'approver', 'latestApprovalRequest.workflow.steps', 'latestApprovalRequest.decisions.decidedBy'];

    public function index(Request $request)
    {
        return ExpenseResource::collection(
            Expense::query()
                ->with(self::WITH)
                ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
                ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
                ->latest('expense_date')
                ->paginate(20)
        );
    }

    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();
        $data['currency'] ??= Company::query()->value('currency') ?? 'TZS';

        $expense = Expense::query()->create([
            ...$data,
            'created_by' => Auth::id(),
        ])->refresh();

        return new ExpenseResource($expense->load(self::WITH));
    }

    public function show(Expense $expense)
    {
        return new ExpenseResource($expense->load(self::WITH));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        abort_if($expense->status !== ExpenseStatus::Draft, 409, 'Only draft expenses can be edited.');

        $expense->update($request->validated());

        return new ExpenseResource($expense->load(self::WITH));
    }

    public function destroy(Expense $expense)
    {
        abort_if($expense->status !== ExpenseStatus::Draft, 409, 'Only draft expenses can be deleted.');

        $expense->delete();

        return response()->json(status: 204);
    }

    public function submit(Expense $expense, ExpenseService $service, ApprovalEngine $engine)
    {
        $expense = $service->submit($expense);

        $engine->start($expense, WorkflowSubjectType::Expense->value, (float) $expense->amount);

        return new ExpenseResource($expense->load(self::WITH));
    }

    public function approve(Expense $expense, ExpenseService $service, ApprovalEngine $engine)
    {
        $pending = $engine->findPendingRequestFor($expense);

        if ($pending) {
            $decided = $engine->decide($pending, Auth::user(), true);

            if ($decided->status === ApprovalRequestStatus::Approved) {
                $service->approve($expense);
            }
        } else {
            abort_unless(Auth::user()->can('expenses.items.approve'), 403);
            $service->approve($expense);
        }

        return new ExpenseResource($expense->fresh()->load(self::WITH));
    }

    public function reject(RejectExpenseRequest $request, Expense $expense, ExpenseService $service, ApprovalEngine $engine)
    {
        $reason = $request->validated('reason');
        $pending = $engine->findPendingRequestFor($expense);

        if ($pending) {
            $engine->decide($pending, Auth::user(), false, $reason);
            $service->reject($expense, $reason);
        } else {
            abort_unless(Auth::user()->can('expenses.items.approve'), 403);
            $service->reject($expense, $reason);
        }

        return new ExpenseResource($expense->fresh()->load(self::WITH));
    }

    public function markPaid(Expense $expense, ExpenseService $service)
    {
        return new ExpenseResource($service->markPaid($expense)->load(self::WITH));
    }
}
