import {
  Alert,
  Button,
  Chip,
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
import ArrowBackRoundedIcon from '@mui/icons-material/ArrowBackRounded';
import BlockRoundedIcon from '@mui/icons-material/BlockRounded';
import CalculateRoundedIcon from '@mui/icons-material/CalculateRounded';
import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded';
import CheckRoundedIcon from '@mui/icons-material/CheckRounded';
import CloseRoundedIcon from '@mui/icons-material/CloseRounded';
import DownloadRoundedIcon from '@mui/icons-material/DownloadRounded';
import LockRoundedIcon from '@mui/icons-material/LockRounded';
import PaymentsRoundedIcon from '@mui/icons-material/PaymentsRounded';
import ReceiptLongRoundedIcon from '@mui/icons-material/ReceiptLongRounded';
import SendRoundedIcon from '@mui/icons-material/SendRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useParams } from 'react-router-dom';
import {
  approvePayrollRun,
  calculatePayrollRun,
  downloadSalaryPaymentBatchCsv,
  fetchPayrollRun,
  finalizePayrollRun,
  generateSalaryPaymentBatch,
  postPayrollRunToAccounting,
  rejectPayrollRun,
  submitPayrollRun,
  updatePayrollRunEmployeeStatus,
  updateSalaryPayment,
} from '../../../../api/endpoints/hr';
import type { PayrollRunEmployee, SalaryPayment } from '../../../../types';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { useCurrencyFormatter } from '../../../../hooks/useCurrency';
import { downloadBlobAsFile } from '../../../../utils/downloadFile';

export function PayrollRunDetailPage() {
  const { t } = useTranslation('hr');
  const { format } = useCurrencyFormatter();
  const { t: tc } = useTranslation('common');
  const { id } = useParams();
  const runId = Number(id);
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const canManage = usePermission('hr.payroll_runs.manage');
  const canApprove = usePermission('hr.payroll_runs.approve');

  const [rejectOpen, setRejectOpen] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [expandedEmployeeId, setExpandedEmployeeId] = useState<number | null>(null);

  const { data: run, isLoading } = useQuery({ queryKey: ['hr', 'payroll-runs', runId], queryFn: () => fetchPayrollRun(runId) });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'payroll-runs', runId] });

  const calculateMutation = useMutation({
    mutationFn: () => calculatePayrollRun(runId),
    onSuccess: () => {
      invalidate();
      showToast(t('payrollRuns.toast.calculated'));
    },
  });

  const submitMutation = useMutation({
    mutationFn: () => submitPayrollRun(runId),
    onSuccess: () => {
      invalidate();
      showToast(t('payrollRuns.toast.submitted'));
    },
  });

  const approveMutation = useMutation({
    mutationFn: () => approvePayrollRun(runId),
    onSuccess: () => {
      invalidate();
      showToast(t('payrollRuns.toast.approved'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: (reason: string) => rejectPayrollRun(runId, reason),
    onSuccess: () => {
      invalidate();
      setRejectOpen(false);
      setRejectReason('');
      showToast(t('payrollRuns.toast.rejected'));
    },
  });

  const finalizeMutation = useMutation({
    mutationFn: () => finalizePayrollRun(runId),
    onSuccess: () => {
      invalidate();
      showToast(t('payrollRuns.toast.finalized'));
    },
  });

  const toggleStatusMutation = useMutation({
    mutationFn: ({ id: rowId, status }: { id: number; status: 'included' | 'excluded' }) => updatePayrollRunEmployeeStatus(rowId, status),
    onSuccess: () => {
      invalidate();
      showToast(t('payrollRuns.toast.employeeUpdated'));
    },
  });

  const postToAccountingMutation = useMutation({
    mutationFn: () => postPayrollRunToAccounting(runId),
    onSuccess: () => {
      invalidate();
      showToast(t('payrollRuns.toast.posted'));
    },
  });

  const generatePaymentsMutation = useMutation({
    mutationFn: () => generateSalaryPaymentBatch(runId),
    onSuccess: () => {
      invalidate();
      showToast(t('payrollRuns.toast.paymentsGenerated'));
    },
  });

  const markPaymentMutation = useMutation({
    mutationFn: ({ paymentId, status }: { paymentId: number; status: 'paid' | 'failed' }) => updateSalaryPayment(paymentId, status),
    onSuccess: () => {
      invalidate();
      showToast(t('payrollRuns.toast.paymentUpdated'));
    },
  });

  const handleDownloadCsv = () => {
    if (!run?.salary_payment_batch) return;
    const batch = run.salary_payment_batch;
    return downloadBlobAsFile(() => downloadSalaryPaymentBatchCsv(batch.id), `salary-payments-${batch.batch_number ?? batch.id}.csv`);
  };

  if (isLoading || !run) {
    return <CircularProgress />;
  }

  const runEmployees = run.run_employees ?? [];
  const exceptionCount = runEmployees.filter((e) => e.status === 'exception').length;

  return (
    <Stack spacing={3}>
      <Stack direction="row" alignItems="center" spacing={1}>
        <IconButton component={RouterLink} to="/app/hr/payroll-periods" size="small">
          <ArrowBackRoundedIcon fontSize="small" />
        </IconButton>
        <Typography variant="h5" fontWeight={700}>
          {t('payrollRuns.title')} — {run.period?.name} (#{run.run_number})
        </Typography>
        <StatusChip status={run.status} label={t(`payrollRunStatuses.${run.status}`)} />
      </Stack>

      {exceptionCount > 0 && (
        <Alert severity="warning">{t('payrollRuns.exceptionsWarning', { count: exceptionCount })}</Alert>
      )}

      <Paper variant="outlined" sx={{ p: 3 }}>
        <Stack direction="row" spacing={4} flexWrap="wrap" useFlexGap>
          <SummaryStat label={t('payrollRuns.summary.gross')} value={format(Number(run.total_gross))} />
          <SummaryStat label={t('payrollRuns.summary.deductions')} value={format(Number(run.total_deductions))} />
          <SummaryStat label={t('payrollRuns.summary.net')} value={format(Number(run.total_net))} highlight />
          <SummaryStat label={t('payrollRuns.summary.employerContributions')} value={format(Number(run.total_employer_contributions))} />
          <SummaryStat label={t('payrollRuns.summary.employerCost')} value={format(Number(run.total_employer_cost))} />
          <SummaryStat label={t('payrollRuns.summary.employeeCount')} value={String(run.employee_count ?? runEmployees.length)} />
        </Stack>
      </Paper>

      <Stack direction="row" spacing={1} flexWrap="wrap" useFlexGap>
        {(run.status === 'draft' || run.status === 'calculated') && canManage && (
          <Button variant="contained" startIcon={<CalculateRoundedIcon />} onClick={() => calculateMutation.mutate()} disabled={calculateMutation.isPending}>
            {run.status === 'calculated' ? t('payrollRuns.recalculate') : t('payrollRuns.calculate')}
          </Button>
        )}
        {run.status === 'calculated' && canManage && (
          <Button variant="outlined" startIcon={<SendRoundedIcon />} onClick={() => submitMutation.mutate()} disabled={submitMutation.isPending || exceptionCount > 0}>
            {t('payrollRuns.submitForApproval')}
          </Button>
        )}
        {run.status === 'pending_approval' && canApprove && (
          <>
            <Button variant="contained" color="success" startIcon={<CheckCircleRoundedIcon />} onClick={() => approveMutation.mutate()} disabled={approveMutation.isPending}>
              {t('payrollRuns.approve')}
            </Button>
            <Button variant="outlined" color="error" startIcon={<CloseRoundedIcon />} onClick={() => setRejectOpen(true)}>
              {t('payrollRuns.reject')}
            </Button>
          </>
        )}
        {run.status === 'approved' && canApprove && (
          <Button variant="contained" startIcon={<LockRoundedIcon />} onClick={() => finalizeMutation.mutate()} disabled={finalizeMutation.isPending}>
            {t('payrollRuns.finalize')}
          </Button>
        )}
        {run.status === 'finalized' && canApprove && !run.journal_entry_id && (
          <Button variant="contained" startIcon={<ReceiptLongRoundedIcon />} onClick={() => postToAccountingMutation.mutate()} disabled={postToAccountingMutation.isPending}>
            {t('payrollRuns.postToAccounting')}
          </Button>
        )}
        {run.status === 'finalized' && canManage && !run.salary_payment_batch && (
          <Button variant="outlined" startIcon={<PaymentsRoundedIcon />} onClick={() => generatePaymentsMutation.mutate()} disabled={generatePaymentsMutation.isPending}>
            {t('payrollRuns.generatePayments')}
          </Button>
        )}
        {run.status === 'finalized' && (
          <Button variant="text" component={RouterLink} to="/app/hr/payslips">
            {t('payrollRuns.viewPayslips', { count: run.payslip_count ?? 0 })}
          </Button>
        )}
      </Stack>

      {run.journal_entry_id && (
        <Alert severity="success">{t('payrollRuns.postedNotice')}</Alert>
      )}

      {run.salary_payment_batch && (
        <>
          <Stack direction="row" justifyContent="space-between" alignItems="center">
            <Typography variant="h6">{t('payrollRuns.paymentsTitle')}</Typography>
            <Button size="small" startIcon={<DownloadRoundedIcon />} onClick={handleDownloadCsv}>
              {t('payrollRuns.exportCsv')}
            </Button>
          </Stack>
          <Paper variant="outlined">
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>{t('payrollRuns.table.employee')}</TableCell>
                    <TableCell align="right">{t('payrollRuns.table.amount')}</TableCell>
                    <TableCell>{t('payrollRuns.table.method')}</TableCell>
                    <TableCell>{tc('labels.status')}</TableCell>
                    <TableCell align="right">{tc('actions.actions')}</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {(run.salary_payment_batch.payments ?? []).map((payment) => (
                    <PaymentRow
                      key={payment.id}
                      payment={payment}
                      canManage={canManage}
                      onMark={(status) => markPaymentMutation.mutate({ paymentId: payment.id, status })}
                    />
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </Paper>
        </>
      )}

      <Typography variant="h6">{t('payrollRuns.employeesTitle')}</Typography>

      <Paper variant="outlined">
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>{t('payrollRuns.table.employee')}</TableCell>
                <TableCell align="right">{t('payrollRuns.table.basicSalary')}</TableCell>
                <TableCell align="right">{t('payrollRuns.table.gross')}</TableCell>
                <TableCell align="right">{t('payrollRuns.table.deductions')}</TableCell>
                <TableCell align="right">{t('payrollRuns.table.net')}</TableCell>
                <TableCell>{tc('labels.status')}</TableCell>
                <TableCell align="right">{tc('actions.actions')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {runEmployees.map((runEmployee) => (
                <RunEmployeeRow
                  key={runEmployee.id}
                  runEmployee={runEmployee}
                  expanded={expandedEmployeeId === runEmployee.id}
                  onToggle={() => setExpandedEmployeeId(expandedEmployeeId === runEmployee.id ? null : runEmployee.id)}
                  canManage={canManage && run.status === 'calculated'}
                  onToggleStatus={(status) => toggleStatusMutation.mutate({ id: runEmployee.id, status })}
                />
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      </Paper>

      <Dialog open={rejectOpen} onClose={() => setRejectOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('payrollRuns.reject')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('payrollRuns.rejectReason')}
            fullWidth
            multiline
            minRows={2}
            sx={{ mt: 1 }}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejectOpen(false)}>{tc('actions.cancel')}</Button>
          <Button
            color="error"
            variant="contained"
            disabled={!rejectReason.trim() || rejectMutation.isPending}
            onClick={() => rejectMutation.mutate(rejectReason)}
          >
            {t('payrollRuns.reject')}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}

function SummaryStat({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
  return (
    <Stack spacing={0.5}>
      <Typography variant="caption" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="h6" fontWeight={highlight ? 800 : 600}>
        {value}
      </Typography>
    </Stack>
  );
}

function RunEmployeeRow({
  runEmployee,
  expanded,
  onToggle,
  canManage,
  onToggleStatus,
}: {
  runEmployee: PayrollRunEmployee;
  expanded: boolean;
  onToggle: () => void;
  canManage: boolean;
  onToggleStatus: (status: 'included' | 'excluded') => void;
}) {
  const { t } = useTranslation('hr');
  const { format } = useCurrencyFormatter();

  return (
    <>
      <TableRow hover onClick={onToggle} sx={{ cursor: 'pointer' }}>
        <TableCell>{runEmployee.employee?.name ?? '—'}</TableCell>
        <TableCell align="right">{format(Number(runEmployee.basic_salary))}</TableCell>
        <TableCell align="right">{format(Number(runEmployee.gross_pay))}</TableCell>
        <TableCell align="right">{format(Number(runEmployee.total_deductions))}</TableCell>
        <TableCell align="right">{format(Number(runEmployee.net_pay))}</TableCell>
        <TableCell>
          <StatusChip status={runEmployee.status} label={t(`payrollRunEmployeeStatuses.${runEmployee.status}`)} />
        </TableCell>
        <TableCell align="right" onClick={(e) => e.stopPropagation()}>
          {canManage && runEmployee.status !== 'exception' && (
            <Tooltip title={runEmployee.status === 'included' ? t('payrollRuns.exclude') : t('payrollRuns.include')}>
              <IconButton size="small" onClick={() => onToggleStatus(runEmployee.status === 'included' ? 'excluded' : 'included')}>
                {runEmployee.status === 'included' ? <BlockRoundedIcon fontSize="small" /> : <CheckRoundedIcon fontSize="small" />}
              </IconButton>
            </Tooltip>
          )}
        </TableCell>
      </TableRow>
      {expanded && (
        <TableRow>
          <TableCell colSpan={7} sx={{ backgroundColor: 'action.hover' }}>
            {runEmployee.exception_notes && (
              <Alert severity={runEmployee.status === 'exception' ? 'error' : 'info'} sx={{ mb: 2 }}>
                {runEmployee.exception_notes}
              </Alert>
            )}
            <Stack direction="row" spacing={4} flexWrap="wrap" useFlexGap>
              <LineItemList title={t('payrollRuns.earnings')} items={runEmployee.earnings ?? []} />
              <LineItemList title={t('payrollRuns.deductions')} items={runEmployee.deductions ?? []} />
              <LineItemList title={t('payrollRuns.employerContributions')} items={runEmployee.employer_contributions ?? []} />
            </Stack>
          </TableCell>
        </TableRow>
      )}
    </>
  );
}

function PaymentRow({
  payment,
  canManage,
  onMark,
}: {
  payment: SalaryPayment;
  canManage: boolean;
  onMark: (status: 'paid' | 'failed') => void;
}) {
  const { t } = useTranslation('hr');
  const { format } = useCurrencyFormatter();

  return (
    <TableRow>
      <TableCell>{payment.employee?.name ?? '—'}</TableCell>
      <TableCell align="right">{format(Number(payment.amount))}</TableCell>
      <TableCell>{t(`paymentMethods.${payment.payment_method}`)}</TableCell>
      <TableCell>
        <StatusChip status={payment.status} label={t(`salaryPaymentStatuses.${payment.status}`)} />
      </TableCell>
      <TableCell align="right">
        {canManage && payment.status === 'pending' && (
          <>
            <Tooltip title={t('payrollRuns.markPaid')}>
              <IconButton size="small" color="success" onClick={() => onMark('paid')}>
                <CheckCircleRoundedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
            <Tooltip title={t('payrollRuns.markFailed')}>
              <IconButton size="small" color="error" onClick={() => onMark('failed')}>
                <CloseRoundedIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </>
        )}
      </TableCell>
    </TableRow>
  );
}

function LineItemList({ title, items }: { title: string; items: { id: number; label: string; amount: string }[] }) {
  const { format } = useCurrencyFormatter();
  if (items.length === 0) return null;

  return (
    <Stack spacing={0.5} sx={{ minWidth: 220 }}>
      <Typography variant="caption" fontWeight={600} color="text.secondary">
        {title}
      </Typography>
      {items.map((item) => (
        <Stack key={item.id} direction="row" justifyContent="space-between">
          <Typography variant="body2">{item.label}</Typography>
          <Chip size="small" variant="outlined" label={format(Number(item.amount))} />
        </Stack>
      ))}
    </Stack>
  );
}
