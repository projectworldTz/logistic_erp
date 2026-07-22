import { api } from '../axios';
import type {
  EmployeeIdentityManualReview,
  EmployeeIdentityVerification,
  IdentityProviderSettings,
} from '../../types';

export interface IdentityVerificationPayload {
  document_type: string;
  identity_number: string;
  country_code: string;
  date_of_birth?: string | null;
  phone_number?: string | null;
}

export async function verifyIdentity(payload: IdentityVerificationPayload): Promise<EmployeeIdentityVerification> {
  const { data } = await api.post<{ data: EmployeeIdentityVerification }>('/hr/identity-verifications', payload);
  return data.data;
}

export async function fetchIdentityVerification(id: number): Promise<EmployeeIdentityVerification> {
  const { data } = await api.get<{ data: EmployeeIdentityVerification }>(`/hr/identity-verifications/${id}`);
  return data.data;
}

export async function confirmIdentityVerification(id: number): Promise<EmployeeIdentityVerification> {
  const { data } = await api.post<{ data: EmployeeIdentityVerification }>(`/hr/identity-verifications/${id}/confirm`);
  return data.data;
}

export async function rejectIdentityVerification(id: number): Promise<EmployeeIdentityVerification> {
  const { data } = await api.post<{ data: EmployeeIdentityVerification }>(`/hr/identity-verifications/${id}/reject`);
  return data.data;
}

export async function retryIdentityVerification(
  id: number,
  payload: IdentityVerificationPayload,
): Promise<EmployeeIdentityVerification> {
  const { data } = await api.post<{ data: EmployeeIdentityVerification }>(`/hr/identity-verifications/${id}/retry`, payload);
  return data.data;
}

export interface ManualReviewPayload {
  reason: string;
  notes?: string;
  supporting_document_type?: string;
  supporting_document_number?: string;
  file?: File | null;
}

export async function submitIdentityManualReview(
  verificationId: number,
  payload: ManualReviewPayload,
): Promise<EmployeeIdentityManualReview> {
  const form = new FormData();
  form.append('reason', payload.reason);
  if (payload.notes) form.append('notes', payload.notes);
  if (payload.supporting_document_type) form.append('supporting_document_type', payload.supporting_document_type);
  if (payload.supporting_document_number) form.append('supporting_document_number', payload.supporting_document_number);
  if (payload.file) form.append('file', payload.file);

  const { data } = await api.post<{ data: EmployeeIdentityManualReview }>(
    `/hr/identity-verifications/${verificationId}/manual-review`,
    form,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  );
  return data.data;
}

export async function fetchEmployeeIdentityVerifications(employeeId: number): Promise<EmployeeIdentityVerification[]> {
  const { data } = await api.get<{ data: EmployeeIdentityVerification[] }>(`/hr/employees/${employeeId}/identity-verifications`);
  return data.data;
}

export async function fetchEmployeeIdentityManualReviews(employeeId: number): Promise<EmployeeIdentityManualReview[]> {
  const { data } = await api.get<{ data: EmployeeIdentityManualReview[] }>(`/hr/employees/${employeeId}/identity-manual-reviews`);
  return data.data;
}

export async function approveIdentityManualReview(id: number, reviewerNotes?: string): Promise<EmployeeIdentityManualReview> {
  const { data } = await api.post<{ data: EmployeeIdentityManualReview }>(
    `/hr/identity-manual-reviews/${id}/approve`,
    { reviewer_notes: reviewerNotes },
  );
  return data.data;
}

export async function rejectIdentityManualReview(id: number, reviewerNotes?: string): Promise<EmployeeIdentityManualReview> {
  const { data } = await api.post<{ data: EmployeeIdentityManualReview }>(
    `/hr/identity-manual-reviews/${id}/reject`,
    { reviewer_notes: reviewerNotes },
  );
  return data.data;
}

export async function resyncEmployeeIdentity(
  employeeId: number,
  payload: IdentityVerificationPayload,
): Promise<EmployeeIdentityVerification> {
  const { data } = await api.post<{ data: EmployeeIdentityVerification }>(`/hr/employees/${employeeId}/identity-resync`, payload);
  return data.data;
}

export async function fetchIdentityProviderSettings(): Promise<IdentityProviderSettings> {
  const { data } = await api.get<{ data: IdentityProviderSettings }>('/hr/identity-provider-settings');
  return data.data;
}
