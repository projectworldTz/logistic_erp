import { Tab, Tabs } from '@mui/material';
import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

export function HrTabs() {
  const { t } = useTranslation('hr');
  const location = useLocation();
  const navigate = useNavigate();
  const value = location.pathname.startsWith('/app/hr/departments')
    ? 1
    : location.pathname.startsWith('/app/hr/attendance')
      ? 2
      : 0;

  const paths = ['/app/hr', '/app/hr/departments', '/app/hr/attendance'];

  return (
    <Tabs value={value} onChange={(_, next) => navigate(paths[next])} sx={{ mb: 3 }}>
      <Tab label={t('tabs.employees')} />
      <Tab label={t('tabs.departments')} />
      <Tab label={t('tabs.attendance')} />
    </Tabs>
  );
}
