import {
  Button,
  Card,
  CardContent,
  Checkbox,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid,
  IconButton,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  Stack,
  Tooltip,
  Typography,
} from '@mui/material';
import ArrowDownwardIcon from '@mui/icons-material/ArrowDownward';
import ArrowUpwardIcon from '@mui/icons-material/ArrowUpward';
import TuneIcon from '@mui/icons-material/Tune';
import { BarChart } from '@mui/x-charts/BarChart';
import { LineChart } from '@mui/x-charts/LineChart';
import { PieChart } from '@mui/x-charts/PieChart';
import { useQuery } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { fetchAnalyticsOverview } from '../../../api/endpoints/analytics';
import { fetchCompany, fetchDashboardSummary } from '../../../api/endpoints/dashboard';
import { fetchReportsOverview } from '../../../api/endpoints/reports';
import { StatWidgetCard } from '../../../components/common/StatWidgetCard';
import { useAuthStore } from '../../../hooks/useAuth';
import { useThemeMode } from '../../../app/theme/ThemeProvider';
import { formatCurrency } from '../../../utils/currency';
import { DASHBOARD_WIDGET_KEYS, useDashboardWidgetLayout, type DashboardWidgetKey } from '../../../hooks/useDashboardWidgetLayout';

// Validated categorical slots from the design system's reference palette
// (dataviz skill) — fixed per entity, reused verbatim from AnalyticsPage.
const BLUE = { light: '#2a78d6', dark: '#3987e5' }; // slot 1
const GREEN = { light: '#008300', dark: '#008300' }; // slot 2
const YELLOW = { light: '#eda100', dark: '#c98500' }; // slot 4
const AQUA = { light: '#1baf7a', dark: '#199e70' }; // slot 5
const VIOLET = { light: '#4a3aa7', dark: '#9085e9' }; // slot 7
const RED = { light: '#e34948', dark: '#e66767' }; // slot 8

const WIDGET_LABEL_KEYS: Record<DashboardWidgetKey, string> = {
  daily_shipments: 'dailyShipments',
  pending_customs: 'pendingCustoms',
  active_containers: 'activeContainers',
  outstanding_invoices: 'outstandingInvoices',
  revenue: 'revenue',
  expenses: 'expenses',
  fleet_status: 'fleetActive',
  warehouse_status: 'warehouseUtilization',
};

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
  const [customizeOpen, setCustomizeOpen] = useState(false);
  const { layout, moveWidget, toggleWidget, visibleOrder } = useDashboardWidgetLayout(user?.id);

  const availableWidgetKeys = DASHBOARD_WIDGET_KEYS.filter((key) => widgets?.[key] !== undefined);

  const widgetCards: Partial<Record<DashboardWidgetKey, ReactNode>> = widgets
    ? {
        daily_shipments: <StatWidgetCard label={t('widgets.dailyShipments')} value={widgets.daily_shipments} />,
        pending_customs: <StatWidgetCard label={t('widgets.pendingCustoms')} value={widgets.pending_customs} />,
        active_containers: <StatWidgetCard label={t('widgets.activeContainers')} value={widgets.active_containers} />,
        outstanding_invoices: <StatWidgetCard label={t('widgets.outstandingInvoices')} value={widgets.outstanding_invoices} />,
        revenue: <StatWidgetCard label={t('widgets.revenue')} value={formatCurrency(widgets.revenue ?? 0, company?.currency)} />,
        expenses: <StatWidgetCard label={t('widgets.expenses')} value={formatCurrency(widgets.expenses ?? 0, company?.currency)} />,
        fleet_status: <StatWidgetCard label={t('widgets.fleetActive')} value={widgets.fleet_status?.active} />,
        warehouse_status: (
          <StatWidgetCard label={t('widgets.warehouseUtilization')} value={`${widgets.warehouse_status?.utilization_percent}%`} />
        ),
      }
    : {};

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
      <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
        <Stack>
          <Typography variant="h5" fontWeight={700}>
            {t('welcomeBack', { name: user?.name?.split(' ')[0] ?? '' })}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {t('subtitle')}
          </Typography>
        </Stack>
        {availableWidgetKeys.length > 0 && (
          <Button size="small" variant="outlined" startIcon={<TuneIcon />} onClick={() => setCustomizeOpen(true)}>
            {t('customize.button')}
          </Button>
        )}
      </Stack>

      {isLoading && <CircularProgress />}

      {widgets && (
        <Grid container spacing={2}>
          {visibleOrder(availableWidgetKeys).map((key) => (
            <Grid key={key} size={{ xs: 12, sm: 6, md: 3 }}>
              {widgetCards[key]}
            </Grid>
          ))}
        </Grid>
      )}

      <Dialog open={customizeOpen} onClose={() => setCustomizeOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('customize.dialogTitle')}</DialogTitle>
        <DialogContent>
          <Typography variant="body2" color="text.secondary" sx={{ mb: 1 }}>
            {t('customize.dialogSubtitle')}
          </Typography>
          <List dense>
            {layout.order
              .filter((key) => availableWidgetKeys.includes(key))
              .map((key, index, arr) => (
                <ListItem
                  key={key}
                  secondaryAction={
                    <Stack direction="row">
                      <Tooltip title={t('customize.moveUp') as string}>
                        <span>
                          <IconButton edge="end" size="small" disabled={index === 0} onClick={() => moveWidget(key, -1)}>
                            <ArrowUpwardIcon fontSize="small" />
                          </IconButton>
                        </span>
                      </Tooltip>
                      <Tooltip title={t('customize.moveDown') as string}>
                        <span>
                          <IconButton
                            edge="end"
                            size="small"
                            disabled={index === arr.length - 1}
                            onClick={() => moveWidget(key, 1)}
                          >
                            <ArrowDownwardIcon fontSize="small" />
                          </IconButton>
                        </span>
                      </Tooltip>
                    </Stack>
                  }
                >
                  <ListItemIcon sx={{ minWidth: 36 }}>
                    <Checkbox
                      edge="start"
                      checked={!layout.hidden.includes(key)}
                      onChange={() => toggleWidget(key)}
                    />
                  </ListItemIcon>
                  <ListItemText primary={t(`widgets.${WIDGET_LABEL_KEYS[key]}`)} />
                </ListItem>
              ))}
          </List>
        </DialogContent>
        <DialogActions>
          <Button variant="contained" onClick={() => setCustomizeOpen(false)}>
            {t('customize.done')}
          </Button>
        </DialogActions>
      </Dialog>

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
