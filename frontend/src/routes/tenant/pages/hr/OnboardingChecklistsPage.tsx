import {
  Checkbox,
  CircularProgress,
  IconButton,
  LinearProgress,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tooltip,
  Typography,
} from '@mui/material';
import ExpandMoreRoundedIcon from '@mui/icons-material/ExpandMoreRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Fragment, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { fetchOnboardingChecklists, toggleOnboardingTask } from '../../../../api/endpoints/hr';
import { EmptyState } from '../../../../components/common/EmptyState';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

export function OnboardingChecklistsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const canManage = usePermission('hr.onboarding.manage');

  const [expandedId, setExpandedId] = useState<number | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'onboarding-checklists'], queryFn: () => fetchOnboardingChecklists() });

  const toggleMutation = useMutation({
    mutationFn: toggleOnboardingTask,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['hr', 'onboarding-checklists'] });
      showToast(t('toast.taskUpdated'));
    },
  });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Typography variant="h6">{t('onboarding.title')}</Typography>

      {isLoading && <CircularProgress />}
      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('onboarding.empty.title')} description={t('onboarding.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('onboarding.table.employee')}</TableCell>
                  <TableCell>{t('onboarding.table.progress')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((checklist) => (
                  <Fragment key={checklist.id}>
                    <TableRow hover onClick={() => setExpandedId(expandedId === checklist.id ? null : checklist.id)} sx={{ cursor: 'pointer' }}>
                      <TableCell>{checklist.employee?.name ?? '—'}</TableCell>
                      <TableCell sx={{ width: 200 }}>
                        <Stack direction="row" spacing={1} alignItems="center">
                          <LinearProgress variant="determinate" value={checklist.progress ?? 0} sx={{ flex: 1, height: 6, borderRadius: 3 }} />
                          <Typography variant="caption">{checklist.progress ?? 0}%</Typography>
                        </Stack>
                      </TableCell>
                      <TableCell>
                        <StatusChip status={checklist.status} label={t(`onboardingStatuses.${checklist.status}`)} />
                      </TableCell>
                      <TableCell align="right">
                        <IconButton size="small" onClick={() => setExpandedId(expandedId === checklist.id ? null : checklist.id)}>
                          <ExpandMoreRoundedIcon fontSize="small" sx={{ transform: expandedId === checklist.id ? 'rotate(180deg)' : 'none', transition: '0.2s' }} />
                        </IconButton>
                      </TableCell>
                    </TableRow>
                    {expandedId === checklist.id && (
                      <TableRow>
                        <TableCell colSpan={4} sx={{ backgroundColor: 'action.hover' }}>
                          <Stack spacing={0.5}>
                            {(checklist.tasks ?? []).map((task) => (
                              <Stack key={task.id} direction="row" spacing={1} alignItems="center" onClick={(e) => e.stopPropagation()}>
                                <Checkbox
                                  size="small"
                                  checked={task.is_completed}
                                  disabled={!canManage}
                                  onChange={() => toggleMutation.mutate(task.id)}
                                />
                                <Tooltip title={task.description ?? ''}>
                                  <Typography
                                    variant="body2"
                                    sx={{ textDecoration: task.is_completed ? 'line-through' : 'none', color: task.is_completed ? 'text.secondary' : 'text.primary' }}
                                  >
                                    {task.title}
                                  </Typography>
                                </Tooltip>
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
    </Stack>
  );
}
