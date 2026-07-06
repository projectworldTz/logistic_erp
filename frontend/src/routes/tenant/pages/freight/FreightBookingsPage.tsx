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
import { createFreightBooking, deleteFreightBooking, fetchFreightBookings, updateFreightBooking } from '../../../../api/endpoints/freight';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import type { FreightBooking } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

const STATUS_COLOR: Record<FreightBooking['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  booked: 'default',
  cargo_received: 'info',
  in_transit: 'warning',
  arrived: 'info',
  delivered: 'success',
  cancelled: 'error',
};

const STATUS_OPTIONS: FreightBooking['status'][] = [
  'booked',
  'cargo_received',
  'in_transit',
  'arrived',
  'delivered',
  'cancelled',
];

function buildSchema(t: (key: string) => string) {
  return z.object({
    customer_id: z.number({ message: t('validation.selectCustomer') }),
    direction: z.enum(['import', 'export']),
    mode: z.enum(['sea', 'air', 'land']),
    carrier: z.string().optional(),
    origin_port: z.string().optional(),
    destination_port: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function FreightBookingsPage() {
  const { t } = useTranslation('freight');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<FreightBooking | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['freight', 'bookings'], queryFn: () => fetchFreightBookings() });
  const { data: customers } = useQuery({ queryKey: ['crm', 'customers'], queryFn: () => fetchCustomers() });

  const invalidateBookings = () => queryClient.invalidateQueries({ queryKey: ['freight', 'bookings'] });

  const createMutation = useMutation({
    mutationFn: createFreightBooking,
    onSuccess: () => {
      invalidateBookings();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: FreightBooking['status'] }) =>
      updateFreightBooking(id, { status }),
    onSuccess: () => {
      invalidateBookings();
      showToast(t('toast.statusUpdated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteFreightBooking,
    onSuccess: () => {
      invalidateBookings();
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
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { direction: 'export', mode: 'sea' } });

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
          {t('newBooking')}
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
                  <TableCell>{t('table.directionMode')}</TableCell>
                  <TableCell>{t('table.carrier')}</TableCell>
                  <TableCell>{t('table.route')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((booking) => (
                  <TableRow key={booking.id}>
                    <TableCell>{booking.reference_no ?? '—'}</TableCell>
                    <TableCell>{booking.customer?.company_name ?? '—'}</TableCell>
                    <TableCell>
                      {t(`direction.${booking.direction}`)} / {t(`mode.${booking.mode}`)}
                    </TableCell>
                    <TableCell>{booking.carrier ?? '—'}</TableCell>
                    <TableCell>
                      {booking.origin_port ?? '—'} → {booking.destination_port ?? '—'}
                    </TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={booking.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: booking.id, status: e.target.value as FreightBooking['status'] })
                        }
                        renderValue={(value) => (
                          <Chip label={t(`statuses.${value}`)} size="small" color={STATUS_COLOR[value as FreightBooking['status']]} />
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
                        <IconButton size="small" onClick={() => setPendingDelete(booking)}>
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
              <TextField label={t('form.carrier')} fullWidth {...register('carrier')} />
              <TextField label={t('form.originPort')} fullWidth {...register('origin_port')} />
              <TextField label={t('form.destinationPort')} fullWidth {...register('destination_port')} />
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
