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
import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded';
import DeleteIcon from '@mui/icons-material/Delete';
import SendRoundedIcon from '@mui/icons-material/SendRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm, type Resolver } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  acknowledgePerformanceReview,
  createPerformanceReview,
  deletePerformanceReview,
  fetchEmployees,
  fetchPerformanceReviews,
  submitPerformanceReview,
} from '../../../../api/endpoints/hr';
import type { PerformanceReview } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { useAuthStore } from '../../../../hooks/useAuth';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    review_period_start: z.string().min(1, t('validation.dateRequired')),
    review_period_end: z.string().min(1, t('validation.dateRequired')),
    review_date: z.string().min(1, t('validation.dateRequired')),
    overall_rating: z.coerce.number().min(0).max(5).optional(),
    strengths: z.string().optional(),
    areas_for_improvement: z.string().optional(),
    goals: z.string().optional(),
    comments: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function PerformanceReviewsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const canManage = usePermission('hr.performance.manage');
  const currentEmployeeUserId = useAuthStore((s) => s.user?.id);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<PerformanceReview | null>(null);
  const [ackTarget, setAckTarget] = useState<PerformanceReview | null>(null);
  const [ackComment, setAckComment] = useState('');

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'performance-reviews'], queryFn: () => fetchPerformanceReviews() });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'performance-reviews'] });

  const createMutation = useMutation({
    mutationFn: createPerformanceReview,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.reviewCreated'));
    },
  });
  const submitMutation = useMutation({
    mutationFn: submitPerformanceReview,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.reviewSubmitted'));
    },
  });
  const ackMutation = useMutation({
    mutationFn: ({ id, comment }: { id: number; comment: string }) => acknowledgePerformanceReview(id, comment),
    onSuccess: () => {
      invalidate();
      setAckTarget(null);
      setAckComment('');
      showToast(t('toast.reviewAcknowledged'));
    },
  });
  const deleteMutation = useMutation({
    mutationFn: deletePerformanceReview,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.reviewDeleted'));
    },
  });

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) as Resolver<FormValues> });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('performanceReviews.title')}</Typography>
        {canManage && (
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => {
              reset({});
              setDialogOpen(true);
            }}
          >
            {t('performanceReviews.newReview')}
          </Button>
        )}
      </Stack>

      {isLoading && <CircularProgress />}
      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('performanceReviews.empty.title')} description={t('performanceReviews.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('performanceReviews.table.employee')}</TableCell>
                  <TableCell>{t('performanceReviews.table.period')}</TableCell>
                  <TableCell align="right">{t('performanceReviews.table.rating')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((review) => {
                  const isOwnReview = review.employee?.user_id === currentEmployeeUserId;
                  return (
                    <TableRow key={review.id}>
                      <TableCell>{review.employee?.name ?? '—'}</TableCell>
                      <TableCell>
                        {review.review_period_start.slice(0, 10)} — {review.review_period_end.slice(0, 10)}
                      </TableCell>
                      <TableCell align="right">{review.overall_rating ?? '—'}</TableCell>
                      <TableCell>
                        <StatusChip status={review.status} label={t(`performanceReviewStatuses.${review.status}`)} />
                      </TableCell>
                      <TableCell align="right">
                        {review.status === 'draft' && canManage && (
                          <>
                            <Tooltip title={t('performanceReviews.submit')}>
                              <IconButton size="small" onClick={() => submitMutation.mutate(review.id)}>
                                <SendRoundedIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                            <Tooltip title={tc('actions.delete')}>
                              <IconButton size="small" onClick={() => setPendingDelete(review)}>
                                <DeleteIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                          </>
                        )}
                        {review.status === 'submitted' && isOwnReview && (
                          <Tooltip title={t('performanceReviews.acknowledge')}>
                            <IconButton size="small" color="success" onClick={() => setAckTarget(review)}>
                              <CheckCircleRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        )}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>{t('performanceReviews.newReview')}</DialogTitle>
        <Stack
          component="form"
          onSubmit={handleSubmit((values) =>
            createMutation.mutate({ ...values, overall_rating: values.overall_rating !== undefined ? String(values.overall_rating) : undefined }),
          )}
        >
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('performanceReviews.table.employee')}
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
              <TextField
                label={t('performanceReviews.form.periodStart')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('review_period_start')}
              />
              <TextField
                label={t('performanceReviews.form.periodEnd')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('review_period_end')}
              />
              <TextField
                label={t('performanceReviews.form.reviewDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('review_date')}
              />
              <TextField label={t('performanceReviews.form.overallRating')} type="number" slotProps={{ htmlInput: { step: 0.1, min: 0, max: 5 } }} fullWidth {...register('overall_rating')} />
              <TextField label={t('performanceReviews.form.strengths')} fullWidth multiline minRows={2} {...register('strengths')} />
              <TextField label={t('performanceReviews.form.areasForImprovement')} fullWidth multiline minRows={2} {...register('areas_for_improvement')} />
              <TextField label={t('performanceReviews.form.goals')} fullWidth multiline minRows={2} {...register('goals')} />
              <TextField label={t('performanceReviews.form.comments')} fullWidth multiline minRows={2} {...register('comments')} />
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

      <Dialog open={!!ackTarget} onClose={() => setAckTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('performanceReviews.acknowledge')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('performanceReviews.form.employeeComments')}
            fullWidth
            multiline
            minRows={2}
            sx={{ mt: 1 }}
            value={ackComment}
            onChange={(e) => setAckComment(e.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setAckTarget(null)}>{tc('actions.cancel')}</Button>
          <Button
            variant="contained"
            disabled={ackMutation.isPending}
            onClick={() => ackTarget && ackMutation.mutate({ id: ackTarget.id, comment: ackComment })}
          >
            {t('performanceReviews.acknowledge')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('performanceReviews.deleteDialog.title')}
        message={t('performanceReviews.deleteDialog.message', { name: pendingDelete?.employee?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
