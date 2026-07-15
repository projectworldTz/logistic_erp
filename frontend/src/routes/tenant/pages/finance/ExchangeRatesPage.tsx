import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  Card,
  CardContent,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
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
import SwapHorizIcon from '@mui/icons-material/SwapHoriz';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { AxiosError } from 'axios';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  convertCurrency,
  createExchangeRate,
  deleteExchangeRate,
  fetchExchangeRates,
  type ConvertCurrencyResult,
} from '../../../../api/endpoints/currency';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

function buildRateSchema(t: (key: string) => string) {
  return z.object({
    base_currency: z.string().length(3, t('validation.currencyLength')),
    quote_currency: z.string().length(3, t('validation.currencyLength')),
    rate: z.number().positive(t('validation.rateMin')),
    rate_date: z.string().min(1, t('validation.dateRequired')),
  });
}

type RateFormValues = z.infer<ReturnType<typeof buildRateSchema>>;

function buildConvertSchema(t: (key: string) => string) {
  return z.object({
    amount: z.number().min(0, t('validation.amountMin')),
    from: z.string().length(3, t('validation.currencyLength')),
    to: z.string().length(3, t('validation.currencyLength')),
  });
}

type ConvertFormValues = z.infer<ReturnType<typeof buildConvertSchema>>;

export function ExchangeRatesPage() {
  const { t } = useTranslation('exchangeRates');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<{ id: number; label: string } | null>(null);
  const [convertResult, setConvertResult] = useState<ConvertCurrencyResult | null>(null);
  const [convertError, setConvertError] = useState<string | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['finance', 'exchange-rates'], queryFn: () => fetchExchangeRates() });

  const rateSchema = buildRateSchema(t);
  const {
    register: registerRate,
    handleSubmit: handleRateSubmit,
    reset: resetRate,
    formState: { errors: rateErrors },
  } = useForm<RateFormValues>({
    resolver: zodResolver(rateSchema),
    defaultValues: { base_currency: 'USD', quote_currency: 'TZS', rate_date: new Date().toISOString().slice(0, 10) },
  });

  const convertSchema = buildConvertSchema(t);
  const {
    register: registerConvert,
    handleSubmit: handleConvertSubmit,
    formState: { errors: convertErrors },
  } = useForm<ConvertFormValues>({
    resolver: zodResolver(convertSchema),
    defaultValues: { from: 'USD', to: 'TZS' },
  });

  const createMutation = useMutation({
    mutationFn: createExchangeRate,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['finance', 'exchange-rates'] });
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteExchangeRate,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['finance', 'exchange-rates'] });
      setPendingDelete(null);
      showToast(t('toast.deleted'));
    },
  });

  const convertMutation = useMutation({
    mutationFn: convertCurrency,
    onSuccess: (result) => {
      setConvertResult(result);
      setConvertError(null);
    },
    onError: (error: AxiosError<{ message?: string }>) => {
      setConvertResult(null);
      setConvertError(error.response?.data?.message ?? t('convert.error'));
    },
  });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Stack>
          <Typography variant="h5" fontWeight={700}>
            {t('title')}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {t('subtitle')}
          </Typography>
        </Stack>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            resetRate({ base_currency: 'USD', quote_currency: 'TZS', rate_date: new Date().toISOString().slice(0, 10) });
            setDialogOpen(true);
          }}
        >
          {t('newRate')}
        </Button>
      </Stack>

      <Card variant="outlined">
        <CardContent>
          <Stack spacing={2}>
            <Typography variant="subtitle1" fontWeight={700}>
              {t('convert.title')}
            </Typography>
            <Stack
              component="form"
              direction={{ xs: 'column', sm: 'row' }}
              spacing={2}
              alignItems={{ sm: 'center' }}
              onSubmit={handleConvertSubmit((values) => convertMutation.mutate(values))}
            >
              <TextField
                label={t('convert.amount')}
                type="number"
                size="small"
                {...registerConvert('amount', { valueAsNumber: true })}
                error={!!convertErrors.amount}
              />
              <TextField
                label={t('convert.from')}
                size="small"
                sx={{ width: 100 }}
                {...registerConvert('from')}
                error={!!convertErrors.from}
              />
              <SwapHorizIcon color="action" />
              <TextField
                label={t('convert.to')}
                size="small"
                sx={{ width: 100 }}
                {...registerConvert('to')}
                error={!!convertErrors.to}
              />
              <Button type="submit" variant="outlined" disabled={convertMutation.isPending}>
                {t('convert.action')}
              </Button>
            </Stack>
            {convertResult && (
              <Typography variant="body1" fontWeight={600}>
                {t('convert.result', {
                  amount: convertResult.amount,
                  from: convertResult.from,
                  converted: convertResult.converted_amount,
                  to: convertResult.to,
                  rate: convertResult.rate,
                })}
              </Typography>
            )}
            {convertError && (
              <Typography variant="body2" color="error.main">
                {convertError}
              </Typography>
            )}
          </Stack>
        </CardContent>
      </Card>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('empty.title')} description={t('empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('table.pair')}</TableCell>
                  <TableCell align="right">{t('table.rate')}</TableCell>
                  <TableCell>{t('table.date')}</TableCell>
                  <TableCell>{t('table.recordedBy')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((rate) => (
                  <TableRow key={rate.id}>
                    <TableCell>
                      {rate.base_currency} → {rate.quote_currency}
                    </TableCell>
                    <TableCell align="right">{rate.rate}</TableCell>
                    <TableCell>{rate.rate_date.slice(0, 10)}</TableCell>
                    <TableCell>{rate.creator?.name ?? '—'}</TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton
                          size="small"
                          onClick={() =>
                            setPendingDelete({ id: rate.id, label: `${rate.base_currency} → ${rate.quote_currency}` })
                          }
                        >
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
        <DialogTitle>{t('newRate')}</DialogTitle>
        <Stack component="form" onSubmit={handleRateSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('form.baseCurrency')}
                fullWidth
                {...registerRate('base_currency')}
                error={!!rateErrors.base_currency}
                helperText={rateErrors.base_currency?.message}
              />
              <TextField
                label={t('form.quoteCurrency')}
                fullWidth
                {...registerRate('quote_currency')}
                error={!!rateErrors.quote_currency}
                helperText={rateErrors.quote_currency?.message}
              />
              <TextField
                label={t('form.rate')}
                type="number"
                fullWidth
                {...registerRate('rate', { valueAsNumber: true })}
                error={!!rateErrors.rate}
                helperText={rateErrors.rate?.message ?? t('form.rateHelp')}
              />
              <TextField
                label={t('form.rateDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...registerRate('rate_date')}
                error={!!rateErrors.rate_date}
              />
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
        message={t('deleteDialog.message', { pair: pendingDelete?.label ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
