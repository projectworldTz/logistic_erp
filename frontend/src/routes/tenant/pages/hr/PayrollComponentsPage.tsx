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
  createEmployeePayrollComponent,
  createPayrollComponent,
  deleteEmployeePayrollComponent,
  deletePayrollComponent,
  fetchEmployeePayrollComponents,
  fetchEmployees,
  fetchPayrollComponents,
} from '../../../../api/endpoints/hr';
import type { EmployeePayrollComponent, PayrollComponent } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { useToast } from '../../../../hooks/useToast';
import { useCurrencyFormatter } from '../../../../hooks/useCurrency';
import { HrTabs } from './HrTabs';

const TYPE_OPTIONS: PayrollComponent['type'][] = ['earning', 'deduction', 'employer_contribution'];
const METHOD_OPTIONS: PayrollComponent['calculation_method'][] = ['fixed', 'percentage', 'formula'];
const PERCENTAGE_BASE_OPTIONS: NonNullable<PayrollComponent['percentage_base']>[] = ['basic_salary', 'gross_pay'];

function buildComponentSchema(t: (key: string) => string) {
  return z.object({
    code: z.string().min(1, t('validation.nameRequired')).max(100),
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    type: z.enum(TYPE_OPTIONS as [PayrollComponent['type'], ...PayrollComponent['type'][]]),
    calculation_method: z.enum(METHOD_OPTIONS as [PayrollComponent['calculation_method'], ...PayrollComponent['calculation_method'][]]),
    amount: z.coerce.number().min(0).optional(),
    percentage: z.coerce.number().min(0).max(100).optional(),
    percentage_base: z.enum(PERCENTAGE_BASE_OPTIONS as [NonNullable<PayrollComponent['percentage_base']>, ...NonNullable<PayrollComponent['percentage_base']>[]]).optional(),
    effective_date: z.string().min(1, t('validation.dateRequired')),
  });
}

type ComponentFormValues = z.infer<ReturnType<typeof buildComponentSchema>>;

function buildAssignSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    payroll_component_id: z.number({ message: t('payrollComponents.validation.componentRequired') }),
    amount: z.coerce.number().min(0).optional(),
    percentage: z.coerce.number().min(0).max(100).optional(),
    effective_date: z.string().min(1, t('validation.dateRequired')),
  });
}

type AssignFormValues = z.infer<ReturnType<typeof buildAssignSchema>>;

export function PayrollComponentsPage() {
  const { t } = useTranslation('hr');
  const { format } = useCurrencyFormatter();
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const componentSchema = buildComponentSchema(t);
  const assignSchema = buildAssignSchema(t);

  const [componentDialogOpen, setComponentDialogOpen] = useState(false);
  const [assignDialogOpen, setAssignDialogOpen] = useState(false);
  const [pendingDeleteComponent, setPendingDeleteComponent] = useState<PayrollComponent | null>(null);
  const [pendingDeleteAssignment, setPendingDeleteAssignment] = useState<EmployeePayrollComponent | null>(null);

  const { data: components, isLoading: componentsLoading } = useQuery({
    queryKey: ['hr', 'payroll-components'],
    queryFn: () => fetchPayrollComponents(true),
  });
  const { data: assignments, isLoading: assignmentsLoading } = useQuery({
    queryKey: ['hr', 'employee-payroll-components'],
    queryFn: () => fetchEmployeePayrollComponents(),
  });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidateComponents = () => queryClient.invalidateQueries({ queryKey: ['hr', 'payroll-components'] });
  const invalidateAssignments = () => queryClient.invalidateQueries({ queryKey: ['hr', 'employee-payroll-components'] });

  const createComponentMutation = useMutation({
    mutationFn: createPayrollComponent,
    onSuccess: () => {
      invalidateComponents();
      setComponentDialogOpen(false);
      showToast(t('toast.payrollComponentCreated'));
    },
  });

  const deleteComponentMutation = useMutation({
    mutationFn: deletePayrollComponent,
    onSuccess: () => {
      invalidateComponents();
      setPendingDeleteComponent(null);
      showToast(t('toast.payrollComponentDeleted'));
    },
  });

  const createAssignmentMutation = useMutation({
    mutationFn: createEmployeePayrollComponent,
    onSuccess: () => {
      invalidateAssignments();
      setAssignDialogOpen(false);
      showToast(t('toast.payrollComponentAssigned'));
    },
  });

  const deleteAssignmentMutation = useMutation({
    mutationFn: deleteEmployeePayrollComponent,
    onSuccess: () => {
      invalidateAssignments();
      setPendingDeleteAssignment(null);
      showToast(t('toast.payrollComponentUnassigned'));
    },
  });

  const componentForm = useForm<ComponentFormValues>({
    resolver: zodResolver(componentSchema) as Resolver<ComponentFormValues>,
    defaultValues: {
      type: 'earning',
      calculation_method: 'fixed',
      effective_date: new Date().toISOString().slice(0, 10),
    },
  });

  const assignForm = useForm<AssignFormValues>({
    resolver: zodResolver(assignSchema) as Resolver<AssignFormValues>,
    defaultValues: { effective_date: new Date().toISOString().slice(0, 10) },
  });

  const calculationMethod = componentForm.watch('calculation_method');
  const componentRows = components?.data ?? [];
  const assignmentRows = assignments?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('payrollComponents.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            componentForm.reset({ type: 'earning', calculation_method: 'fixed', effective_date: new Date().toISOString().slice(0, 10) });
            setComponentDialogOpen(true);
          }}
        >
          {t('payrollComponents.newComponent')}
        </Button>
      </Stack>

      {componentsLoading && <CircularProgress />}

      {componentRows.length === 0 && !componentsLoading && (
        <EmptyState title={t('payrollComponents.empty.title')} description={t('payrollComponents.empty.description')} />
      )}

      {componentRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('payrollComponents.table.code')}</TableCell>
                  <TableCell>{t('payrollComponents.table.name')}</TableCell>
                  <TableCell>{t('payrollComponents.table.type')}</TableCell>
                  <TableCell>{t('payrollComponents.table.method')}</TableCell>
                  <TableCell align="right">{t('payrollComponents.table.value')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {componentRows.map((component) => (
                  <TableRow key={component.id}>
                    <TableCell>{component.code}</TableCell>
                    <TableCell>{component.name}</TableCell>
                    <TableCell>{t(`payrollComponentTypes.${component.type}`)}</TableCell>
                    <TableCell>{t(`payrollCalculationMethods.${component.calculation_method}`)}</TableCell>
                    <TableCell align="right">
                      {component.calculation_method === 'fixed' && component.amount ? format(Number(component.amount)) : null}
                      {component.calculation_method === 'percentage' && component.percentage ? `${component.percentage}%` : null}
                      {component.calculation_method === 'formula' ? '—' : null}
                    </TableCell>
                    <TableCell>
                      <StatusChip status={component.is_active ? 'active' : 'inactive'} label={component.is_active ? tc('labels.active') : tc('labels.inactive')} />
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDeleteComponent(component)}>
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
        <Typography variant="h6">{t('payrollComponents.assignmentsTitle')}</Typography>
        <Button
          variant="outlined"
          startIcon={<AddIcon />}
          onClick={() => {
            assignForm.reset({ effective_date: new Date().toISOString().slice(0, 10) });
            setAssignDialogOpen(true);
          }}
        >
          {t('payrollComponents.assignComponent')}
        </Button>
      </Stack>

      {assignmentsLoading && <CircularProgress />}

      {assignmentRows.length === 0 && !assignmentsLoading && (
        <EmptyState title={t('payrollComponents.assignmentsEmpty.title')} description={t('payrollComponents.assignmentsEmpty.description')} />
      )}

      {assignmentRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('payrollComponents.table.employee')}</TableCell>
                  <TableCell>{t('payrollComponents.table.component')}</TableCell>
                  <TableCell>{t('shifts.table.effectiveDate')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {assignmentRows.map((assignment) => (
                  <TableRow key={assignment.id}>
                    <TableCell>{assignment.employee?.name ?? '—'}</TableCell>
                    <TableCell>{assignment.payroll_component?.name ?? '—'}</TableCell>
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

      <Dialog open={componentDialogOpen} onClose={() => setComponentDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('payrollComponents.form.dialogTitle')}</DialogTitle>
        <Stack
          component="form"
          onSubmit={componentForm.handleSubmit((values) =>
            createComponentMutation.mutate({
              ...values,
              amount: values.amount !== undefined ? String(values.amount) : undefined,
              percentage: values.percentage !== undefined ? String(values.percentage) : undefined,
            }),
          )}
        >
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('payrollComponents.form.code')}
                fullWidth
                {...componentForm.register('code')}
                error={!!componentForm.formState.errors.code}
                helperText={componentForm.formState.errors.code?.message}
              />
              <TextField
                label={t('payrollComponents.form.name')}
                fullWidth
                {...componentForm.register('name')}
                error={!!componentForm.formState.errors.name}
                helperText={componentForm.formState.errors.name?.message}
              />
              <TextField label={t('payrollComponents.form.type')} select fullWidth defaultValue="earning" {...componentForm.register('type')}>
                {TYPE_OPTIONS.map((type) => (
                  <MenuItem key={type} value={type}>
                    {t(`payrollComponentTypes.${type}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label={t('payrollComponents.form.method')}
                select
                fullWidth
                defaultValue="fixed"
                {...componentForm.register('calculation_method')}
              >
                {METHOD_OPTIONS.map((method) => (
                  <MenuItem key={method} value={method}>
                    {t(`payrollCalculationMethods.${method}`)}
                  </MenuItem>
                ))}
              </TextField>
              {calculationMethod === 'fixed' && (
                <TextField label={t('payrollComponents.form.amount')} type="number" fullWidth {...componentForm.register('amount')} />
              )}
              {calculationMethod === 'percentage' && (
                <>
                  <TextField
                    label={t('payrollComponents.form.percentage')}
                    type="number"
                    fullWidth
                    {...componentForm.register('percentage')}
                  />
                  <Controller
                    name="percentage_base"
                    control={componentForm.control}
                    render={({ field }) => (
                      <TextField label={t('payrollComponents.form.percentageBase')} select fullWidth {...field} value={field.value ?? ''}>
                        {PERCENTAGE_BASE_OPTIONS.map((base) => (
                          <MenuItem key={base} value={base}>
                            {t(`payrollPercentageBases.${base}`)}
                          </MenuItem>
                        ))}
                      </TextField>
                    )}
                  />
                </>
              )}
              <TextField
                label={t('payrollComponents.form.effectiveDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...componentForm.register('effective_date')}
                error={!!componentForm.formState.errors.effective_date}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setComponentDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createComponentMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={assignDialogOpen} onClose={() => setAssignDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('payrollComponents.assignComponent')}</DialogTitle>
        <Stack
          component="form"
          onSubmit={assignForm.handleSubmit((values) =>
            createAssignmentMutation.mutate({
              ...values,
              amount: values.amount !== undefined ? String(values.amount) : undefined,
              percentage: values.percentage !== undefined ? String(values.percentage) : undefined,
            }),
          )}
        >
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={assignForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('payrollComponents.table.employee')}
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
                name="payroll_component_id"
                control={assignForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('payrollComponents.table.component')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!assignForm.formState.errors.payroll_component_id}
                  >
                    {componentRows.map((component) => (
                      <MenuItem key={component.id} value={component.id}>
                        {component.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField label={t('payrollComponents.form.amountOverride')} type="number" fullWidth {...assignForm.register('amount')} />
              <TextField
                label={t('payrollComponents.form.effectiveDate')}
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
        open={!!pendingDeleteComponent}
        title={t('payrollComponents.deleteDialog.title')}
        message={t('payrollComponents.deleteDialog.message', { name: pendingDeleteComponent?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteComponentMutation.isPending}
        onConfirm={() => pendingDeleteComponent && deleteComponentMutation.mutate(pendingDeleteComponent.id)}
        onCancel={() => setPendingDeleteComponent(null)}
      />

      <ConfirmDialog
        open={!!pendingDeleteAssignment}
        title={t('payrollComponents.removeAssignmentDialog.title')}
        message={t('payrollComponents.removeAssignmentDialog.message', { name: pendingDeleteAssignment?.employee?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteAssignmentMutation.isPending}
        onConfirm={() => pendingDeleteAssignment && deleteAssignmentMutation.mutate(pendingDeleteAssignment.id)}
        onCancel={() => setPendingDeleteAssignment(null)}
      />
    </Stack>
  );
}
