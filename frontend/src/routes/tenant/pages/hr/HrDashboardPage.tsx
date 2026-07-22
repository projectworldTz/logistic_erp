import { Card, CardContent, CircularProgress, Grid, Stack, Typography } from '@mui/material';
import BadgeRoundedIcon from '@mui/icons-material/BadgeRounded';
import EventBusyRoundedIcon from '@mui/icons-material/EventBusyRounded';
import GroupAddRoundedIcon from '@mui/icons-material/GroupAddRounded';
import PaymentsRoundedIcon from '@mui/icons-material/PaymentsRounded';
import ScheduleRoundedIcon from '@mui/icons-material/ScheduleRounded';
import WorkOffRoundedIcon from '@mui/icons-material/WorkOffRounded';
import { LineChart } from '@mui/x-charts/LineChart';
import { PieChart } from '@mui/x-charts/PieChart';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchHrDashboard } from '../../../../api/endpoints/hr';
import { StatWidgetCard } from '../../../../components/common/StatWidgetCard';
import { useThemeMode } from '../../../../app/theme/ThemeProvider';
import { useCurrencyFormatter } from '../../../../hooks/useCurrency';
import { HrTabs } from './HrTabs';

// Validated categorical slots from the design system's reference palette
// (dataviz skill) — reused verbatim from DashboardHomePage/AnalyticsPage.
const BLUE = { light: '#2a78d6', dark: '#3987e5' };
const GREEN = { light: '#008300', dark: '#008300' };
const YELLOW = { light: '#eda100', dark: '#c98500' };
const AQUA = { light: '#1baf7a', dark: '#199e70' };
const VIOLET = { light: '#4a3aa7', dark: '#9085e9' };
const RED = { light: '#e34948', dark: '#e66767' };

export function HrDashboardPage() {
  const { t } = useTranslation('hr');
  const { format } = useCurrencyFormatter();
  const { mode } = useThemeMode();
  const pick = (palette: { light: string; dark: string }) => palette[mode];

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'dashboard'], queryFn: fetchHrDashboard });

  if (isLoading || !data) {
    return (
      <Stack spacing={3}>
        <Typography variant="h5" fontWeight={700}>{t('title')}</Typography>
        <HrTabs />
        <CircularProgress />
      </Stack>
    );
  }

  const departmentEntries = Object.entries(data.headcount.by_department);
  const attendanceEntries = Object.entries(data.attendance.today);
  const trend = data.payroll.trend;

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>{t('title')}</Typography>

      <HrTabs />

      <Typography variant="h6">{t('dashboard.title')}</Typography>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard label={t('dashboard.stats.headcount')} value={data.headcount.total} icon={<BadgeRoundedIcon />} accentColor={pick(BLUE)} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('dashboard.stats.pendingLeave')}
            value={data.leave.pending_requests}
            icon={<EventBusyRoundedIcon />}
            accentColor={pick(YELLOW)}
            tone={data.leave.pending_requests > 0 ? 'warning' : undefined}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('dashboard.stats.pendingPayrollApprovals')}
            value={data.payroll.pending_approval_runs}
            icon={<PaymentsRoundedIcon />}
            accentColor={pick(VIOLET)}
            tone={data.payroll.pending_approval_runs > 0 ? 'warning' : undefined}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('dashboard.stats.openVacancies')}
            value={data.recruitment.open_vacancies}
            icon={<GroupAddRoundedIcon />}
            accentColor={pick(AQUA)}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('dashboard.stats.expiringContracts')}
            value={data.expiring.contracts + data.expiring.documents}
            icon={<ScheduleRoundedIcon />}
            accentColor={pick(RED)}
            tone={data.expiring.contracts + data.expiring.documents > 0 ? 'error' : undefined}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('dashboard.stats.outstandingLoans')}
            value={format(Number(data.loans.outstanding_loan_balance))}
            icon={<PaymentsRoundedIcon />}
            accentColor={pick(GREEN)}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('dashboard.stats.inProgressExits')}
            value={data.exits.in_progress}
            icon={<WorkOffRoundedIcon />}
            accentColor={pick(RED)}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('dashboard.stats.candidatesInPipeline')}
            value={data.recruitment.candidates_in_pipeline}
            icon={<GroupAddRoundedIcon />}
            accentColor={pick(AQUA)}
          />
        </Grid>
      </Grid>

      <Grid container spacing={2}>
        {trend.length > 0 && (
          <Grid size={{ xs: 12, md: 8 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('dashboard.charts.payrollTrend')}
                </Typography>
                <LineChart
                  height={280}
                  xAxis={[{ scaleType: 'point', data: trend.map((r) => r.period_name) }]}
                  series={[
                    { data: trend.map((r) => Number(r.total_net)), label: t('dashboard.charts.netPay'), color: pick(BLUE), area: true, valueFormatter: (v) => format(v ?? 0) },
                    { data: trend.map((r) => Number(r.total_employer_cost)), label: t('dashboard.charts.employerCost'), color: pick(VIOLET), valueFormatter: (v) => format(v ?? 0) },
                  ]}
                />
              </CardContent>
            </Card>
          </Grid>
        )}

        {departmentEntries.length > 0 && (
          <Grid size={{ xs: 12, md: 4 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('dashboard.charts.headcountByDepartment')}
                </Typography>
                <PieChart
                  height={280}
                  series={[
                    {
                      data: departmentEntries.map(([name, count], index) => ({
                        id: name,
                        value: count,
                        label: name,
                        color: pick([BLUE, AQUA, VIOLET, YELLOW, GREEN, RED][index % 6]),
                      })),
                      innerRadius: 45,
                      paddingAngle: 2,
                      cornerRadius: 3,
                    },
                  ]}
                />
              </CardContent>
            </Card>
          </Grid>
        )}

        {attendanceEntries.length > 0 && (
          <Grid size={{ xs: 12, md: 5 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('dashboard.charts.attendanceToday')}
                </Typography>
                <PieChart
                  height={260}
                  series={[
                    {
                      data: attendanceEntries.map(([status, count]) => ({
                        id: status,
                        value: count,
                        label: t(`attendanceStatuses.${status}`),
                        color: status === 'present' ? pick(GREEN) : status === 'absent' ? pick(RED) : status === 'late' ? pick(YELLOW) : pick(AQUA),
                      })),
                      innerRadius: 45,
                      paddingAngle: 2,
                      cornerRadius: 3,
                    },
                  ]}
                />
              </CardContent>
            </Card>
          </Grid>
        )}
      </Grid>
    </Stack>
  );
}
