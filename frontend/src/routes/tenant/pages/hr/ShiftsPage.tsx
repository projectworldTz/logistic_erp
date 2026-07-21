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
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm, type Resolver } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createEmployeeShift,
  createShift,
  deleteEmployeeShift,
  deleteShift,
  fetchEmployeeShifts,
  fetchEmployees,
  fetchShifts,
} from '../../../../api/endpoints/hr';
import type { EmployeeShift, Shift } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildShiftSchema(t: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    start_time: z.string().min(1),
    end_time: z.string().min(1),
    break_minutes: z.coerce.number().min(0).default(0),
    grace_minutes: z.coerce.number().min(0).default(0),
  });
}

type ShiftFormValues = z.infer<ReturnType<typeof buildShiftSchema>>;

function buildAssignSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    shift_id: z.number({ message: t('shifts.validation.shiftRequired') }),
    effective_date: z.string().min(1, t('validation.dateRequired')),
  });
}

type AssignFormValues = z.infer<ReturnType<typeof buildAssignSchema>>;

export function ShiftsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const shiftSchema = buildShiftSchema(t);
  const assignSchema = buildAssignSchema(t);

  const [shiftDialogOpen, setShiftDialogOpen] = useState(false);
  const [assignDialogOpen, setAssignDialogOpen] = useState(false);
  const [pendingDeleteShift, setPendingDeleteShift] = useState<Shift | null>(null);
  const [pendingDeleteAssignment, setPendingDeleteAssignment] = useState<EmployeeShift | null>(null);

  const { data: shifts, isLoading: shiftsLoading } = useQuery({ queryKey: ['hr', 'shifts'], queryFn: fetchShifts });
  const { data: assignments, isLoading: assignmentsLoading } = useQuery({
    queryKey: ['hr', 'employee-shifts'],
    queryFn: () => fetchEmployeeShifts(),
  });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidateShifts = () => queryClient.invalidateQueries({ queryKey: ['hr', 'shifts'] });
  const invalidateAssignments = () => queryClient.invalidateQueries({ queryKey: ['hr', 'employee-shifts'] });

  const createShiftMutation = useMutation({
    mutationFn: createShift,
    onSuccess: () => {
      invalidateShifts();
      setShiftDialogOpen(false);
      showToast(t('toast.shiftCreated'));
    },
  });

  const deleteShiftMutation = useMutation({
    mutationFn: deleteShift,
    onSuccess: () => {
      invalidateShifts();
      setPendingDeleteShift(null);
      showToast(t('toast.shiftDeleted'));
    },
  });

  const createAssignmentMutation = useMutation({
    mutationFn: createEmployeeShift,
    onSuccess: () => {
      invalidateAssignments();
      setAssignDialogOpen(false);
      showToast(t('toast.shiftAssigned'));
    },
  });

  const deleteAssignmentMutation = useMutation({
    mutationFn: deleteEmployeeShift,
    onSuccess: () => {
      invalidateAssignments();
      setPendingDeleteAssignment(null);
      showToast(t('toast.shiftAssignmentRemoved'));
    },
  });

  const shiftForm = useForm<ShiftFormValues>({
    resolver: zodResolver(shiftSchema) as Resolver<ShiftFormValues>,
    defaultValues: { break_minutes: 0, grace_minutes: 0 },
  });

  const assignForm = useForm<AssignFormValues>({
    resolver: zodResolver(assignSchema),
    defaultValues: { effective_date: new Date().toISOString().slice(0, 10) },
  });

  const shiftRows = shifts?.data ?? [];
  const assignmentRows = assignments?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('shifts.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            shiftForm.reset({ break_minutes: 0, grace_minutes: 0 });
            setShiftDialogOpen(true);
          }}
        >
          {t('shifts.newShift')}
        </Button>
      </Stack>

      {shiftsLoading && <CircularProgress />}

      {shiftRows.length === 0 && !shiftsLoading && (
        <EmptyState title={t('shifts.empty.title')} description={t('shifts.empty.description')} />
      )}

      {shiftRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('shifts.table.name')}</TableCell>
                  <TableCell>{t('shifts.table.startTime')}</TableCell>
                  <TableCell>{t('shifts.table.endTime')}</TableCell>
                  <TableCell align="right">{t('shifts.table.graceMinutes')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {shiftRows.map((shift) => (
                  <TableRow key={shift.id}>
                    <TableCell>{shift.name}</TableCell>
                    <TableCell>{shift.start_time}</TableCell>
                    <TableCell>{shift.end_time}</TableCell>
                    <TableCell align="right">{shift.grace_minutes}</TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDeleteShift(shift)}>
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

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('shifts.assignmentsTitle')}</Typography>
        <Button
          variant="outlined"
          startIcon={<AddIcon />}
          onClick={() => {
            assignForm.reset({ effective_date: new Date().toISOString().slice(0, 10) });
            setAssignDialogOpen(true);
          }}
        >
          {t('shifts.assignShift')}
        </Button>
      </Stack>

      {assignmentsLoading && <CircularProgress />}

      {assignmentRows.length === 0 && !assignmentsLoading && (
        <EmptyState title={t('shifts.assignmentsEmpty.title')} description={t('shifts.assignmentsEmpty.description')} />
      )}

      {assignmentRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('shifts.table.employee')}</TableCell>
                  <TableCell>{t('shifts.table.shift')}</TableCell>
                  <TableCell>{t('shifts.table.effectiveDate')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {assignmentRows.map((assignment) => (
                  <TableRow key={assignment.id}>
                    <TableCell>{assignment.employee?.name ?? '—'}</TableCell>
                    <TableCell>{assignment.shift?.name ?? '—'}</TableCell>
                    <TableCell>{assignment.effective_date.slice(0, 10)}</TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDeleteAssignment(assignment)}>
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

      <Dialog open={shiftDialogOpen} onClose={() => setShiftDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('shifts.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={shiftForm.handleSubmit((values) => createShiftMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('shifts.form.name')}
                fullWidth
                {...shiftForm.register('name')}
                error={!!shiftForm.formState.errors.name}
                helperText={shiftForm.formState.errors.name?.message}
              />
              <TextField
                label={t('shifts.form.startTime')}
                type="time"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...shiftForm.register('start_time')}
                error={!!shiftForm.formState.errors.start_time}
              />
              <TextField
                label={t('shifts.form.endTime')}
                type="time"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...shiftForm.register('end_time')}
                error={!!shiftForm.formState.errors.end_time}
              />
              <TextField
                label={t('shifts.form.breakMinutes')}
                type="number"
                fullWidth
                {...shiftForm.register('break_minutes')}
              />
              <TextField
                label={t('shifts.form.graceMinutes')}
                type="number"
                fullWidth
                {...shiftForm.register('grace_minutes')}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setShiftDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createShiftMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={assignDialogOpen} onClose={() => setAssignDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('shifts.assignShift')}</DialogTitle>
        <Stack component="form" onSubmit={assignForm.handleSubmit((values) => createAssignmentMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={assignForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('shifts.form.employee')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!assignForm.formState.errors.employee_id}
                  >
                    {employees?.data.map((employee) => (
                      <MenuItem key={employee.id} value={employee.id}>
                        {employee.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <Controller
                name="shift_id"
                control={assignForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('shifts.form.shift')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!assignForm.formState.errors.shift_id}
                  >
                    {shiftRows.map((shift) => (
                      <MenuItem key={shift.id} value={shift.id}>
                        {shift.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField
                label={t('shifts.form.effectiveDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...assignForm.register('effective_date')}
                error={!!assignForm.formState.errors.effective_date}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setAssignDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createAssignmentMutation.isPending}>
              {tc('actions.save')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDeleteShift}
        title={t('shifts.deleteDialog.title')}
        message={t('shifts.deleteDialog.message', { name: pendingDeleteShift?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteShiftMutation.isPending}
        onConfirm={() => pendingDeleteShift && deleteShiftMutation.mutate(pendingDeleteShift.id)}
        onCancel={() => setPendingDeleteShift(null)}
      />

      <ConfirmDialog
        open={!!pendingDeleteAssignment}
        title={t('shifts.removeAssignmentDialog.title')}
        message={t('shifts.removeAssignmentDialog.message', { name: pendingDeleteAssignment?.employee?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteAssignmentMutation.isPending}
        onConfirm={() => pendingDeleteAssignment && deleteAssignmentMutation.mutate(pendingDeleteAssignment.id)}
        onCancel={() => setPendingDeleteAssignment(null)}
      />
    </Stack>
  );
}
