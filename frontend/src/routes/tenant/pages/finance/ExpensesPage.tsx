import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  CircularProgress,
  Checkbox,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  FormControlLabel,
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
import EditIcon from '@mui/icons-material/Edit';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  approveExpense,
  createExpense,
  deleteExpense,
  fetchExpenses,
  markExpensePaid,
  rejectExpense,
  submitExpense,
  updateExpense,
} from '../../../../api/endpoints/finance';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import { fetchShipments } from '../../../../api/endpoints/shipments';
import type { Expense } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { useToast } from '../../../../hooks/useToast';
import { usePermission } from '../../../../hooks/usePermission';
import { useAuthStore } from '../../../../hooks/useAuth';

// Stable reference: an inline `?? []` fallback in a Zustand selector returns
// a new array every call, which useSyncExternalStore treats as "changed" and
// loops forever re-rendering.
const EMPTY_ROLES: string[] = [];

const CATEGORY_OPTIONS: Expense['category'][] = [
  'customs_duty',
  'trucking',
  'port_fees',
  'documentation',
  'warehousing',
  'insurance',
  'utilities',
  'office_supplies',
  'other',
];

const STATUS_FILTERS: Array<Expense['status'] | ''> = ['', 'draft', 'submitted', 'approved', 'rejected', 'paid'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    customer_id: z.number().optional(),
    shipment_id: z.number().optional(),
    category: z.enum(['customs_duty', 'trucking', 'port_fees', 'documentation', 'warehousing', 'insurance', 'utilities', 'office_supplies', 'other']),
    description: z.string().min(1, t('validation.descriptionRequired')),
    amount: z.number({ message: t('validation.amountRequired') }).min(0.01, t('validation.amountRequired')),
    expense_date: z.string().min(1, t('validation.dateRequired')),
    is_billable: z.boolean().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function ExpensesPage() {
  const { t } = useTranslation('expenses');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const canApprove = usePermission('expenses.items.approve');
  const canViewCustomers = usePermission('crm.customers.view');
  const canViewShipments = usePermission('shipments.items.view');
  const currentUserRoles = useAuthStore((s) => s.user?.roles ?? EMPTY_ROLES);
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingExpense, setEditingExpense] = useState<Expense | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Expense | null>(null);
  const [pendingReject, setPendingReject] = useState<Expense | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [statusFilter, setStatusFilter] = useState<Expense['status'] | ''>('');

  const { data, isLoading } = useQuery({
    queryKey: ['finance', 'expenses', statusFilter],
    queryFn: () => fetchExpenses(1, statusFilter || undefined),
  });
  const { data: customers } = useQuery({ queryKey: ['crm', 'customers'], queryFn: () => fetchCustomers(), enabled: canViewCustomers });
  const { data: shipments } = useQuery({ queryKey: ['shipments', 'items'], queryFn: () => fetchShipments(), enabled: canViewShipments });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['finance', 'expenses'] });

  const createMutation = useMutation({
    mutationFn: createExpense,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<Expense> }) => updateExpense(id, payload),
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      setEditingExpense(null);
      showToast(t('toast.updated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteExpense,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.deleted'));
    },
  });

  const submitMutation = useMutation({
    mutationFn: submitExpense,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.submitted'));
    },
  });

  const approveMutation = useMutation({
    mutationFn: approveExpense,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.approved'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => rejectExpense(id, reason),
    onSuccess: () => {
      invalidate();
      setPendingReject(null);
      setRejectReason('');
      showToast(t('toast.rejected'));
    },
  });

  const markPaidMutation = useMutation({
    mutationFn: markExpensePaid,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.markedPaid'));
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
    defaultValues: { category: 'other', expense_date: new Date().toISOString().slice(0, 10), is_billable: false },
  });

  const openCreateDialog = () => {
    setEditingExpense(null);
    reset({ category: 'other', expense_date: new Date().toISOString().slice(0, 10), is_billable: false });
    setDialogOpen(true);
  };

  const openEditDialog = (expense: Expense) => {
    setEditingExpense(expense);
    reset({
      customer_id: expense.customer_id ?? undefined,
      shipment_id: expense.shipment_id ?? undefined,
      category: expense.category,
      description: expense.description,
      amount: Number(expense.amount),
      expense_date: expense.expense_date.slice(0, 10),
      is_billable: expense.is_billable,
    });
    setDialogOpen(true);
  };

  const onSubmitForm = (values: FormValues) => {
    const payload = { ...values, amount: String(values.amount) };
    if (editingExpense) {
      updateMutation.mutate({ id: editingExpense.id, payload });
    } else {
      createMutation.mutate(payload);
    }
  };

  const rows = data?.data ?? [];

  const canActOnExpense = (expense: Expense) => {
    const pendingRequest = expense.approval_request?.status === 'pending' ? expense.approval_request : null;

    if (pendingRequest) {
      return !!pendingRequest.current_step_role && currentUserRoles.includes(pendingRequest.current_step_role);
    }

    // No workflow attached at all — legacy single-approver behavior.
    return canApprove;
  };

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('title')}
        </Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreateDialog}>
          {t('newExpense')}
        </Button>
      </Stack>

      <TextField
        label={tc('labels.status')}
        select
        size="small"
        sx={{ maxWidth: 220 }}
        value={statusFilter}
        onChange={(e) => setStatusFilter(e.target.value as Expense['status'] | '')}
      >
        {STATUS_FILTERS.map((status) => (
          <MenuItem key={status || 'all'} value={status}>
            {status ? t(`statuses.${status}`) : t('filters.allStatuses')}
          </MenuItem>
        ))}
      </TextField>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('empty.title')} description={t('empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('table.expenseNo')}</TableCell>
                  <TableCell>{t('table.category')}</TableCell>
                  <TableCell>{t('table.description')}</TableCell>
                  <TableCell align="right">{t('table.amount')}</TableCell>
                  <TableCell>{t('table.date')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((expense) => (
                  <TableRow key={expense.id}>
                    <TableCell>{expense.expense_number ?? '—'}</TableCell>
                    <TableCell>{t(`categories.${expense.category}`)}</TableCell>
                    <TableCell>{expense.description}</TableCell>
                    <TableCell align="right">
                      {expense.currency} {expense.amount}
                    </TableCell>
                    <TableCell>{expense.expense_date.slice(0, 10)}</TableCell>
                    <TableCell>
                      <Stack spacing={0.5}>
                        <Tooltip title={expense.status === 'rejected' ? expense.rejection_reason ?? '' : ''}>
                          <span><StatusChip status={expense.status} label={t(`statuses.${expense.status}`)} /></span>
                        </Tooltip>
                        {expense.status === 'submitted' && expense.approval_request?.status === 'pending' && expense.approval_request.total_steps && expense.approval_request.total_steps > 1 && (
                          <Typography variant="caption" color="text.secondary">
                            {t('approval.stepProgress', {
                              current: expense.approval_request.current_step_position,
                              total: expense.approval_request.total_steps,
                              role: expense.approval_request.current_step_role,
                            })}
                          </Typography>
                        )}
                      </Stack>
                    </TableCell>
                    <TableCell align="right">
                      <Stack direction="row" spacing={0.5} justifyContent="flex-end">
                        {expense.status === 'draft' && (
                          <>
                            <Tooltip title={tc('actions.edit')}>
                              <IconButton size="small" onClick={() => openEditDialog(expense)}>
                                <EditIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                            <Tooltip title={tc('actions.delete')}>
                              <IconButton size="small" onClick={() => setPendingDelete(expense)}>
                                <DeleteIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                            <Button size="small" variant="outlined" onClick={() => submitMutation.mutate(expense.id)}>
                              {t('actions.submit')}
                            </Button>
                          </>
                        )}
                        {expense.status === 'submitted' && canActOnExpense(expense) && (
                          <>
                            <Button size="small" onClick={() => setPendingReject(expense)}>
                              {t('actions.reject')}
                            </Button>
                            <Button size="small" variant="contained" onClick={() => approveMutation.mutate(expense.id)}>
                              {t('actions.approve')}
                            </Button>
                          </>
                        )}
                        {expense.status === 'approved' && (
                          <Button size="small" variant="contained" onClick={() => markPaidMutation.mutate(expense.id)}>
                            {t('actions.markPaid')}
                          </Button>
                        )}
                      </Stack>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{editingExpense ? t('form.editDialogTitle') : t('form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onSubmitForm)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField label={t('form.category')} select fullWidth defaultValue="other" {...register('category')}>
                {CATEGORY_OPTIONS.map((category) => (
                  <MenuItem key={category} value={category}>
                    {t(`categories.${category}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label={t('form.description')}
                fullWidth
                multiline
                minRows={2}
                {...register('description')}
                error={!!errors.description}
                helperText={errors.description?.message}
              />
              <Controller
                name="amount"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('form.amount')}
                    type="number"
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!errors.amount}
                    helperText={errors.amount?.message}
                  />
                )}
              />
              <TextField
                label={t('form.expenseDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('expense_date')}
                error={!!errors.expense_date}
                helperText={errors.expense_date?.message}
              />
              <Controller
                name="customer_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('form.customer')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : undefined)}
                  >
                    <MenuItem value="">—</MenuItem>
                    {customers?.data.map((customer) => (
                      <MenuItem key={customer.id} value={customer.id}>
                        {customer.company_name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <Controller
                name="shipment_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('form.shipment')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : undefined)}
                  >
                    <MenuItem value="">—</MenuItem>
                    {shipments?.data.map((shipment) => (
                      <MenuItem key={shipment.id} value={shipment.id}>
                        {shipment.shipment_number}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <FormControlLabel
                control={
                  <Controller
                    name="is_billable"
                    control={control}
                    render={({ field }) => (
                      <Checkbox checked={!!field.value} onChange={(e) => field.onChange(e.target.checked)} />
                    )}
                  />
                }
                label={t('form.isBillable')}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending || updateMutation.isPending}>
              {editingExpense ? tc('actions.save') : tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={!!pendingReject} onClose={() => setPendingReject(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('rejectDialog.title')}</DialogTitle>
        <DialogContent>
          <DialogContentText sx={{ mb: 2 }}>{t('rejectDialog.description')}</DialogContentText>
          <TextField
            label={t('rejectDialog.reason')}
            fullWidth
            multiline
            minRows={2}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPendingReject(null)}>{tc('actions.cancel')}</Button>
          <Button
            variant="contained"
            color="error"
            disabled={!rejectReason.trim() || rejectMutation.isPending}
            onClick={() => pendingReject && rejectMutation.mutate({ id: pendingReject.id, reason: rejectReason })}
          >
            {t('actions.reject')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('deleteDialog.title')}
        message={t('deleteDialog.message', { number: pendingDelete?.expense_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
