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
import AssignmentReturnRoundedIcon from '@mui/icons-material/AssignmentReturnRounded';
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createEmployeeAsset,
  deleteEmployeeAsset,
  fetchEmployeeAssets,
  fetchEmployees,
  returnEmployeeAsset,
} from '../../../../api/endpoints/hr';
import type { EmployeeAsset } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

type AssetType = 'laptop' | 'phone' | 'vehicle' | 'uniform' | 'tool' | 'other';
const ASSET_TYPE_OPTIONS: AssetType[] = ['laptop', 'phone', 'vehicle', 'uniform', 'tool', 'other'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    asset_type: z.enum(ASSET_TYPE_OPTIONS as [AssetType, ...AssetType[]]),
    asset_name: z.string().min(1, t('validation.nameRequired')),
    serial_number: z.string().optional(),
    assigned_date: z.string().min(1, t('validation.dateRequired')),
    condition_at_assignment: z.string().optional(),
    notes: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function EmployeeAssetsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const canManage = usePermission('hr.assets.manage');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<EmployeeAsset | null>(null);
  const [returnTarget, setReturnTarget] = useState<EmployeeAsset | null>(null);
  const [returnCondition, setReturnCondition] = useState('');

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'employee-assets'], queryFn: () => fetchEmployeeAssets() });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'employee-assets'] });

  const createMutation = useMutation({
    mutationFn: createEmployeeAsset,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.assetAssigned'));
    },
  });
  const returnMutation = useMutation({
    mutationFn: ({ id, condition }: { id: number; condition: string }) =>
      returnEmployeeAsset(id, { return_date: new Date().toISOString().slice(0, 10), condition_at_return: condition, status: 'returned' }),
    onSuccess: () => {
      invalidate();
      setReturnTarget(null);
      setReturnCondition('');
      showToast(t('toast.assetReturned'));
    },
  });
  const deleteMutation = useMutation({
    mutationFn: deleteEmployeeAsset,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.assetDeleted'));
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
    defaultValues: { asset_type: 'laptop', assigned_date: new Date().toISOString().slice(0, 10) },
  });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('assets.title')}</Typography>
        {canManage && (
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => {
              reset({ asset_type: 'laptop', assigned_date: new Date().toISOString().slice(0, 10) });
              setDialogOpen(true);
            }}
          >
            {t('assets.newAsset')}
          </Button>
        )}
      </Stack>

      {isLoading && <CircularProgress />}
      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('assets.empty.title')} description={t('assets.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('assets.table.employee')}</TableCell>
                  <TableCell>{t('assets.table.type')}</TableCell>
                  <TableCell>{t('assets.table.name')}</TableCell>
                  <TableCell>{t('assets.table.assignedDate')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((asset) => (
                  <TableRow key={asset.id}>
                    <TableCell>{asset.employee?.name ?? '—'}</TableCell>
                    <TableCell>{t(`assetTypes.${asset.asset_type}`)}</TableCell>
                    <TableCell>{asset.asset_name}</TableCell>
                    <TableCell>{asset.assigned_date.slice(0, 10)}</TableCell>
                    <TableCell>
                      <StatusChip status={asset.status} label={t(`assetStatuses.${asset.status}`)} />
                    </TableCell>
                    <TableCell align="right">
                      {asset.status === 'assigned' && canManage && (
                        <Tooltip title={t('assets.return')}>
                          <IconButton size="small" onClick={() => setReturnTarget(asset)}>
                            <AssignmentReturnRoundedIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                      {canManage && (
                        <Tooltip title={tc('actions.delete')}>
                          <IconButton size="small" onClick={() => setPendingDelete(asset)}>
                            <DeleteIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('assets.newAsset')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('assets.table.employee')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!errors.employee_id}
                  >
                    {employees?.data.map((employee) => (
                      <MenuItem key={employee.id} value={employee.id}>
                        {employee.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField label={t('assets.table.type')} select fullWidth defaultValue="laptop" {...register('asset_type')}>
                {ASSET_TYPE_OPTIONS.map((type) => (
                  <MenuItem key={type} value={type}>
                    {t(`assetTypes.${type}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label={t('assets.table.name')}
                fullWidth
                {...register('asset_name')}
                error={!!errors.asset_name}
                helperText={errors.asset_name?.message}
              />
              <TextField label={t('assets.form.serialNumber')} fullWidth {...register('serial_number')} />
              <TextField
                label={t('assets.table.assignedDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('assigned_date')}
              />
              <TextField label={t('assets.form.conditionAtAssignment')} fullWidth {...register('condition_at_assignment')} />
              <TextField label={t('assets.form.notes')} fullWidth multiline minRows={2} {...register('notes')} />
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

      <Dialog open={!!returnTarget} onClose={() => setReturnTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('assets.return')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('assets.form.conditionAtReturn')}
            fullWidth
            sx={{ mt: 1 }}
            value={returnCondition}
            onChange={(e) => setReturnCondition(e.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setReturnTarget(null)}>{tc('actions.cancel')}</Button>
          <Button
            variant="contained"
            disabled={returnMutation.isPending}
            onClick={() => returnTarget && returnMutation.mutate({ id: returnTarget.id, condition: returnCondition })}
          >
            {t('assets.return')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('assets.deleteDialog.title')}
        message={t('assets.deleteDialog.message', { name: pendingDelete?.asset_name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
