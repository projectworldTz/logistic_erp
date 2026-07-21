import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  Checkbox,
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
import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded';
import CloseRoundedIcon from '@mui/icons-material/CloseRounded';
import DeleteIcon from '@mui/icons-material/Delete';
import CancelRoundedIcon from '@mui/icons-material/CancelRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm, type Resolver } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  approveLeaveRequest,
  cancelLeaveRequest,
  createLeaveRequest,
  createLeaveType,
  deleteLeaveType,
  fetchEmployees,
  fetchLeaveBalances,
  fetchLeaveRequests,
  fetchLeaveTypes,
  rejectLeaveRequest,
} from '../../../../api/endpoints/hr';
import type { LeaveRequest, LeaveType } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildLeaveTypeSchema(t: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    is_paid: z.boolean().default(true),
    default_annual_days: z.coerce.number().min(0).optional(),
  });
}

type LeaveTypeFormValues = z.infer<ReturnType<typeof buildLeaveTypeSchema>>;

function buildLeaveRequestSchema(t: (key: string) => string) {
  return z.object({
    employee_id: z.number({ message: t('validation.employeeRequired') }),
    leave_type_id: z.number({ message: t('leaveManagement.validation.leaveTypeRequired') }),
    start_date: z.string().min(1, t('validation.dateRequired')),
    end_date: z.string().min(1, t('validation.dateRequired')),
    reason: z.string().optional(),
  });
}

type LeaveRequestFormValues = z.infer<ReturnType<typeof buildLeaveRequestSchema>>;

export function LeaveManagementPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const leaveTypeSchema = buildLeaveTypeSchema(t);
  const leaveRequestSchema = buildLeaveRequestSchema(t);
  const canApprove = usePermission('hr.leave.approve');

  const [typeDialogOpen, setTypeDialogOpen] = useState(false);
  const [requestDialogOpen, setRequestDialogOpen] = useState(false);
  const [pendingDeleteType, setPendingDeleteType] = useState<LeaveType | null>(null);
  const [rejectTarget, setRejectTarget] = useState<LeaveRequest | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [balanceYear] = useState(new Date().getFullYear());

  const { data: leaveTypes, isLoading: typesLoading } = useQuery({ queryKey: ['hr', 'leave-types'], queryFn: fetchLeaveTypes });
  const { data: leaveRequests, isLoading: requestsLoading } = useQuery({
    queryKey: ['hr', 'leave-requests'],
    queryFn: () => fetchLeaveRequests(),
  });
  const { data: leaveBalances } = useQuery({
    queryKey: ['hr', 'leave-balances', balanceYear],
    queryFn: () => fetchLeaveBalances(undefined, balanceYear),
  });
  const { data: employees } = useQuery({ queryKey: ['hr', 'employees'], queryFn: () => fetchEmployees() });

  const invalidateTypes = () => queryClient.invalidateQueries({ queryKey: ['hr', 'leave-types'] });
  const invalidateRequests = () => {
    queryClient.invalidateQueries({ queryKey: ['hr', 'leave-requests'] });
    queryClient.invalidateQueries({ queryKey: ['hr', 'leave-balances'] });
  };

  const createTypeMutation = useMutation({
    mutationFn: createLeaveType,
    onSuccess: () => {
      invalidateTypes();
      setTypeDialogOpen(false);
      showToast(t('toast.leaveTypeCreated'));
    },
  });

  const deleteTypeMutation = useMutation({
    mutationFn: deleteLeaveType,
    onSuccess: () => {
      invalidateTypes();
      setPendingDeleteType(null);
      showToast(t('toast.leaveTypeDeleted'));
    },
  });

  const createRequestMutation = useMutation({
    mutationFn: createLeaveRequest,
    onSuccess: () => {
      invalidateRequests();
      setRequestDialogOpen(false);
      showToast(t('toast.leaveRequestCreated'));
    },
  });

  const approveMutation = useMutation({
    mutationFn: approveLeaveRequest,
    onSuccess: () => {
      invalidateRequests();
      showToast(t('toast.leaveRequestApproved'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => rejectLeaveRequest(id, reason),
    onSuccess: () => {
      invalidateRequests();
      setRejectTarget(null);
      setRejectReason('');
      showToast(t('toast.leaveRequestRejected'));
    },
  });

  const cancelMutation = useMutation({
    mutationFn: cancelLeaveRequest,
    onSuccess: () => {
      invalidateRequests();
      showToast(t('toast.leaveRequestCancelled'));
    },
  });

  const typeForm = useForm<LeaveTypeFormValues>({
    resolver: zodResolver(leaveTypeSchema) as Resolver<LeaveTypeFormValues>,
    defaultValues: { is_paid: true },
  });

  const requestForm = useForm<LeaveRequestFormValues>({
    resolver: zodResolver(leaveRequestSchema),
    defaultValues: {
      start_date: new Date().toISOString().slice(0, 10),
      end_date: new Date().toISOString().slice(0, 10),
    },
  });

  const leaveTypeRows = leaveTypes?.data ?? [];
  const leaveRequestRows = leaveRequests?.data ?? [];
  const leaveBalanceRows = leaveBalances?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('leaveManagement.requestsTitle')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            requestForm.reset({
              start_date: new Date().toISOString().slice(0, 10),
              end_date: new Date().toISOString().slice(0, 10),
            });
            setRequestDialogOpen(true);
          }}
        >
          {t('leaveManagement.newRequest')}
        </Button>
      </Stack>

      {requestsLoading && <CircularProgress />}

      {leaveRequestRows.length === 0 && !requestsLoading && (
        <EmptyState title={t('leaveManagement.requestsEmpty.title')} description={t('leaveManagement.requestsEmpty.description')} />
      )}

      {leaveRequestRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('leaveManagement.table.employee')}</TableCell>
                  <TableCell>{t('leaveManagement.table.leaveType')}</TableCell>
                  <TableCell>{t('leaveManagement.table.dates')}</TableCell>
                  <TableCell align="right">{t('leaveManagement.table.days')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {leaveRequestRows.map((request) => (
                  <TableRow key={request.id}>
                    <TableCell>{request.employee?.name ?? '—'}</TableCell>
                    <TableCell>{request.leave_type?.name ?? '—'}</TableCell>
                    <TableCell>
                      {request.start_date.slice(0, 10)} — {request.end_date.slice(0, 10)}
                    </TableCell>
                    <TableCell align="right">{request.days}</TableCell>
                    <TableCell>
                      <StatusChip status={request.status} label={t(`leaveRequestStatuses.${request.status}`)} />
                    </TableCell>
                    <TableCell align="right">
                      {request.status === 'pending' && canApprove && (
                        <>
                          <Tooltip title={t('leaveManagement.approve')}>
                            <IconButton size="small" color="success" onClick={() => approveMutation.mutate(request.id)}>
                              <CheckCircleRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                          <Tooltip title={t('leaveManagement.reject')}>
                            <IconButton size="small" color="error" onClick={() => setRejectTarget(request)}>
                              <CloseRoundedIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </>
                      )}
                      {request.status === 'pending' && (
                        <Tooltip title={t('leaveManagement.cancel')}>
                          <IconButton size="small" onClick={() => cancelMutation.mutate(request.id)}>
                            <CancelRoundedIcon fontSize="small" />
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

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('leaveManagement.typesTitle')}</Typography>
        <Button
          variant="outlined"
          startIcon={<AddIcon />}
          onClick={() => {
            typeForm.reset({ is_paid: true });
            setTypeDialogOpen(true);
          }}
        >
          {t('leaveManagement.newLeaveType')}
        </Button>
      </Stack>

      {typesLoading && <CircularProgress />}

      {leaveTypeRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('leaveManagement.table.name')}</TableCell>
                  <TableCell>{t('leaveManagement.table.paid')}</TableCell>
                  <TableCell align="right">{t('leaveManagement.table.defaultAnnualDays')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {leaveTypeRows.map((leaveType) => (
                  <TableRow key={leaveType.id}>
                    <TableCell>{leaveType.name}</TableCell>
                    <TableCell>{leaveType.is_paid ? tc('labels.yes') : tc('labels.no')}</TableCell>
                    <TableCell align="right">{leaveType.default_annual_days ?? '—'}</TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDeleteType(leaveType)}>
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

      <Typography variant="h6">{t('leaveManagement.balancesTitle', { year: balanceYear })}</Typography>

      {leaveBalanceRows.length === 0 && (
        <EmptyState title={t('leaveManagement.balancesEmpty.title')} description={t('leaveManagement.balancesEmpty.description')} />
      )}

      {leaveBalanceRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('leaveManagement.table.employee')}</TableCell>
                  <TableCell>{t('leaveManagement.table.leaveType')}</TableCell>
                  <TableCell align="right">{t('leaveManagement.table.entitled')}</TableCell>
                  <TableCell align="right">{t('leaveManagement.table.used')}</TableCell>
                  <TableCell align="right">{t('leaveManagement.table.available')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {leaveBalanceRows.map((balance) => (
                  <TableRow key={balance.id}>
                    <TableCell>{balance.employee?.name ?? '—'}</TableCell>
                    <TableCell>{balance.leave_type?.name ?? '—'}</TableCell>
                    <TableCell align="right">{balance.entitled_days}</TableCell>
                    <TableCell align="right">{balance.used_days}</TableCell>
                    <TableCell align="right">{balance.available_days}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={typeDialogOpen} onClose={() => setTypeDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('leaveManagement.newLeaveType')}</DialogTitle>
        <Stack component="form" onSubmit={typeForm.handleSubmit((values) => createTypeMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('leaveManagement.table.name')}
                fullWidth
                {...typeForm.register('name')}
                error={!!typeForm.formState.errors.name}
                helperText={typeForm.formState.errors.name?.message}
              />
              <TextField
                label={t('leaveManagement.table.defaultAnnualDays')}
                type="number"
                fullWidth
                {...typeForm.register('default_annual_days')}
              />
              <Controller
                name="is_paid"
                control={typeForm.control}
                render={({ field }) => (
                  <FormControlLabel
                    control={<Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)} />}
                    label={t('leaveManagement.table.paid')}
                  />
                )}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setTypeDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createTypeMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={requestDialogOpen} onClose={() => setRequestDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('leaveManagement.newRequest')}</DialogTitle>
        <Stack component="form" onSubmit={requestForm.handleSubmit((values) => createRequestMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="employee_id"
                control={requestForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('leaveManagement.table.employee')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!requestForm.formState.errors.employee_id}
                  >
                    {employees?.data.map((employee) => (
                      <MenuItem key={employee.id} value={employee.id}>
                        {employee.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <Controller
                name="leave_type_id"
                control={requestForm.control}
                render={({ field }) => (
                  <TextField
                    label={t('leaveManagement.table.leaveType')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(Number(e.target.value))}
                    error={!!requestForm.formState.errors.leave_type_id}
                  >
                    {leaveTypeRows.map((leaveType) => (
                      <MenuItem key={leaveType.id} value={leaveType.id}>
                        {leaveType.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField
                label={t('leaveManagement.form.startDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...requestForm.register('start_date')}
                error={!!requestForm.formState.errors.start_date}
              />
              <TextField
                label={t('leaveManagement.form.endDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...requestForm.register('end_date')}
                error={!!requestForm.formState.errors.end_date}
              />
              <TextField
                label={t('leaveManagement.form.reason')}
                fullWidth
                multiline
                minRows={2}
                {...requestForm.register('reason')}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setRequestDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createRequestMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={!!rejectTarget} onClose={() => setRejectTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('leaveManagement.reject')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('leaveManagement.rejectReason')}
            fullWidth
            multiline
            minRows={2}
            sx={{ mt: 1 }}
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setRejectTarget(null)}>{tc('actions.cancel')}</Button>
          <Button
            color="error"
            variant="contained"
            disabled={!rejectReason.trim() || rejectMutation.isPending}
            onClick={() => rejectTarget && rejectMutation.mutate({ id: rejectTarget.id, reason: rejectReason })}
          >
            {t('leaveManagement.reject')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDeleteType}
        title={t('leaveManagement.deleteTypeDialog.title')}
        message={t('leaveManagement.deleteTypeDialog.message', { name: pendingDeleteType?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteTypeMutation.isPending}
        onConfirm={() => pendingDeleteType && deleteTypeMutation.mutate(pendingDeleteType.id)}
        onCancel={() => setPendingDeleteType(null)}
      />
    </Stack>
  );
}
