import {
  Box,
  Button,
  Card,
  CardContent,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid,
  IconButton,
  MenuItem,
  Paper,
  Stack,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tabs,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import CancelRoundedIcon from '@mui/icons-material/CancelRounded';
import DownloadRoundedIcon from '@mui/icons-material/DownloadRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import {
  cancelMyLeaveRequest,
  createMyLeaveRequest,
  downloadPayslipPdf,
  fetchMyAssets,
  fetchMyAttendance,
  fetchMyLeaveBalances,
  fetchMyLeaveRequests,
  fetchMyLeaveTypes,
  fetchMyProfile,
  fetchPayslips,
} from '../../../api/endpoints/hr';
import type { Payslip } from '../../../types';
import { EmptyState } from '../../../components/common/EmptyState';
import { StatusChip } from '../../../components/common/StatusChip';
import { useToast } from '../../../hooks/useToast';
import { formatCurrency } from '../../../utils/currency';
import { downloadBlobAsFile } from '../../../utils/downloadFile';

interface LeaveRequestFormValues {
  leave_type_id: number | '';
  start_date: string;
  end_date: string;
  reason: string;
}

function ProfileTab() {
  const { t } = useTranslation('hr');
  const { data, isLoading } = useQuery({ queryKey: ['hr', 'my', 'profile'], queryFn: fetchMyProfile });

  if (isLoading) return <CircularProgress />;
  if (!data) return <EmptyState title={t('myHr.profile.empty.title')} description={t('myHr.profile.empty.description')} />;

  const rows: [string, string][] = [
    [t('myHr.profile.employeeNumber'), data.employee_number ?? '—'],
    [t('myHr.profile.department'), data.department?.name ?? '—'],
    [t('myHr.profile.designation'), data.designation?.name ?? '—'],
    [t('myHr.profile.employmentType'), data.employment_type],
    [t('myHr.profile.status'), data.status],
    [t('myHr.profile.hireDate'), data.hire_date.slice(0, 10)],
    [t('myHr.profile.email'), data.email ?? '—'],
    [t('myHr.profile.phone'), data.phone ?? '—'],
  ];

  return (
    <Card variant="outlined">
      <CardContent>
        <Typography variant="h6" gutterBottom>
          {data.name}
        </Typography>
        <Grid container spacing={2}>
          {rows.map(([label, value]) => (
            <Grid size={{ xs: 12, sm: 6 }} key={label}>
              <Typography variant="caption" color="text.secondary" display="block">
                {label}
              </Typography>
              <Typography variant="body1">{value}</Typography>
            </Grid>
          ))}
        </Grid>
      </CardContent>
    </Card>
  );
}

function AttendanceTab() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const { data, isLoading } = useQuery({ queryKey: ['hr', 'my', 'attendance'], queryFn: () => fetchMyAttendance() });
  const rows = data?.data ?? [];

  if (isLoading) return <CircularProgress />;
  if (rows.length === 0) return <EmptyState title={t('myHr.attendance.empty.title')} description={t('myHr.attendance.empty.description')} />;

  return (
    <Paper variant="outlined">
      <TableContainer>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>{t('attendance.table.date')}</TableCell>
              <TableCell>{tc('labels.status')}</TableCell>
              <TableCell>{t('attendance.table.checkIn')}</TableCell>
              <TableCell>{t('attendance.table.checkOut')}</TableCell>
              <TableCell align="right">{t('attendance.table.lateMinutes')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {rows.map((record) => (
              <TableRow key={record.id}>
                <TableCell>{record.date.slice(0, 10)}</TableCell>
                <TableCell>
                  <StatusChip status={record.status} label={t(`attendanceStatuses.${record.status}`)} />
                </TableCell>
                <TableCell>{record.check_in ?? '—'}</TableCell>
                <TableCell>{record.check_out ?? '—'}</TableCell>
                <TableCell align="right">{record.late_minutes ?? 0}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  );
}

function LeaveTab() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const [dialogOpen, setDialogOpen] = useState(false);

  const { data: leaveTypes } = useQuery({ queryKey: ['hr', 'my', 'leave-types'], queryFn: fetchMyLeaveTypes });
  const { data: balances } = useQuery({ queryKey: ['hr', 'my', 'leave-balances'], queryFn: () => fetchMyLeaveBalances() });
  const { data: requests, isLoading } = useQuery({ queryKey: ['hr', 'my', 'leave-requests'], queryFn: fetchMyLeaveRequests });

  const form = useForm<LeaveRequestFormValues>({ defaultValues: { leave_type_id: '', start_date: '', end_date: '', reason: '' } });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['hr', 'my', 'leave-requests'] });
    queryClient.invalidateQueries({ queryKey: ['hr', 'my', 'leave-balances'] });
  };

  const createMutation = useMutation({
    mutationFn: createMyLeaveRequest,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      form.reset({ leave_type_id: '', start_date: '', end_date: '', reason: '' });
      showToast(t('toast.leaveRequestCreated'));
    },
  });

  const cancelMutation = useMutation({
    mutationFn: cancelMyLeaveRequest,
    onSuccess: () => {
      invalidate();
      showToast(t('myHr.leave.cancelled'));
    },
  });

  const balanceRows = balances?.data ?? [];
  const requestRows = requests?.data ?? [];

  return (
    <Stack spacing={3}>
      {balanceRows.length > 0 && (
        <Grid container spacing={2}>
          {balanceRows.map((balance) => (
            <Grid size={{ xs: 12, sm: 6, md: 4 }} key={balance.id}>
              <Card variant="outlined">
                <CardContent>
                  <Typography variant="subtitle2">{balance.leave_type?.name ?? '—'}</Typography>
                  <Typography variant="h5" fontWeight={700}>
                    {balance.available_days}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    {t('myHr.leave.daysAvailable')}
                  </Typography>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>
      )}

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('myHr.leave.requestsTitle')}</Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={() => setDialogOpen(true)}>
          {t('myHr.leave.newRequest')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}
      {!isLoading && requestRows.length === 0 && (
        <EmptyState title={t('myHr.leave.empty.title')} description={t('myHr.leave.empty.description')} />
      )}

      {requestRows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('leaveManagement.table.leaveType')}</TableCell>
                  <TableCell>{t('leaveManagement.table.dates')}</TableCell>
                  <TableCell align="right">{t('leaveManagement.table.days')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {requestRows.map((request) => (
                  <TableRow key={request.id}>
                    <TableCell>{request.leave_type?.name ?? '—'}</TableCell>
                    <TableCell>
                      {request.start_date.slice(0, 10)} — {request.end_date.slice(0, 10)}
                    </TableCell>
                    <TableCell align="right">{request.days}</TableCell>
                    <TableCell>
                      <StatusChip status={request.status} label={t(`leaveRequestStatuses.${request.status}`)} />
                    </TableCell>
                    <TableCell align="right">
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

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('myHr.leave.newRequest')}</DialogTitle>
        <Stack
          component="form"
          onSubmit={form.handleSubmit((values) =>
            createMutation.mutate({
              leave_type_id: Number(values.leave_type_id),
              start_date: values.start_date,
              end_date: values.end_date,
              reason: values.reason || undefined,
            }),
          )}
        >
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="leave_type_id"
                control={form.control}
                rules={{ required: true }}
                render={({ field }) => (
                  <TextField label={t('leaveManagement.table.leaveType')} select fullWidth value={field.value} onChange={(e) => field.onChange(Number(e.target.value))}>
                    {(leaveTypes?.data ?? []).map((leaveType) => (
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
                {...form.register('start_date', { required: true })}
              />
              <TextField
                label={t('leaveManagement.form.endDate')}
                type="date"
                fullWidth
                slotProps={{ inputLabel: { shrink: true } }}
                {...form.register('end_date', { required: true })}
              />
              <TextField label={t('leaveManagement.form.reason')} fullWidth multiline minRows={2} {...form.register('reason')} />
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

function PayslipsTab() {
  const { t } = useTranslation('hr');
  const { data, isLoading } = useQuery({ queryKey: ['hr', 'my', 'payslips'], queryFn: () => fetchPayslips() });
  const rows = data?.data ?? [];

  const handleDownload = (payslip: Payslip) =>
    downloadBlobAsFile(() => downloadPayslipPdf(payslip.id), `payslip-${payslip.payslip_number ?? payslip.id}.pdf`);

  if (isLoading) return <CircularProgress />;
  if (rows.length === 0) return <EmptyState title={t('payslips.empty.title')} description={t('payslips.empty.description')} />;

  return (
    <Paper variant="outlined">
      <TableContainer>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>{t('payslips.table.number')}</TableCell>
              <TableCell>{t('payslips.table.period')}</TableCell>
              <TableCell align="right">{t('payslips.table.gross')}</TableCell>
              <TableCell align="right">{t('payslips.table.net')}</TableCell>
              <TableCell align="right"> </TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {rows.map((payslip) => (
              <TableRow key={payslip.id}>
                <TableCell>{payslip.payslip_number ?? '—'}</TableCell>
                <TableCell>{payslip.period?.name ?? '—'}</TableCell>
                <TableCell align="right">{formatCurrency(Number(payslip.gross_pay))}</TableCell>
                <TableCell align="right">{formatCurrency(Number(payslip.net_pay))}</TableCell>
                <TableCell align="right">
                  <Tooltip title={t('payslips.download')}>
                    <IconButton size="small" onClick={() => handleDownload(payslip)}>
                      <DownloadRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  );
}

function AssetsTab() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const { data, isLoading } = useQuery({ queryKey: ['hr', 'my', 'assets'], queryFn: fetchMyAssets });
  const rows = data?.data ?? [];

  if (isLoading) return <CircularProgress />;
  if (rows.length === 0) return <EmptyState title={t('assets.empty.title')} description={t('assets.empty.description')} />;

  return (
    <Paper variant="outlined">
      <TableContainer>
        <Table>
          <TableHead>
            <TableRow>
              <TableCell>{t('assets.table.name')}</TableCell>
              <TableCell>{t('assets.table.type')}</TableCell>
              <TableCell>{t('assets.table.serial')}</TableCell>
              <TableCell>{t('assets.table.assignedDate')}</TableCell>
              <TableCell>{tc('labels.status')}</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {rows.map((asset) => (
              <TableRow key={asset.id}>
                <TableCell>{asset.asset_name}</TableCell>
                <TableCell>{asset.asset_type}</TableCell>
                <TableCell>{asset.serial_number ?? '—'}</TableCell>
                <TableCell>{asset.assigned_date.slice(0, 10)}</TableCell>
                <TableCell>
                  <StatusChip status={asset.status} label={t(`assetStatuses.${asset.status}`)} />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Paper>
  );
}

export function MyHrPage() {
  const { t } = useTranslation('hr');
  const [tab, setTab] = useState(0);

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('myHr.title')}
      </Typography>

      <Tabs value={tab} onChange={(_, next) => setTab(next)}>
        <Tab label={t('myHr.tabs.profile')} />
        <Tab label={t('myHr.tabs.attendance')} />
        <Tab label={t('myHr.tabs.leave')} />
        <Tab label={t('myHr.tabs.payslips')} />
        <Tab label={t('myHr.tabs.assets')} />
      </Tabs>

      <Box>
        {tab === 0 && <ProfileTab />}
        {tab === 1 && <AttendanceTab />}
        {tab === 2 && <LeaveTab />}
        {tab === 3 && <PayslipsTab />}
        {tab === 4 && <AssetsTab />}
      </Box>
    </Stack>
  );
}
