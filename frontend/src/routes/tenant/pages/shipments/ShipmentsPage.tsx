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
  Link,
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
import NotificationsActiveIcon from '@mui/icons-material/NotificationsActive';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router-dom';
import { z } from 'zod';
import {
  checkShipmentSla,
  createShipment,
  deleteShipment,
  fetchShipments,
  updateShipment,
} from '../../../../api/endpoints/shipments';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import { fetchBranches } from '../../../../api/endpoints/dashboard';
import type { Shipment } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useAuthStore } from '../../../../hooks/useAuth';
import { useToast } from '../../../../hooks/useToast';

const STATUS_COLOR: Record<Shipment['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  booked: 'default',
  in_transit: 'warning',
  arrived: 'info',
  cleared: 'success',
  delivered: 'success',
  cancelled: 'error',
};

const STATUS_OPTIONS: Shipment['status'][] = ['booked', 'in_transit', 'arrived', 'cleared', 'delivered', 'cancelled'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    customer_id: z.number({ message: t('validation.selectCustomer') }),
    branch_id: z.number().optional(),
    direction: z.enum(['import', 'export']),
    mode: z.enum(['sea', 'air', 'land']),
    origin_port: z.string().optional(),
    destination_port: z.string().optional(),
    bl_awb_number: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function ShipmentsPage() {
  const { t } = useTranslation('shipments');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Shipment | null>(null);
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];
  const canManageShipments = permissions.includes('shipments.items.manage');
  const { data, isLoading } = useQuery({ queryKey: ['shipments', 'items'], queryFn: () => fetchShipments() });
  const { data: customers } = useQuery({ queryKey: ['crm', 'customers'], queryFn: () => fetchCustomers() });
  const { data: branches } = useQuery({ queryKey: ['tenant', 'branches'], queryFn: fetchBranches });

  const invalidateShipments = () => queryClient.invalidateQueries({ queryKey: ['shipments', 'items'] });

  const createMutation = useMutation({
    mutationFn: createShipment,
    onSuccess: () => {
      invalidateShipments();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: Shipment['status'] }) => updateShipment(id, { status }),
    onSuccess: () => {
      invalidateShipments();
      showToast(t('toast.statusUpdated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteShipment,
    onSuccess: () => {
      invalidateShipments();
      setPendingDelete(null);
      showToast(t('toast.deleted'));
    },
  });

  const slaCheckMutation = useMutation({
    mutationFn: checkShipmentSla,
    onSuccess: (result) => {
      invalidateShipments();
      showToast(t('toast.slaChecked', { delayed: result.delayed_alerted, nearDeadline: result.near_deadline_alerted }));
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
        <Stack direction="row" spacing={1.5}>
          {canManageShipments && (
            <Button
              variant="outlined"
              startIcon={<NotificationsActiveIcon />}
              disabled={slaCheckMutation.isPending}
              onClick={() => slaCheckMutation.mutate()}
            >
              {t('checkSla')}
            </Button>
          )}
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => {
              reset();
              setDialogOpen(true);
            }}
          >
            {t('newShipment')}
          </Button>
        </Stack>
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
                  <TableCell>{t('table.shipmentNo')}</TableCell>
                  <TableCell>{t('table.customer')}</TableCell>
                  <TableCell>{t('table.directionMode')}</TableCell>
                  <TableCell>{t('table.route')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((shipment) => (
                  <TableRow key={shipment.id}>
                    <TableCell>
                      <Stack direction="row" spacing={1} alignItems="center">
                        <Link component={RouterLink} to={`/app/shipments/${shipment.id}`} underline="hover">
                          {shipment.shipment_number ?? '—'}
                        </Link>
                        {shipment.is_at_risk && <Chip label={t('riskBadge.atRisk')} size="small" color="error" variant="outlined" />}
                      </Stack>
                    </TableCell>
                    <TableCell>{shipment.customer?.company_name ?? '—'}</TableCell>
                    <TableCell>
                      {t(`direction.${shipment.direction}`)} / {t(`mode.${shipment.mode}`)}
                    </TableCell>
                    <TableCell>
                      {shipment.origin_port ?? '—'} → {shipment.destination_port ?? '—'}
                    </TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={shipment.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: shipment.id, status: e.target.value as Shipment['status'] })
                        }
                        renderValue={(value) => (
                          <Chip label={t(`statuses.${value}`)} size="small" color={STATUS_COLOR[value as Shipment['status']]} />
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
                        <IconButton size="small" onClick={() => setPendingDelete(shipment)}>
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
              <TextField label={t('form.direction')} select fullWidth defaultValue="export" {...register('direction')}>
                <MenuItem value="import">{t('direction.import')}</MenuItem>
                <MenuItem value="export">{t('direction.export')}</MenuItem>
              </TextField>
              <TextField label={t('form.mode')} select fullWidth defaultValue="sea" {...register('mode')}>
                <MenuItem value="sea">{t('mode.sea')}</MenuItem>
                <MenuItem value="air">{t('mode.air')}</MenuItem>
                <MenuItem value="land">{t('mode.land')}</MenuItem>
              </TextField>
              <TextField label={t('form.originPort')} fullWidth {...register('origin_port')} />
              <TextField label={t('form.destinationPort')} fullWidth {...register('destination_port')} />
              <TextField label={t('form.blAwbNumber')} fullWidth {...register('bl_awb_number')} />
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
        message={t('deleteDialog.message', { number: pendingDelete?.shipment_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
