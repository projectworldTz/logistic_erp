import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router-dom';
import { DashboardShell, type ShellNavGroup } from '../../components/layout/DashboardShell';
import { useAuthStore } from '../../hooks/useAuth';
import { BrandThemeProvider } from '../../app/theme/BrandThemeProvider';
import { TENANT_NAV_GROUPS } from './nav/navConfig';

export function TenantLayout() {
  const { t } = useTranslation('nav');
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];

  const navItems: ShellNavGroup[] = TENANT_NAV_GROUPS.map((group) => ({
    label: group.labelKey ? t(group.labelKey) : undefined,
    icon: group.icon,
    items: group.items
      .filter((item) => !item.permission || permissions.includes(item.permission))
      .map((item) => ({ label: t(item.labelKey), path: item.path, enabled: item.enabled, icon: item.icon })),
  })).filter((group) => group.items.length > 0);

  return (
    <BrandThemeProvider>
      <DashboardShell title="Logistics ERP" navItems={navItems}>
        <Outlet />
      </DashboardShell>
    </BrandThemeProvider>
  );
}
