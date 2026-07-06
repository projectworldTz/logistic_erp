import { api } from '../axios';
import type { Paginated, Quotation } from '../../types';

export async function fetchQuotations(page = 1): Promise<Paginated<Quotation>> {
  const { data } = await api.get<Paginated<Quotation>>('/quotations/items', { params: { page } });
  return data;
}

export async function createQuotation(payload: Partial<Quotation>): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>('/quotations/items', payload);
  return data.data;
}

export async function updateQuotation(id: number, payload: Partial<Quotation>): Promise<Quotation> {
  const { data } = await api.put<{ data: Quotation }>(`/quotations/items/${id}`, payload);
  return data.data;
}

export async function deleteQuotation(id: number): Promise<void> {
  await api.delete(`/quotations/items/${id}`);
}
