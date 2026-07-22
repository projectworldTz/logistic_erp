import { useQuery } from '@tanstack/react-query';
import { fetchCompany } from '../api/endpoints/dashboard';
import { formatCurrency } from '../utils/currency';

/**
 * Converts an amount recorded in `fromCurrency` into the tenant's current
 * system currency (Company Settings) using the owner-set USD<->TZS rate.
 * Stored amounts are never touched — this only affects what's displayed,
 * so switching the system currency instantly updates every screen without
 * rewriting historical records.
 */
export function convertToSystemCurrency(amount: number, fromCurrency: string | null | undefined, systemCurrency: string, rate: number): number {
  const from = (fromCurrency || systemCurrency).toUpperCase();
  const system = systemCurrency.toUpperCase();

  if (from === system || !rate) return amount;
  if (from === 'USD' && system === 'TZS') return amount * rate;
  if (from === 'TZS' && system === 'USD') return amount / rate;

  return amount;
}

export function useCurrencyFormatter() {
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany, retry: false });

  const systemCurrency = company?.currency || 'TZS';
  const rate = Number(company?.usd_to_tzs_rate) || 0;

  const convert = (amount: number, fromCurrency?: string | null) => convertToSystemCurrency(amount, fromCurrency, systemCurrency, rate);

  const format = (amount: number | string, fromCurrency?: string | null) => {
    const numericAmount = typeof amount === 'string' ? Number(amount) : amount;
    return formatCurrency(convert(numericAmount, fromCurrency), systemCurrency);
  };

  return { systemCurrency, rate, convert, format };
}
