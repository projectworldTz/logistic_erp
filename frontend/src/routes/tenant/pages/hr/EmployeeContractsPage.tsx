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
import SendRoundedIcon from '@mui/icons-material/SendRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm, type Resolver } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router-dom';
import { z } from 'zod';
import {
  approveEmployeeContract,
  createEmployeeContract,
  deleteEmployeeContract,
  fetchEmployeeContracts,
  fetchEmployees,
  rejectEmployeeContract,
  submitEmployeeContract,
} from '../../../../api/endpoints/hr';
import type { EmployeeContract, EmploymentType, PayFrequency } from '../../../../types';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { EmptyState } from '../../../../components/common/EmptyState';
import { StatusChip } from '../../../../components/common/StatusChip';
import { useAuthStore } from '../../../../hooks/useAuth';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { formatCurrency } from '../../../../utils/currency';
import { HrTabs } from './HrTabs';

const EMPLOYMENT_TYPE_OPTIONS: EmploymentType[] = [
  'permanent', 'full_time', 'part_time', 'contract', 'temporary', 'casual', 'intern', 'consultant', 'driver', 'commission_based', 'daily_paid',
];
const PAY_FREQUENCY_OPTIONS: PayFrequency[] = ['monthly', 'biweekly', 'weekly', 'daily', 'hourly'];
const EMPTY_ROLES: string[] = [];

function buildSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    employment_type: z.enum(EMPLOYMENT_TYPE_OPTIONS as [EmploymentType, ...EmploymentType[]]),
    effective_date: z.string().min(1, t('validation.dateRequired')),
    expiry_date: z.string().optional(),
    basic_salary: z.coerce.number().min(0),
    pay_frequency: z.enum(PAY_FREQUENCY_OPTIONS as [PayFrequency, ...PayFrequency[]]),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function EmployeeContractsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [searchParams] = useSearchParams();
  const employeeFilter = searchParams.get('employee_id') ? Number(searchParams.get('employee_id')) : undefined;
  const [dialogOpen, setDialogOpen] = useState(false);
  const [rejectTarget, setRejectTarget] = useState<EmployeeContract | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [pendingDelete, setPendingDelete] = useState<EmployeeContract | null>(null);

  const canApprove = usePermission('hr.contracts.approve');
  const currentUserRoles = useAuthStore((s) => s.user?.roles ?? EMPTY_ROLES);

  const { data, isLoading } = useQuery({
    queryKey: ['hr', 'contracts', employeeFilter],
    queryFn: () => fetchEmployeeContracts(employeeFilter),
  });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees(1) });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'contracts'] });

  const createMutation = useMutation({
    mutationFn: createEmployeeContract,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.contractCreated'));
    },
  });

  const submitMutation = useMutation({
    mutationFn: submitEmployeeContract,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.contractSubmitted'));
    },
  });

  const approveMutation = useMutation({
    mutationFn: approveEmployeeContract,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.contractApproved'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => rejectEmployeeContract(id, reason),
    onSuccess: () => {
      invalidate();
      setRejectTarget(null);
      setRejectReason('');
      showToast(t('toast.contractRejected'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteEmployeeContract,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.contractDeleted'));
    },
  });

  const {
    control,
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema) as Resolver<FormValues>,
    defaultValues: { employment_type: 'permanent', pay_frequency: 'monthly', effective_date: new Date().toISOString().slice(0, 10) },
  });

  const openCreateDialog = () => {
    reset({
      employee_id: employeeFilter,
      employment_type: 'permanent',
      pay_frequency: 'monthly',
      effective_date: new Date().toISOString().slice(0, 10),
    });
    setDialogOpen(true);
  };

  const rows = data?.data ?? [];

  const canActOnContract = (contract: EmployeeContract) => {
    const pendingRequest = contract.approval_request?.status === 'pending' ? contract.approval_request : null;
    if (pendingRequest) {
      return !!pendingRequest.current_step_role && currentUserRoles.includes(pendingRequest.current_step_role);
    }
    return canApprove;
  };

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>{t('title')}</Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('contracts.title')}</Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreateDialog}>
          {t('contracts.newContract')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('contracts.empty.title')} description={t('contracts.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('contracts.table.number')}</TableCell>
                  <TableCell>{t('contracts.table.employee')}</TableCell>
                  <TableCell>{t('contracts.table.employmentType')}</TableCell>
                  <TableCell align="right">{t('contracts.table.basicSalary')}</TableCell>
                  <TableCell>{t('contracts.table.effectiveDate')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((contract) => (
                  <TableRow key={contract.id}>
                    <TableCell>{contract.contract_number ?? '—'}</TableCell>
                    <TableCell>{contract.employee?.name ?? '—'}</TableCell>
                    <TableCell>{t(`employmentTypes.${contract.employment_type}`)}</TableCell>
                    <TableCell align="right">{formatCurrency(Number(contract.basic_salary))}</TableCell>
                    <TableCell>{contract.effective_date.slice(0, 10)}</TableCell>
                    <TableCell>
                      <StatusChip status={contract.status} label={t(`contractStatuses.${contract.status}`)} />
                    </TableCell>
                    <TableCell align="right">
                      {contract.status === 'draft' && (
                        <>
                          <Tooltip title={t('contracts.submit')}>
                            <IconButton size="small" onClick={() => submitMutation.mutate(contract.id)}>
                              <SendRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={tc('actions.delete')}>
                            <IconButton size="small" onClick={() => setPendingDelete(contract)}>
                              <DeleteIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                      {contract.status === 'pending_approval' && canActOnContract(contract) && (
                        <>
                          <Tooltip title={t('contracts.approve')}>
                            <IconButton size="small" color="success" onClick={() => approveMutation.mutate(contract.id)}>
                              <CheckCircleRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={t('contracts.reject')}>
                            <IconButton size="small" color="error" onClick={() => setRejectTarget(contract)}>
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
        <DialogTitle>{t('contracts.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate({ ...values, basic_salary: String(values.basic_salary) }))}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('contracts.form.employee')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : undefined)}
                    error={!!errors.employee_id}
                    helperText={errors.employee_id?.message}
                  >
                    {employees?.data.map((employee) => (
                      <MenuItem key={employee.id} value={employee.id}>{employee.name}</MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField label={t('contracts.form.employmentType')} select fullWidth defaultValue="permanent" {...register('employment_type')}>
                {EMPLOYMENT_TYPE_OPTIONS.map((type) => (
                  <MenuItem key={type} value={type}>{t(`employmentTypes.${type}`)}</MenuItem>
                ))}
              </TextField>
              <TextField
                label={t('contracts.form.effectiveDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('effective_date')}
                error={!!errors.effective_date}
                helperText={errors.effective_date?.message}
              />
              <TextField
                label={t('contracts.form.expiryDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('expiry_date')}
              />
              <TextField
                label={t('contracts.form.basicSalary')}
                type="number"
                fullWidth
                {...register('basic_salary')}
                error={!!errors.basic_salary}
                helperText={errors.basic_salary?.message}
              />
              <TextField label={t('contracts.form.payFrequency')} select fullWidth defaultValue="monthly" {...register('pay_frequency')}>
                {PAY_FREQUENCY_OPTIONS.map((freq) => (
                  <MenuItem key={freq} value={freq}>{t(`payFrequencies.${freq}`)}</MenuItem>
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

      <Dialog open={!!rejectTarget} onClose={() => setRejectTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('contracts.reject')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('contracts.rejectReason')}
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
            {t('contracts.reject')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('contracts.deleteDialog.title')}
        message={t('contracts.deleteDialog.message', { number: pendingDelete?.contract_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
