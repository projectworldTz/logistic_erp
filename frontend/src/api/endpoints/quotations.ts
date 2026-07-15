import { api } from '../axios';
import type { Paginated, Quotation, Shipment } from '../../types';

export interface QuotationItemInput {
  description: string;
  quantity: number;
  unit_price: number;
}

export interface QuotationPayload extends Partial<Omit<Quotation, 'items' | 'tax_amount'>> {
  tax_amount?: string | number;
  items?: QuotationItemInput[];
}

export async function fetchQuotations(page = 1): Promise<Paginated<Quotation>> {
  const { data } = await api.get<Paginated<Quotation>>('/quotations/items', { params: { page } });
  return data;
}

export async function createQuotation(payload: QuotationPayload): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>('/quotations/items', payload);
  return data.data;
}

export async function updateQuotation(id: number, payload: QuotationPayload): Promise<Quotation> {
  const { data } = await api.put<{ data: Quotation }>(`/quotations/items/${id}`, payload);
  return data.data;
}

export async function deleteQuotation(id: number): Promise<void> {
  await api.delete(`/quotations/items/${id}`);
}

export async function convertQuotationToShipment(id: number): Promise<Shipment> {
  const { data } = await api.post<{ data: Shipment }>(`/quotations/items/${id}/convert-to-shipment`);
  return data.data;
}

export async function submitQuotationForApproval(id: number): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>(`/quotations/items/${id}/submit`);
  return data.data;
}

export async function approveQuotation(id: number): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>(`/quotations/items/${id}/approve`);
  return data.data;
}

export async function rejectQuotation(id: number, reason: string): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>(`/quotations/items/${id}/reject`, { reason });
  return data.data;
}
