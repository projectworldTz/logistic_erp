import { api } from '../axios';
import type { Account, JournalEntry, Paginated } from '../../types';

export async function fetchAccounts(page = 1): Promise<Paginated<Account>> {
  const { data } = await api.get<Paginated<Account>>('/accounting/accounts', { params: { page } });
  return data;
}

export async function createAccount(payload: Partial<Account>): Promise<Account> {
  const { data } = await api.post<{ data: Account }>('/accounting/accounts', payload);
  return data.data;
}

export async function deleteAccount(id: number): Promise<void> {
  await api.delete(`/accounting/accounts/${id}`);
}

export async function fetchJournalEntries(page = 1): Promise<Paginated<JournalEntry>> {
  const { data } = await api.get<Paginated<JournalEntry>>('/accounting/journal-entries', { params: { page } });
  return data;
}

interface JournalEntryPayload {
  entry_date: string;
  description?: string;
  reference?: string;
  lines: { account_id: number; debit: number; credit: number }[];
}

export async function createJournalEntry(payload: JournalEntryPayload): Promise<JournalEntry> {
  const { data } = await api.post<{ data: JournalEntry }>('/accounting/journal-entries', payload);
  return data.data;
}

export async function deleteJournalEntry(id: number): Promise<void> {
  await api.delete(`/accounting/journal-entries/${id}`);
}

export async function postJournalEntry(id: number): Promise<JournalEntry> {
  const { data } = await api.post<{ data: JournalEntry }>(`/accounting/journal-entries/${id}/post`);
  return data.data;
}

export async function voidJournalEntry(id: number): Promise<JournalEntry> {
  const { data } = await api.post<{ data: JournalEntry }>(`/accounting/journal-entries/${id}/void`);
  return data.data;
}
