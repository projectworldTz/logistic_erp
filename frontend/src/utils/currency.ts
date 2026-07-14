/**
 * Formats an amount using the tenant's configured currency (set at
 * registration, changeable later in Company Settings) instead of a
 * hardcoded "$" — so switching the setting actually changes what's shown.
 */
export function formatCurrency(amount: number | string, currencyCode?: string | null): string {
  const numericAmount = typeof amount === 'string' ? Number(amount) : amount;
  const code = currencyCode && currencyCode.length === 3 ? currencyCode : 'USD';

  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: code }).format(numericAmount);
  } catch {
    return `${code} ${numericAmount.toLocaleString()}`;
  }
}
