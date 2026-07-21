import { api } from '../axios';
import type { ExchangeRate, Paginated } from '../../types';

export interface ExchangeRatePayload {
  base_currency: string;
  quote_currency: string;
  rate: number;
  rate_date: string;
}

export interface ConvertCurrencyResult {
  amount: number;
  from: string;
  to: string;
  converted_amount: number;
  rate: number;
}

export async function fetchExchangeRates(page = 1): Promise<Paginated<ExchangeRate>> {
  const { data } = await api.get<Paginated<ExchangeRate>>('/finance/exchange-rates', { params: { page } });
  return data;
}

export async function createExchangeRate(payload: ExchangeRatePayload): Promise<ExchangeRate> {
  const { data } = await api.post<{ data: ExchangeRate }>('/finance/exchange-rates', payload);
  return data.data;
}

export async function deleteExchangeRate(id: number): Promise<void> {
  await api.delete(`/finance/exchange-rates/${id}`);
}

export async function convertCurrency(payload: { amount: number; from: string; to: string; date?: string }): Promise<ConvertCurrencyResult> {
  // Failures are shown inline next to the converter, not as a toast.
  const { data } = await api.post<ConvertCurrencyResult>('/finance/exchange-rates/convert', payload, { skipErrorToast: true });
  return data;
}
