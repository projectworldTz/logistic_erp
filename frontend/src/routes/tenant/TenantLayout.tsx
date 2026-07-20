import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router-dom';
import { AppShell, type AppShellNavGroup } from '../../components/layout/AppShell';
import { useAuthStore } from '../../hooks/useAuth';
import { BrandThemeProvider } from '../../app/theme/BrandThemeProvider';
import { TENANT_NAV_GROUPS } from './nav/navConfig';

export function TenantLayout() {
  const { t } = useTranslation('nav');
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];

  const navGroups: AppShellNavGroup[] = TENANT_NAV_GROUPS.map((group) => ({
    label: group.labelKey ? t(group.labelKey) : undefined,
    icon: group.icon,
    items: group.items
      .filter((item) => !item.permission || permissions.includes(item.permission))
      .map((item) => ({
        label: t(item.labelKey),
        path: item.path,
        enabled: item.enabled,
        icon: item.icon,
        description: t(`descriptions.${item.labelKey}`, { defaultValue: '' }),
      })),
  })).filter((group) => group.items.length > 0);

  return (
    <BrandThemeProvider>
      <AppShell title="Logistics ERP" navGroups={navGroups} homePath="/app/dashboard">
        <Outlet />
      </AppShell>
    </BrandThemeProvider>
  );
}
