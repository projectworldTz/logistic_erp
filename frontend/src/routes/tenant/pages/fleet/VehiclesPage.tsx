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
import HistoryIcon from '@mui/icons-material/History';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createVehicle,
  createVehicleLog,
  deleteVehicle,
  deleteVehicleLog,
  fetchVehicleLogs,
  fetchVehicles,
  updateVehicle,
} from '../../../../api/endpoints/fleet';
import type { Vehicle, VehicleLog } from '../../../../types';
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

const LOG_TYPE_OPTIONS: VehicleLog['type'][] = ['maintenance', 'fuel', 'insurance', 'trip'];

const LOG_TYPE_COLOR: Record<VehicleLog['type'], 'info' | 'warning' | 'success' | 'default'> = {
  maintenance: 'warning',
  fuel: 'info',
  insurance: 'success',
  trip: 'default',
};

function buildSchema(t: (key: string) => string) {
  return z.object({
    registration_number: z.string().min(1, t('validation.registrationNumberRequired')).max(20),
    vehicle_type: z.enum(['truck', 'van', 'trailer', 'forklift', 'other']),
    make: z.string().optional(),
    model: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

function buildLogSchema(t: (key: string) => string) {
  return z.object({
    type: z.enum(['maintenance', 'fuel', 'insurance', 'trip']),
    log_date: z.string().min(1, t('logs.validation.dateRequired')),
    description: z.string().min(1, t('logs.validation.descriptionRequired')),
    cost: z.number().optional(),
    odometer_km: z.number().optional(),
    liters: z.number().optional(),
    policy_number: z.string().optional(),
    expiry_date: z.string().optional(),
    origin: z.string().optional(),
    destination: z.string().optional(),
    distance_km: z.number().optional(),
  });
}

type LogFormValues = z.infer<ReturnType<typeof buildLogSchema>>;

export function VehiclesPage() {
  const { t } = useTranslation('fleet');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Vehicle | null>(null);
  const [logsVehicle, setLogsVehicle] = useState<Vehicle | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['fleet', 'vehicles'], queryFn: () => fetchVehicles() });

  const { data: logsData, isLoading: logsLoading } = useQuery({
    queryKey: ['fleet', 'vehicles', logsVehicle?.id, 'logs'],
    queryFn: () => fetchVehicleLogs(logsVehicle!.id),
    enabled: !!logsVehicle,
  });

  const logSchema = buildLogSchema(t);
  const {
    register: registerLog,
    control: logControl,
    handleSubmit: handleLogSubmit,
    reset: resetLog,
    watch: watchLog,
    formState: { errors: logErrors },
  } = useForm<LogFormValues>({
    resolver: zodResolver(logSchema),
    defaultValues: { type: 'fuel', log_date: new Date().toISOString().slice(0, 10) },
  });
  const selectedLogType = watchLog('type');

  const createLogMutation = useMutation({
    mutationFn: (values: LogFormValues) => createVehicleLog(logsVehicle!.id, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['fleet', 'vehicles', logsVehicle?.id, 'logs'] });
      resetLog({ type: 'fuel', log_date: new Date().toISOString().slice(0, 10) });
      showToast(t('logs.toast.created'));
    },
  });

  const deleteLogMutation = useMutation({
    mutationFn: (logId: number) => deleteVehicleLog(logsVehicle!.id, logId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['fleet', 'vehicles', logsVehicle?.id, 'logs'] });
      showToast(t('logs.toast.deleted'));
    },
  });

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
                      <Tooltip title={t('logs.title')}>
                        <IconButton size="small" onClick={() => setLogsVehicle(vehicle)}>
                          <HistoryIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
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

      <Dialog open={!!logsVehicle} onClose={() => setLogsVehicle(null)} fullWidth maxWidth="sm">
        <DialogTitle>{t('logs.dialogTitle', { number: logsVehicle?.registration_number ?? '' })}</DialogTitle>
        <DialogContent>
          <Stack spacing={2}>
            <Stack component="form" spacing={1.5} onSubmit={handleLogSubmit((values) => createLogMutation.mutate(values))}>
              <Stack direction="row" spacing={1.5}>
                <TextField label={t('logs.form.type')} select fullWidth size="small" {...registerLog('type')}>
                  {LOG_TYPE_OPTIONS.map((type) => (
                    <MenuItem key={type} value={type}>
                      {t(`logs.types.${type}`)}
                    </MenuItem>
                  ))}
                </TextField>
                <TextField
                  label={t('logs.form.logDate')}
                  type="date"
                  fullWidth
                  size="small"
                  slotProps={{ inputLabel: { shrink: true } }}
                  {...registerLog('log_date')}
                  error={!!logErrors.log_date}
                />
              </Stack>
              <TextField
                label={t('logs.form.description')}
                fullWidth
                size="small"
                {...registerLog('description')}
                error={!!logErrors.description}
              />
              <Stack direction="row" spacing={1.5}>
                <Controller
                  name="cost"
                  control={logControl}
                  render={({ field }) => (
                    <TextField
                      label={t('logs.form.cost')}
                      type="number"
                      fullWidth
                      size="small"
                      value={field.value ?? ''}
                      onChange={(e) => field.onChange(e.target.value === '' ? undefined : Number(e.target.value))}
                    />
                  )}
                />
                {(selectedLogType === 'maintenance' || selectedLogType === 'fuel') && (
                  <Controller
                    name="odometer_km"
                    control={logControl}
                    render={({ field }) => (
                      <TextField
                        label={t('logs.form.odometerKm')}
                        type="number"
                        fullWidth
                        size="small"
                        value={field.value ?? ''}
                        onChange={(e) => field.onChange(e.target.value === '' ? undefined : Number(e.target.value))}
                      />
                    )}
                  />
                )}
              </Stack>

              {selectedLogType === 'fuel' && (
                <Controller
                  name="liters"
                  control={logControl}
                  render={({ field }) => (
                    <TextField
                      label={t('logs.form.liters')}
                      type="number"
                      fullWidth
                      size="small"
                      value={field.value ?? ''}
                      onChange={(e) => field.onChange(e.target.value === '' ? undefined : Number(e.target.value))}
                    />
                  )}
                />
              )}

              {selectedLogType === 'insurance' && (
                <Stack direction="row" spacing={1.5}>
                  <TextField label={t('logs.form.policyNumber')} fullWidth size="small" {...registerLog('policy_number')} />
                  <TextField
                    label={t('logs.form.expiryDate')}
                    type="date"
                    fullWidth
                    size="small"
                    slotProps={{ inputLabel: { shrink: true } }}
                    {...registerLog('expiry_date')}
                  />
                </Stack>
              )}

              {selectedLogType === 'trip' && (
                <Stack direction="row" spacing={1.5}>
                  <TextField label={t('logs.form.origin')} fullWidth size="small" {...registerLog('origin')} />
                  <TextField label={t('logs.form.destination')} fullWidth size="small" {...registerLog('destination')} />
                  <Controller
                    name="distance_km"
                    control={logControl}
                    render={({ field }) => (
                      <TextField
                        label={t('logs.form.distanceKm')}
                        type="number"
                        fullWidth
                        size="small"
                        value={field.value ?? ''}
                        onChange={(e) => field.onChange(e.target.value === '' ? undefined : Number(e.target.value))}
                      />
                    )}
                  />
                </Stack>
              )}

              <Button type="submit" variant="outlined" size="small" startIcon={<AddIcon />} disabled={createLogMutation.isPending} sx={{ alignSelf: 'flex-start' }}>
                {t('logs.form.addLog')}
              </Button>
            </Stack>

            {logsLoading && <CircularProgress size={24} />}

            {logsData && logsData.data.length === 0 && (
              <Typography variant="body2" color="text.secondary">
                {t('logs.empty')}
              </Typography>
            )}

            {logsData && logsData.data.length > 0 && (
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>{t('logs.table.type')}</TableCell>
                    <TableCell>{t('logs.table.date')}</TableCell>
                    <TableCell>{t('logs.table.description')}</TableCell>
                    <TableCell align="right">{t('logs.table.cost')}</TableCell>
                    <TableCell align="right" />
                  </TableRow>
                </TableHead>
                <TableBody>
                  {logsData.data.map((log) => (
                    <TableRow key={log.id}>
                      <TableCell>
                        <Chip label={t(`logs.types.${log.type}`)} size="small" color={LOG_TYPE_COLOR[log.type]} />
                      </TableCell>
                      <TableCell>{log.log_date}</TableCell>
                      <TableCell>{log.description}</TableCell>
                      <TableCell align="right">{log.cost ? `${log.currency ?? ''} ${log.cost}` : '—'}</TableCell>
                      <TableCell align="right">
                        <IconButton size="small" onClick={() => deleteLogMutation.mutate(log.id)}>
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setLogsVehicle(null)}>{tc('actions.close')}</Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
