import { Tab, Tabs } from '@mui/material';
import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

export function CrmTabs() {
  const { t } = useTranslation('crm');
  const location = useLocation();
  const navigate = useNavigate();
  const value = location.pathname.startsWith('/app/crm/customers') ? 1 : 0;

  return (
    <Tabs
      value={value}
      onChange={(_, next) => navigate(next === 0 ? '/app/crm' : '/app/crm/customers')}
      sx={{ mb: 3 }}
    >
      <Tab label={t('tabs.leads')} />
      <Tab label={t('tabs.customers')} />
    </Tabs>
  );
}
