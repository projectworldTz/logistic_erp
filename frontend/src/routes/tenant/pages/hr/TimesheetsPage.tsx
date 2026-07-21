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
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm, type Resolver } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  approveTimesheet,
  createTimesheet,
  fetchEmployees,
  fetchTimesheets,
  rejectTimesheet,
} from '../../../../api/endpoints/hr';
import type { Timesheet } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    date: z.string().min(1, t('validation.dateRequired')),
    total_hours: z.coerce.number().min(0),
    activity: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function TimesheetsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const canApprove = usePermission('hr.timesheets.approve');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [rejectTarget, setRejectTarget] = useState<Timesheet | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'timesheets'], queryFn: () => fetchTimesheets() });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'timesheets'] });

  const createMutation = useMutation({
    mutationFn: createTimesheet,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.timesheetCreated'));
    },
  });

  const approveMutation = useMutation({
    mutationFn: approveTimesheet,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.timesheetApproved'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => rejectTimesheet(id, reason),
    onSuccess: () => {
      invalidate();
      setRejectTarget(null);
      setRejectReason('');
      showToast(t('toast.timesheetRejected'));
    },
  });

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema) as Resolver<FormValues>,
    defaultValues: { date: new Date().toISOString().slice(0, 10) },
  });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('timesheets.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset({ date: new Date().toISOString().slice(0, 10) });
            setDialogOpen(true);
          }}
        >
          {t('timesheets.newTimesheet')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('timesheets.empty.title')} description={t('timesheets.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('timesheets.table.employee')}</TableCell>
                  <TableCell>{t('timesheets.table.date')}</TableCell>
                  <TableCell align="right">{t('timesheets.table.hours')}</TableCell>
                  <TableCell>{t('timesheets.table.activity')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((timesheet) => (
                  <TableRow key={timesheet.id}>
                    <TableCell>{timesheet.employee?.name ?? '—'}</TableCell>
                    <TableCell>{timesheet.date.slice(0, 10)}</TableCell>
                    <TableCell align="right">{timesheet.total_hours}</TableCell>
                    <TableCell>{timesheet.activity ?? '—'}</TableCell>
                    <TableCell>
                      <StatusChip status={timesheet.status} label={t(`timesheetStatuses.${timesheet.status}`)} />
                    </TableCell>
                    <TableCell align="right">
                      {timesheet.status === 'pending' && canApprove && (
                        <>
                          <Tooltip title={t('timesheets.approve')}>
                            <IconButton size="small" color="success" onClick={() => approveMutation.mutate(timesheet.id)}>
                              <CheckCircleRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={t('timesheets.reject')}>
                            <IconButton size="small" color="error" onClick={() => setRejectTarget(timesheet)}>
                              <CloseRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
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
        <DialogTitle>{t('timesheets.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate({ ...values, total_hours: String(values.total_hours) }))}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('timesheets.form.employee')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!errors.employee_id}
                    helperText={errors.employee_id?.message}
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
                label={t('timesheets.form.date')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('date')}
                error={!!errors.date}
                helperText={errors.date?.message}
              />
              <TextField
                label={t('timesheets.form.hours')}
                type="number"
                fullWidth
                {...register('total_hours')}
                error={!!errors.total_hours}
                helperText={errors.total_hours?.message}
              />
              <TextField
                label={t('timesheets.form.activity')}
                fullWidth
                multiline
                minRows={2}
                {...register('activity')}
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

      <Dialog open={!!rejectTarget} onClose={() => setRejectTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('timesheets.reject')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('timesheets.rejectReason')}
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
            {t('timesheets.reject')}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
