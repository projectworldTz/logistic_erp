import { api } from '../axios';
import type { Invoice, Paginated } from '../../types';

export async function fetchInvoices(page = 1): Promise<Paginated<Invoice>> {
  const { data } = await api.get<Paginated<Invoice>>('/finance/invoices', { params: { page } });
  return data;
}

export async function createInvoice(payload: Partial<Invoice>): Promise<Invoice> {
  const { data } = await api.post<{ data: Invoice }>('/finance/invoices', payload);
  return data.data;
}

export async function updateInvoice(id: number, payload: Partial<Invoice>): Promise<Invoice> {
  const { data } = await api.put<{ data: Invoice }>(`/finance/invoices/${id}`, payload);
  return data.data;
}

export async function deleteInvoice(id: number): Promise<void> {
  await api.delete(`/finance/invoices/${id}`);
}

export async function downloadInvoicePdf(id: number): Promise<Blob> {
  const { data } = await api.get(`/finance/invoices/${id}/pdf`, { responseType: 'blob' });
  return data;
}
