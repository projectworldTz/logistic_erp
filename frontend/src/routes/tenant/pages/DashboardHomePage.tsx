import { CircularProgress, Grid, Stack, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchDashboardSummary } from '../../../api/endpoints/dashboard';
import { StatWidgetCard } from '../../../components/common/StatWidgetCard';
import { useAuthStore } from '../../../hooks/useAuth';

export function DashboardHomePage() {
  const { t } = useTranslation('dashboard');
  const user = useAuthStore((s) => s.user);
  const { data, isLoading } = useQuery({
    queryKey: ['tenant', 'dashboard-summary'],
    queryFn: fetchDashboardSummary,
  });

  const widgets = data?.widgets;

  return (
    <Stack spacing={3}>
      <Stack>
        <Typography variant="h5" fontWeight={700}>
          {t('welcomeBack', { name: user?.name?.split(' ')[0] ?? '' })}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {t('subtitle')}
        </Typography>
      </Stack>

      {isLoading && <CircularProgress />}

      {widgets && (
        <Grid container spacing={2}>
          {widgets.daily_shipments !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.dailyShipments')} value={widgets.daily_shipments} />
            </Grid>
          )}
          {widgets.pending_customs !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.pendingCustoms')} value={widgets.pending_customs} />
            </Grid>
          )}
          {widgets.active_containers !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.activeContainers')} value={widgets.active_containers} />
            </Grid>
          )}
          {widgets.outstanding_invoices !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.outstandingInvoices')} value={widgets.outstanding_invoices} />
            </Grid>
          )}
          {widgets.revenue !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.revenue')} value={`$${widgets.revenue}`} />
            </Grid>
          )}
          {widgets.expenses !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.expenses')} value={`$${widgets.expenses}`} />
            </Grid>
          )}
          {widgets.fleet_status !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.fleetActive')} value={widgets.fleet_status.active} />
            </Grid>
          )}
          {widgets.warehouse_status !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.warehouseUtilization')} value={`${widgets.warehouse_status.utilization_percent}%`} />
            </Grid>
          )}
        </Grid>
      )}
    </Stack>
  );
}
