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
import { createCandidate, deleteCandidate, fetchCandidates } from '../../../../api/endpoints/hr';
import type { Candidate } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildSchema(t: (key: string) => string) {
  return z.object({
    first_name: z.string().min(1, t('validation.nameRequired')),
    last_name: z.string().min(1, t('validation.nameRequired')),
    email: z.string().email(t('validation.invalidEmail')).optional().or(z.literal('')),
    phone: z.string().optional(),
    source: z.string().optional(),
    notes: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function CandidatesPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const canManage = usePermission('hr.recruitment.manage');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Candidate | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'candidates'], queryFn: () => fetchCandidates() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'candidates'] });

  const createMutation = useMutation({
    mutationFn: createCandidate,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.candidateCreated'));
    },
  });
  const deleteMutation = useMutation({
    mutationFn: deleteCandidate,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.candidateDeleted'));
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
        <Typography variant="h6">{t('candidates.title')}</Typography>
        {canManage && (
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => {
              reset({});
              setDialogOpen(true);
            }}
          >
            {t('candidates.newCandidate')}
          </Button>
        )}
      </Stack>

      {isLoading && <CircularProgress />}
      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('candidates.empty.title')} description={t('candidates.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('candidates.table.name')}</TableCell>
                  <TableCell>{t('candidates.table.email')}</TableCell>
                  <TableCell>{t('candidates.table.phone')}</TableCell>
                  <TableCell>{t('candidates.table.source')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((candidate) => (
                  <TableRow key={candidate.id}>
                    <TableCell>{candidate.name}</TableCell>
                    <TableCell>{candidate.email ?? '—'}</TableCell>
                    <TableCell>{candidate.phone ?? '—'}</TableCell>
                    <TableCell>{candidate.source ?? '—'}</TableCell>
                    <TableCell align="right">
                      {canManage && (
                        <Tooltip title={tc('actions.delete')}>
                          <IconButton size="small" onClick={() => setPendingDelete(candidate)}>
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
        <DialogTitle>{t('candidates.newCandidate')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('candidates.form.firstName')}
                fullWidth
                {...register('first_name')}
                error={!!errors.first_name}
                helperText={errors.first_name?.message}
              />
              <TextField
                label={t('candidates.form.lastName')}
                fullWidth
                {...register('last_name')}
                error={!!errors.last_name}
                helperText={errors.last_name?.message}
              />
              <TextField
                label={t('candidates.table.email')}
                fullWidth
                {...register('email')}
                error={!!errors.email}
                helperText={errors.email?.message}
              />
              <TextField label={t('candidates.table.phone')} fullWidth {...register('phone')} />
              <TextField label={t('candidates.table.source')} fullWidth {...register('source')} />
              <TextField label={t('candidates.form.notes')} fullWidth multiline minRows={2} {...register('notes')} />
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
        title={t('candidates.deleteDialog.title')}
        message={t('candidates.deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
