import { api } from '../axios';
import type {
  AuditLog,
  ErrorLogItem,
  LandingContent,
  LandingContentKey,
  LandingContentSection,
  Paginated,
  Plan,
  PlatformMetrics,
  Subscription,
  SystemHealth,
  Tenant,
} from '../../types';

export async function fetchTenants(page = 1): Promise<Paginated<Tenant>> {
  const { data } = await api.get<Paginated<Tenant>>('/platform/tenants', { params: { page } });
  return data;
}

export async function fetchTenant(id: number): Promise<Tenant> {
  const { data } = await api.get<{ data: Tenant }>(`/platform/tenants/${id}`);
  return data.data;
}

export async function suspendTenant(id: number): Promise<Tenant> {
  const { data } = await api.post<{ data: Tenant }>(`/platform/tenants/${id}/suspend`);
  return data.data;
}

export async function activateTenant(id: number): Promise<Tenant> {
  const { data } = await api.post<{ data: Tenant }>(`/platform/tenants/${id}/activate`);
  return data.data;
}

export async function fetchPlatformPlans(): Promise<Plan[]> {
  const { data } = await api.get<{ data: Plan[] }>('/platform/plans');
  return data.data;
}

export async function createPlan(payload: Partial<Plan>): Promise<Plan> {
  const { data } = await api.post<{ data: Plan }>('/platform/plans', payload);
  return data.data;
}

export async function updatePlan(id: number, payload: Partial<Plan>): Promise<Plan> {
  const { data } = await api.put<{ data: Plan }>(`/platform/plans/${id}`, payload);
  return data.data;
}

export async function deletePlan(id: number): Promise<void> {
  await api.delete(`/platform/plans/${id}`);
}

export async function fetchSubscriptions(page = 1): Promise<Paginated<Subscription>> {
  const { data } = await api.get<Paginated<Subscription>>('/platform/subscriptions', { params: { page } });
  return data;
}

export async function fetchPlatformMetrics(): Promise<PlatformMetrics> {
  const { data } = await api.get<PlatformMetrics>('/platform/metrics');
  return data;
}

export async function fetchSystemHealth(): Promise<SystemHealth> {
  const { data } = await api.get<SystemHealth>('/platform/system-health');
  return data;
}

export async function fetchPlatformAuditLogs(page = 1): Promise<Paginated<AuditLog>> {
  const { data } = await api.get<Paginated<AuditLog>>('/platform/audit-logs', { params: { page } });
  return data;
}

export interface ErrorLogFilters {
  page?: number;
  tenant_id?: number;
  resolved?: boolean;
  q?: string;
}

export async function fetchErrorLogs(filters: ErrorLogFilters = {}): Promise<Paginated<ErrorLogItem>> {
  const { data } = await api.get<Paginated<ErrorLogItem>>('/platform/error-logs', { params: filters });
  return data;
}

export async function fetchErrorLog(id: number): Promise<ErrorLogItem> {
  const { data } = await api.get<{ data: ErrorLogItem }>(`/platform/error-logs/${id}`);
  return data.data;
}

export async function resolveErrorLog(id: number): Promise<ErrorLogItem> {
  const { data } = await api.post<{ data: ErrorLogItem }>(`/platform/error-logs/${id}/resolve`);
  return data.data;
}

export async function fetchPlatformLandingContent(): Promise<LandingContentSection[]> {
  const { data } = await api.get<{ data: LandingContentSection[] }>('/platform/landing-content');
  return data.data;
}

export async function updateLandingContentSection<K extends LandingContentKey>(
  key: K,
  content: LandingContent[K],
): Promise<LandingContentSection<K>> {
  const { data } = await api.put<{ data: LandingContentSection<K> }>(`/platform/landing-content/${key}`, { content });
  return data.data;
}

export async function uploadLandingImage(file: File, purpose: 'hero' | 'avatar'): Promise<string> {
  const form = new FormData();
  form.append('image', file);
  form.append('purpose', purpose);

  const { data } = await api.post<{ url: string }>('/platform/landing-content/upload-image', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.url;
}
