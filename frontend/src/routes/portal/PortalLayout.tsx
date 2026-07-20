import ChatIcon from '@mui/icons-material/Chat';
import DashboardIcon from '@mui/icons-material/Dashboard';
import DescriptionIcon from '@mui/icons-material/Description';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong';
import RequestQuoteIcon from '@mui/icons-material/RequestQuote';
import VpnKeyIcon from '@mui/icons-material/VpnKey';
import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router-dom';
import { AppShell, type AppShellNavGroup } from '../../components/layout/AppShell';
import { BrandThemeProvider } from '../../app/theme/BrandThemeProvider';

const iconProps = { fontSize: 'small' } as const;

export function PortalLayout() {
  const { t } = useTranslation('portal');

  const navGroups: AppShellNavGroup[] = [
    {
      items: [
        { label: t('nav.dashboard'), path: '/portal/dashboard', enabled: true, icon: <DashboardIcon {...iconProps} /> },
        { label: t('nav.shipments'), path: '/portal/shipments', enabled: true, icon: <LocalShippingIcon {...iconProps} /> },
        { label: t('nav.invoices'), path: '/portal/invoices', enabled: true, icon: <ReceiptLongIcon {...iconProps} /> },
        { label: t('nav.quotations'), path: '/portal/quotations', enabled: true, icon: <RequestQuoteIcon {...iconProps} /> },
        { label: t('nav.documents'), path: '/portal/documents', enabled: true, icon: <DescriptionIcon {...iconProps} /> },
        { label: t('nav.messages'), path: '/portal/messages', enabled: true, icon: <ChatIcon {...iconProps} /> },
        { label: t('nav.apiKeys'), path: '/portal/api-keys', enabled: true, icon: <VpnKeyIcon {...iconProps} /> },
      ],
    },
  ];

  return (
    <BrandThemeProvider>
      <AppShell title="Client Portal" navGroups={navGroups} homePath="/portal/dashboard" showCommandPalette={false}>
        <Outlet />
      </AppShell>
    </BrandThemeProvider>
  );
}
