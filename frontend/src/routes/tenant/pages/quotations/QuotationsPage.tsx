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
import { useForm, Controller } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { createQuotation, deleteQuotation, fetchQuotations, updateQuotation } from '../../../../api/endpoints/quotations';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import type { Quotation } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

const STATUS_COLOR: Record<Quotation['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  draft: 'default',
  sent: 'info',
  accepted: 'success',
  rejected: 'error',
  expired: 'warning',
};

const STATUS_OPTIONS: Quotation['status'][] = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

const numeric = (message: string) => z.string().min(1, message).refine((v) => !Number.isNaN(Number(v)) && Number(v) >= 0, message);

function buildSchema(t: (key: string) => string) {
  return z
    .object({
      customer_id: z.number({ message: t('validation.selectCustomer') }),
      direction: z.enum(['import', 'export']),
      mode: z.enum(['sea', 'air', 'land']),
      issue_date: z.string().min(1, t('validation.issueDateRequired')),
      valid_until: z.string().min(1, t('validation.validUntilRequired')),
      subtotal: numeric(t('validation.subtotalMin')),
      tax_amount: numeric(t('validation.taxMin')),
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
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Quotation | null>(null);
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

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: Quotation['status'] }) => updateQuotation(id, { status }),
    onSuccess: () => {
      invalidateQuotations();
      showToast(t('toast.statusUpdated'));
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
      subtotal: '0',
      tax_amount: '0',
    },
  });

  const onCreate = (values: FormValues) => {
    const total = (Number(values.subtotal) + Number(values.tax_amount)).toFixed(2);
    createMutation.mutate({ ...values, total_amount: total });
  };

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('title')}
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset();
            setDialogOpen(true);
          }}
        >
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
                      <Select
                        size="small"
                        value={quotation.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: quotation.id, status: e.target.value as Quotation['status'] })
                        }
                        renderValue={(value) => (
                          <Chip
                            label={t(`statuses.${value}`)}
                            size="small"
                            color={STATUS_COLOR[value as Quotation['status']]}
                          />
                        )}
                      >
                        {STATUS_OPTIONS.map((status) => (
                          <MenuItem key={status} value={status}>
                            {t(`statuses.${status}`)}
                          </MenuItem>
                        ))}
                      </Select>
                    </TableCell>
                    <TableCell align="right">
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

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
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
              <TextField label={t('form.direction')} select fullWidth defaultValue="export" {...register('direction')}>
                <MenuItem value="import">{t('direction.import')}</MenuItem>
                <MenuItem value="export">{t('direction.export')}</MenuItem>
              </TextField>
              <TextField label={t('form.mode')} select fullWidth defaultValue="sea" {...register('mode')}>
                <MenuItem value="sea">{t('mode.sea')}</MenuItem>
                <MenuItem value="air">{t('mode.air')}</MenuItem>
                <MenuItem value="land">{t('mode.land')}</MenuItem>
              </TextField>
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
              <TextField
                label={t('form.subtotal')}
                fullWidth
                {...register('subtotal')}
                error={!!errors.subtotal}
                helperText={errors.subtotal?.message}
              />
              <TextField
                label={t('form.taxAmount')}
                fullWidth
                {...register('tax_amount')}
                error={!!errors.tax_amount}
                helperText={errors.tax_amount?.message}
              />
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
        title={t('deleteDialog.title')}
        message={t('deleteDialog.message', { number: pendingDelete?.quotation_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
