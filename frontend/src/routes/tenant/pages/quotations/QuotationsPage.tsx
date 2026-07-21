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
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import SendIcon from '@mui/icons-material/Send';
import CheckIcon from '@mui/icons-material/Check';
import CloseIcon from '@mui/icons-material/Close';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, Controller, useFieldArray, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { z } from 'zod';
import {
  approveQuotation,
  convertQuotationToShipment,
  createQuotation,
  deleteQuotation,
  fetchQuotations,
  rejectQuotation,
  submitQuotationForApproval,
  updateQuotation,
} from '../../../../api/endpoints/quotations';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import type { Quotation } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { useAuthStore } from '../../../../hooks/useAuth';
import { useToast } from '../../../../hooks/useToast';

// Stable reference: an inline `?? []` fallback in a Zustand selector returns
// a new array every call, which useSyncExternalStore treats as "changed" and
// loops forever re-rendering.
const EMPTY_ROLES: string[] = [];

const STATUS_OPTIONS: Quotation['status'][] = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

function buildSchema(t: (key: string) => string) {
  return z
    .object({
      customer_id: z.number({ message: t('validation.selectCustomer') }),
      direction: z.enum(['import', 'export']),
      mode: z.enum(['sea', 'air', 'land']),
      origin_port: z.string().optional(),
      destination_port: z.string().optional(),
      issue_date: z.string().min(1, t('validation.issueDateRequired')),
      valid_until: z.string().min(1, t('validation.validUntilRequired')),
      tax_amount: z.number().min(0, t('validation.taxMin')),
      items: z
        .array(
          z.object({
            description: z.string().min(1, t('validation.itemDescriptionRequired')),
            quantity: z.number().min(0.01, t('validation.itemQuantityMin')),
            unit_price: z.number().min(0, t('validation.itemPriceMin')),
          }),
        )
        .min(1, t('validation.itemsRequired')),
    })
    .refine((data) => data.valid_until >= data.issue_date, {
      message: t('validation.validUntilAfterIssue'),
      path: ['valid_until'],
    });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function QuotationsPage() {
  const { t } = useTranslation('quotations');
  const { t: tc } = useTranslation('common');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingQuotation, setEditingQuotation] = useState<Quotation | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Quotation | null>(null);
  const [pendingReject, setPendingReject] = useState<Quotation | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const currentUserRoles = useAuthStore((s) => s.user?.roles ?? EMPTY_ROLES);
  const { data, isLoading } = useQuery({ queryKey: ['quotations', 'items'], queryFn: () => fetchQuotations() });
  const { data: customers } = useQuery({ queryKey: ['crm', 'customers'], queryFn: () => fetchCustomers() });

  const invalidateQuotations = () => queryClient.invalidateQueries({ queryKey: ['quotations', 'items'] });

  const createMutation = useMutation({
    mutationFn: createQuotation,
    onSuccess: () => {
      invalidateQuotations();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof updateQuotation>[1] }) =>
      updateQuotation(id, payload),
    onSuccess: () => {
      invalidateQuotations();
      setDialogOpen(false);
      setEditingQuotation(null);
      showToast(t('toast.updated'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: Quotation['status'] }) => updateQuotation(id, { status }),
    onSuccess: () => {
      invalidateQuotations();
      showToast(t('toast.statusUpdated'));
    },
  });

  const submitMutation = useMutation({
    mutationFn: submitQuotationForApproval,
    onSuccess: (quotation) => {
      invalidateQuotations();
      showToast(quotation.approval_request ? t('toast.submittedForApproval') : t('toast.statusUpdated'));
    },
  });

  const approveMutation = useMutation({
    mutationFn: approveQuotation,
    onSuccess: () => {
      invalidateQuotations();
      showToast(t('toast.approved'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => rejectQuotation(id, reason),
    onSuccess: () => {
      invalidateQuotations();
      setPendingReject(null);
      setRejectReason('');
      showToast(t('toast.rejected'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteQuotation,
    onSuccess: () => {
      invalidateQuotations();
      setPendingDelete(null);
      showToast(t('toast.deleted'));
    },
  });

  const convertMutation = useMutation({
    mutationFn: convertQuotationToShipment,
    onSuccess: (shipment) => {
      invalidateQuotations();
      showToast(t('toast.converted'));
      navigate(`/app/shipments/${shipment.id}`);
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
    defaultValues: {
      direction: 'export',
      mode: 'sea',
      issue_date: new Date().toISOString().slice(0, 10),
      valid_until: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
      tax_amount: 0,
      items: [{ description: '', quantity: 1, unit_price: 0 }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: 'items' });
  const watchedItems = useWatch({ control, name: 'items' });
  const watchedTax = useWatch({ control, name: 'tax_amount' });
  const subtotal = (watchedItems ?? []).reduce((sum, item) => sum + (Number(item?.quantity) || 0) * (Number(item?.unit_price) || 0), 0);
  const total = subtotal + (Number(watchedTax) || 0);

  const onSubmit = (values: FormValues) => {
    if (editingQuotation) {
      updateMutation.mutate({ id: editingQuotation.id, payload: values });
    } else {
      createMutation.mutate(values);
    }
  };

  const openCreateDialog = () => {
    setEditingQuotation(null);
    reset({
      direction: 'export',
      mode: 'sea',
      issue_date: new Date().toISOString().slice(0, 10),
      valid_until: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
      tax_amount: 0,
      items: [{ description: '', quantity: 1, unit_price: 0 }],
    });
    setDialogOpen(true);
  };

  const openEditDialog = (quotation: Quotation) => {
    setEditingQuotation(quotation);
    reset({
      customer_id: quotation.customer_id,
      direction: quotation.direction,
      mode: quotation.mode,
      origin_port: quotation.origin_port ?? '',
      destination_port: quotation.destination_port ?? '',
      issue_date: quotation.issue_date,
      valid_until: quotation.valid_until,
      tax_amount: Number(quotation.tax_amount),
      items:
        quotation.items && quotation.items.length > 0
          ? quotation.items.map((item) => ({
              description: item.description,
              quantity: Number(item.quantity),
              unit_price: Number(item.unit_price),
            }))
          : [{ description: '', quantity: 1, unit_price: 0 }],
    });
    setDialogOpen(true);
  };

  const canActOnQuotation = (quotation: Quotation) => {
    const pendingRequest = quotation.approval_request?.status === 'pending' ? quotation.approval_request : null;

    if (!pendingRequest) return false;

    return !!pendingRequest.current_step_role && currentUserRoles.includes(pendingRequest.current_step_role);
  };

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('title')}
        </Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreateDialog}>
          {t('newQuotation')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && (
        <EmptyState title={t('empty.title')} description={t('empty.description')} />
      )}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('table.quotationNo')}</TableCell>
                  <TableCell>{t('table.customer')}</TableCell>
                  <TableCell>{t('table.directionMode')}</TableCell>
                  <TableCell>{t('table.validUntil')}</TableCell>
                  <TableCell>{t('table.total')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((quotation) => (
                  <TableRow key={quotation.id}>
                    <TableCell>{quotation.quotation_number ?? '—'}</TableCell>
                    <TableCell>{quotation.customer?.company_name ?? '—'}</TableCell>
                    <TableCell>
                      {quotation.direction} / {quotation.mode}
                    </TableCell>
                    <TableCell>{quotation.valid_until}</TableCell>
                    <TableCell>
                      {quotation.currency} {quotation.total_amount}
                    </TableCell>
                    <TableCell>
                      <Stack spacing={0.5}>
                        <Select
                          size="small"
                          value={quotation.status}
                          onChange={(e) =>
                            statusMutation.mutate({ id: quotation.id, status: e.target.value as Quotation['status'] })
                          }
                          renderValue={(value) => <StatusChip status={value as string} label={t(`statuses.${value}`)} />}
                        >
                          {STATUS_OPTIONS.map((status) => (
                            <MenuItem key={status} value={status}>
                              {t(`statuses.${status}`)}
                            </MenuItem>
                          ))}
                        </Select>
                        {quotation.approval_request?.status === 'pending' && (
                          <Typography variant="caption" color="text.secondary">
                            {t('approval.stepProgress', {
                              current: quotation.approval_request.current_step_position,
                              total: quotation.approval_request.total_steps,
                              role: quotation.approval_request.current_step_role,
                            })}
                          </Typography>
                        )}
                      </Stack>
                    </TableCell>
                    <TableCell align="right">
                      {quotation.status === 'draft' && !quotation.approval_request && (
                        <Tooltip title={t('actions.submitForApproval')}>
                          <IconButton
                            size="small"
                            disabled={submitMutation.isPending}
                            onClick={() => submitMutation.mutate(quotation.id)}
                          >
                            <SendIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                      {canActOnQuotation(quotation) && (
                        <>
                          <Tooltip title={t('actions.approve')}>
                            <IconButton
                              size="small"
                              color="success"
                              disabled={approveMutation.isPending}
                              onClick={() => approveMutation.mutate(quotation.id)}
                            >
                              <CheckIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={t('actions.reject')}>
                            <IconButton size="small" color="error" onClick={() => setPendingReject(quotation)}>
                              <CloseIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                      {quotation.status === 'accepted' && !quotation.has_shipment && (
                        <Tooltip title={t('actions.convertToShipment')}>
                          <IconButton
                            size="small"
                            color="primary"
                            disabled={convertMutation.isPending}
                            onClick={() => convertMutation.mutate(quotation.id)}
                          >
                            <LocalShippingIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                      <Tooltip title={tc('actions.edit')}>
                        <IconButton size="small" onClick={() => openEditDialog(quotation)}>
                          <EditIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(quotation)}>
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

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>{editingQuotation ? t('form.editDialogTitle') : t('form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onSubmit)}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="customer_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('form.customer')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!errors.customer_id}
                    helperText={errors.customer_id?.message}
                  >
                    {customers?.data.map((customer) => (
                      <MenuItem key={customer.id} value={customer.id}>
                        {customer.company_name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <Stack direction="row" spacing={2}>
                <TextField label={t('form.direction')} select fullWidth defaultValue="export" {...register('direction')}>
                  <MenuItem value="import">{t('direction.import')}</MenuItem>
                  <MenuItem value="export">{t('direction.export')}</MenuItem>
                </TextField>
                <TextField label={t('form.mode')} select fullWidth defaultValue="sea" {...register('mode')}>
                  <MenuItem value="sea">{t('mode.sea')}</MenuItem>
                  <MenuItem value="air">{t('mode.air')}</MenuItem>
                  <MenuItem value="land">{t('mode.land')}</MenuItem>
                </TextField>
              </Stack>
              <Stack direction="row" spacing={2}>
                <TextField label={t('form.originPort')} fullWidth {...register('origin_port')} />
                <TextField label={t('form.destinationPort')} fullWidth {...register('destination_port')} />
              </Stack>
              <Stack direction="row" spacing={2}>
                <TextField
                  label={t('form.issueDate')}
                  type="date"
                  fullWidth
                  InputLabelProps={{ shrink: true }}
                  {...register('issue_date')}
                  error={!!errors.issue_date}
                  helperText={errors.issue_date?.message}
                />
                <TextField
                  label={t('form.validUntil')}
                  type="date"
                  fullWidth
                  InputLabelProps={{ shrink: true }}
                  {...register('valid_until')}
                  error={!!errors.valid_until}
                  helperText={errors.valid_until?.message}
                />
              </Stack>

              <Typography variant="subtitle2">{t('form.itemsTitle')}</Typography>
              {fields.map((field, index) => (
                <Stack direction="row" spacing={1} alignItems="flex-start" key={field.id}>
                  <Controller
                    name={`items.${index}.description`}
                    control={control}
                    render={({ field: f }) => (
                      <TextField
                        label={t('form.itemDescription')}
                        size="small"
                        fullWidth
                        value={f.value}
                        onChange={f.onChange}
                        error={!!errors.items?.[index]?.description}
                      />
                    )}
                  />
                  <Controller
                    name={`items.${index}.quantity`}
                    control={control}
                    render={({ field: f }) => (
                      <TextField
                        label={t('form.itemQuantity')}
                        type="number"
                        size="small"
                        sx={{ width: 110 }}
                        value={f.value ?? ''}
                        onChange={(e) => f.onChange(Number(e.target.value))}
                        error={!!errors.items?.[index]?.quantity}
                      />
                    )}
                  />
                  <Controller
                    name={`items.${index}.unit_price`}
                    control={control}
                    render={({ field: f }) => (
                      <TextField
                        label={t('form.itemUnitPrice')}
                        type="number"
                        size="small"
                        sx={{ width: 130 }}
                        value={f.value ?? ''}
                        onChange={(e) => f.onChange(Number(e.target.value))}
                        error={!!errors.items?.[index]?.unit_price}
                      />
                    )}
                  />
                  <IconButton size="small" onClick={() => remove(index)} disabled={fields.length === 1} sx={{ mt: 0.5 }}>
                    <DeleteIcon fontSize="small" />
                  </IconButton>
                </Stack>
              ))}
              <Button
                size="small"
                startIcon={<AddIcon />}
                onClick={() => append({ description: '', quantity: 1, unit_price: 0 })}
                sx={{ alignSelf: 'flex-start' }}
              >
                {t('form.addItem')}
              </Button>

              <Controller
                name="tax_amount"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('form.taxAmount')}
                    type="number"
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!errors.tax_amount}
                    helperText={errors.tax_amount?.message}
                  />
                )}
              />

              <Stack direction="row" justifyContent="space-between">
                <Typography variant="body2" color="text.secondary">
                  {t('form.subtotal')}
                </Typography>
                <Typography variant="body2">{subtotal.toFixed(2)}</Typography>
              </Stack>
              <Stack direction="row" justifyContent="space-between">
                <Typography variant="subtitle2">{t('form.total')}</Typography>
                <Typography variant="subtitle2">{total.toFixed(2)}</Typography>
              </Stack>
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending || updateMutation.isPending}>
              {editingQuotation ? tc('actions.save') : tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('deleteDialog.title')}
        message={t('deleteDialog.message', { number: pendingDelete?.quotation_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />

      <Dialog open={!!pendingReject} onClose={() => setPendingReject(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('rejectDialog.title')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('rejectDialog.reason')}
            fullWidth
            multiline
            minRows={2}
            sx={{ mt: 1 }}
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
    </Stack>
  );
}
