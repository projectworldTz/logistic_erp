import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
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
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { createPublicHoliday, deletePublicHoliday, fetchPublicHolidays } from '../../../../api/endpoints/hr';
import type { PublicHoliday } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildSchema(t: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    date: z.string().min(1, t('validation.dateRequired')),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function PublicHolidaysPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<PublicHoliday | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'public-holidays'], queryFn: () => fetchPublicHolidays() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'public-holidays'] });

  const createMutation = useMutation({
    mutationFn: createPublicHoliday,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.publicHolidayCreated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deletePublicHoliday,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.publicHolidayDeleted'));
    },
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('publicHolidays.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset({ name: '', date: '' });
            setDialogOpen(true);
          }}
        >
          {t('publicHolidays.newHoliday')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('publicHolidays.empty.title')} description={t('publicHolidays.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('publicHolidays.table.name')}</TableCell>
                  <TableCell>{t('publicHolidays.table.date')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((holiday) => (
                  <TableRow key={holiday.id}>
                    <TableCell>{holiday.name}</TableCell>
                    <TableCell>{holiday.date.slice(0, 10)}</TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(holiday)}>
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
        <DialogTitle>{t('publicHolidays.newHoliday')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('publicHolidays.table.name')}
                fullWidth
                {...register('name')}
                error={!!errors.name}
                helperText={errors.name?.message}
              />
              <TextField
                label={t('publicHolidays.table.date')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('date')}
                error={!!errors.date}
                helperText={errors.date?.message}
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
        title={t('publicHolidays.deleteDialog.title')}
        message={t('publicHolidays.deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
