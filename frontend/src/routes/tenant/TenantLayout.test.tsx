import { Route, Routes } from 'react-router-dom';
import { render, screen } from '../../test/test-utils';
import { useAuthStore } from '../../hooks/useAuth';
import { TenantLayout } from './TenantLayout';
import type { User } from '../../types';

function renderWithPermissions(permissions: string[]) {
  useAuthStore.setState({
    token: 'fake-token',
    user: {
      id: 1,
      tenant_id: 8,
      branch_id: null,
      customer_id: null,
      name: 'Jane Doe',
      email: 'jane@acme.test',
      phone: null,
      is_super_admin: false,
      status: 'active',
      two_factor_enabled: false,
      roles: ['Sales Executive'],
      permissions,
    } satisfies User,
  });

  return render(
    <Routes>
      <Route path="/*" element={<TenantLayout />} />
    </Routes>,
    { initialEntries: ['/app/dashboard'] },
  );
}

describe('TenantLayout permission-based nav filtering', () => {
  afterEach(() => {
    useAuthStore.setState({ token: null, user: null });
  });

  it('shows only Dashboard (no permission required) when the user has no module permissions', () => {
    renderWithPermissions([]);

    expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'CRM' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Quotations' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument();
  });

  it('shows exactly the nav items matching the permissions the user has', () => {
    renderWithPermissions(['crm.customers.view', 'quotations.items.view']);

    expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'CRM' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Quotations' })).toBeInTheDocument();

    // Not granted — must not render, not just be disabled.
    expect(screen.queryByRole('link', { name: 'Shipments' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Users' })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Finance' })).not.toBeInTheDocument();
  });

  it('shows every module when the user has every permission', () => {
    renderWithPermissions([
      'crm.customers.view',
      'quotations.items.view',
      'shipments.items.view',
      'clearing.files.view',
      'freight.bookings.view',
      'containers.items.view',
      'warehouse.items.view',
      'fleet.vehicles.view',
      'finance.invoices.view',
      'accounting.accounts.view',
      'documents.files.view',
      'reports.view',
      'core.users.view',
      'core.branches.view',
      'core.audit.view',
      'core.company.view',
    ]);

    for (const label of ['CRM', 'Quotations', 'Shipments', 'Clearing', 'Users', 'Company Settings']) {
      expect(screen.getByRole('link', { name: label })).toBeInTheDocument();
    }
  });
});
