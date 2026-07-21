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
import DeleteIcon from '@mui/icons-material/Delete';
import LockRoundedIcon from '@mui/icons-material/LockRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, type Resolver } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router-dom';
import { z } from 'zod';
import { closeJobVacancy, createJobVacancy, deleteJobVacancy, fetchJobVacancies } from '../../../../api/endpoints/hr';
import type { JobVacancy } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildSchema(t: (key: string) => string) {
  return z.object({
    title: z.string().min(1, t('validation.nameRequired')),
    employment_type: z.string().optional(),
    number_of_openings: z.coerce.number().int().min(1).max(100).default(1),
    description: z.string().optional(),
    requirements: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function JobVacanciesPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const canManage = usePermission('hr.recruitment.manage');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<JobVacancy | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'job-vacancies'], queryFn: () => fetchJobVacancies() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'job-vacancies'] });

  const createMutation = useMutation({
    mutationFn: createJobVacancy,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.vacancyCreated'));
    },
  });
  const closeMutation = useMutation({
    mutationFn: closeJobVacancy,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.vacancyClosed'));
    },
  });
  const deleteMutation = useMutation({
    mutationFn: deleteJobVacancy,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.vacancyDeleted'));
    },
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) as Resolver<FormValues>, defaultValues: { number_of_openings: 1 } });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('vacancies.title')}</Typography>
        {canManage && (
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => {
              reset({ number_of_openings: 1 });
              setDialogOpen(true);
            }}
          >
            {t('vacancies.newVacancy')}
          </Button>
        )}
      </Stack>

      {isLoading && <CircularProgress />}
      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('vacancies.empty.title')} description={t('vacancies.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('vacancies.table.title')}</TableCell>
                  <TableCell align="right">{t('vacancies.table.openings')}</TableCell>
                  <TableCell align="right">{t('vacancies.table.applications')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((vacancy) => (
                  <TableRow key={vacancy.id}>
                    <TableCell>{vacancy.title}</TableCell>
                    <TableCell align="right">{vacancy.number_of_openings}</TableCell>
                    <TableCell align="right">
                      <Button
                        size="small"
                        component={RouterLink}
                        to={`/app/hr/job-applications?job_vacancy_id=${vacancy.id}`}
                      >
                        {vacancy.applications_count ?? 0}
                      </Button>
                    </TableCell>
                    <TableCell>
                      <StatusChip status={vacancy.status} label={t(`vacancyStatuses.${vacancy.status}`)} />
                    </TableCell>
                    <TableCell align="right">
                      {vacancy.status === 'open' && canManage && (
                        <Tooltip title={t('vacancies.close')}>
                          <IconButton size="small" onClick={() => closeMutation.mutate(vacancy.id)}>
                            <LockRoundedIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                      {canManage && (vacancy.applications_count ?? 0) === 0 && (
                        <Tooltip title={tc('actions.delete')}>
                          <IconButton size="small" onClick={() => setPendingDelete(vacancy)}>
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

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>{t('vacancies.newVacancy')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('vacancies.table.title')}
                fullWidth
                {...register('title')}
                error={!!errors.title}
                helperText={errors.title?.message}
              />
              <TextField label={t('vacancies.form.employmentType')} select fullWidth defaultValue="" {...register('employment_type')}>
                <MenuItem value="">{tc('labels.none')}</MenuItem>
                {['full_time', 'part_time', 'contract', 'temporary', 'casual', 'intern'].map((type) => (
                  <MenuItem key={type} value={type}>
                    {t(`employmentTypes.${type}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField label={t('vacancies.table.openings')} type="number" fullWidth {...register('number_of_openings')} />
              <TextField label={t('vacancies.form.description')} fullWidth multiline minRows={2} {...register('description')} />
              <TextField label={t('vacancies.form.requirements')} fullWidth multiline minRows={2} {...register('requirements')} />
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
        title={t('vacancies.deleteDialog.title')}
        message={t('vacancies.deleteDialog.message', { title: pendingDelete?.title ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
