import { api } from '../axios';
import type { DetentionCharge, DetentionDashboardRow, DetentionRateCard, Invoice, Paginated } from '../../types';

export interface DetentionRateTierInput {
  from_day: number;
  to_day: number | null;
  daily_rate: number;
}

export interface DetentionRateCardPayload {
  name: string;
  container_type?: string | null;
  free_days: number;
  currency?: string;
  is_default?: boolean;
  tiers: DetentionRateTierInput[];
}

export async function fetchDetentionDashboard(): Promise<{ data: DetentionDashboardRow[] }> {
  const { data } = await api.get<{ data: DetentionDashboardRow[] }>('/detention/dashboard');
  return data;
}

export async function fetchDetentionRateCards(): Promise<{ data: DetentionRateCard[] }> {
  const { data } = await api.get<{ data: DetentionRateCard[] }>('/detention/rate-cards');
  return data;
}

export async function createDetentionRateCard(payload: DetentionRateCardPayload): Promise<DetentionRateCard> {
  const { data } = await api.post<{ data: DetentionRateCard }>('/detention/rate-cards', payload);
  return data.data;
}

export async function updateDetentionRateCard(id: number, payload: Partial<DetentionRateCardPayload>): Promise<DetentionRateCard> {
  const { data } = await api.put<{ data: DetentionRateCard }>(`/detention/rate-cards/${id}`, payload);
  return data.data;
}

export async function deleteDetentionRateCard(id: number): Promise<void> {
  await api.delete(`/detention/rate-cards/${id}`);
}

export async function fetchDetentionCharges(page = 1): Promise<Paginated<DetentionCharge>> {
  const { data } = await api.get<Paginated<DetentionCharge>>('/detention/charges', { params: { page } });
  return data;
}

export async function calculateDetentionCharge(containerId: number): Promise<DetentionCharge> {
  const { data } = await api.post<{ data: DetentionCharge }>(`/containers/items/${containerId}/detention/calculate`);
  return data.data;
}

export async function waiveDetentionCharge(chargeId: number, reason: string): Promise<DetentionCharge> {
  const { data } = await api.post<{ data: DetentionCharge }>(`/detention/charges/${chargeId}/waive`, { reason });
  return data.data;
}

export async function generateDetentionInvoice(chargeId: number): Promise<Invoice> {
  const { data } = await api.post<{ data: Invoice }>(`/detention/charges/${chargeId}/generate-invoice`);
  return data.data;
}
