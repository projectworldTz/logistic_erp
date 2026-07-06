import {
  Alert,
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
import PlaylistAddCheckIcon from '@mui/icons-material/PlaylistAddCheck';
import BlockIcon from '@mui/icons-material/Block';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo, useState } from 'react';
import { useForm, useFieldArray, useWatch, Controller } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import {
  createJournalEntry,
  deleteJournalEntry,
  fetchJournalEntries,
  postJournalEntry,
  voidJournalEntry,
} from '../../../../api/endpoints/accounting';
import { fetchAccounts } from '../../../../api/endpoints/accounting';
import type { JournalEntry } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';
import { AccountingTabs } from './AccountingTabs';

const STATUS_COLOR: Record<JournalEntry['status'], 'default' | 'warning' | 'success'> = {
  draft: 'default',
  posted: 'success',
  voided: 'warning',
};

interface LineFormValues {
  account_id: number | null;
  debit: string;
  credit: string;
}

interface FormValues {
  entry_date: string;
  description: string;
  lines: LineFormValues[];
}

export function JournalEntriesPage() {
  const { t } = useTranslation('accounting');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<JournalEntry | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['accounting', 'journal-entries'], queryFn: () => fetchJournalEntries() });
  const { data: accounts } = useQuery({ queryKey: ['accounting', 'accounts'], queryFn: () => fetchAccounts() });

  const invalidateEntries = () => queryClient.invalidateQueries({ queryKey: ['accounting', 'journal-entries'] });

  const createMutation = useMutation({
    mutationFn: createJournalEntry,
    onSuccess: () => {
      invalidateEntries();
      setDialogOpen(false);
      showToast(t('journalEntries.toast.created'));
    },
  });

  const postMutation = useMutation({ mutationFn: postJournalEntry, onSuccess: invalidateEntries });
  const voidMutation = useMutation({ mutationFn: voidJournalEntry, onSuccess: invalidateEntries });
  const deleteMutation = useMutation({
    mutationFn: deleteJournalEntry,
    onSuccess: () => {
      invalidateEntries();
      setPendingDelete(null);
      showToast(t('journalEntries.toast.deleted'));
    },
  });

  const { register, control, handleSubmit, reset } = useForm<FormValues>({
    defaultValues: {
      entry_date: new Date().toISOString().slice(0, 10),
      description: '',
      lines: [
        { account_id: null, debit: '', credit: '' },
        { account_id: null, debit: '', credit: '' },
      ],
    },
  });

  const { fields, append, remove } = useFieldArray({ control, name: 'lines' });
  const watchedLines = useWatch({ control, name: 'lines' });

  const { totalDebit, totalCredit, balanced } = useMemo(() => {
    const lines = watchedLines ?? [];
    const totalDebit = lines.reduce((sum, l) => sum + (Number(l.debit) || 0), 0);
    const totalCredit = lines.reduce((sum, l) => sum + (Number(l.credit) || 0), 0);
    return { totalDebit, totalCredit, balanced: totalDebit > 0 && totalDebit === totalCredit };
  }, [watchedLines]);

  const onCreate = (values: FormValues) => {
    createMutation.mutate({
      entry_date: values.entry_date,
      description: values.description || undefined,
      lines: values.lines
        .filter((l) => l.account_id)
        .map((l) => ({
          account_id: l.account_id as number,
          debit: Number(l.debit) || 0,
          credit: Number(l.credit) || 0,
        })),
    });
  };

  return (
    <Stack spacing={3}>
      <AccountingTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('journalEntries.title')}
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset();
            setDialogOpen(true);
          }}
        >
          {t('journalEntries.newEntry')}
        </Button>
      </Stack>

      {createMutation.isError && (
        <Alert severity="error">{t('journalEntries.errors.saveFailed')}</Alert>
      )}

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && (
        <EmptyState title={t('journalEntries.empty.title')} description={t('journalEntries.empty.description')} />
      )}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('journalEntries.table.entryNo')}</TableCell>
                  <TableCell>{tc('labels.date')}</TableCell>
                  <TableCell>{tc('labels.description')}</TableCell>
                  <TableCell>{tc('labels.total')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((entry) => (
                  <TableRow key={entry.id}>
                    <TableCell>{entry.entry_number ?? '—'}</TableCell>
                    <TableCell>{entry.entry_date}</TableCell>
                    <TableCell>{entry.description ?? '—'}</TableCell>
                    <TableCell>{entry.total_debit ?? '—'}</TableCell>
                    <TableCell>
                      <Chip label={t(`journalEntries.statuses.${entry.status}`)} size="small" color={STATUS_COLOR[entry.status]} />
                    </TableCell>
                    <TableCell align="right">
                      {entry.status === 'draft' && (
                        <>
                          <Tooltip title={t('journalEntries.actions.post')}>
                            <IconButton size="small" onClick={() => postMutation.mutate(entry.id)}>
                              <PlaylistAddCheckIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={tc('actions.delete')}>
                            <IconButton size="small" onClick={() => setPendingDelete(entry)}>
                              <DeleteIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                      {entry.status === 'posted' && (
                        <Tooltip title={t('journalEntries.actions.void')}>
                          <IconButton size="small" onClick={() => voidMutation.mutate(entry.id)}>
                            <BlockIcon fontSize="small" />
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

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>{t('journalEntries.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('journalEntries.form.entryDate')}
                type="date"
                fullWidth
                InputLabelProps={{ shrink: true }}
                {...register('entry_date')}
              />
              <TextField label={t('journalEntries.form.description')} fullWidth {...register('description')} />

              <Typography variant="subtitle2">{t('journalEntries.form.lines')}</Typography>
              {fields.map((field, index) => (
                <Stack key={field.id} direction="row" spacing={1} alignItems="center">
                  <Controller
                    name={`lines.${index}.account_id`}
                    control={control}
                    render={({ field: accountField }) => (
                      <TextField
                        label={t('journalEntries.form.account')}
                        select
                        size="small"
                        sx={{ flex: 2 }}
                        value={accountField.value ?? ''}
                        onChange={(e) => accountField.onChange(Number(e.target.value))}
                      >
                        {accounts?.data.map((account) => (
                          <MenuItem key={account.id} value={account.id}>
                            {account.code} — {account.name}
                          </MenuItem>
                        ))}
                      </TextField>
                    )}
                  />
                  <TextField
                    label={t('journalEntries.form.debit')}
                    size="small"
                    sx={{ flex: 1 }}
                    {...register(`lines.${index}.debit`)}
                  />
                  <TextField
                    label={t('journalEntries.form.credit')}
                    size="small"
                    sx={{ flex: 1 }}
                    {...register(`lines.${index}.credit`)}
                  />
                  <IconButton size="small" onClick={() => remove(index)} disabled={fields.length <= 2}>
                    <DeleteIcon fontSize="small" />
                  </IconButton>
                </Stack>
              ))}
              <Button size="small" onClick={() => append({ account_id: null, debit: '', credit: '' })}>
                {t('journalEntries.form.addLine')}
              </Button>

              <Alert severity={balanced ? 'success' : 'warning'}>
                {t('journalEntries.form.balanceSummary', {
                  debit: totalDebit.toFixed(2),
                  credit: totalCredit.toFixed(2),
                })}
                {balanced ? ` — ${t('journalEntries.form.balanced')}` : ` — ${t('journalEntries.form.mustBalance')}`}
              </Alert>
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending || !balanced}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('journalEntries.deleteDialog.title')}
        message={t('journalEntries.deleteDialog.message', { number: pendingDelete?.entry_number ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
