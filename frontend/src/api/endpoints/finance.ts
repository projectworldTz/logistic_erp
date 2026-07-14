import { api } from '../axios';
import type { Expense, Invoice, Paginated } from '../../types';

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

export async function fetchExpenses(page = 1, status?: string): Promise<Paginated<Expense>> {
  const { data } = await api.get<Paginated<Expense>>('/finance/expenses', { params: { page, status } });
  return data;
}

export async function createExpense(payload: Partial<Expense>): Promise<Expense> {
  const { data } = await api.post<{ data: Expense }>('/finance/expenses', payload);
  return data.data;
}

export async function updateExpense(id: number, payload: Partial<Expense>): Promise<Expense> {
  const { data } = await api.put<{ data: Expense }>(`/finance/expenses/${id}`, payload);
  return data.data;
}

export async function deleteExpense(id: number): Promise<void> {
  await api.delete(`/finance/expenses/${id}`);
}

export async function submitExpense(id: number): Promise<Expense> {
  const { data } = await api.post<{ data: Expense }>(`/finance/expenses/${id}/submit`);
  return data.data;
}

export async function approveExpense(id: number): Promise<Expense> {
  const { data } = await api.post<{ data: Expense }>(`/finance/expenses/${id}/approve`);
  return data.data;
}

export async function rejectExpense(id: number, reason: string): Promise<Expense> {
  const { data } = await api.post<{ data: Expense }>(`/finance/expenses/${id}/reject`, { reason });
  return data.data;
}

export async function markExpensePaid(id: number): Promise<Expense> {
  const { data } = await api.post<{ data: Expense }>(`/finance/expenses/${id}/mark-paid`);
  return data.data;
}
