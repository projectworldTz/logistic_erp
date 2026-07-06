import { zodResolver } from '@hookform/resolvers/zod';
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
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { createAccount, deleteAccount, fetchAccounts } from '../../../../api/endpoints/accounting';
import type { Account } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';
import { AccountingTabs } from './AccountingTabs';

const TYPE_COLOR: Record<Account['type'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  asset: 'info',
  liability: 'warning',
  equity: 'default',
  revenue: 'success',
  expense: 'error',
};

const TYPE_OPTIONS: Account['type'][] = ['asset', 'liability', 'equity', 'revenue', 'expense'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    code: z.string().min(1, t('accounts.validation.codeRequired')).max(20),
    name: z.string().min(1, t('accounts.validation.nameRequired')).max(255),
    type: z.enum(['asset', 'liability', 'equity', 'revenue', 'expense']),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function AccountsPage() {
  const { t } = useTranslation('accounting');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Account | null>(null);
  const { data, isLoading } = useQuery({ queryKey: ['accounting', 'accounts'], queryFn: () => fetchAccounts() });

  const invalidateAccounts = () => queryClient.invalidateQueries({ queryKey: ['accounting', 'accounts'] });

  const createMutation = useMutation({
    mutationFn: createAccount,
    onSuccess: () => {
      invalidateAccounts();
      setDialogOpen(false);
      showToast(t('accounts.toast.created'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteAccount,
    onSuccess: () => {
      invalidateAccounts();
      setPendingDelete(null);
      showToast(t('accounts.toast.deleted'));
    },
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { type: 'asset' } });

  const onCreate = (values: FormValues) => createMutation.mutate(values);

  return (
    <Stack spacing={3}>
      <AccountingTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('accounts.title')}
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset();
            setDialogOpen(true);
          }}
        >
          {t('accounts.newAccount')}
        </Button>
      </Stack>

      {deleteMutation.isError && (
        <Alert severity="error">{t('accounts.errors.hasJournalLines')}</Alert>
      )}

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && (
        <EmptyState title={t('accounts.empty.title')} description={t('accounts.empty.description')} />
      )}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('accounts.table.code')}</TableCell>
                  <TableCell>{tc('labels.name')}</TableCell>
                  <TableCell>{t('accounts.table.type')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((account) => (
                  <TableRow key={account.id}>
                    <TableCell>{account.code}</TableCell>
                    <TableCell>{account.name}</TableCell>
                    <TableCell>
                      <Chip label={t(`accounts.types.${account.type}`)} size="small" color={TYPE_COLOR[account.type]} />
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(account)}>
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
        <DialogTitle>{t('accounts.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('accounts.form.code')}
                fullWidth
                {...register('code')}
                error={!!errors.code}
                helperText={errors.code?.message}
              />
              <TextField
                label={tc('labels.name')}
                fullWidth
                {...register('name')}
                error={!!errors.name}
                helperText={errors.name?.message}
              />
              <TextField label={t('accounts.form.type')} select fullWidth defaultValue="asset" {...register('type')}>
                {TYPE_OPTIONS.map((type) => (
                  <MenuItem key={type} value={type}>
                    {t(`accounts.types.${type}`)}
                  </MenuItem>
                ))}
              </TextField>
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
        title={t('accounts.deleteDialog.title')}
        message={t('accounts.deleteDialog.message', {
          code: pendingDelete?.code ?? '',
          name: pendingDelete?.name ?? '',
        })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
