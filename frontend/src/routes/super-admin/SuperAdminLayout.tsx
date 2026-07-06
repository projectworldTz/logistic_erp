import { Outlet } from 'react-router-dom';
import { DashboardShell, type ShellNavItem } from '../../components/layout/DashboardShell';

const PLATFORM_NAV_ITEMS: ShellNavItem[] = [
  { label: 'Tenants', path: '/platform/tenants', enabled: true },
  { label: 'Plans', path: '/platform/plans', enabled: true },
  { label: 'Landing Content', path: '/platform/landing-content', enabled: true },
  { label: 'Metrics', path: '/platform/metrics', enabled: true },
  { label: 'Audit Log', path: '/platform/audit-log', enabled: true },
  { label: 'Error Log', path: '/platform/error-log', enabled: true },
];

export function SuperAdminLayout() {
  return (
    <DashboardShell title="Platform Admin" navItems={PLATFORM_NAV_ITEMS}>
      <Outlet />
    </DashboardShell>
  );
}
