import ChatIcon from '@mui/icons-material/Chat';
import DashboardIcon from '@mui/icons-material/Dashboard';
import DescriptionIcon from '@mui/icons-material/Description';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong';
import RequestQuoteIcon from '@mui/icons-material/RequestQuote';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router-dom';
import { DashboardShell, type ShellNavGroup } from '../../components/layout/DashboardShell';
import { BrandThemeProvider } from '../../app/theme/BrandThemeProvider';
import { fetchCompany } from '../../api/endpoints/dashboard';

const iconProps = { fontSize: 'small' } as const;

export function PortalLayout() {
  const { t } = useTranslation('portal');
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany, retry: false });

  const navItems: ShellNavGroup[] = [
    {
      items: [
        { label: t('nav.dashboard'), path: '/portal/dashboard', enabled: true, icon: <DashboardIcon {...iconProps} /> },
        { label: t('nav.shipments'), path: '/portal/shipments', enabled: true, icon: <LocalShippingIcon {...iconProps} /> },
        { label: t('nav.invoices'), path: '/portal/invoices', enabled: true, icon: <ReceiptLongIcon {...iconProps} /> },
        { label: t('nav.quotations'), path: '/portal/quotations', enabled: true, icon: <RequestQuoteIcon {...iconProps} /> },
        { label: t('nav.documents'), path: '/portal/documents', enabled: true, icon: <DescriptionIcon {...iconProps} /> },
        { label: t('nav.messages'), path: '/portal/messages', enabled: true, icon: <ChatIcon {...iconProps} /> },
      ],
    },
  ];

  return (
    <BrandThemeProvider>
      <DashboardShell title={company?.name ? `${company.name} Portal` : 'Client Portal'} navItems={navItems}>
        <Outlet />
      </DashboardShell>
    </BrandThemeProvider>
  );
}
