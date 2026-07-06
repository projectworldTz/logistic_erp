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
import { createClearingFile, deleteClearingFile, fetchClearingFiles, updateClearingFile } from '../../../../api/endpoints/clearing';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import type { ClearingFile } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

const STATUS_COLOR: Record<ClearingFile['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  pending: 'default',
  documents_received: 'info',
  under_clearance: 'warning',
  customs_hold: 'error',
  cleared: 'success',
  delivered: 'success',
  cancelled: 'error',
};

const STATUS_OPTIONS: ClearingFile['status'][] = [
  'pending',
  'documents_received',
  'under_clearance',
  'customs_hold',
  'cleared',
  'delivered',
  'cancelled',
];

function buildSchema(t: (key: string) => string) {
  return z.object({
    customer_id: z.number({ message: t('validation.selectCustomer') }),
    direction: z.enum(['import', 'export']),
    mode: z.enum(['sea', 'air', 'land']),
    port_of_loading: z.string().optional(),
    port_of_discharge: z.string().optional(),
    bl_awb_number: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function ClearingFilesPage() {
  const { t } = useTranslation('clearing');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<ClearingFile | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['clearing', 'files'], queryFn: () => fetchClearingFiles() });
  const { data: customers } = useQuery({ queryKey: ['crm', 'customers'], queryFn: () => fetchCustomers() });

  const invalidateFiles = () => queryClient.invalidateQueries({ queryKey: ['clearing', 'files'] });

  const createMutation = useMutation({
    mutationFn: createClearingFile,
    onSuccess: () => {
      invalidateFiles();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: ClearingFile['status'] }) =>
      updateClearingFile(id, { status }),
    onSuccess: () => {
      invalidateFiles();
      showToast(t('toast.statusUpdated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteClearingFile,
    onSuccess: () => {
      invalidateFiles();
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
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { direction: 'import', mode: 'sea' } });

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
          {t('newClearingFile')}
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
                  <TableCell>{t('table.blAwb')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((file) => (
                  <TableRow key={file.id}>
                    <TableCell>{file.reference_no ?? '—'}</TableCell>
                    <TableCell>{file.customer?.company_name ?? '—'}</TableCell>
                    <TableCell>
                      {t(`direction.${file.direction}`)} / {t(`mode.${file.mode}`)}
                    </TableCell>
                    <TableCell>{file.bl_awb_number ?? '—'}</TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={file.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: file.id, status: e.target.value as ClearingFile['status'] })
                        }
                        renderValue={(value) => <Chip label={t(`statuses.${value}`)} size="small" color={STATUS_COLOR[value as ClearingFile['status']]} />}
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
                        <IconButton size="small" onClick={() => setPendingDelete(file)}>
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
              <TextField label={t('form.direction')} select fullWidth defaultValue="import" {...register('direction')}>
                <MenuItem value="import">{t('direction.import')}</MenuItem>
                <MenuItem value="export">{t('direction.export')}</MenuItem>
              </TextField>
              <TextField label={t('form.mode')} select fullWidth defaultValue="sea" {...register('mode')}>
                <MenuItem value="sea">{t('mode.sea')}</MenuItem>
                <MenuItem value="air">{t('mode.air')}</MenuItem>
                <MenuItem value="land">{t('mode.land')}</MenuItem>
              </TextField>
              <TextField label={t('form.portOfLoading')} fullWidth {...register('port_of_loading')} />
              <TextField label={t('form.portOfDischarge')} fullWidth {...register('port_of_discharge')} />
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
        message={t('deleteDialog.message', { number: pendingDelete?.reference_no ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
