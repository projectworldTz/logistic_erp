import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router-dom';
import { DashboardShell } from '../../components/layout/DashboardShell';
import { BrandThemeProvider } from '../../app/theme/BrandThemeProvider';
import { fetchCompany } from '../../api/endpoints/dashboard';

export function PortalLayout() {
  const { t } = useTranslation('portal');
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany, retry: false });

  const navItems = [
    { label: t('nav.dashboard'), path: '/portal/dashboard', enabled: true },
    { label: t('nav.shipments'), path: '/portal/shipments', enabled: true },
    { label: t('nav.invoices'), path: '/portal/invoices', enabled: true },
    { label: t('nav.quotations'), path: '/portal/quotations', enabled: true },
    { label: t('nav.documents'), path: '/portal/documents', enabled: true },
    { label: t('nav.messages'), path: '/portal/messages', enabled: true },
  ];

  return (
    <BrandThemeProvider>
      <DashboardShell title={company?.name ? `${company.name} Portal` : 'Client Portal'} navItems={navItems}>
        <Outlet />
      </DashboardShell>
    </BrandThemeProvider>
  );
}
