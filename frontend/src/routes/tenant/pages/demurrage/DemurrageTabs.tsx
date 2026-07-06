import { Tab, Tabs } from '@mui/material';
import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

export function DemurrageTabs() {
  const { t } = useTranslation('demurrage');
  const location = useLocation();
  const navigate = useNavigate();
  const value = location.pathname.startsWith('/app/demurrage/rate-cards') ? 1 : 0;

  return (
    <Tabs
      value={value}
      onChange={(_, next) => navigate(next === 0 ? '/app/demurrage' : '/app/demurrage/rate-cards')}
      sx={{ mb: 3 }}
    >
      <Tab label={t('tabs.dashboard')} />
      <Tab label={t('tabs.rateCards')} />
    </Tabs>
  );
}
