import { api } from '../axios';
import type { DemurrageCharge, DemurrageDashboardRow, DemurrageRateCard, Invoice, Paginated } from '../../types';

export interface DemurrageRateTierInput {
  from_day: number;
  to_day: number | null;
  daily_rate: number;
}

export interface DemurrageRateCardPayload {
  name: string;
  container_type?: string | null;
  free_days: number;
  currency?: string;
  is_default?: boolean;
  tiers: DemurrageRateTierInput[];
}

export async function fetchDemurrageDashboard(): Promise<{ data: DemurrageDashboardRow[] }> {
  const { data } = await api.get<{ data: DemurrageDashboardRow[] }>('/demurrage/dashboard');
  return data;
}

export async function fetchDemurrageRateCards(): Promise<{ data: DemurrageRateCard[] }> {
  const { data } = await api.get<{ data: DemurrageRateCard[] }>('/demurrage/rate-cards');
  return data;
}

export async function createDemurrageRateCard(payload: DemurrageRateCardPayload): Promise<DemurrageRateCard> {
  const { data } = await api.post<{ data: DemurrageRateCard }>('/demurrage/rate-cards', payload);
  return data.data;
}

export async function updateDemurrageRateCard(id: number, payload: Partial<DemurrageRateCardPayload>): Promise<DemurrageRateCard> {
  const { data } = await api.put<{ data: DemurrageRateCard }>(`/demurrage/rate-cards/${id}`, payload);
  return data.data;
}

export async function deleteDemurrageRateCard(id: number): Promise<void> {
  await api.delete(`/demurrage/rate-cards/${id}`);
}

export async function fetchDemurrageCharges(page = 1): Promise<Paginated<DemurrageCharge>> {
  const { data } = await api.get<Paginated<DemurrageCharge>>('/demurrage/charges', { params: { page } });
  return data;
}

export interface DemurrageCalculateResult {
  data: DemurrageCharge | null;
  reason?: 'within_free_days' | 'no_new_charge';
}

export async function calculateDemurrageCharge(containerId: number): Promise<DemurrageCalculateResult> {
  const { data } = await api.post<DemurrageCalculateResult>(`/containers/items/${containerId}/demurrage/calculate`);
  return data;
}

export async function waiveDemurrageCharge(chargeId: number, reason: string): Promise<DemurrageCharge> {
  const { data } = await api.post<{ data: DemurrageCharge }>(`/demurrage/charges/${chargeId}/waive`, { reason });
  return data.data;
}

export async function generateDemurrageInvoice(chargeId: number): Promise<Invoice> {
  const { data } = await api.post<{ data: Invoice }>(`/demurrage/charges/${chargeId}/generate-invoice`);
  return data.data;
}
