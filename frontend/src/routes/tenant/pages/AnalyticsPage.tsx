import {
  Card,
  CardContent,
  CircularProgress,
  Grid,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { BarChart } from '@mui/x-charts/BarChart';
import { LineChart } from '@mui/x-charts/LineChart';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchAnalyticsOverview } from '../../../api/endpoints/analytics';
import { StatWidgetCard } from '../../../components/common/StatWidgetCard';
import { EmptyState } from '../../../components/common/EmptyState';
import { useThemeMode } from '../../../app/theme/ThemeProvider';

// Validated categorical/sequential slots from the design system's reference
// palette (see dataviz skill) — used verbatim, not re-derived.
const SEQUENTIAL_BLUE = { light: '#2a78d6', dark: '#3987e5' };
const SERIES_AQUA = { light: '#1baf7a', dark: '#199e70' };

export function AnalyticsPage() {
  const { t } = useTranslation('analytics');
  const { mode } = useThemeMode();
  const { data, isLoading } = useQuery({ queryKey: ['analytics', 'overview'], queryFn: () => fetchAnalyticsOverview() });

  const blue = SEQUENTIAL_BLUE[mode];
  const aqua = SERIES_AQUA[mode];

  if (isLoading) {
    return <CircularProgress />;
  }

  if (!data) {
    return <EmptyState title={t('empty')} />;
  }

  const transitEntries = Object.entries(data.operational.avg_transit_days_by_mode);
  const revenueEntries = Object.entries(data.financial.revenue_by_month);
  const volumeEntries = Object.entries(data.trends.shipment_volume_by_month);
  const agingEntries = Object.entries(data.financial.ar_aging);
  const hasAnyData =
    transitEntries.length > 0 || revenueEntries.length > 0 || volumeEntries.length > 0 ||
    data.top_customers.by_revenue.length > 0 || data.top_customers.by_volume.length > 0;

  return (
    <Stack spacing={3}>
      <Stack>
        <Typography variant="h5" fontWeight={700}>
          {t('title')}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {t('subtitle')}
        </Typography>
      </Stack>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('kpis.onTimeDelivery')}
            value={data.operational.on_time_delivery_rate !== null ? `${data.operational.on_time_delivery_rate}%` : t('kpis.noData')}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('kpis.avgClearanceDays')}
            value={data.operational.avg_customs_clearance_days !== null ? t('kpis.days', { count: data.operational.avg_customs_clearance_days }) : t('kpis.noData')}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('kpis.avgDwellDays')}
            value={data.operational.avg_container_dwell_days !== null ? t('kpis.days', { count: data.operational.avg_container_dwell_days }) : t('kpis.noData')}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard
            label={t('kpis.fleetUtilization')}
            value={data.operational.fleet_utilization_percent !== null ? `${data.operational.fleet_utilization_percent}%` : t('kpis.noData')}
          />
        </Grid>
      </Grid>

      {!hasAnyData && <EmptyState title={t('empty')} />}

      {hasAnyData && (
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, md: 6 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('charts.revenueByMonth')}
                </Typography>
                {revenueEntries.length === 0 ? (
                  <Typography variant="body2" color="text.secondary">{t('kpis.noData')}</Typography>
                ) : (
                  <LineChart
                    height={260}
                    xAxis={[{ scaleType: 'point', data: revenueEntries.map(([month]) => month) }]}
                    series={[{ data: revenueEntries.map(([, value]) => value), label: t('charts.revenueByMonthSeries'), color: blue, area: true }]}
                    hideLegend
                  />
                )}
              </CardContent>
            </Card>
          </Grid>

          <Grid size={{ xs: 12, md: 6 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('charts.shipmentVolume')}
                </Typography>
                {volumeEntries.length === 0 ? (
                  <Typography variant="body2" color="text.secondary">{t('kpis.noData')}</Typography>
                ) : (
                  <BarChart
                    height={260}
                    xAxis={[{ scaleType: 'band', data: volumeEntries.map(([month]) => month) }]}
                    series={[
                      { data: volumeEntries.map(([, v]) => v.import), label: t('direction.import'), color: blue },
                      { data: volumeEntries.map(([, v]) => v.export), label: t('direction.export'), color: aqua },
                    ]}
                  />
                )}
              </CardContent>
            </Card>
          </Grid>

          <Grid size={{ xs: 12, md: 6 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('charts.transitTimeByMode')}
                </Typography>
                {transitEntries.length === 0 ? (
                  <Typography variant="body2" color="text.secondary">{t('kpis.noData')}</Typography>
                ) : (
                  <BarChart
                    height={240}
                    xAxis={[{ scaleType: 'band', data: transitEntries.map(([mode]) => mode) }]}
                    series={[{ data: transitEntries.map(([, v]) => v), label: t('charts.transitTimeByModeSeries'), color: blue }]}
                    hideLegend
                  />
                )}
              </CardContent>
            </Card>
          </Grid>

          <Grid size={{ xs: 12, md: 6 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('charts.arAging')}
                </Typography>
                <BarChart
                  height={240}
                  xAxis={[{ scaleType: 'band', data: agingEntries.map(([bucket]) => t(`agingBuckets.${bucket}`)) }]}
                  series={[{ data: agingEntries.map(([, v]) => v), label: t('charts.arAgingSeries'), color: blue }]}
                  hideLegend
                />
              </CardContent>
            </Card>
          </Grid>

          <Grid size={{ xs: 12, md: 6 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('charts.topCustomersByRevenue')}
                </Typography>
                {data.top_customers.by_revenue.length === 0 ? (
                  <Typography variant="body2" color="text.secondary">{t('kpis.noData')}</Typography>
                ) : (
                  <BarChart
                    height={260}
                    layout="horizontal"
                    yAxis={[{ scaleType: 'band', data: data.top_customers.by_revenue.map((c) => c.customer ?? '—') }]}
                    series={[{ data: data.top_customers.by_revenue.map((c) => c.revenue ?? 0), color: blue }]}
                    hideLegend
                  />
                )}
              </CardContent>
            </Card>
          </Grid>

          <Grid size={{ xs: 12, md: 6 }}>
            <Card variant="outlined" sx={{ height: '100%' }}>
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('charts.topCustomersByVolume')}
                </Typography>
                {data.top_customers.by_volume.length === 0 ? (
                  <Typography variant="body2" color="text.secondary">{t('kpis.noData')}</Typography>
                ) : (
                  <BarChart
                    height={260}
                    layout="horizontal"
                    yAxis={[{ scaleType: 'band', data: data.top_customers.by_volume.map((c) => c.customer ?? '—') }]}
                    series={[{ data: data.top_customers.by_volume.map((c) => c.shipment_count ?? 0), color: aqua }]}
                    hideLegend
                  />
                )}
              </CardContent>
            </Card>
          </Grid>

          <Grid size={12}>
            <Card variant="outlined">
              <CardContent>
                <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1 }}>
                  {t('charts.margins')}
                </Typography>
                {data.financial.margins.length === 0 ? (
                  <Typography variant="body2" color="text.secondary">{t('marginsTable.empty')}</Typography>
                ) : (
                  <Paper variant="outlined">
                    <TableContainer>
                      <Table size="small">
                        <TableHead>
                          <TableRow>
                            <TableCell>{t('marginsTable.shipment')}</TableCell>
                            <TableCell align="right">{t('marginsTable.quoted')}</TableCell>
                            <TableCell align="right">{t('marginsTable.invoiced')}</TableCell>
                            <TableCell align="right">{t('marginsTable.variance')}</TableCell>
                          </TableRow>
                        </TableHead>
                        <TableBody>
                          {data.financial.margins.map((row, index) => (
                            <TableRow key={index}>
                              <TableCell>{row.shipment_number ?? '—'}</TableCell>
                              <TableCell align="right">{row.quoted_amount.toLocaleString()}</TableCell>
                              <TableCell align="right">{row.invoiced_amount.toLocaleString()}</TableCell>
                              <TableCell align="right" sx={{ color: row.variance >= 0 ? 'success.main' : 'error.main' }}>
                                {row.variance >= 0 ? '+' : ''}{row.variance.toLocaleString()}
                              </TableCell>
                            </TableRow>
                          ))}
                        </TableBody>
                      </Table>
                    </TableContainer>
                  </Paper>
                )}
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      )}
    </Stack>
  );
}
