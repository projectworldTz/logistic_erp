import { api } from '../axios';
import type { Branch, Company, DashboardSummary, Paginated, AuditLog, User } from '../../types';

export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const { data } = await api.get<{ data: DashboardSummary }>('/dashboard/summary');
  return data.data;
}

export async function fetchCompany(): Promise<Company> {
  const { data } = await api.get<{ data: Company }>('/company');
  return data.data;
}

export async function updateCompany(payload: Partial<Company>): Promise<Company> {
  const { data } = await api.put<{ data: Company }>('/company', payload);
  return data.data;
}

export async function fetchBranches(): Promise<Branch[]> {
  const { data } = await api.get<{ data: Branch[] }>('/branches');
  return data.data;
}

export async function fetchUsers(): Promise<User[]> {
  const { data } = await api.get<{ data: User[] }>('/users');
  return data.data;
}

export async function fetchAuditLogs(page = 1): Promise<Paginated<AuditLog>> {
  const { data } = await api.get<Paginated<AuditLog>>('/audit-logs', { params: { page } });
  return data;
}
