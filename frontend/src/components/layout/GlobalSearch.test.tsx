import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import { render, screen, waitFor } from '../../test/test-utils';
import { GlobalSearch } from './GlobalSearch';
import { searchAll } from '../../api/endpoints/search';

vi.mock('../../api/endpoints/search', () => ({
  searchAll: vi.fn(),
}));

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return { ...actual, useNavigate: () => mockNavigate };
});

describe('GlobalSearch', () => {
  beforeEach(() => {
    vi.mocked(searchAll).mockReset();
    mockNavigate.mockReset();
  });

  it('does not call searchAll before the popover is opened', () => {
    render(<GlobalSearch />);
    expect(searchAll).not.toHaveBeenCalled();
  });

  it('debounces and calls searchAll once a query of 2+ characters is typed, then renders grouped results', async () => {
    vi.mocked(searchAll).mockResolvedValue({
      customers: [{ id: 1, label: 'Kilimanjaro Coffee Exporters Ltd', path: '/app/crm/customers/1' }],
      quotations: [{ id: 5, label: 'QT-2026-00005', path: '/app/quotations' }],
    });

    const user = userEvent.setup();
    render(<GlobalSearch />);

    await user.click(screen.getByLabelText('Search'));
    await user.type(screen.getByPlaceholderText(/search customers/i), 'Kilimanjaro');

    await waitFor(() => expect(searchAll).toHaveBeenCalledWith('Kilimanjaro'));

    expect(await screen.findByText('Kilimanjaro Coffee Exporters Ltd')).toBeInTheDocument();
    expect(screen.getByText('QT-2026-00005')).toBeInTheDocument();
    expect(screen.getByText('Customers')).toBeInTheDocument();
    expect(screen.getByText('Quotations')).toBeInTheDocument();
  });

  it('navigates to the result path and closes the popover when a result is clicked', async () => {
    vi.mocked(searchAll).mockResolvedValue({
      customers: [{ id: 1, label: 'Kilimanjaro Coffee Exporters Ltd', path: '/app/crm/customers/1' }],
    });

    const user = userEvent.setup();
    render(<GlobalSearch />);

    await user.click(screen.getByLabelText('Search'));
    await user.type(screen.getByPlaceholderText(/search customers/i), 'Kilimanjaro');

    const result = await screen.findByText('Kilimanjaro Coffee Exporters Ltd');
    await user.click(result);

    expect(mockNavigate).toHaveBeenCalledWith('/app/crm/customers/1');
    await waitFor(() => expect(screen.queryByText('Kilimanjaro Coffee Exporters Ltd')).not.toBeInTheDocument());
  });
});
