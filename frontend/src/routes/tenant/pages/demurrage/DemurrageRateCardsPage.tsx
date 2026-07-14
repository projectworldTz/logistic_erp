import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  Checkbox,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
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
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useFieldArray, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createDemurrageRateCard,
  deleteDemurrageRateCard,
  fetchDemurrageRateCards,
} from '../../../../api/endpoints/demurrage';
import type { DemurrageRateCard } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';
import { DemurrageTabs } from './DemurrageTabs';

const TYPE_OPTIONS = ['dry_20', 'dry_40', 'dry_40_hc', 'reefer_20', 'reefer_40', 'open_top', 'flat_rack', 'tank'] as const;

function buildSchema(t: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    container_type: z.string().optional(),
    free_days: z.number({ message: t('validation.freeDaysRequired') }).min(0),
    currency: z.string().min(3).max(3).optional(),
    is_default: z.boolean().optional(),
    tiers: z
      .array(
        z.object({
          from_day: z.number().min(1, t('validation.fromDayRequired')),
          to_day: z.number().nullable(),
          daily_rate: z.number().min(0, t('validation.dailyRateRequired')),
        }),
      )
      .min(1, t('validation.atLeastOneTier')),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function DemurrageRateCardsPage() {
  const { t } = useTranslation('demurrage');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<DemurrageRateCard | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['demurrage', 'rate-cards'], queryFn: fetchDemurrageRateCards });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['demurrage', 'rate-cards'] });

  const createMutation = useMutation({
    mutationFn: createDemurrageRateCard,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.rateCardCreated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteDemurrageRateCard,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.rateCardDeleted'));
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
      currency: 'TZS',
      free_days: 5,
      is_default: false,
      tiers: [{ from_day: 1, to_day: null, daily_rate: 0 }],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: 'tiers' });

  const onCreate = (values: FormValues) =>
    createMutation.mutate({
      ...values,
      container_type: values.container_type || null,
      tiers: values.tiers.map((tier) => ({
        from_day: tier.from_day,
        to_day: tier.to_day ?? null,
        daily_rate: tier.daily_rate,
      })),
    });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <DemurrageTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('rateCards.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset();
            setDialogOpen(true);
          }}
        >
          {t('rateCards.newRateCard')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && (
        <EmptyState title={t('rateCards.empty.title')} description={t('rateCards.empty.description')} />
      )}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('rateCards.table.name')}</TableCell>
                  <TableCell>{t('rateCards.table.containerType')}</TableCell>
                  <TableCell align="right">{t('rateCards.table.freeDays')}</TableCell>
                  <TableCell>{t('rateCards.table.currency')}</TableCell>
                  <TableCell>{t('rateCards.table.tiers')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((rateCard) => (
                  <TableRow key={rateCard.id}>
                    <TableCell>
                      {rateCard.name}
                      {rateCard.is_default && (
                        <Chip label={t('rateCards.defaultChip')} size="small" sx={{ ml: 1 }} color="primary" />
                      )}
                    </TableCell>
                    <TableCell>{rateCard.container_type ? t(`types.${rateCard.container_type}`) : t('rateCards.allTypes')}</TableCell>
                    <TableCell align="right">{rateCard.free_days}</TableCell>
                    <TableCell>{rateCard.currency}</TableCell>
                    <TableCell>
                      {rateCard.tiers
                        .map((tier) => `${tier.from_day}-${tier.to_day ?? '∞'}: ${tier.daily_rate}/day`)
                        .join(', ')}
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(rateCard)}>
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

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>{t('rateCards.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('rateCards.form.name')}
                fullWidth
                {...register('name')}
                error={!!errors.name}
                helperText={errors.name?.message}
              />
              <TextField label={t('rateCards.form.containerType')} select fullWidth defaultValue="" {...register('container_type')}>
                <MenuItem value="">{t('rateCards.allTypes')}</MenuItem>
                {TYPE_OPTIONS.map((type) => (
                  <MenuItem key={type} value={type}>
                    {t(`types.${type}`)}
                  </MenuItem>
                ))}
              </TextField>
              <Stack direction="row" spacing={2}>
                <Controller
                  name="free_days"
                  control={control}
                  render={({ field }) => (
                    <TextField
                      label={t('rateCards.form.freeDays')}
                      type="number"
                      fullWidth
                      value={field.value ?? ''}
                      onChange={(e) => field.onChange(Number(e.target.value))}
                      error={!!errors.free_days}
                      helperText={errors.free_days?.message}
                    />
                  )}
                />
                <TextField label={t('rateCards.form.currency')} fullWidth {...register('currency')} />
              </Stack>
              <FormControlLabel
                control={<Controller name="is_default" control={control} render={({ field }) => <Checkbox checked={!!field.value} onChange={(e) => field.onChange(e.target.checked)} />} />}
                label={t('rateCards.form.isDefault')}
              />

              <Typography variant="subtitle2">{t('rateCards.form.tiersTitle')}</Typography>
              {fields.map((field, index) => (
                <Stack direction="row" spacing={1} alignItems="center" key={field.id}>
                  <Controller
                    name={`tiers.${index}.from_day`}
                    control={control}
                    render={({ field: f }) => (
                      <TextField
                        label={t('rateCards.form.fromDay')}
                        type="number"
                        size="small"
                        value={f.value ?? ''}
                        onChange={(e) => f.onChange(Number(e.target.value))}
                      />
                    )}
                  />
                  <Controller
                    name={`tiers.${index}.to_day`}
                    control={control}
                    render={({ field: f }) => (
                      <TextField
                        label={t('rateCards.form.toDay')}
                        type="number"
                        size="small"
                        value={f.value ?? ''}
                        onChange={(e) => f.onChange(e.target.value === '' ? null : Number(e.target.value))}
                        placeholder={t('rateCards.form.openEnded')}
                      />
                    )}
                  />
                  <Controller
                    name={`tiers.${index}.daily_rate`}
                    control={control}
                    render={({ field: f }) => (
                      <TextField
                        label={t('rateCards.form.dailyRate')}
                        type="number"
                        size="small"
                        value={f.value ?? ''}
                        onChange={(e) => f.onChange(Number(e.target.value))}
                      />
                    )}
                  />
                  <IconButton size="small" onClick={() => remove(index)} disabled={fields.length === 1}>
                    <DeleteIcon fontSize="small" />
                  </IconButton>
                </Stack>
              ))}
              <Button
                size="small"
                startIcon={<AddIcon />}
                onClick={() => append({ from_day: 1, to_day: null, daily_rate: 0 })}
                sx={{ alignSelf: 'flex-start' }}
              >
                {t('rateCards.form.addTier')}
              </Button>
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
        title={t('rateCards.deleteDialog.title')}
        message={t('rateCards.deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
