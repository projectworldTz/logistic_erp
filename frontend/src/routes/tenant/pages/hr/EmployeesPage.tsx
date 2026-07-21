import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  Link,
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
import EditIcon from '@mui/icons-material/Edit';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router-dom';
import { z } from 'zod';
import {
  createEmployee,
  deleteEmployee,
  fetchDepartments,
  fetchDesignations,
  fetchEmployees,
  updateEmployee,
} from '../../../../api/endpoints/hr';
import { fetchBranches } from '../../../../api/endpoints/dashboard';
import type { Employee } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

const STATUS_OPTIONS: Employee['status'][] = ['active', 'on_leave', 'probation', 'suspended', 'terminated'];
const EMPLOYMENT_TYPE_OPTIONS: Employee['employment_type'][] = [
  'permanent', 'full_time', 'part_time', 'contract', 'temporary', 'casual', 'intern', 'consultant', 'driver', 'commission_based', 'daily_paid',
];

function buildSchema(t: (key: string) => string) {
  return z.object({
    department_id: z.number().optional(),
    branch_id: z.number().optional(),
    designation_id: z.number().optional(),
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    email: z.string().email(t('validation.invalidEmail')).optional().or(z.literal('')),
    phone: z.string().optional(),
    job_title: z.string().optional(),
    employment_type: z.enum(EMPLOYMENT_TYPE_OPTIONS as [Employee['employment_type'], ...Employee['employment_type'][]]),
    hire_date: z.string().min(1, t('validation.hireDateRequired')),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function EmployeesPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingEmployee, setEditingEmployee] = useState<Employee | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Employee | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });
  const { data: departments } = useQuery({ queryKey: ['hr', 'departments'], queryFn: fetchDepartments });
  const { data: designations } = useQuery({ queryKey: ['hr', 'designations'], queryFn: () => fetchDesignations() });
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'employees'] });

  const createMutation = useMutation({
    mutationFn: createEmployee,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.employeeCreated'));
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<Employee> }) => updateEmployee(id, payload),
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      setEditingEmployee(null);
      showToast(t('toast.employeeUpdated'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: Employee['status'] }) => updateEmployee(id, { status }),
    onSuccess: () => {
      invalidate();
      showToast(t('toast.employeeUpdated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteEmployee,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.employeeDeleted'));
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
    defaultValues: { employment_type: 'full_time', hire_date: new Date().toISOString().slice(0, 10) },
  });

  const openCreateDialog = () => {
    setEditingEmployee(null);
    reset({ employment_type: 'full_time', hire_date: new Date().toISOString().slice(0, 10) });
    setDialogOpen(true);
  };

  const openEditDialog = (employee: Employee) => {
    setEditingEmployee(employee);
    reset({
      department_id: employee.department_id ?? undefined,
      branch_id: employee.branch_id ?? undefined,
      designation_id: employee.designation_id ?? undefined,
      name: employee.name,
      email: employee.email ?? '',
      phone: employee.phone ?? '',
      job_title: employee.job_title ?? '',
      employment_type: employee.employment_type,
      hire_date: employee.hire_date.slice(0, 10),
    });
    setDialogOpen(true);
  };

  const onSubmitForm = (values: FormValues) => {
    if (editingEmployee) {
      updateMutation.mutate({ id: editingEmployee.id, payload: values });
    } else {
      createMutation.mutate(values);
    }
  };

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('employees.title')}</Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreateDialog}>
          {t('employees.newEmployee')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('employees.empty.title')} description={t('employees.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('employees.table.employeeNo')}</TableCell>
                  <TableCell>{t('employees.table.name')}</TableCell>
                  <TableCell>{t('employees.table.jobTitle')}</TableCell>
                  <TableCell>{t('employees.table.department')}</TableCell>
                  <TableCell>{t('employees.table.employmentType')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((employee) => (
                  <TableRow key={employee.id}>
                    <TableCell>{employee.employee_number ?? '—'}</TableCell>
                    <TableCell>
                      <Link component={RouterLink} to={`/app/hr/employees/${employee.id}`} underline="hover">
                        {employee.name}
                      </Link>
                    </TableCell>
                    <TableCell>{employee.designation?.name ?? employee.job_title ?? '—'}</TableCell>
                    <TableCell>{employee.department?.name ?? '—'}</TableCell>
                    <TableCell>{t(`employmentTypes.${employee.employment_type}`)}</TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={employee.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: employee.id, status: e.target.value as Employee['status'] })
                        }
                        renderValue={(value) => <StatusChip status={value as string} label={t(`statuses.${value}`)} />}
                      >
                        {STATUS_OPTIONS.map((status) => (
                          <MenuItem key={status} value={status}>
                            {t(`statuses.${status}`)}
                          </MenuItem>
                        ))}
                      </Select>
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.edit')}>
                        <IconButton size="small" onClick={() => openEditDialog(employee)}>
                          <EditIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(employee)}>
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
        <DialogTitle>{editingEmployee ? t('employees.form.editDialogTitle') : t('employees.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onSubmitForm)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('employees.form.name')}
                fullWidth
                {...register('name')}
                error={!!errors.name}
                helperText={errors.name?.message}
              />
              <TextField label={t('employees.form.jobTitle')} fullWidth {...register('job_title')} />
              <TextField
                label={t('employees.form.email')}
                fullWidth
                {...register('email')}
                error={!!errors.email}
                helperText={errors.email?.message}
              />
              <TextField label={t('employees.form.phone')} fullWidth {...register('phone')} />
              <Controller
                name="department_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('employees.form.department')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : undefined)}
                  >
                    <MenuItem value="">—</MenuItem>
                    {departments?.data.map((department) => (
                      <MenuItem key={department.id} value={department.id}>
                        {department.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <Controller
                name="branch_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('employees.form.branch')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : undefined)}
                  >
                    <MenuItem value="">—</MenuItem>
                    {branches?.map((branch) => (
                      <MenuItem key={branch.id} value={branch.id}>
                        {branch.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <Controller
                name="designation_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('employees.form.designation')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : undefined)}
                  >
                    <MenuItem value="">—</MenuItem>
                    {designations?.data.map((designation) => (
                      <MenuItem key={designation.id} value={designation.id}>
                        {designation.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField label={t('employees.form.employmentType')} select fullWidth defaultValue="full_time" {...register('employment_type')}>
                {EMPLOYMENT_TYPE_OPTIONS.map((type) => (
                  <MenuItem key={type} value={type}>
                    {t(`employmentTypes.${type}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label={t('employees.form.hireDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('hire_date')}
                error={!!errors.hire_date}
                helperText={errors.hire_date?.message}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending || updateMutation.isPending}>
              {editingEmployee ? tc('actions.save') : tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('employees.deleteDialog.title')}
        message={t('employees.deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
