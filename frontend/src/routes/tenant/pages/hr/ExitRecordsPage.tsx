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
import ExpandMoreRoundedIcon from '@mui/icons-material/ExpandMoreRounded';
import LockRoundedIcon from '@mui/icons-material/LockRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  completeExitRecord,
  createExitRecord,
  fetchEmployees,
  fetchExitRecords,
  updateExitRecord,
} from '../../../../api/endpoints/hr';
import type { ExitRecord } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { useCurrencyFormatter } from '../../../../hooks/useCurrency';
import { HrTabs } from './HrTabs';

type ExitType = 'resignation' | 'termination' | 'retirement' | 'end_of_contract' | 'redundancy';
const EXIT_TYPE_OPTIONS: ExitType[] = ['resignation', 'termination', 'retirement', 'end_of_contract', 'redundancy'];

function buildSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    exit_type: z.enum(EXIT_TYPE_OPTIONS as [ExitType, ...ExitType[]]),
    notice_date: z.string().min(1, t('validation.dateRequired')),
    last_working_date: z.string().min(1, t('validation.dateRequired')),
    reason: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function ExitRecordsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const canManage = usePermission('hr.exits.manage');

  const [dialogOpen, setDialogOpen] = useState(false);
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'exit-records'], queryFn: fetchExitRecords });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'exit-records'] });

  const createMutation = useMutation({
    mutationFn: createExitRecord,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.exitInitiated'));
    },
  });
  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Partial<ExitRecord> }) => updateExitRecord(id, payload),
    onSuccess: () => {
      invalidate();
      showToast(t('toast.exitUpdated'));
    },
  });
  const completeMutation = useMutation({
    mutationFn: completeExitRecord,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.exitCompleted'));
    },
  });

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { exit_type: 'resignation' } });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('exits.title')}</Typography>
        {canManage && (
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => {
              reset({ exit_type: 'resignation' });
              setDialogOpen(true);
            }}
          >
            {t('exits.newExit')}
          </Button>
        )}
      </Stack>

      {isLoading && <CircularProgress />}
      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('exits.empty.title')} description={t('exits.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table size="small">
              <TableHead>
                <TableRow>
                  <TableCell>{t('exits.table.employee')}</TableCell>
                  <TableCell>{t('exits.table.type')}</TableCell>
                  <TableCell>{t('exits.table.lastWorkingDate')}</TableCell>
                  <TableCell align="right">{t('exits.table.settlement')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((record) => (
                  <ExitRow
                    key={record.id}
                    record={record}
                    expanded={expandedId === record.id}
                    onToggle={() => setExpandedId(expandedId === record.id ? null : record.id)}
                    canManage={canManage}
                    onUpdate={(payload) => updateMutation.mutate({ id: record.id, payload })}
                    onComplete={() => completeMutation.mutate(record.id)}
                  />
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('exits.newExit')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('exits.table.employee')}
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
              <TextField label={t('exits.table.type')} select fullWidth defaultValue="resignation" {...register('exit_type')}>
                {EXIT_TYPE_OPTIONS.map((type) => (
                  <MenuItem key={type} value={type}>
                    {t(`exitTypes.${type}`)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField
                label={t('exits.form.noticeDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('notice_date')}
              />
              <TextField
                label={t('exits.table.lastWorkingDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...register('last_working_date')}
              />
              <TextField label={t('exits.form.reason')} fullWidth multiline minRows={2} {...register('reason')} />
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
    </Stack>
  );
}

function ExitRow({
  record,
  expanded,
  onToggle,
  canManage,
  onUpdate,
  onComplete,
}: {
  record: ExitRecord;
  expanded: boolean;
  onToggle: () => void;
  canManage: boolean;
  onUpdate: (payload: Partial<ExitRecord>) => void;
  onComplete: () => void;
}) {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const { format } = useCurrencyFormatter();

  return (
    <>
      <TableRow hover onClick={onToggle} sx={{ cursor: 'pointer' }}>
        <TableCell>{record.employee?.name ?? '—'}</TableCell>
        <TableCell>{t(`exitTypes.${record.exit_type}`)}</TableCell>
        <TableCell>{record.last_working_date.slice(0, 10)}</TableCell>
        <TableCell align="right">
          {record.final_settlement_amount !== null ? format(Number(record.final_settlement_amount)) : '—'}
        </TableCell>
        <TableCell>
          <StatusChip status={record.status} label={t(`exitStatuses.${record.status}`)} />
        </TableCell>
        <TableCell align="right" onClick={(e) => e.stopPropagation()}>
          {canManage && record.status !== 'completed' && (
            <Tooltip title={t('exits.complete')}>
              <span>
                <IconButton
                  size="small"
                  color="success"
                  disabled={!record.assets_cleared || !record.handover_completed}
                  onClick={onComplete}
                >
                  <LockRoundedIcon fontSize="small" />
                </IconButton>
              </span>
            </Tooltip>
          )}
          <IconButton size="small" onClick={onToggle}>
            <ExpandMoreRoundedIcon fontSize="small" sx={{ transform: expanded ? 'rotate(180deg)' : 'none', transition: '0.2s' }} />
          </IconButton>
        </TableCell>
      </TableRow>
      {expanded && (
        <TableRow>
          <TableCell colSpan={6} sx={{ backgroundColor: 'action.hover' }}>
            <Stack spacing={2}>
              <Stack direction="row" spacing={4} flexWrap="wrap" useFlexGap>
                <SettlementStat label={t('exits.summary.unusedLeaveDays')} value={record.unused_leave_days ?? '—'} />
                <SettlementStat label={t('exits.summary.leavePayout')} value={record.leave_payout_amount ? format(Number(record.leave_payout_amount)) : '—'} />
                <SettlementStat label={t('exits.summary.outstandingLoan')} value={record.outstanding_loan_balance ? format(Number(record.outstanding_loan_balance)) : '—'} />
                <SettlementStat label={t('exits.summary.outstandingAdvance')} value={record.outstanding_advance_balance ? format(Number(record.outstanding_advance_balance)) : '—'} />
                <SettlementStat label={t('exits.summary.finalSettlement')} value={record.final_settlement_amount ? format(Number(record.final_settlement_amount)) : '—'} highlight />
              </Stack>
              {canManage && record.status !== 'completed' && (
                <Stack direction="row" spacing={2}>
                  <FormControlLabel
                    control={<Checkbox checked={record.assets_cleared} onChange={(e) => onUpdate({ assets_cleared: e.target.checked })} />}
                    label={t('exits.assetsCleared')}
                  />
                  <FormControlLabel
                    control={<Checkbox checked={record.handover_completed} onChange={(e) => onUpdate({ handover_completed: e.target.checked })} />}
                    label={t('exits.handoverCompleted')}
                  />
                </Stack>
              )}
              {!canManage && (
                <Stack direction="row" spacing={2}>
                  <Chip size="small" label={t('exits.assetsCleared')} color={record.assets_cleared ? 'success' : 'default'} />
                  <Chip size="small" label={t('exits.handoverCompleted')} color={record.handover_completed ? 'success' : 'default'} />
                </Stack>
              )}
              {record.reason && (
                <Typography variant="body2" color="text.secondary">
                  {tc('labels.reason', { defaultValue: 'Reason' })}: {record.reason}
                </Typography>
              )}
            </Stack>
          </TableCell>
        </TableRow>
      )}
    </>
  );
}

function SettlementStat({ label, value, highlight }: { label: string; value: string; highlight?: boolean }) {
  return (
    <Stack spacing={0.5}>
      <Typography variant="caption" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="body1" fontWeight={highlight ? 700 : 500}>
        {value}
      </Typography>
    </Stack>
  );
}
