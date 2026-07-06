import { Tab, Tabs } from '@mui/material';
import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

export function AccountingTabs() {
  const { t } = useTranslation('accounting');
  const location = useLocation();
  const navigate = useNavigate();
  const value = location.pathname.startsWith('/app/accounting/journal-entries') ? 1 : 0;

  return (
    <Tabs
      value={value}
      onChange={(_, next) => navigate(next === 0 ? '/app/accounting' : '/app/accounting/journal-entries')}
      sx={{ mb: 3 }}
    >
      <Tab label={t('tabs.chartOfAccounts')} />
      <Tab label={t('tabs.journalEntries')} />
    </Tabs>
  );
}
