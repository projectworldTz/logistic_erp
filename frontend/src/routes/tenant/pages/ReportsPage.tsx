import { Box, Button, Card, CardContent, CircularProgress, Grid, Stack, Typography } from '@mui/material';
import PrintIcon from '@mui/icons-material/Print';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchCompany } from '../../../api/endpoints/dashboard';
import { fetchReportsOverview } from '../../../api/endpoints/reports';
import { StatWidgetCard } from '../../../components/common/StatWidgetCard';
import { StatusBreakdownBar } from '../../../components/common/StatusBreakdownBar';

const STATUS_COLORS: Record<string, string> = {
  // shared meanings across modules
  pending: 'text.disabled',
  draft: 'text.disabled',
  new: 'text.disabled',
  at_port: 'text.disabled',
  booked: 'text.disabled',
  received: 'text.disabled',
  active: 'success.main',
  cleared: 'success.main',
  delivered: 'success.main',
  posted: 'success.main',
  paid: 'success.main',
  dispatched: 'success.main',
  in_transit: 'warning.main',
  under_clearance: 'warning.main',
  in_maintenance: 'warning.main',
  cargo_received: 'info.main',
  at_warehouse: 'info.main',
  arrived: 'info.main',
  sent: 'info.main',
  stored: 'info.main',
  contacted: 'info.main',
  qualified: 'info.main',
  customs_hold: 'error.main',
  cancelled: 'error.main',
  voided: 'warning.main',
  overdue: 'error.main',
  damaged: 'error.main',
  out_of_service: 'error.main',
  returned: 'success.main',
  empty_return: 'text.disabled',
  lost: 'error.main',
  converted: 'success.main',
  retired: 'text.disabled',
};

function statusColor(status: string): string {
  return STATUS_COLORS[status] ?? 'text.secondary';
}

interface BreakdownCardProps {
  title: string;
  total: number;
  byStatus: Record<string, number>;
}

function BreakdownCard({ title, total, byStatus }: BreakdownCardProps) {
  const { t } = useTranslation('reports');
  const entries = Object.entries(byStatus);

  return (
    <Card variant="outlined" sx={{ height: '100%' }}>
      <CardContent>
        <Stack spacing={2}>
          <Stack direction="row" justifyContent="space-between" alignItems="baseline">
            <Typography variant="subtitle1" fontWeight={700}>
              {title}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {t('totalCount', { count: total })}
            </Typography>
          </Stack>
          {entries.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              {t('noRecords')}
            </Typography>
          ) : (
            <Stack spacing={1.5}>
              {entries.map(([status, count]) => (
                <StatusBreakdownBar
                  key={status}
                  label={status}
                  count={count}
                  total={total}
                  color={statusColor(status)}
                />
              ))}
            </Stack>
          )}
        </Stack>
      </CardContent>
    </Card>
  );
}

export function ReportsPage() {
  const { t } = useTranslation('reports');
  const { data, isLoading } = useQuery({ queryKey: ['reports', 'overview'], queryFn: fetchReportsOverview });
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany });

  return (
    <Stack spacing={3}>
      <Box sx={{ display: 'none', '@media print': { display: 'block', mb: 2 } }}>
        <Typography variant="h6" fontWeight={700}>
          {company?.name}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {t('reportGeneratedAt', { date: new Date().toLocaleString() })}
        </Typography>
      </Box>

      <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
        <Stack>
          <Typography variant="h5" fontWeight={700}>
            {t('title')}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {t('subtitle')}
          </Typography>
        </Stack>
        <Button className="no-print" variant="outlined" startIcon={<PrintIcon />} onClick={() => window.print()}>
          {t('printReport')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {data && (
        <>
          <Grid container spacing={2}>
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.customers')} value={data.crm.customers_total} />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.leads')} value={data.crm.leads_total} />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.revenueCollected')} value={`$${data.finance.paid_amount.toLocaleString()}`} />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.outstanding')} value={`$${data.finance.outstanding_amount.toLocaleString()}`} />
            </Grid>
          </Grid>

          <Grid container spacing={2}>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.quotations')} total={data.quotations.total} byStatus={data.quotations.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.shipments')} total={data.shipments.total} byStatus={data.shipments.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.clearingFiles')} total={data.clearing.total} byStatus={data.clearing.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.freightBookings')} total={data.freight.total} byStatus={data.freight.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.containers')} total={data.containers.total} byStatus={data.containers.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.warehouseItems')} total={data.warehouse.total} byStatus={data.warehouse.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.fleetVehicles')} total={data.fleet.total} byStatus={data.fleet.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.invoices')} total={data.finance.invoices_total} byStatus={data.finance.invoices_by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard
                title={t('breakdown.journalEntries')}
                total={Object.values(data.accounting.journal_entries_by_status).reduce((a, b) => a + b, 0)}
                byStatus={data.accounting.journal_entries_by_status}
              />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <Card variant="outlined" sx={{ height: '100%' }}>
                <CardContent>
                  <Stack spacing={2}>
                    <Typography variant="subtitle1" fontWeight={700}>
                      {t('chartOfAccounts.title')}
                    </Typography>
                    <Stack direction="row" justifyContent="space-between">
                      <Typography variant="body2" color="text.secondary">
                        {t('chartOfAccounts.accountsConfigured')}
                      </Typography>
                      <Typography variant="body2" fontWeight={600}>
                        {data.accounting.accounts_total}
                      </Typography>
                    </Stack>
                    <Stack direction="row" justifyContent="space-between">
                      <Typography variant="body2" color="text.secondary">
                        {t('chartOfAccounts.documentsArchived')}
                      </Typography>
                      <Typography variant="body2" fontWeight={600}>
                        {data.documents.total}
                      </Typography>
                    </Stack>
                  </Stack>
                </CardContent>
              </Card>
            </Grid>
          </Grid>
        </>
      )}
    </Stack>
  );
}
