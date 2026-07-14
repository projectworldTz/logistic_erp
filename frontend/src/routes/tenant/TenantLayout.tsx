import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router-dom';
import { DashboardShell } from '../../components/layout/DashboardShell';
import { useAuthStore } from '../../hooks/useAuth';
import { BrandThemeProvider } from '../../app/theme/BrandThemeProvider';
import { TENANT_NAV_ITEMS } from './nav/navConfig';

export function TenantLayout() {
  const { t } = useTranslation('nav');
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];
  const navItems = TENANT_NAV_ITEMS.filter((item) => !item.permission || permissions.includes(item.permission)).map(
    (item) => ({ label: t(item.labelKey), path: item.path, enabled: item.enabled }),
  );

  return (
    <BrandThemeProvider>
      <DashboardShell title="Logistics ERP" navItems={navItems}>
        <Outlet />
      </DashboardShell>
    </BrandThemeProvider>
  );
}
