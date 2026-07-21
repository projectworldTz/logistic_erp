import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  MenuItem,
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
import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded';
import CloseRoundedIcon from '@mui/icons-material/CloseRounded';
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm, type Resolver } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  approveOvertimeRequest,
  createOvertimeRequest,
  deleteOvertimeRequest,
  fetchEmployees,
  fetchOvertimeRequests,
  rejectOvertimeRequest,
} from '../../../../api/endpoints/hr';
import type { OvertimeRequest } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    date: z.string().min(1, t('validation.dateRequired')),
    hours: z.coerce.number().min(0.25).max(24),
    reason: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function OvertimeRequestsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const canApprove = usePermission('hr.overtime.approve');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<OvertimeRequest | null>(null);
  const [rejectTarget, setRejectTarget] = useState<OvertimeRequest | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'overtime-requests'], queryFn: () => fetchOvertimeRequests() });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'overtime-requests'] });

  const createMutation = useMutation({
    mutationFn: createOvertimeRequest,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.overtimeRequestCreated'));
    },
  });
  const deleteMutation = useMutation({
    mutationFn: deleteOvertimeRequest,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.overtimeRequestDeleted'));
    },
  });
  const approveMutation = useMutation({
    mutationFn: approveOvertimeRequest,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.overtimeRequestApproved'));
    },
  });
  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => rejectOvertimeRequest(id, reason),
    onSuccess: () => {
      invalidate();
      setRejectTarget(null);
      setRejectReason('');
      showToast(t('toast.overtimeRequestRejected'));
    },
  });

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) as Resolver<FormValues>, defaultValues: { date: new Date().toISOString().slice(0, 10) } });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('overtimeRequests.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset({ date: new Date().toISOString().slice(0, 10) });
            setDialogOpen(true);
          }}
        >
          {t('overtimeRequests.newRequest')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}
      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('overtimeRequests.empty.title')} description={t('overtimeRequests.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('overtimeRequests.table.employee')}</TableCell>
                  <TableCell>{t('overtimeRequests.table.date')}</TableCell>
                  <TableCell align="right">{t('overtimeRequests.table.hours')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((request) => (
                  <TableRow key={request.id}>
                    <TableCell>{request.employee?.name ?? '—'}</TableCell>
                    <TableCell>{request.date.slice(0, 10)}</TableCell>
                    <TableCell align="right">{request.hours}</TableCell>
                    <TableCell>
                      <StatusChip status={request.status} label={t(`overtimeRequestStatuses.${request.status}`)} />
                    </TableCell>
                    <TableCell align="right">
                      {request.status === 'pending' && canApprove && (
                        <>
                          <Tooltip title={t('overtimeRequests.approve')}>
                            <IconButton size="small" color="success" onClick={() => approveMutation.mutate(request.id)}>
                              <CheckCircleRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={t('overtimeRequests.reject')}>
                            <IconButton size="small" color="error" onClick={() => setRejectTarget(request)}>
                              <CloseRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                      {request.status === 'pending' && (
                        <Tooltip title={tc('actions.delete')}>
                          <IconButton size="small" onClick={() => setPendingDelete(request)}>
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
        <DialogTitle>{t('overtimeRequests.newRequest')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate({ ...values, hours: String(values.hours) }))}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('overtimeRequests.table.employee')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!errors.employee_id}
                  >
                    {employees?.data.map((employee) => (
                      <MenuItem key={employee.id} value={employee.id}>
                        {employee.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField
                label={t('overtimeRequests.table.date')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('date')}
                error={!!errors.date}
              />
              <TextField
                label={t('overtimeRequests.table.hours')}
                type="number"
                fullWidth
                {...register('hours')}
                error={!!errors.hours}
                helperText={errors.hours?.message}
              />
              <TextField label={t('overtimeRequests.form.reason')} fullWidth multiline minRows={2} {...register('reason')} />
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

      <Dialog open={!!rejectTarget} onClose={() => setRejectTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('overtimeRequests.reject')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('overtimeRequests.rejectReason')}
            fullWidth
            multiline
            minRows={2}
            sx={{ mt: 1 }}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejectTarget(null)}>{tc('actions.cancel')}</Button>
          <Button
            color="error"
            variant="contained"
            disabled={!rejectReason.trim() || rejectMutation.isPending}
            onClick={() => rejectTarget && rejectMutation.mutate({ id: rejectTarget.id, reason: rejectReason })}
          >
            {t('overtimeRequests.reject')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('overtimeRequests.deleteDialog.title')}
        message={t('overtimeRequests.deleteDialog.message', { name: pendingDelete?.employee?.name ?? '', date: pendingDelete?.date.slice(0, 10) ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
