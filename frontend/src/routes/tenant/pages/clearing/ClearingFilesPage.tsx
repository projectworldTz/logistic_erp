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
import EditIcon from '@mui/icons-material/Edit';
import QrCode2Icon from '@mui/icons-material/QrCode2';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createClearingFile,
  deleteClearingFile,
  fetchClearingFiles,
  fetchReleaseOrderQr,
  updateClearingFile,
} from '../../../../api/endpoints/clearing';
import { fetchCustomers } from '../../../../api/endpoints/crm';
import type { ClearingFile } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { TrackingQrCode } from '../../../../components/common/TrackingQrCode';
import { useToast } from '../../../../hooks/useToast';

const STATUS_OPTIONS: ClearingFile['status'][] = [
  'pending',
  'documents_received',
  'under_clearance',
  'customs_hold',
  'cleared',
  'delivered',
  'cancelled',
];

const ASSESSMENT_STATUS_OPTIONS: ClearingFile['assessment_status'][] = ['pending', 'assessed', 'objected', 'released'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    customer_id: z.number({ message: t('validation.selectCustomer') }),
    direction: z.enum(['import', 'export']),
    mode: z.enum(['sea', 'air', 'land']),
    port_of_loading: z.string().optional(),
    port_of_discharge: z.string().optional(),
    bl_awb_number: z.string().optional(),
    declaration_number: z.string().optional(),
    sad_number: z.string().optional(),
    hs_code: z.string().optional(),
    customs_value: z.string().optional(),
    duty_amount: z.string().optional(),
    vat_amount: z.string().optional(),
    release_order_number: z.string().optional(),
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
  const [editingFile, setEditingFile] = useState<ClearingFile | null>(null);
  const [pendingDelete, setPendingDelete] = useState<ClearingFile | null>(null);
  const [qrFile, setQrFile] = useState<ClearingFile | null>(null);
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

  const assessmentStatusMutation = useMutation({
    mutationFn: ({ id, assessment_status }: { id: number; assessment_status: ClearingFile['assessment_status'] }) =>
      updateClearingFile(id, { assessment_status }),
    onSuccess: () => {
      invalidateFiles();
      showToast(t('toast.assessmentStatusUpdated'));
    },
  });

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<ClearingFile> }) => updateClearingFile(id, payload),
    onSuccess: () => {
      invalidateFiles();
      setDialogOpen(false);
      setEditingFile(null);
      showToast(t('toast.updated'));
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

  const onSubmit = (values: FormValues) => {
    if (editingFile) {
      updateMutation.mutate({ id: editingFile.id, payload: values });
    } else {
      createMutation.mutate(values);
    }
  };

  const openEditDialog = (file: ClearingFile) => {
    setEditingFile(file);
    reset({
      customer_id: file.customer_id,
      direction: file.direction,
      mode: file.mode,
      port_of_loading: file.port_of_loading ?? '',
      port_of_discharge: file.port_of_discharge ?? '',
      bl_awb_number: file.bl_awb_number ?? '',
      declaration_number: file.declaration_number ?? '',
      sad_number: file.sad_number ?? '',
      hs_code: file.hs_code ?? '',
      customs_value: file.customs_value ?? '',
      duty_amount: file.duty_amount ?? '',
      vat_amount: file.vat_amount ?? '',
      release_order_number: file.release_order_number ?? '',
    });
    setDialogOpen(true);
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
            setEditingFile(null);
            reset({ direction: 'import', mode: 'sea' });
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
                  <TableCell>{t('table.sadNumber')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell>{t('table.assessmentStatus')}</TableCell>
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
                    <TableCell>{file.sad_number ?? '—'}</TableCell>
                    <TableCell>
                      <Select
                        size="small"
                        value={file.status}
                        onChange={(e) =>
                          statusMutation.mutate({ id: file.id, status: e.target.value as ClearingFile['status'] })
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
                    <TableCell>
                      <Select
                        size="small"
                        value={file.assessment_status}
                        onChange={(e) =>
                          assessmentStatusMutation.mutate({
                            id: file.id,
                            assessment_status: e.target.value as ClearingFile['assessment_status'],
                          })
                        }
                        renderValue={(value) => <StatusChip status={value as string} label={t(`assessmentStatuses.${value}`)} />}
                      >
                        {ASSESSMENT_STATUS_OPTIONS.map((status) => (
                          <MenuItem key={status} value={status}>
                            {t(`assessmentStatuses.${status}`)}
                          </MenuItem>
                        ))}
                      </Select>
                    </TableCell>
                    <TableCell align="right">
                      {file.release_order_number && (
                        <Tooltip title={t('actions.viewReleaseOrderQr')}>
                          <IconButton size="small" onClick={() => setQrFile(file)}>
                            <QrCode2Icon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                      <Tooltip title={tc('actions.edit')}>
                        <IconButton size="small" onClick={() => openEditDialog(file)}>
                          <EditIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
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
        <DialogTitle>{editingFile ? t('form.editDialogTitle') : t('form.dialogTitle')}</DialogTitle>
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
                    disabled={!!editingFile}
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
              <TextField label={t('form.declarationNumber')} fullWidth {...register('declaration_number')} />
              <TextField label={t('form.sadNumber')} fullWidth {...register('sad_number')} />
              <TextField label={t('form.hsCode')} fullWidth {...register('hs_code')} />
              <TextField label={t('form.customsValue')} type="number" fullWidth {...register('customs_value')} />
              <TextField label={t('form.dutyAmount')} type="number" fullWidth {...register('duty_amount')} />
              <TextField label={t('form.vatAmount')} type="number" fullWidth {...register('vat_amount')} />
              <TextField label={t('form.releaseOrderNumber')} fullWidth {...register('release_order_number')} />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending || updateMutation.isPending}>
              {editingFile ? tc('actions.save') : tc('actions.create')}
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

      <Dialog open={!!qrFile} onClose={() => setQrFile(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('releaseOrderQrDialog.title')}</DialogTitle>
        <DialogContent>
          <Stack alignItems="center">
            <TrackingQrCode
              queryKey={['clearing', 'files', qrFile?.id, 'release-order-qr']}
              fetchQr={() => fetchReleaseOrderQr(qrFile!.id)}
              trackingCode={qrFile?.release_order_number ?? null}
              alt={t('releaseOrderQrDialog.alt')}
              caption={t('releaseOrderQrDialog.caption')}
              downloadLabel={t('releaseOrderQrDialog.download')}
              filenamePrefix="release-order-qr"
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setQrFile(null)}>{tc('actions.close')}</Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
