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
import { z } from 'zod';
import {
  approveLoan,
  approveSalaryAdvance,
  createLoan,
  createSalaryAdvance,
  deleteLoan,
  deleteSalaryAdvance,
  fetchEmployees,
  fetchLoans,
  fetchSalaryAdvances,
  rejectLoan,
  rejectSalaryAdvance,
  submitLoan,
  submitSalaryAdvance,
} from '../../../../api/endpoints/hr';
import type { EmployeeLoan, SalaryAdvance } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { formatCurrency } from '../../../../utils/currency';
import { HrTabs } from './HrTabs';

function buildLoanSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    principal_amount: z.coerce.number().min(0.01),
    interest_rate: z.coerce.number().min(0).max(100).optional(),
    number_of_installments: z.coerce.number().int().min(1).max(120),
    start_date: z.string().min(1, t('validation.dateRequired')),
    reason: z.string().optional(),
  });
}

type LoanFormValues = z.infer<ReturnType<typeof buildLoanSchema>>;

function buildAdvanceSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    amount: z.coerce.number().min(0.01),
    number_of_installments: z.coerce.number().int().min(1).max(24),
    request_date: z.string().min(1, t('validation.dateRequired')),
    reason: z.string().optional(),
  });
}

type AdvanceFormValues = z.infer<ReturnType<typeof buildAdvanceSchema>>;

export function LoansAdvancesPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const loanSchema = buildLoanSchema(t);
  const advanceSchema = buildAdvanceSchema(t);
  const canApproveLoans = usePermission('hr.loans.approve');
  const canApproveAdvances = usePermission('hr.advances.approve');

  const [loanDialogOpen, setLoanDialogOpen] = useState(false);
  const [advanceDialogOpen, setAdvanceDialogOpen] = useState(false);
  const [pendingDeleteLoan, setPendingDeleteLoan] = useState<EmployeeLoan | null>(null);
  const [pendingDeleteAdvance, setPendingDeleteAdvance] = useState<SalaryAdvance | null>(null);
  const [rejectLoanTarget, setRejectLoanTarget] = useState<EmployeeLoan | null>(null);
  const [rejectAdvanceTarget, setRejectAdvanceTarget] = useState<SalaryAdvance | null>(null);
  const [rejectReason, setRejectReason] = useState('');

  const { data: loans, isLoading: loansLoading } = useQuery({ queryKey: ['hr', 'loans'], queryFn: () => fetchLoans() });
  const { data: advances, isLoading: advancesLoading } = useQuery({ queryKey: ['hr', 'salary-advances'], queryFn: () => fetchSalaryAdvances() });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidateLoans = () => queryClient.invalidateQueries({ queryKey: ['hr', 'loans'] });
  const invalidateAdvances = () => queryClient.invalidateQueries({ queryKey: ['hr', 'salary-advances'] });

  const createLoanMutation = useMutation({
    mutationFn: createLoan,
    onSuccess: () => {
      invalidateLoans();
      setLoanDialogOpen(false);
      showToast(t('toast.loanCreated'));
    },
  });
  const deleteLoanMutation = useMutation({
    mutationFn: deleteLoan,
    onSuccess: () => {
      invalidateLoans();
      setPendingDeleteLoan(null);
      showToast(t('toast.loanDeleted'));
    },
  });
  const submitLoanMutation = useMutation({
    mutationFn: submitLoan,
    onSuccess: () => {
      invalidateLoans();
      showToast(t('toast.loanSubmitted'));
    },
  });
  const approveLoanMutation = useMutation({
    mutationFn: approveLoan,
    onSuccess: () => {
      invalidateLoans();
      showToast(t('toast.loanApproved'));
    },
  });
  const rejectLoanMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => rejectLoan(id, reason),
    onSuccess: () => {
      invalidateLoans();
      setRejectLoanTarget(null);
      setRejectReason('');
      showToast(t('toast.loanRejected'));
    },
  });

  const createAdvanceMutation = useMutation({
    mutationFn: createSalaryAdvance,
    onSuccess: () => {
      invalidateAdvances();
      setAdvanceDialogOpen(false);
      showToast(t('toast.advanceCreated'));
    },
  });
  const deleteAdvanceMutation = useMutation({
    mutationFn: deleteSalaryAdvance,
    onSuccess: () => {
      invalidateAdvances();
      setPendingDeleteAdvance(null);
      showToast(t('toast.advanceDeleted'));
    },
  });
  const submitAdvanceMutation = useMutation({
    mutationFn: submitSalaryAdvance,
    onSuccess: () => {
      invalidateAdvances();
      showToast(t('toast.advanceSubmitted'));
    },
  });
  const approveAdvanceMutation = useMutation({
    mutationFn: approveSalaryAdvance,
    onSuccess: () => {
      invalidateAdvances();
      showToast(t('toast.advanceApproved'));
    },
  });
  const rejectAdvanceMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => rejectSalaryAdvance(id, reason),
    onSuccess: () => {
      invalidateAdvances();
      setRejectAdvanceTarget(null);
      setRejectReason('');
      showToast(t('toast.advanceRejected'));
    },
  });

  const loanForm = useForm<LoanFormValues>({
    resolver: zodResolver(loanSchema) as Resolver<LoanFormValues>,
    defaultValues: { interest_rate: 0, start_date: new Date().toISOString().slice(0, 10) },
  });
  const advanceForm = useForm<AdvanceFormValues>({
    resolver: zodResolver(advanceSchema) as Resolver<AdvanceFormValues>,
    defaultValues: { number_of_installments: 1, request_date: new Date().toISOString().slice(0, 10) },
  });

  const loanRows = loans?.data ?? [];
  const advanceRows = advances?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('loans.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            loanForm.reset({ interest_rate: 0, start_date: new Date().toISOString().slice(0, 10) });
            setLoanDialogOpen(true);
          }}
        >
          {t('loans.newLoan')}
        </Button>
      </Stack>

      {loansLoading && <CircularProgress />}
      {loanRows.length === 0 && !loansLoading && (
        <EmptyState title={t('loans.empty.title')} description={t('loans.empty.description')} />
      )}
      {loanRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('loans.table.number')}</TableCell>
                  <TableCell>{t('loans.table.employee')}</TableCell>
                  <TableCell align="right">{t('loans.table.principal')}</TableCell>
                  <TableCell align="right">{t('loans.table.installment')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {loanRows.map((loan) => (
                  <TableRow key={loan.id}>
                    <TableCell>{loan.loan_number ?? '—'}</TableCell>
                    <TableCell>{loan.employee?.name ?? '—'}</TableCell>
                    <TableCell align="right">{formatCurrency(Number(loan.principal_amount))}</TableCell>
                    <TableCell align="right">{formatCurrency(Number(loan.installment_amount))}</TableCell>
                    <TableCell>
                      <StatusChip status={loan.status} label={t(`loanStatuses.${loan.status}`)} />
                    </TableCell>
                    <TableCell align="right">
                      {loan.status === 'draft' && (
                        <>
                          <Tooltip title={t('loans.submit')}>
                            <IconButton size="small" onClick={() => submitLoanMutation.mutate(loan.id)}>
                              <SendRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={tc('actions.delete')}>
                            <IconButton size="small" onClick={() => setPendingDeleteLoan(loan)}>
                              <DeleteIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                      {loan.status === 'pending_approval' && canApproveLoans && (
                        <>
                          <Tooltip title={t('loans.approve')}>
                            <IconButton size="small" color="success" onClick={() => approveLoanMutation.mutate(loan.id)}>
                              <CheckCircleRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={t('loans.reject')}>
                            <IconButton size="small" color="error" onClick={() => setRejectLoanTarget(loan)}>
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

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('advances.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            advanceForm.reset({ number_of_installments: 1, request_date: new Date().toISOString().slice(0, 10) });
            setAdvanceDialogOpen(true);
          }}
        >
          {t('advances.newAdvance')}
        </Button>
      </Stack>

      {advancesLoading && <CircularProgress />}
      {advanceRows.length === 0 && !advancesLoading && (
        <EmptyState title={t('advances.empty.title')} description={t('advances.empty.description')} />
      )}
      {advanceRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('advances.table.number')}</TableCell>
                  <TableCell>{t('advances.table.employee')}</TableCell>
                  <TableCell align="right">{t('advances.table.amount')}</TableCell>
                  <TableCell align="right">{t('advances.table.installment')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {advanceRows.map((advance) => (
                  <TableRow key={advance.id}>
                    <TableCell>{advance.advance_number ?? '—'}</TableCell>
                    <TableCell>{advance.employee?.name ?? '—'}</TableCell>
                    <TableCell align="right">{formatCurrency(Number(advance.amount))}</TableCell>
                    <TableCell align="right">{formatCurrency(Number(advance.installment_amount))}</TableCell>
                    <TableCell>
                      <StatusChip status={advance.status} label={t(`loanStatuses.${advance.status}`)} />
                    </TableCell>
                    <TableCell align="right">
                      {advance.status === 'draft' && (
                        <>
                          <Tooltip title={t('advances.submit')}>
                            <IconButton size="small" onClick={() => submitAdvanceMutation.mutate(advance.id)}>
                              <SendRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={tc('actions.delete')}>
                            <IconButton size="small" onClick={() => setPendingDeleteAdvance(advance)}>
                              <DeleteIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                      {advance.status === 'pending_approval' && canApproveAdvances && (
                        <>
                          <Tooltip title={t('advances.approve')}>
                            <IconButton size="small" color="success" onClick={() => approveAdvanceMutation.mutate(advance.id)}>
                              <CheckCircleRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={t('advances.reject')}>
                            <IconButton size="small" color="error" onClick={() => setRejectAdvanceTarget(advance)}>
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

      <Dialog open={loanDialogOpen} onClose={() => setLoanDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('loans.newLoan')}</DialogTitle>
        <Stack
          component="form"
          onSubmit={loanForm.handleSubmit((values) =>
            createLoanMutation.mutate({
              ...values,
              principal_amount: String(values.principal_amount),
              interest_rate: values.interest_rate !== undefined ? String(values.interest_rate) : undefined,
            }),
          )}
        >
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={loanForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('loans.table.employee')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!loanForm.formState.errors.employee_id}
                  >
                    {employees?.data.map((employee) => (
                      <MenuItem key={employee.id} value={employee.id}>
                        {employee.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField label={t('loans.form.principal')} type="number" fullWidth {...loanForm.register('principal_amount')} />
              <TextField label={t('loans.form.interestRate')} type="number" fullWidth {...loanForm.register('interest_rate')} />
              <TextField label={t('loans.form.installments')} type="number" fullWidth {...loanForm.register('number_of_installments')} />
              <TextField
                label={t('loans.form.startDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...loanForm.register('start_date')}
              />
              <TextField label={t('loans.form.reason')} fullWidth multiline minRows={2} {...loanForm.register('reason')} />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setLoanDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createLoanMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={advanceDialogOpen} onClose={() => setAdvanceDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('advances.newAdvance')}</DialogTitle>
        <Stack
          component="form"
          onSubmit={advanceForm.handleSubmit((values) => createAdvanceMutation.mutate({ ...values, amount: String(values.amount) }))}
        >
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={advanceForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('advances.table.employee')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!advanceForm.formState.errors.employee_id}
                  >
                    {employees?.data.map((employee) => (
                      <MenuItem key={employee.id} value={employee.id}>
                        {employee.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField label={t('advances.form.amount')} type="number" fullWidth {...advanceForm.register('amount')} />
              <TextField label={t('advances.form.installments')} type="number" fullWidth {...advanceForm.register('number_of_installments')} />
              <TextField
                label={t('advances.form.requestDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...advanceForm.register('request_date')}
              />
              <TextField label={t('advances.form.reason')} fullWidth multiline minRows={2} {...advanceForm.register('reason')} />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setAdvanceDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createAdvanceMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={!!rejectLoanTarget} onClose={() => setRejectLoanTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('loans.reject')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('loans.rejectReason')}
            fullWidth
            multiline
            minRows={2}
            sx={{ mt: 1 }}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejectLoanTarget(null)}>{tc('actions.cancel')}</Button>
          <Button
            color="error"
            variant="contained"
            disabled={!rejectReason.trim() || rejectLoanMutation.isPending}
            onClick={() => rejectLoanTarget && rejectLoanMutation.mutate({ id: rejectLoanTarget.id, reason: rejectReason })}
          >
            {t('loans.reject')}
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={!!rejectAdvanceTarget} onClose={() => setRejectAdvanceTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('advances.reject')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('advances.rejectReason')}
            fullWidth
            multiline
            minRows={2}
            sx={{ mt: 1 }}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejectAdvanceTarget(null)}>{tc('actions.cancel')}</Button>
          <Button
            color="error"
            variant="contained"
            disabled={!rejectReason.trim() || rejectAdvanceMutation.isPending}
            onClick={() => rejectAdvanceTarget && rejectAdvanceMutation.mutate({ id: rejectAdvanceTarget.id, reason: rejectReason })}
          >
            {t('advances.reject')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDeleteLoan}
        title={t('loans.deleteDialog.title')}
        message={t('loans.deleteDialog.message', { number: pendingDeleteLoan?.loan_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteLoanMutation.isPending}
        onConfirm={() => pendingDeleteLoan && deleteLoanMutation.mutate(pendingDeleteLoan.id)}
        onCancel={() => setPendingDeleteLoan(null)}
      />

      <ConfirmDialog
        open={!!pendingDeleteAdvance}
        title={t('advances.deleteDialog.title')}
        message={t('advances.deleteDialog.message', { number: pendingDeleteAdvance?.advance_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteAdvanceMutation.isPending}
        onConfirm={() => pendingDeleteAdvance && deleteAdvanceMutation.mutate(pendingDeleteAdvance.id)}
        onCancel={() => setPendingDeleteAdvance(null)}
      />
    </Stack>
  );
}
