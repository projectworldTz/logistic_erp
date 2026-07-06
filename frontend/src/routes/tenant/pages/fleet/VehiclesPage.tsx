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
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { createVehicle, deleteVehicle, fetchVehicles, updateVehicle } from '../../../../api/endpoints/fleet';
import type { Vehicle } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

const STATUS_COLOR: Record<Vehicle['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  active: 'success',
  in_maintenance: 'warning',
  out_of_service: 'error',
  retired: 'default',
};

const STATUS_OPTIONS: Vehicle['status'][] = ['active', 'in_maintenance', 'out_of_service', 'retired'];

const TYPE_OPTIONS: Vehicle['vehicle_type'][] = ['truck', 'van', 'trailer', 'forklift', 'other'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    registration_number: z.string().min(1, t('validation.registrationNumberRequired')).max(20),
    vehicle_type: z.enum(['truck', 'van', 'trailer', 'forklift', 'other']),
    make: z.string().optional(),
    model: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function VehiclesPage() {
  const { t } = useTranslation('fleet');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Vehicle | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['fleet', 'vehicles'], queryFn: () => fetchVehicles() });

  const invalidateVehicles = () => queryClient.invalidateQueries({ queryKey: ['fleet', 'vehicles'] });

  const createMutation = useMutation({
    mutationFn: createVehicle,
    onSuccess: () => {
      invalidateVehicles();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: Vehicle['status'] }) => updateVehicle(id, { status }),
    onSuccess: () => {
      invalidateVehicles();
      showToast(t('toast.statusUpdated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteVehicle,
    onSuccess: () => {
      invalidateVehicles();
      setPendingDelete(null);
      showToast(t('toast.deleted'));
    },
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { vehicle_type: 'truck' } });

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
          {t('newVehicle')}
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
                  <TableCell>{t('table.registrationNo')}</TableCell>
                  <TableCell>{t('table.type')}</TableCell>
                  <TableCell>{t('table.makeModel')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((vehicle) => (
                  <TableRow key={vehicle.id}>
                    <TableCell>{vehicle.registration_number}</TableCell>
                    <TableCell>{t(`types.${vehicle.vehicle_type}`)}</TableCell>
                    <TableCell>
                      {vehicle.make ?? '—'} {vehicle.model ?? ''}
                    </TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={vehicle.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: vehicle.id, status: e.target.value as Vehicle['status'] })
                        }
                        renderValue={(value) => (
                          <Chip label={t(`statuses.${value}`)} size="small" color={STATUS_COLOR[value as Vehicle['status']]} />
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
                        <IconButton size="small" onClick={() => setPendingDelete(vehicle)}>
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
              <TextField
                label={t('form.registrationNumber')}
                fullWidth
                {...register('registration_number')}
                error={!!errors.registration_number}
                helperText={errors.registration_number?.message}
              />
              <TextField label={t('form.type')} select fullWidth defaultValue="truck" {...register('vehicle_type')}>
                {TYPE_OPTIONS.map((type) => (
                  <MenuItem key={type} value={type}>
                    {t(`types.${type}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField label={t('form.make')} fullWidth {...register('make')} />
              <TextField label={t('form.model')} fullWidth {...register('model')} />
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
        message={t('deleteDialog.message', { number: pendingDelete?.registration_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
