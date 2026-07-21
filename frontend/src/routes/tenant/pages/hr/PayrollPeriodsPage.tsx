import { zodResolver } from '@hookform/resolvers/zod';
import {
  Box,
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import PlayArrowIcon from '@mui/icons-material/PlayArrow';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { createPayrollPeriod, createPayrollRun, deletePayrollPeriod, fetchPayrollPeriods } from '../../../../api/endpoints/hr';
import type { PayrollPeriod } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildSchema(t: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    period_start: z.string().min(1, t('validation.dateRequired')),
    period_end: z.string().min(1, t('validation.dateRequired')),
    payment_date: z.string().min(1, t('validation.dateRequired')),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function PayrollPeriodsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<PayrollPeriod | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'payroll-periods'], queryFn: fetchPayrollPeriods });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'payroll-periods'] });

  const createMutation = useMutation({
    mutationFn: createPayrollPeriod,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.payrollPeriodCreated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deletePayrollPeriod,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.payrollPeriodDeleted'));
    },
  });

  const createRunMutation = useMutation({
    mutationFn: (periodId: number) => createPayrollRun(periodId),
    onSuccess: (run) => {
      invalidate();
      navigate(`/app/hr/payroll-runs/${run.id}`);
    },
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('payrollPeriods.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset({ name: '', period_start: '', period_end: '', payment_date: '' });
            setDialogOpen(true);
          }}
        >
          {t('payrollPeriods.newPeriod')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('payrollPeriods.empty.title')} description={t('payrollPeriods.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('payrollPeriods.table.name')}</TableCell>
                  <TableCell>{t('payrollPeriods.table.dates')}</TableCell>
                  <TableCell>{t('payrollPeriods.table.paymentDate')}</TableCell>
                  <TableCell>{t('payrollPeriods.table.latestRun')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((period) => (
                  <TableRow key={period.id}>
                    <TableCell>
                      <Stack direction="row" spacing={1} alignItems="center">
                        <span>{period.name}</span>
                        {period.is_locked && <StatusChip status="locked" label={t('payrollPeriods.locked')} />}
                      </Stack>
                    </TableCell>
                    <TableCell>
                      {period.period_start.slice(0, 10)} — {period.period_end.slice(0, 10)}
                    </TableCell>
                    <TableCell>{period.payment_date.slice(0, 10)}</TableCell>
                    <TableCell>
                      {period.latest_run ? (
                        <Box
                          component="span"
                          sx={{ cursor: 'pointer', display: 'inline-flex' }}
                          onClick={() => navigate(`/app/hr/payroll-runs/${period.latest_run?.id}`)}
                        >
                          <StatusChip status={period.latest_run.status} label={t(`payrollRunStatuses.${period.latest_run.status}`)} />
                        </Box>
                      ) : (
                        '—'
                      )}
                    </TableCell>
                    <TableCell align="right">
                      {!period.latest_run && (
                        <Tooltip title={t('payrollPeriods.createRun')}>
                          <IconButton
                            size="small"
                            onClick={() => createRunMutation.mutate(period.id)}
                            disabled={createRunMutation.isPending}
                          >
                            <PlayArrowIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                      {!period.latest_run && (
                        <Tooltip title={tc('actions.delete')}>
                          <IconButton size="small" onClick={() => setPendingDelete(period)}>
                            <DeleteIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('payrollPeriods.newPeriod')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('payrollPeriods.table.name')}
                fullWidth
                {...register('name')}
                error={!!errors.name}
                helperText={errors.name?.message}
              />
              <TextField
                label={t('payrollPeriods.form.periodStart')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('period_start')}
                error={!!errors.period_start}
              />
              <TextField
                label={t('payrollPeriods.form.periodEnd')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('period_end')}
                error={!!errors.period_end}
              />
              <TextField
                label={t('payrollPeriods.form.paymentDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('payment_date')}
                error={!!errors.payment_date}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('payrollPeriods.deleteDialog.title')}
        message={t('payrollPeriods.deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
