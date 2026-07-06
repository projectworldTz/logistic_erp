import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router-dom';
import { DashboardShell } from '../../components/layout/DashboardShell';

export function PortalLayout() {
  const { t } = useTranslation('portal');

  const navItems = [
    { label: t('nav.dashboard'), path: '/portal/dashboard', enabled: true },
    { label: t('nav.shipments'), path: '/portal/shipments', enabled: true },
    { label: t('nav.invoices'), path: '/portal/invoices', enabled: true },
    { label: t('nav.quotations'), path: '/portal/quotations', enabled: true },
    { label: t('nav.documents'), path: '/portal/documents', enabled: true },
    { label: t('nav.messages'), path: '/portal/messages', enabled: true },
  ];

  return (
    <DashboardShell title="Client Portal" navItems={navItems}>
      <Outlet />
    </DashboardShell>
  );
}
