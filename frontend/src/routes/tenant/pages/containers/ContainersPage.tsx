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
import EditIcon from '@mui/icons-material/Edit';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { createContainer, deleteContainer, fetchContainers, updateContainer } from '../../../../api/endpoints/containers';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import type { Container } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

const STATUS_COLOR: Record<Container['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  at_port: 'default',
  in_transit: 'warning',
  at_warehouse: 'info',
  delivered: 'success',
  returned: 'success',
  empty_return: 'error',
};

const STATUS_OPTIONS: Container['status'][] = [
  'at_port',
  'in_transit',
  'at_warehouse',
  'delivered',
  'returned',
  'empty_return',
];

const TYPE_OPTIONS: Container['container_type'][] = [
  'dry_20',
  'dry_40',
  'dry_40_hc',
  'reefer_20',
  'reefer_40',
  'open_top',
  'flat_rack',
  'tank',
];

function buildSchema(t: (key: string) => string) {
  return z.object({
    customer_id: z.number({ message: t('validation.selectCustomer') }),
    container_number: z.string().min(1, t('validation.containerNumberRequired')).max(20),
    container_type: z.enum(['dry_20', 'dry_40', 'dry_40_hc', 'reefer_20', 'reefer_40', 'open_top', 'flat_rack', 'tank']),
    shipping_line: z.string().optional(),
    vessel_name: z.string().optional(),
    voyage_number: z.string().optional(),
    port_of_loading: z.string().optional(),
    port_of_discharge: z.string().optional(),
    seal_number: z.string().optional(),
    location: z.string().optional(),
    gate_in_date: z.string().optional(),
    eta: z.string().optional(),
    ata: z.string().optional(),
    gate_out_date: z.string().optional(),
    empty_return_date: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function ContainersPage() {
  const { t } = useTranslation('containers');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingContainer, setEditingContainer] = useState<Container | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Container | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['containers', 'items'], queryFn: () => fetchContainers() });
  const { data: customers } = useQuery({ queryKey: ['crm', 'customers'], queryFn: () => fetchCustomers() });

  const invalidateContainers = () => queryClient.invalidateQueries({ queryKey: ['containers', 'items'] });

  const createMutation = useMutation({
    mutationFn: createContainer,
    onSuccess: () => {
      invalidateContainers();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: Container['status'] }) => updateContainer(id, { status }),
    onSuccess: () => {
      invalidateContainers();
      showToast(t('toast.statusUpdated'));
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<Container> }) => updateContainer(id, payload),
    onSuccess: () => {
      invalidateContainers();
      setDialogOpen(false);
      setEditingContainer(null);
      showToast(t('toast.updated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteContainer,
    onSuccess: () => {
      invalidateContainers();
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
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { container_type: 'dry_20' } });

  const onSubmit = (values: FormValues) => {
    if (editingContainer) {
      updateMutation.mutate({ id: editingContainer.id, payload: values });
    } else {
      createMutation.mutate(values);
    }
  };

  const openCreateDialog = () => {
    setEditingContainer(null);
    reset({ container_type: 'dry_20' });
    setDialogOpen(true);
  };

  const openEditDialog = (container: Container) => {
    setEditingContainer(container);
    reset({
      customer_id: container.customer_id,
      container_number: container.container_number,
      container_type: container.container_type,
      shipping_line: container.shipping_line ?? '',
      vessel_name: container.vessel_name ?? '',
      voyage_number: container.voyage_number ?? '',
      port_of_loading: container.port_of_loading ?? '',
      port_of_discharge: container.port_of_discharge ?? '',
      seal_number: container.seal_number ?? '',
      location: container.location ?? '',
      gate_in_date: container.gate_in_date ?? '',
      eta: container.eta ?? '',
      ata: container.ata ?? '',
      gate_out_date: container.gate_out_date ?? '',
      empty_return_date: container.empty_return_date ?? '',
    });
    setDialogOpen(true);
  };

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('title')}
        </Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={openCreateDialog}>
          {t('newContainer')}
        </Button>
      </Stack>

      {createMutation.isError && <Chip label={t('errors.duplicateNumber')} color="error" />}

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
                  <TableCell>{t('table.containerNo')}</TableCell>
                  <TableCell>{t('table.customer')}</TableCell>
                  <TableCell>{t('table.type')}</TableCell>
                  <TableCell>{t('table.vesselVoyage')}</TableCell>
                  <TableCell>{t('table.eta')}</TableCell>
                  <TableCell>{t('table.location')}</TableCell>
                  <TableCell>{t('table.gateIn')}</TableCell>
                  <TableCell>{t('table.gateOut')}</TableCell>
                  <TableCell>{t('table.emptyReturn')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((container) => (
                  <TableRow key={container.id}>
                    <TableCell>{container.container_number}</TableCell>
                    <TableCell>{container.customer?.company_name ?? '—'}</TableCell>
                    <TableCell>{t(`types.${container.container_type}`)}</TableCell>
                    <TableCell>
                      {container.vessel_name || container.voyage_number
                        ? `${container.vessel_name ?? '—'} / ${container.voyage_number ?? '—'}`
                        : '—'}
                    </TableCell>
                    <TableCell>{container.eta ?? '—'}</TableCell>
                    <TableCell>{container.location ?? '—'}</TableCell>
                    <TableCell>{container.gate_in_date ?? '—'}</TableCell>
                    <TableCell>{container.gate_out_date ?? '—'}</TableCell>
                    <TableCell>{container.empty_return_date ?? '—'}</TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={container.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: container.id, status: e.target.value as Container['status'] })
                        }
                        renderValue={(value) => (
                          <Chip
                            label={t(`statuses.${value}`)}
                            size="small"
                            color={STATUS_COLOR[value as Container['status']]}
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
                      <Tooltip title={tc('actions.edit')}>
                        <IconButton size="small" onClick={() => openEditDialog(container)}>
                          <EditIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(container)}>
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
        <DialogTitle>{editingContainer ? t('form.editDialogTitle') : t('form.dialogTitle')}</DialogTitle>
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
              <TextField
                label={t('form.containerNumber')}
                fullWidth
                {...register('container_number')}
                error={!!errors.container_number}
                helperText={errors.container_number?.message}
              />
              <TextField label={t('form.type')} select fullWidth defaultValue="dry_20" {...register('container_type')}>
                {TYPE_OPTIONS.map((type) => (
                  <MenuItem key={type} value={type}>
                    {t(`types.${type}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField label={t('form.shippingLine')} fullWidth {...register('shipping_line')} />
              <TextField label={t('form.vesselName')} fullWidth {...register('vessel_name')} />
              <TextField label={t('form.voyageNumber')} fullWidth {...register('voyage_number')} />
              <TextField label={t('form.portOfLoading')} fullWidth {...register('port_of_loading')} />
              <TextField label={t('form.portOfDischarge')} fullWidth {...register('port_of_discharge')} />
              <TextField label={t('form.sealNumber')} fullWidth {...register('seal_number')} />
              <TextField label={t('form.location')} fullWidth {...register('location')} />
              <TextField
                label={t('form.gateInDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('gate_in_date')}
              />
              <TextField
                label={t('form.eta')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('eta')}
              />
              <TextField
                label={t('form.ata')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('ata')}
              />
              <TextField
                label={t('form.gateOutDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('gate_out_date')}
              />
              <TextField
                label={t('form.emptyReturnDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('empty_return_date')}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending || updateMutation.isPending}>
              {editingContainer ? tc('actions.save') : tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('deleteDialog.title')}
        message={t('deleteDialog.message', { number: pendingDelete?.container_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
