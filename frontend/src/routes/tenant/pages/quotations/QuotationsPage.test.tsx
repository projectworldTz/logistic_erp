import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import { render, screen, waitFor, within } from '../../../../test/test-utils';
import { QuotationsPage } from './QuotationsPage';
import { deleteQuotation, fetchQuotations } from '../../../../api/endpoints/quotations';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import type { Quotation } from '../../../../types';

vi.mock('../../../../api/endpoints/quotations', () => ({
  fetchQuotations: vi.fn(),
  createQuotation: vi.fn(),
  updateQuotation: vi.fn(),
  deleteQuotation: vi.fn(),
}));

vi.mock('../../../../api/endpoints/crm', () => ({
  fetchCustomers: vi.fn(),
}));

const sampleQuotation: Quotation = {
  id: 42,
  customer_id: 1,
  customer: { id: 1, company_name: 'Kilimanjaro Coffee Exporters Ltd' } as Quotation['customer'],
  quotation_number: 'QT-2026-00042',
  direction: 'export',
  mode: 'air',
  origin_port: 'Dar es Salaam',
  destination_port: 'Amsterdam',
  issue_date: '2026-07-01',
  valid_until: '2026-07-15',
  status: 'draft',
  subtotal: '1000.00',
  tax_amount: '0.00',
  total_amount: '1000.00',
  currency: 'USD',
  notes: null,
  created_at: '2026-07-01T00:00:00.000000Z',
};

describe('QuotationsPage', () => {
  beforeEach(() => {
    vi.mocked(fetchQuotations).mockResolvedValue({
      data: [sampleQuotation],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
    });
    vi.mocked(fetchCustomers).mockResolvedValue({
      data: [{ id: 1, company_name: 'Kilimanjaro Coffee Exporters Ltd' } as never],
      meta: { current_page: 1, last_page: 1, per_page: 30, total: 1 },
    });
    vi.mocked(deleteQuotation).mockResolvedValue(undefined);
  });

  it('renders the fetched quotation in the table', async () => {
    render(<QuotationsPage />);

    expect(await screen.findByText('QT-2026-00042')).toBeInTheDocument();
    expect(screen.getByText('Kilimanjaro Coffee Exporters Ltd')).toBeInTheDocument();
  });

  it('opens a confirmation dialog instead of deleting immediately when the delete icon is clicked', async () => {
    const user = userEvent.setup();
    render(<QuotationsPage />);

    await screen.findByText('QT-2026-00042');
    await user.click(screen.getByRole('button', { name: 'Delete' }));

    expect(await screen.findByText('Delete quotation')).toBeInTheDocument();
    expect(deleteQuotation).not.toHaveBeenCalled();
  });

  it('does not delete when the confirmation dialog is cancelled', async () => {
    const user = userEvent.setup();
    render(<QuotationsPage />);

    await screen.findByText('QT-2026-00042');
    await user.click(screen.getByRole('button', { name: 'Delete' }));
    await screen.findByText('Delete quotation');

    await user.click(screen.getByRole('button', { name: 'Cancel' }));

    await waitFor(() => expect(screen.queryByText('Delete quotation')).not.toBeInTheDocument());
    expect(deleteQuotation).not.toHaveBeenCalled();
  });

  it('deletes and shows a success toast when the confirmation dialog is confirmed', async () => {
    const user = userEvent.setup();
    render(<QuotationsPage />);

    await screen.findByText('QT-2026-00042');
    await user.click(screen.getByRole('button', { name: 'Delete' }));
    await screen.findByText('Delete quotation');

    const dialog = await screen.findByRole('dialog');
    await user.click(within(dialog).getByRole('button', { name: 'Delete' }));

    // React Query's mutationFn is invoked with a second internal context argument
    // (e.g. { client }) in this version, so only assert on the first argument.
    await waitFor(() => expect(vi.mocked(deleteQuotation).mock.calls[0]?.[0]).toBe(42));
    expect(await screen.findByText('Quotation deleted.')).toBeInTheDocument();
  });
});
