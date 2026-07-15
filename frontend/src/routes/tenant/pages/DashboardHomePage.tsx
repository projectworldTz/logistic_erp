import { Card, CardContent, CircularProgress, Grid, Stack, Typography } from '@mui/material';
import { BarChart } from '@mui/x-charts/BarChart';
import { LineChart } from '@mui/x-charts/LineChart';
import { PieChart } from '@mui/x-charts/PieChart';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchAnalyticsOverview } from '../../../api/endpoints/analytics';
import { fetchCompany, fetchDashboardSummary } from '../../../api/endpoints/dashboard';
import { fetchReportsOverview } from '../../../api/endpoints/reports';
import { StatWidgetCard } from '../../../components/common/StatWidgetCard';
import { useAuthStore } from '../../../hooks/useAuth';
import { useThemeMode } from '../../../app/theme/ThemeProvider';
import { formatCurrency } from '../../../utils/currency';

// Validated categorical slots from the design system's reference palette
// (dataviz skill) — fixed per entity, reused verbatim from AnalyticsPage.
const BLUE = { light: '#2a78d6', dark: '#3987e5' }; // slot 1
const GREEN = { light: '#008300', dark: '#008300' }; // slot 2
const YELLOW = { light: '#eda100', dark: '#c98500' }; // slot 4
const AQUA = { light: '#1baf7a', dark: '#199e70' }; // slot 5
const VIOLET = { light: '#4a3aa7', dark: '#9085e9' }; // slot 7
const RED = { light: '#e34948', dark: '#e66767' }; // slot 8

export function DashboardHomePage() {
  const { t } = useTranslation('dashboard');
  const { t: ts } = useTranslation('shipments');
  const { mode } = useThemeMode();
  const user = useAuthStore((s) => s.user);
  const permissions = user?.permissions ?? [];
  const canViewAnalytics = permissions.includes('analytics.view');
  const canViewReports = permissions.includes('reports.view');

  const { data, isLoading } = useQuery({
    queryKey: ['tenant', 'dashboard-summary'],
    queryFn: fetchDashboardSummary,
  });
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany });
  const { data: analytics } = useQuery({
    queryKey: ['analytics', 'overview'],
    queryFn: () => fetchAnalyticsOverview(),
    enabled: canViewAnalytics,
  });
  const { data: reports } = useQuery({
    queryKey: ['reports', 'overview'],
    queryFn: () => fetchReportsOverview(),
    enabled: canViewReports,
  });

  const widgets = data?.widgets;

  const pick = (palette: { light: string; dark: string }) => palette[mode];

  const shipmentStatusColor: Record<string, string> = {
    booked: pick(BLUE),
    in_transit: pick(YELLOW),
    arrived: pick(AQUA),
    cleared: pick(VIOLET),
    delivered: pick(GREEN),
    cancelled: pick(RED),
  };

  const revenueEntries = Object.entries(analytics?.financial.revenue_by_month ?? {});
  const volumeEntries = Object.entries(analytics?.trends.shipment_volume_by_month ?? {});
  const shipmentStatusEntries = Object.entries(reports?.shipments.by_status ?? {}).filter(([, count]) => count > 0);

  const hasCharts =
    !!widgets?.fleet_status || revenueEntries.length > 0 || volumeEntries.length > 0 || shipmentStatusEntries.length > 0;

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
              <StatWidgetCard label={t('widgets.revenue')} value={formatCurrency(widgets.revenue, company?.currency)} />
            </Grid>
          )}
          {widgets.expenses !== undefined && (
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.expenses')} value={formatCurrency(widgets.expenses, company?.currency)} />
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

      {widgets?.shipment_intelligence && (
        <Stack spacing={2}>
          <Typography variant="h6" fontWeight={700}>
            {t('shipmentIntelligence.sectionTitle')}
          </Typography>
          <Grid container spacing={2}>
            <Grid size={{ xs: 12, sm: 6, md: 4 }}>
              <StatWidgetCard label={t('shipmentIntelligence.active')} value={widgets.shipment_intelligence.active} />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 4 }}>
              <StatWidgetCard label={t('shipmentIntelligence.released')} value={widgets.shipment_intelligence.released} />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 4 }}>
              <StatWidgetCard
                label={t('shipmentIntelligence.delayed')}
                value={widgets.shipment_intelligence.delayed}
                tone={widgets.shipment_intelligence.delayed > 0 ? 'error' : undefined}
              />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 4 }}>
              <StatWidgetCard
                label={t('shipmentIntelligence.nearDeadline')}
                value={widgets.shipment_intelligence.near_deadline}
                tone={widgets.shipment_intelligence.near_deadline > 0 ? 'warning' : undefined}
              />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 4 }}>
              <StatWidgetCard label={t('shipmentIntelligence.customersServed')} value={widgets.shipment_intelligence.customers_served} />
            </Grid>
            {widgets.shipment_intelligence.avg_customs_clearance_days !== null && (
              <Grid size={{ xs: 12, sm: 6, md: 4 }}>
                <StatWidgetCard
                  label={t('shipmentIntelligence.avgCustomsClearance')}
                  value={t('shipmentIntelligence.daysValue', { count: widgets.shipment_intelligence.avg_customs_clearance_days })}
                />
              </Grid>
            )}
          </Grid>
        </Stack>
      )}

      {hasCharts && (
        <Stack spacing={2}>
          <Typography variant="h6" fontWeight={700}>
            {t('charts.sectionTitle')}
          </Typography>

          <Grid container spacing={2}>
            {revenueEntries.length > 0 && (
              <Grid size={{ xs: 12, md: 8 }}>
                <Card variant="outlined" sx={{ height: '100%' }}>
                  <CardContent>
                    <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                      {t('charts.revenueTrend')}
                    </Typography>
                    <LineChart
                      height={260}
                      xAxis={[{ scaleType: 'point', data: revenueEntries.map(([month]) => month) }]}
                      series={[
                        {
                          data: revenueEntries.map(([, value]) => value),
                          label: t('charts.revenueTrendSeries'),
                          color: pick(BLUE),
                          area: true,
                          valueFormatter: (value) => formatCurrency(value ?? 0, company?.currency),
                        },
                      ]}
                      hideLegend
                    />
                  </CardContent>
                </Card>
              </Grid>
            )}

            {widgets?.fleet_status !== undefined && (
              <Grid size={{ xs: 12, md: 4 }}>
                <Card variant="outlined" sx={{ height: '100%' }}>
                  <CardContent>
                    <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                      {t('charts.fleetStatus')}
                    </Typography>
                    {widgets.fleet_status.active === 0 && widgets.fleet_status.maintenance === 0 ? (
                      <Typography variant="body2" color="text.secondary">
                        {t('charts.noData')}
                      </Typography>
                    ) : (
                      <PieChart
                        height={260}
                        series={[
                          {
                            data: [
                              { id: 'active', value: widgets.fleet_status.active, label: t('charts.fleetActiveLabel'), color: pick(BLUE) },
                              {
                                id: 'maintenance',
                                value: widgets.fleet_status.maintenance,
                                label: t('charts.fleetMaintenanceLabel'),
                                color: pick(YELLOW),
                              },
                            ],
                            innerRadius: 45,
                            paddingAngle: 2,
                            cornerRadius: 3,
                          },
                        ]}
                      />
                    )}
                  </CardContent>
                </Card>
              </Grid>
            )}

            {volumeEntries.length > 0 && (
              <Grid size={{ xs: 12, md: 7 }}>
                <Card variant="outlined" sx={{ height: '100%' }}>
                  <CardContent>
                    <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                      {t('charts.shipmentVolumeTrend')}
                    </Typography>
                    <BarChart
                      height={260}
                      xAxis={[{ scaleType: 'band', data: volumeEntries.map(([month]) => month) }]}
                      series={[
                        { data: volumeEntries.map(([, v]) => v.import), label: t('charts.import'), color: pick(BLUE), stack: 'total' },
                        { data: volumeEntries.map(([, v]) => v.export), label: t('charts.export'), color: pick(AQUA), stack: 'total' },
                      ]}
                    />
                  </CardContent>
                </Card>
              </Grid>
            )}

            {shipmentStatusEntries.length > 0 && (
              <Grid size={{ xs: 12, md: 5 }}>
                <Card variant="outlined" sx={{ height: '100%' }}>
                  <CardContent>
                    <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                      {t('charts.shipmentStatusBreakdown')}
                    </Typography>
                    <PieChart
                      height={260}
                      series={[
                        {
                          data: shipmentStatusEntries.map(([status, count]) => ({
                            id: status,
                            value: count,
                            label: ts(`statuses.${status}`),
                            color: shipmentStatusColor[status] ?? pick(VIOLET),
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
      )}
    </Stack>
  );
}
