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
import DeleteIcon from '@mui/icons-material/Delete';
import TaskAltRoundedIcon from '@mui/icons-material/TaskAltRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  acknowledgeDisciplinaryRecord,
  createDisciplinaryRecord,
  deleteDisciplinaryRecord,
  fetchDisciplinaryRecords,
  fetchEmployees,
  resolveDisciplinaryRecord,
} from '../../../../api/endpoints/hr';
import type { DisciplinaryRecord } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

type DisciplinaryCategory = 'attendance' | 'conduct' | 'performance' | 'safety' | 'policy_violation' | 'other';
type DisciplinarySeverity = 'verbal_warning' | 'written_warning' | 'suspension' | 'termination';

const CATEGORY_OPTIONS: DisciplinaryCategory[] = ['attendance', 'conduct', 'performance', 'safety', 'policy_violation', 'other'];
const SEVERITY_OPTIONS: DisciplinarySeverity[] = ['verbal_warning', 'written_warning', 'suspension', 'termination'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    incident_date: z.string().min(1, t('validation.dateRequired')),
    category: z.enum(CATEGORY_OPTIONS as [DisciplinaryCategory, ...DisciplinaryCategory[]]),
    severity: z.enum(SEVERITY_OPTIONS as [DisciplinarySeverity, ...DisciplinarySeverity[]]),
    description: z.string().min(1, t('validation.descriptionRequired')),
    action_taken: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function DisciplinaryRecordsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const canManage = usePermission('hr.disciplinary.manage');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<DisciplinaryRecord | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'disciplinary-records'], queryFn: () => fetchDisciplinaryRecords() });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'disciplinary-records'] });

  const createMutation = useMutation({
    mutationFn: createDisciplinaryRecord,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.disciplinaryCreated'));
    },
  });
  const ackMutation = useMutation({
    mutationFn: (id: number) => acknowledgeDisciplinaryRecord(id),
    onSuccess: () => {
      invalidate();
      showToast(t('toast.disciplinaryAcknowledged'));
    },
  });
  const resolveMutation = useMutation({
    mutationFn: resolveDisciplinaryRecord,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.disciplinaryResolved'));
    },
  });
  const deleteMutation = useMutation({
    mutationFn: deleteDisciplinaryRecord,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.disciplinaryDeleted'));
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
    defaultValues: { category: 'conduct', severity: 'verbal_warning' },
  });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('disciplinary.title')}</Typography>
        {canManage && (
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => {
              reset({ category: 'conduct', severity: 'verbal_warning' });
              setDialogOpen(true);
            }}
          >
            {t('disciplinary.newRecord')}
          </Button>
        )}
      </Stack>

      {isLoading && <CircularProgress />}
      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('disciplinary.empty.title')} description={t('disciplinary.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('disciplinary.table.employee')}</TableCell>
                  <TableCell>{t('disciplinary.table.date')}</TableCell>
                  <TableCell>{t('disciplinary.table.category')}</TableCell>
                  <TableCell>{t('disciplinary.table.severity')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((record) => (
                  <TableRow key={record.id}>
                    <TableCell>{record.employee?.name ?? '—'}</TableCell>
                    <TableCell>{record.incident_date.slice(0, 10)}</TableCell>
                    <TableCell>{t(`disciplinaryCategories.${record.category}`)}</TableCell>
                    <TableCell>{t(`disciplinarySeverities.${record.severity}`)}</TableCell>
                    <TableCell>
                      <StatusChip status={record.status} label={t(`disciplinaryStatuses.${record.status}`)} />
                    </TableCell>
                    <TableCell align="right">
                      {record.status === 'issued' && (
                        <Tooltip title={t('disciplinary.acknowledge')}>
                          <IconButton size="small" onClick={() => ackMutation.mutate(record.id)}>
                            <CheckCircleRoundedIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                      {(record.status === 'acknowledged' || record.status === 'appealed') && canManage && (
                        <Tooltip title={t('disciplinary.resolve')}>
                          <IconButton size="small" color="success" onClick={() => resolveMutation.mutate(record.id)}>
                            <TaskAltRoundedIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                      {canManage && (
                        <Tooltip title={tc('actions.delete')}>
                          <IconButton size="small" onClick={() => setPendingDelete(record)}>
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
        <DialogTitle>{t('disciplinary.newRecord')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('disciplinary.table.employee')}
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
                label={t('disciplinary.table.date')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('incident_date')}
                error={!!errors.incident_date}
              />
              <TextField label={t('disciplinary.table.category')} select fullWidth defaultValue="conduct" {...register('category')}>
                {CATEGORY_OPTIONS.map((category) => (
                  <MenuItem key={category} value={category}>
                    {t(`disciplinaryCategories.${category}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField label={t('disciplinary.table.severity')} select fullWidth defaultValue="verbal_warning" {...register('severity')}>
                {SEVERITY_OPTIONS.map((severity) => (
                  <MenuItem key={severity} value={severity}>
                    {t(`disciplinarySeverities.${severity}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label={t('disciplinary.form.description')}
                fullWidth
                multiline
                minRows={2}
                {...register('description')}
                error={!!errors.description}
                helperText={errors.description?.message}
              />
              <TextField label={t('disciplinary.form.actionTaken')} fullWidth multiline minRows={2} {...register('action_taken')} />
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
        title={t('disciplinary.deleteDialog.title')}
        message={t('disciplinary.deleteDialog.message', { name: pendingDelete?.employee?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
