import { Tab, Tabs } from '@mui/material';
import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

const PATHS = [
  '/app/hr',
  '/app/hr/employees',
  '/app/hr/departments',
  '/app/hr/designations',
  '/app/hr/contracts',
  '/app/hr/attendance',
  '/app/hr/shifts',
  '/app/hr/leave',
  '/app/hr/timesheets',
  '/app/hr/holidays',
  '/app/hr/payroll-components',
  '/app/hr/statutory-rules',
  '/app/hr/payroll-settings',
  '/app/hr/payroll-periods',
  '/app/hr/loans-advances',
  '/app/hr/overtime-requests',
  '/app/hr/payslips',
  '/app/hr/performance-reviews',
  '/app/hr/disciplinary-records',
  '/app/hr/employee-assets',
  '/app/hr/exit-records',
  '/app/hr/job-vacancies',
  '/app/hr/candidates',
  '/app/hr/job-applications',
  '/app/hr/onboarding',
];

export function HrTabs() {
  const { t } = useTranslation('hr');
  const location = useLocation();
  const navigate = useNavigate();

  const value = PATHS.reduce(
    (bestIndex, path, index) => (location.pathname.startsWith(path) && path.length > PATHS[bestIndex].length ? index : bestIndex),
    0,
  );

  return (
    <Tabs value={value} onChange={(_, next) => navigate(PATHS[next])} variant="scrollable" scrollButtons="auto" sx={{ mb: 3 }}>
      <Tab label={t('tabs.dashboard')} />
      <Tab label={t('tabs.employees')} />
      <Tab label={t('tabs.departments')} />
      <Tab label={t('tabs.designations')} />
      <Tab label={t('tabs.contracts')} />
      <Tab label={t('tabs.attendance')} />
      <Tab label={t('tabs.shifts')} />
      <Tab label={t('tabs.leave')} />
      <Tab label={t('tabs.timesheets')} />
      <Tab label={t('tabs.holidays')} />
      <Tab label={t('tabs.payrollComponents')} />
      <Tab label={t('tabs.statutoryRules')} />
      <Tab label={t('tabs.payrollSettings')} />
      <Tab label={t('tabs.payrollPeriods')} />
      <Tab label={t('tabs.loansAdvances')} />
      <Tab label={t('tabs.overtimeRequests')} />
      <Tab label={t('tabs.payslips')} />
      <Tab label={t('tabs.performanceReviews')} />
      <Tab label={t('tabs.disciplinary')} />
      <Tab label={t('tabs.assets')} />
      <Tab label={t('tabs.exits')} />
      <Tab label={t('tabs.vacancies')} />
      <Tab label={t('tabs.candidates')} />
      <Tab label={t('tabs.applications')} />
      <Tab label={t('tabs.onboarding')} />
    </Tabs>
  );
}
