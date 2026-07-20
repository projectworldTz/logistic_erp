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
import PictureAsPdfIcon from '@mui/icons-material/PictureAsPdf';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createInvoice,
  deleteInvoice,
  downloadInvoicePdf,
  fetchInvoices,
  updateInvoice,
} from '../../../../api/endpoints/finance';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import { fetchShipments } from '../../../../api/endpoints/shipments';
import { fetchBranches } from '../../../../api/endpoints/dashboard';
import type { Invoice } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { useToast } from '../../../../hooks/useToast';

const STATUS_OPTIONS: Invoice['status'][] = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];

const numeric = (message: string) => z.string().min(1, message).refine((v) => !Number.isNaN(Number(v)) && Number(v) >= 0, message);

function buildSchema(t: (key: string) => string) {
  return z
    .object({
      customer_id: z.number({ message: t('validation.selectCustomer') }),
      branch_id: z.number().optional(),
      shipment_id: z.number().optional(),
      issue_date: z.string().min(1, t('validation.issueDateRequired')),
      due_date: z.string().min(1, t('validation.dueDateRequired')),
      subtotal: numeric(t('validation.subtotalMin')),
      tax_amount: numeric(t('validation.taxMin')),
    })
    .refine((data) => data.due_date >= data.issue_date, {
      message: t('validation.dueDateAfterIssue'),
      path: ['due_date'],
    });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function InvoicesPage() {
  const { t } = useTranslation('finance');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Invoice | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['finance', 'invoices'], queryFn: () => fetchInvoices() });
  const { data: customers } = useQuery({ queryKey: ['crm', 'customers'], queryFn: () => fetchCustomers() });
  const { data: shipments } = useQuery({ queryKey: ['shipments', 'items'], queryFn: () => fetchShipments() });
  const { data: branches } = useQuery({ queryKey: ['tenant', 'branches'], queryFn: fetchBranches });

  const invalidateInvoices = () => queryClient.invalidateQueries({ queryKey: ['finance', 'invoices'] });

  const createMutation = useMutation({
    mutationFn: createInvoice,
    onSuccess: () => {
      invalidateInvoices();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: Invoice['status'] }) => updateInvoice(id, { status }),
    onSuccess: () => {
      invalidateInvoices();
      showToast(t('toast.statusUpdated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteInvoice,
    onSuccess: () => {
      invalidateInvoices();
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
      issue_date: new Date().toISOString().slice(0, 10),
      due_date: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
      subtotal: '0',
      tax_amount: '0',
    },
  });

  const onCreate = (values: FormValues) => {
    const total = (Number(values.subtotal) + Number(values.tax_amount)).toFixed(2);
    createMutation.mutate({ ...values, total_amount: total });
  };

  const handleDownloadPdf = async (invoice: Invoice) => {
    const blob = await downloadInvoicePdf(invoice.id);
    const prefix = invoice.status === 'paid' ? 'receipt' : 'invoice';
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${prefix}-${invoice.invoice_number ?? invoice.id}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
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
          {t('newInvoice')}
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
                  <TableCell>{t('table.invoiceNo')}</TableCell>
                  <TableCell>{t('table.customer')}</TableCell>
                  <TableCell>{t('table.dueDate')}</TableCell>
                  <TableCell>{t('table.total')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((invoice) => (
                  <TableRow key={invoice.id}>
                    <TableCell>{invoice.invoice_number ?? '—'}</TableCell>
                    <TableCell>{invoice.customer?.company_name ?? '—'}</TableCell>
                    <TableCell>{invoice.due_date}</TableCell>
                    <TableCell>
                      {invoice.currency} {invoice.total_amount}
                    </TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={invoice.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: invoice.id, status: e.target.value as Invoice['status'] })
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
                      <Tooltip title={invoice.status === 'paid' ? t('actions.downloadReceipt') : t('actions.downloadInvoice')}>
                        <IconButton size="small" onClick={() => handleDownloadPdf(invoice)}>
                          <PictureAsPdfIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(invoice)}>
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
                    <MenuItem value="" disabled>
                      {tc('labels.loading')}
                    </MenuItem>
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
              <Controller
                name="branch_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('form.branch')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : undefined)}
                  >
                    <MenuItem value="">{t('form.noBranch')}</MenuItem>
                    {branches?.map((branch) => (
                      <MenuItem key={branch.id} value={branch.id}>
                        {branch.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
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
                label={t('form.dueDate')}
                type="date"
                fullWidth
                InputLabelProps={{ shrink: true }}
                {...register('due_date')}
                error={!!errors.due_date}
                helperText={errors.due_date?.message}
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
        message={t('deleteDialog.message', { number: pendingDelete?.invoice_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
