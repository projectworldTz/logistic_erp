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
import { createWarehouseItem, deleteWarehouseItem, fetchWarehouseItems, updateWarehouseItem } from '../../../../api/endpoints/warehouse';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import type { WarehouseItem } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

const STATUS_COLOR: Record<WarehouseItem['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  received: 'default',
  stored: 'info',
  picked: 'warning',
  dispatched: 'success',
  damaged: 'error',
};

const STATUS_OPTIONS: WarehouseItem['status'][] = ['received', 'stored', 'picked', 'dispatched', 'damaged'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    customer_id: z.number({ message: t('validation.selectCustomer') }),
    description: z.string().min(1, t('validation.descriptionRequired')).max(255),
    quantity: z
      .string()
      .min(1, t('validation.quantityRequired'))
      .refine((v) => !Number.isNaN(Number(v)) && Number(v) >= 0, t('validation.quantityMin')),
    unit: z.string().optional(),
    bin_location: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function WarehouseItemsPage() {
  const { t } = useTranslation('warehouse');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<WarehouseItem | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['warehouse', 'items'], queryFn: () => fetchWarehouseItems() });
  const { data: customers } = useQuery({ queryKey: ['crm', 'customers'], queryFn: () => fetchCustomers() });

  const invalidateItems = () => queryClient.invalidateQueries({ queryKey: ['warehouse', 'items'] });

  const createMutation = useMutation({
    mutationFn: createWarehouseItem,
    onSuccess: () => {
      invalidateItems();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: WarehouseItem['status'] }) => updateWarehouseItem(id, { status }),
    onSuccess: () => {
      invalidateItems();
      showToast(t('toast.statusUpdated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteWarehouseItem,
    onSuccess: () => {
      invalidateItems();
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
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { unit: 'pcs' } });

  const onCreate = (values: FormValues) => createMutation.mutate(values);

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
          {t('newItem')}
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
                  <TableCell>{t('table.referenceNo')}</TableCell>
                  <TableCell>{t('table.customer')}</TableCell>
                  <TableCell>{t('table.description')}</TableCell>
                  <TableCell>{t('table.qty')}</TableCell>
                  <TableCell>{t('table.bin')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>{item.reference_no ?? '—'}</TableCell>
                    <TableCell>{item.customer?.company_name ?? '—'}</TableCell>
                    <TableCell>{item.description}</TableCell>
                    <TableCell>
                      {item.quantity} {item.unit}
                    </TableCell>
                    <TableCell>{item.bin_location ?? '—'}</TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={item.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: item.id, status: e.target.value as WarehouseItem['status'] })
                        }
                        renderValue={(value) => (
                          <Chip label={t(`statuses.${value}`)} size="small" color={STATUS_COLOR[value as WarehouseItem['status']]} />
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
                        <IconButton size="small" onClick={() => setPendingDelete(item)}>
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
              <TextField
                label={t('form.description')}
                fullWidth
                {...register('description')}
                error={!!errors.description}
                helperText={errors.description?.message}
              />
              <TextField
                label={t('form.quantity')}
                type="number"
                fullWidth
                {...register('quantity')}
                error={!!errors.quantity}
                helperText={errors.quantity?.message}
              />
              <TextField label={t('form.unit')} fullWidth defaultValue="pcs" {...register('unit')} />
              <TextField label={t('form.binLocation')} fullWidth {...register('bin_location')} />
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
        message={t('deleteDialog.message', { number: pendingDelete?.reference_no ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
