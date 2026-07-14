<?php

namespace App\Services\Workflow;

use App\Enums\ApprovalDecisionType;
use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalDecision;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ApprovalEngine
{
    /**
     * Pick the most specific active workflow for this subject type whose
     * amount threshold the given amount satisfies. A higher min_amount is
     * more specific and wins; a null min_amount is the universal fallback.
     */
    public function resolveWorkflow(string $subjectType, ?float $amount): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::query()
            ->where('subject_type', $subjectType)
            ->where('is_active', true)
            ->with('steps')
            ->get()
            ->filter(fn (ApprovalWorkflow $workflow) => $workflow->min_amount === null
                || $amount === null
                || $amount >= (float) $workflow->min_amount)
            ->sortByDesc(fn (ApprovalWorkflow $workflow) => $workflow->min_amount ?? -1)
            ->first();
    }

    /**
     * Start an approval request for a subject if an active workflow with
     * at least one step matches. Returns null if none does — callers
     * should fall back to their own legacy single-approver behavior.
     */
    public function start(Model $subject, string $subjectType, ?float $amount): ?ApprovalRequest
    {
        $workflow = $this->resolveWorkflow($subjectType, $amount);

        if (! $workflow || $workflow->steps->isEmpty()) {
            return null;
        }

        return ApprovalRequest::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'approval_workflow_id' => $workflow->id,
            'current_step_position' => 1,
            'status' => ApprovalRequestStatus::Pending,
            'created_by' => Auth::id(),
        ]);
    }

    public function findPendingRequestFor(Model $subject): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->where('status', ApprovalRequestStatus::Pending)
            ->with('workflow.steps')
            ->latest()
            ->first();
    }

    /**
     * Record a decision at the request's current step and advance or
     * finalize it. Aborts 409 if the request is no longer pending, 403 if
     * the user doesn't hold the current step's approver role.
     */
    public function decide(ApprovalRequest $request, User $user, bool $approve, ?string $comment = null): ApprovalRequest
    {
        abort_if($request->status !== ApprovalRequestStatus::Pending, 409, 'This approval request has already been decided.');

        $request->loadMissing('workflow.steps');
        $step = $request->currentStep();

        abort_if(! $step, 500, 'Approval workflow step configuration is missing.');
        abort_unless($user->hasRole($step->approver_role), 403, "Only a {$step->approver_role} can act on this approval step.");

        ApprovalDecision::query()->create([
            'approval_request_id' => $request->id,
            'step_position' => $request->current_step_position,
            'approver_role' => $step->approver_role,
            'decided_by' => $user->id,
            'decision' => $approve ? ApprovalDecisionType::Approved : ApprovalDecisionType::Rejected,
            'comment' => $comment,
            'decided_at' => now(),
        ]);

        if (! $approve) {
            $request->update(['status' => ApprovalRequestStatus::Rejected]);

            return $request;
        }

        $nextStep = $request->workflow->steps->firstWhere('position', $request->current_step_position + 1);

        $request->update($nextStep
            ? ['current_step_position' => $request->current_step_position + 1]
            : ['status' => ApprovalRequestStatus::Approved]);

        return $request;
    }
}
