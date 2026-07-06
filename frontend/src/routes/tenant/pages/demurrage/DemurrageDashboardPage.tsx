import {
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Grid,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  calculateDemurrageCharge,
  fetchDemurrageCharges,
  fetchDemurrageDashboard,
  generateDemurrageInvoice,
  waiveDemurrageCharge,
} from '../../../../api/endpoints/demurrage';
import type { DemurrageCharge, DemurrageDashboardRow } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { StatWidgetCard } from '../../../../components/common/StatWidgetCard';
import { useToast } from '../../../../hooks/useToast';
import { DemurrageTabs } from './DemurrageTabs';

const RISK_COLOR: Record<DemurrageDashboardRow['risk_level'], 'default' | 'warning' | 'error'> = {
  within_free: 'default',
  at_risk: 'warning',
  accruing: 'error',
};

export function DemurrageDashboardPage() {
  const { t } = useTranslation('demurrage');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const [pendingWaive, setPendingWaive] = useState<DemurrageCharge | null>(null);
  const [waiveReason, setWaiveReason] = useState('');

  const { data, isLoading } = useQuery({ queryKey: ['demurrage', 'dashboard'], queryFn: fetchDemurrageDashboard });
  const { data: pendingCharges, isLoading: chargesLoading } = useQuery({
    queryKey: ['demurrage', 'charges', 'pending'],
    queryFn: () => fetchDemurrageCharges(),
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['demurrage', 'dashboard'] });
    queryClient.invalidateQueries({ queryKey: ['demurrage', 'charges', 'pending'] });
  };

  const calculateMutation = useMutation({
    mutationFn: calculateDemurrageCharge,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.calculated'));
    },
  });

  const waiveMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => waiveDemurrageCharge(id, reason),
    onSuccess: () => {
      invalidate();
      setPendingWaive(null);
      setWaiveReason('');
      showToast(t('toast.waived'));
    },
  });

  const invoiceMutation = useMutation({
    mutationFn: generateDemurrageInvoice,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.invoiced'));
    },
  });

  const rows = data?.data ?? [];
  const pending = (pendingCharges?.data ?? []).filter((charge) => charge.status === 'pending');

  const accruingCount = rows.filter((row) => row.risk_level === 'accruing').length;
  const atRiskCount = rows.filter((row) => row.risk_level === 'at_risk').length;
  const totalAccrued = rows.reduce((sum, row) => sum + row.accrued_amount, 0);
  const currency = rows[0]?.currency ?? 'USD';

  if (isLoading) {
    return <CircularProgress />;
  }

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <DemurrageTabs />

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, sm: 4 }}>
          <StatWidgetCard label={t('stats.accruing')} value={accruingCount} />
        </Grid>
        <Grid size={{ xs: 12, sm: 4 }}>
          <StatWidgetCard label={t('stats.atRisk')} value={atRiskCount} />
        </Grid>
        <Grid size={{ xs: 12, sm: 4 }}>
          <StatWidgetCard label={t('stats.totalAccrued')} value={`${currency} ${totalAccrued.toLocaleString()}`} />
        </Grid>
      </Grid>

      <Typography variant="h6">{t('exceptionBoard.title')}</Typography>

      {rows.length === 0 && (
        <EmptyState title={t('exceptionBoard.empty.title')} description={t('exceptionBoard.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('table.containerNo')}</TableCell>
                  <TableCell>{t('table.customer')}</TableCell>
                  <TableCell align="right">{t('table.dwellDays')}</TableCell>
                  <TableCell align="right">{t('table.freeDaysRemaining')}</TableCell>
                  <TableCell align="right">{t('table.accruedAmount')}</TableCell>
                  <TableCell>{t('table.risk')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={row.container_id}>
                    <TableCell>{row.container_number}</TableCell>
                    <TableCell>{row.customer?.company_name ?? '—'}</TableCell>
                    <TableCell align="right">{row.dwell_days}</TableCell>
                    <TableCell align="right">{row.free_days_remaining}</TableCell>
                    <TableCell align="right">
                      {row.currency} {row.accrued_amount.toLocaleString()}
                    </TableCell>
                    <TableCell>
                      <Chip label={t(`risk.${row.risk_level}`)} size="small" color={RISK_COLOR[row.risk_level]} />
                    </TableCell>
                    <TableCell align="right">
                      <Button
                        size="small"
                        variant="outlined"
                        disabled={calculateMutation.isPending}
                        onClick={() => calculateMutation.mutate(row.container_id)}
                      >
                        {t('actions.calculate')}
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Typography variant="h6">{t('pendingCharges.title')}</Typography>

      {!chargesLoading && pending.length === 0 && (
        <EmptyState title={t('pendingCharges.empty.title')} description={t('pendingCharges.empty.description')} />
      )}

      {pending.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('table.containerNo')}</TableCell>
                  <TableCell>{t('table.customer')}</TableCell>
                  <TableCell align="right">{t('table.chargeableDays')}</TableCell>
                  <TableCell align="right">{t('table.accruedAmount')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {pending.map((charge) => (
                  <TableRow key={charge.id}>
                    <TableCell>{charge.container?.container_number ?? '—'}</TableCell>
                    <TableCell>{charge.customer?.company_name ?? '—'}</TableCell>
                    <TableCell align="right">{charge.chargeable_days}</TableCell>
                    <TableCell align="right">
                      {charge.currency} {Number(charge.amount).toLocaleString()}
                    </TableCell>
                    <TableCell align="right">
                      <Stack direction="row" spacing={1} justifyContent="flex-end">
                        <Button size="small" onClick={() => setPendingWaive(charge)}>
                          {t('actions.waive')}
                        </Button>
                        <Button
                          size="small"
                          variant="contained"
                          disabled={invoiceMutation.isPending}
                          onClick={() => invoiceMutation.mutate(charge.id)}
                        >
                          {t('actions.generateInvoice')}
                        </Button>
                      </Stack>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={!!pendingWaive} onClose={() => setPendingWaive(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('waiveDialog.title')}</DialogTitle>
        <DialogContent>
          <DialogContentText sx={{ mb: 2 }}>{t('waiveDialog.description')}</DialogContentText>
          <TextField
            label={t('waiveDialog.reason')}
            fullWidth
            multiline
            minRows={2}
            value={waiveReason}
            onChange={(e) => setWaiveReason(e.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPendingWaive(null)}>{tc('actions.cancel')}</Button>
          <Button
            variant="contained"
            disabled={!waiveReason.trim() || waiveMutation.isPending}
            onClick={() => pendingWaive && waiveMutation.mutate({ id: pendingWaive.id, reason: waiveReason })}
          >
            {t('actions.waive')}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
