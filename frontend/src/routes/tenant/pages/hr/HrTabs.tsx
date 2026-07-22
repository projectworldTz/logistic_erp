import { Tab, Tabs, alpha } from '@mui/material';
import { useLocation, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useThemeMode } from '../../../../app/theme/ThemeProvider';

// HR gets its own accent so the module reads as a distinct "place" in the
// app, while staying inside the same validated palette family used for the
// HR dashboard's payroll stat card — related to, not clashing with, the
// primary blue used everywhere else.
const VIOLET = { light: '#4a3aa7', dark: '#9085e9' };
const VIOLET_CONTRAST = { light: '#ffffff', dark: '#1e1b4b' };

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
  '/app/hr/identity-settings',
];

export function HrTabs() {
  const { t } = useTranslation('hr');
  const location = useLocation();
  const navigate = useNavigate();
  const { mode } = useThemeMode();
  const accent = VIOLET[mode];
  const accentContrast = VIOLET_CONTRAST[mode];

  const value = PATHS.reduce(
    (bestIndex, path, index) => (location.pathname.startsWith(path) && path.length > PATHS[bestIndex].length ? index : bestIndex),
    0,
  );

  return (
    <Tabs
      value={value}
      onChange={(_, next) => navigate(PATHS[next])}
      variant="standard"
      TabIndicatorProps={{ sx: { display: 'none' } }}
      sx={{
        mb: 3,
        minHeight: 0,
        bgcolor: alpha(accent, mode === 'dark' ? 0.16 : 0.08),
        border: '1px solid',
        borderColor: alpha(accent, mode === 'dark' ? 0.35 : 0.22),
        borderRadius: 3,
        p: 1,
        '& .MuiTabs-flexContainer': { flexWrap: 'wrap', gap: 0.75 },
        '& .MuiTab-root': {
          minHeight: 36,
          py: 0.75,
          px: 1.5,
          borderRadius: 8,
          textTransform: 'none',
          fontWeight: 600,
          border: '1px solid transparent',
          color: 'text.secondary',
          transition: 'background-color 0.15s ease, color 0.15s ease',
          '&:hover': {
            bgcolor: alpha(accent, 0.18),
            color: accent,
          },
        },
        '& .MuiTab-root.Mui-selected': {
          bgcolor: accent,
          color: accentContrast,
        },
      }}
    >
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
      <Tab label={t('tabs.identitySettings')} />
    </Tabs>
  );
}
