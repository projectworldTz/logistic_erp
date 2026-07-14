<?php

namespace App\Http\Controllers\Api\V1\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workflow\StoreApprovalWorkflowRequest;
use App\Http\Requests\Workflow\UpdateApprovalWorkflowRequest;
use App\Http\Resources\ApprovalWorkflowResource;
use App\Models\ApprovalWorkflow;
use Illuminate\Support\Facades\DB;

class ApprovalWorkflowController extends Controller
{
    public function index()
    {
        return ApprovalWorkflowResource::collection(
            ApprovalWorkflow::query()->with('steps')->latest()->get()
        );
    }

    public function store(StoreApprovalWorkflowRequest $request)
    {
        $data = $request->validated();
        $steps = $data['steps'];
        unset($data['steps']);

        $workflow = DB::transaction(function () use ($data, $steps) {
            $workflow = ApprovalWorkflow::query()->create($data);
            $this->syncSteps($workflow, $steps);

            return $workflow;
        });

        return new ApprovalWorkflowResource($workflow->load('steps'));
    }

    public function show(ApprovalWorkflow $workflow)
    {
        return new ApprovalWorkflowResource($workflow->load('steps'));
    }

    public function update(UpdateApprovalWorkflowRequest $request, ApprovalWorkflow $workflow)
    {
        $data = $request->validated();
        $steps = $data['steps'] ?? null;
        unset($data['steps']);

        DB::transaction(function () use ($workflow, $data, $steps) {
            $workflow->update($data);

            if ($steps !== null) {
                $workflow->steps()->delete();
                $this->syncSteps($workflow, $steps);
            }
        });

        return new ApprovalWorkflowResource($workflow->load('steps'));
    }

    public function destroy(ApprovalWorkflow $workflow)
    {
        $workflow->delete();

        return response()->json(status: 204);
    }

    private function syncSteps(ApprovalWorkflow $workflow, array $steps): void
    {
        foreach ($steps as $index => $step) {
            $workflow->steps()->create([
                'position' => $index + 1,
                'approver_role' => $step['approver_role'],
            ]);
        }
    }
}
