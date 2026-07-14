import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  MenuItem,
  Paper,
  Select,
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
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createAttendanceRecord,
  deleteAttendanceRecord,
  fetchAttendanceRecords,
  fetchEmployees,
  updateAttendanceRecord,
} from '../../../../api/endpoints/hr';
import type { AttendanceRecord } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

const STATUS_COLOR: Record<AttendanceRecord['status'], 'success' | 'error' | 'warning' | 'info' | 'default'> = {
  present: 'success',
  absent: 'error',
  late: 'warning',
  on_leave: 'info',
  half_day: 'default',
};

const STATUS_OPTIONS: AttendanceRecord['status'][] = ['present', 'absent', 'late', 'on_leave', 'half_day'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    date: z.string().min(1, t('validation.dateRequired')),
    status: z.enum(['present', 'absent', 'late', 'on_leave', 'half_day']),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function AttendancePage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<AttendanceRecord | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'attendance'], queryFn: () => fetchAttendanceRecords() });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'attendance'] });

  const createMutation = useMutation({
    mutationFn: createAttendanceRecord,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.attendanceCreated'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: AttendanceRecord['status'] }) => updateAttendanceRecord(id, { status }),
    onSuccess: () => {
      invalidate();
      showToast(t('toast.attendanceUpdated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteAttendanceRecord,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.attendanceDeleted'));
    },
  });

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { status: 'present', date: new Date().toISOString().slice(0, 10) },
  });

  const onCreate = (values: FormValues) => createMutation.mutate(values);

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('attendance.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset({ status: 'present', date: new Date().toISOString().slice(0, 10) });
            setDialogOpen(true);
          }}
        >
          {t('attendance.newRecord')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('attendance.empty.title')} description={t('attendance.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('attendance.table.employee')}</TableCell>
                  <TableCell>{t('attendance.table.date')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((record) => (
                  <TableRow key={record.id}>
                    <TableCell>{record.employee?.name ?? '—'}</TableCell>
                    <TableCell>{record.date.slice(0, 10)}</TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={record.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: record.id, status: e.target.value as AttendanceRecord['status'] })
                        }
                        renderValue={(value) => (
                          <Chip
                            label={t(`attendanceStatuses.${value}`)}
                            size="small"
                            color={STATUS_COLOR[value as AttendanceRecord['status']]}
                          />
                        )}
                      >
                        {STATUS_OPTIONS.map((status) => (
                          <MenuItem key={status} value={status}>
                            {t(`attendanceStatuses.${status}`)}
                          </MenuItem>
                        ))}
                      </Select>
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(record)}>
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('attendance.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('attendance.form.employee')}
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
                label={t('attendance.form.date')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('date')}
                error={!!errors.date}
                helperText={errors.date?.message}
              />
              <TextField label={t('attendance.form.status')} select fullWidth defaultValue="present" {...register('status')}>
                {STATUS_OPTIONS.map((status) => (
                  <MenuItem key={status} value={status}>
                    {t(`attendanceStatuses.${status}`)}
                  </MenuItem>
                ))}
              </TextField>
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
        title={t('attendance.deleteDialog.title')}
        message={t('attendance.deleteDialog.message', { name: pendingDelete?.employee?.name ?? '', date: pendingDelete?.date.slice(0, 10) ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
