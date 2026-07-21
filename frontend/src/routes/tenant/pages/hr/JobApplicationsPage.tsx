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
import EventRoundedIcon from '@mui/icons-material/EventRounded';
import ExpandMoreRoundedIcon from '@mui/icons-material/ExpandMoreRounded';
import HowToRegRoundedIcon from '@mui/icons-material/HowToRegRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Fragment, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useSearchParams } from 'react-router-dom';
import { z } from 'zod';
import {
  createInterview,
  createJobApplication,
  fetchCandidates,
  fetchJobApplications,
  fetchJobVacancies,
  hireJobApplication,
  updateJobApplicationStatus,
} from '../../../../api/endpoints/hr';
import type { JobApplication } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

const STATUS_OPTIONS = ['applied', 'screening', 'interview', 'offer', 'rejected', 'withdrawn'] as const;

function buildAppSchema(t: (key: string) => string) {
  return z.object({
    job_vacancy_id: z.number({ message: t('vacancies.title') }),
    candidate_id: z.number({ message: t('candidates.title') }),
    applied_date: z.string().min(1, t('validation.dateRequired')),
  });
}
type AppFormValues = z.infer<ReturnType<typeof buildAppSchema>>;

function buildInterviewSchema(t: (key: string) => string) {
  return z.object({
    scheduled_at: z.string().min(1, t('validation.dateRequired')),
    mode: z.enum(['in_person', 'phone', 'video']),
    location: z.string().optional(),
  });
}
type InterviewFormValues = z.infer<ReturnType<typeof buildInterviewSchema>>;

export function JobApplicationsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const appSchema = buildAppSchema(t);
  const interviewSchema = buildInterviewSchema(t);
  const canManage = usePermission('hr.recruitment.manage');
  const [searchParams] = useSearchParams();
  const vacancyFilter = searchParams.get('job_vacancy_id') ? Number(searchParams.get('job_vacancy_id')) : undefined;

  const [dialogOpen, setDialogOpen] = useState(false);
  const [interviewTarget, setInterviewTarget] = useState<JobApplication | null>(null);
  const [hireTarget, setHireTarget] = useState<JobApplication | null>(null);
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['hr', 'job-applications', vacancyFilter],
    queryFn: () => fetchJobApplications(vacancyFilter),
  });
  const { data: vacancies } = useQuery({ queryKey: ['hr', 'job-vacancies-all'], queryFn: () => fetchJobVacancies() });
  const { data: candidates } = useQuery({ queryKey: ['hr', 'candidates'], queryFn: () => fetchCandidates() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'job-applications'] });

  const createMutation = useMutation({
    mutationFn: createJobApplication,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.applicationCreated'));
    },
  });
  const statusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) => updateJobApplicationStatus(id, status),
    onSuccess: () => {
      invalidate();
      showToast(t('toast.applicationStatusUpdated'));
    },
  });
  const interviewMutation = useMutation({
    mutationFn: (payload: { job_application_id: number; scheduled_at: string; mode: string; location?: string }) => createInterview(payload),
    onSuccess: () => {
      invalidate();
      setInterviewTarget(null);
      showToast(t('toast.interviewScheduled'));
    },
  });
  const hireMutation = useMutation({
    mutationFn: (id: number) => hireJobApplication(id),
    onSuccess: () => {
      invalidate();
      setHireTarget(null);
      showToast(t('toast.candidateHired'));
    },
  });

  const appForm = useForm<AppFormValues>({
    resolver: zodResolver(appSchema),
    defaultValues: { applied_date: new Date().toISOString().slice(0, 10) },
  });
  const interviewForm = useForm<InterviewFormValues>({
    resolver: zodResolver(interviewSchema),
    defaultValues: { mode: 'video' },
  });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('applications.title')}</Typography>
        {canManage && (
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => {
              appForm.reset({ job_vacancy_id: vacancyFilter, applied_date: new Date().toISOString().slice(0, 10) });
              setDialogOpen(true);
            }}
          >
            {t('applications.newApplication')}
          </Button>
        )}
      </Stack>

      {isLoading && <CircularProgress />}
      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('applications.empty.title')} description={t('applications.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('applications.table.candidate')}</TableCell>
                  <TableCell>{t('applications.table.vacancy')}</TableCell>
                  <TableCell>{t('applications.table.appliedDate')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((application) => (
                  <Fragment key={application.id}>
                    <TableRow hover onClick={() => setExpandedId(expandedId === application.id ? null : application.id)} sx={{ cursor: 'pointer' }}>
                      <TableCell>{application.candidate?.name ?? '—'}</TableCell>
                      <TableCell>{application.vacancy?.title ?? '—'}</TableCell>
                      <TableCell>{application.applied_date.slice(0, 10)}</TableCell>
                      <TableCell>
                        <StatusChip status={application.status} label={t(`applicationStatuses.${application.status}`)} />
                      </TableCell>
                      <TableCell align="right" onClick={(e) => e.stopPropagation()}>
                        {canManage && !['hired', 'rejected', 'withdrawn'].includes(application.status) && (
                          <>
                            <Tooltip title={t('applications.scheduleInterview')}>
                              <IconButton size="small" onClick={() => { interviewForm.reset({ mode: 'video' }); setInterviewTarget(application); }}>
                                <EventRoundedIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                            <Tooltip title={t('applications.hire')}>
                              <IconButton size="small" color="success" onClick={() => setHireTarget(application)}>
                                <HowToRegRoundedIcon fontSize="small" />
                              </IconButton>
                            </Tooltip>
                          </>
                        )}
                        <IconButton size="small" onClick={() => setExpandedId(expandedId === application.id ? null : application.id)}>
                          <ExpandMoreRoundedIcon fontSize="small" sx={{ transform: expandedId === application.id ? 'rotate(180deg)' : 'none', transition: '0.2s' }} />
                        </IconButton>
                      </TableCell>
                    </TableRow>
                    {expandedId === application.id && (
                      <TableRow>
                        <TableCell colSpan={5} sx={{ backgroundColor: 'action.hover' }}>
                          <Stack spacing={2}>
                            {canManage && !['hired', 'rejected', 'withdrawn'].includes(application.status) && (
                              <TextField
                                select
                                size="small"
                                label={t('applications.moveToStage')}
                                value={application.status}
                                sx={{ maxWidth: 240 }}
                                onChange={(e) => statusMutation.mutate({ id: application.id, status: e.target.value })}
                              >
                                {STATUS_OPTIONS.map((status) => (
                                  <MenuItem key={status} value={status}>
                                    {t(`applicationStatuses.${status}`)}
                                  </MenuItem>
                                ))}
                              </TextField>
                            )}
                            <Typography variant="subtitle2">{t('applications.interviewsTitle')}</Typography>
                            {(application.interviews ?? []).length === 0 && (
                              <Typography variant="body2" color="text.secondary">{t('applications.noInterviews')}</Typography>
                            )}
                            {(application.interviews ?? []).map((interview) => (
                              <Stack key={interview.id} direction="row" spacing={2} alignItems="center">
                                <Typography variant="body2">{new Date(interview.scheduled_at).toLocaleString()}</Typography>
                                <Chip size="small" label={t(`interviewModes.${interview.mode}`)} />
                                <StatusChip status={interview.status} label={t(`interviewStatuses.${interview.status}`)} />
                                {interview.rating && <Chip size="small" label={`★ ${interview.rating}`} />}
                              </Stack>
                            ))}
                          </Stack>
                        </TableCell>
                      </TableRow>
                    )}
                  </Fragment>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('applications.newApplication')}</DialogTitle>
        <Stack component="form" onSubmit={appForm.handleSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="job_vacancy_id"
                control={appForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('applications.table.vacancy')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                  >
                    {vacancies?.data.map((vacancy) => (
                      <MenuItem key={vacancy.id} value={vacancy.id}>
                        {vacancy.title}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <Controller
                name="candidate_id"
                control={appForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('applications.table.candidate')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                  >
                    {candidates?.data.map((candidate) => (
                      <MenuItem key={candidate.id} value={candidate.id}>
                        {candidate.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField
                label={t('applications.table.appliedDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...appForm.register('applied_date')}
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

      <Dialog open={!!interviewTarget} onClose={() => setInterviewTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('applications.scheduleInterview')}</DialogTitle>
        <Stack
          component="form"
          onSubmit={interviewForm.handleSubmit((values) =>
            interviewTarget && interviewMutation.mutate({ job_application_id: interviewTarget.id, ...values }),
          )}
        >
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('applications.form.scheduledAt')}
                type="datetime-local"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...interviewForm.register('scheduled_at')}
              />
              <TextField label={t('applications.form.mode')} select fullWidth defaultValue="video" {...interviewForm.register('mode')}>
                {['in_person', 'phone', 'video'].map((mode) => (
                  <MenuItem key={mode} value={mode}>
                    {t(`interviewModes.${mode}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField label={t('applications.form.location')} fullWidth {...interviewForm.register('location')} />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setInterviewTarget(null)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={interviewMutation.isPending}>
              {tc('actions.save')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={!!hireTarget} onClose={() => setHireTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('applications.hire')}</DialogTitle>
        <DialogContent>
          <Alert severity="info">{t('applications.hireConfirm', { name: hireTarget?.candidate?.name ?? '' })}</Alert>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setHireTarget(null)}>{tc('actions.cancel')}</Button>
          <Button
            variant="contained"
            color="success"
            disabled={hireMutation.isPending}
            onClick={() => hireTarget && hireMutation.mutate(hireTarget.id)}
          >
            {t('applications.hire')}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
