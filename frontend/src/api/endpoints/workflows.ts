import { api } from '../axios';
import type { ApprovalWorkflow } from '../../types';

export interface ApprovalWorkflowStepInput {
  approver_role: string;
}

export interface ApprovalWorkflowPayload {
  name: string;
  subject_type: string;
  min_amount?: number | null;
  is_active?: boolean;
  steps: ApprovalWorkflowStepInput[];
}

export async function fetchApprovalWorkflows(): Promise<{ data: ApprovalWorkflow[] }> {
  const { data } = await api.get<{ data: ApprovalWorkflow[] }>('/workflows/definitions');
  return data;
}

export async function createApprovalWorkflow(payload: ApprovalWorkflowPayload): Promise<ApprovalWorkflow> {
  const { data } = await api.post<{ data: ApprovalWorkflow }>('/workflows/definitions', payload);
  return data.data;
}

export async function updateApprovalWorkflow(id: number, payload: Partial<ApprovalWorkflowPayload>): Promise<ApprovalWorkflow> {
  const { data } = await api.put<{ data: ApprovalWorkflow }>(`/workflows/definitions/${id}`, payload);
  return data.data;
}

export async function deleteApprovalWorkflow(id: number): Promise<void> {
  await api.delete(`/workflows/definitions/${id}`);
}
