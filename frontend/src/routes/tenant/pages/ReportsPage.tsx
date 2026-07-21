import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Checkbox,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Grid,
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
  ToggleButton,
  ToggleButtonGroup,
  Tooltip,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import DownloadIcon from '@mui/icons-material/Download';
import PrintIcon from '@mui/icons-material/Print';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useQuery } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import {
  createScheduledReport,
  deleteScheduledReport,
  downloadReportExport,
  fetchCustomsReport,
  fetchProfitReport,
  fetchReportsOverview,
  fetchScheduledReports,
  fetchTaxReport,
  importReportData,
  type ExportFormat,
  type ExportModule,
  type ImportModule,
  type ImportResult,
  type ScheduledReportFrequency,
  type ScheduledReportPayload,
} from '../../../api/endpoints/reports';
import { fetchBranches, fetchCompany } from '../../../api/endpoints/dashboard';
import { ConfirmDialog } from '../../../components/common/ConfirmDialog';
import { StatWidgetCard } from '../../../components/common/StatWidgetCard';
import { StatusBreakdownBar } from '../../../components/common/StatusBreakdownBar';
import { useToast } from '../../../hooks/useToast';
import { useAuthStore } from '../../../hooks/useAuth';
import { formatCurrency } from '../../../utils/currency';

const EXPORT_MODULES: { value: ExportModule; labelKey: string; permission: string }[] = [
  { value: 'customers', labelKey: 'export.modules.customers', permission: 'crm.customers.view' },
  { value: 'leads', labelKey: 'export.modules.leads', permission: 'crm.leads.view' },
  { value: 'quotations', labelKey: 'export.modules.quotations', permission: 'quotations.items.view' },
  { value: 'shipments', labelKey: 'export.modules.shipments', permission: 'shipments.items.view' },
  { value: 'invoices', labelKey: 'export.modules.invoices', permission: 'finance.invoices.view' },
  { value: 'expenses', labelKey: 'export.modules.expenses', permission: 'expenses.items.view' },
  { value: 'profit', labelKey: 'export.modules.profit', permission: 'reports.view' },
  { value: 'employees', labelKey: 'export.modules.employees', permission: 'hr.employees.view' },
  { value: 'payslips', labelKey: 'export.modules.payslips', permission: 'hr.payslips.view.all' },
  { value: 'leave_requests', labelKey: 'export.modules.leaveRequests', permission: 'hr.leave.view' },
];

const IMPORT_MODULES: { value: ImportModule; labelKey: string; permission: string }[] = [
  { value: 'customers', labelKey: 'import.modules.customers', permission: 'crm.customers.manage' },
  { value: 'leads', labelKey: 'import.modules.leads', permission: 'crm.leads.manage' },
];

const STATUS_COLORS: Record<string, string> = {
  // shared meanings across modules
  pending: 'text.disabled',
  draft: 'text.disabled',
  new: 'text.disabled',
  at_port: 'text.disabled',
  booked: 'text.disabled',
  received: 'text.disabled',
  active: 'success.main',
  cleared: 'success.main',
  delivered: 'success.main',
  posted: 'success.main',
  paid: 'success.main',
  dispatched: 'success.main',
  in_transit: 'warning.main',
  under_clearance: 'warning.main',
  in_maintenance: 'warning.main',
  cargo_received: 'info.main',
  at_warehouse: 'info.main',
  arrived: 'info.main',
  sent: 'info.main',
  stored: 'info.main',
  contacted: 'info.main',
  qualified: 'info.main',
  customs_hold: 'error.main',
  cancelled: 'error.main',
  voided: 'warning.main',
  overdue: 'error.main',
  damaged: 'error.main',
  out_of_service: 'error.main',
  returned: 'success.main',
  empty_return: 'text.disabled',
  lost: 'error.main',
  converted: 'success.main',
  retired: 'text.disabled',
};

function statusColor(status: string): string {
  return STATUS_COLORS[status] ?? 'text.secondary';
}

interface BreakdownCardProps {
  title: string;
  total: number;
  byStatus: Record<string, number>;
}

function BreakdownCard({ title, total, byStatus }: BreakdownCardProps) {
  const { t } = useTranslation('reports');
  const entries = Object.entries(byStatus);

  return (
    <Card variant="outlined" sx={{ height: '100%' }}>
      <CardContent>
        <Stack spacing={2}>
          <Stack direction="row" justifyContent="space-between" alignItems="baseline">
            <Typography variant="subtitle1" fontWeight={700}>
              {title}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {t('totalCount', { count: total })}
            </Typography>
          </Stack>
          {entries.length === 0 ? (
            <Typography variant="body2" color="text.secondary">
              {t('noRecords')}
            </Typography>
          ) : (
            <Stack spacing={1.5}>
              {entries.map(([status, count]) => (
                <StatusBreakdownBar
                  key={status}
                  label={status}
                  count={count}
                  total={total}
                  color={statusColor(status)}
                />
              ))}
            </Stack>
          )}
        </Stack>
      </CardContent>
    </Card>
  );
}

function DataExportCard() {
  const { t } = useTranslation('reports');
  const { t: tc } = useTranslation('common');
  const { showToast } = useToast();
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];
  const allowedModules = EXPORT_MODULES.filter((m) => permissions.includes(m.permission));
  const [module, setModule] = useState<ExportModule | ''>(allowedModules[0]?.value ?? '');
  const [format, setFormat] = useState<ExportFormat>('csv');

  const exportMutation = useMutation({
    mutationFn: () => downloadReportExport(module as ExportModule, format),
    onMutate: () => showToast(tc('toast.downloading'), 'info'),
    onSuccess: (blob) => {
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `${module}-${new Date().toISOString().slice(0, 10)}.${format}`;
      link.click();
      window.URL.revokeObjectURL(url);
      showToast(tc('toast.downloaded'), 'success');
    },
  });

  if (allowedModules.length === 0) return null;

  return (
    <Card variant="outlined" className="no-print">
      <CardContent>
        <Stack spacing={2}>
          <Stack>
            <Typography variant="subtitle1" fontWeight={700}>
              {t('export.title')}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {t('export.subtitle')}
            </Typography>
          </Stack>

          {exportMutation.isError && (
            <Typography variant="body2" color="error.main">
              {t('export.error')}
            </Typography>
          )}

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'center' }}>
            <TextField
              select
              label={t('export.moduleLabel')}
              value={module}
              onChange={(e) => setModule(e.target.value as ExportModule)}
              sx={{ minWidth: 220 }}
            >
              {allowedModules.map((m) => (
                <MenuItem key={m.value} value={m.value}>
                  {t(m.labelKey)}
                </MenuItem>
              ))}
            </TextField>

            <ToggleButtonGroup
              exclusive
              value={format}
              onChange={(_, value) => value && setFormat(value)}
              size="small"
            >
              <ToggleButton value="csv">CSV</ToggleButton>
              <ToggleButton value="xlsx">Excel</ToggleButton>
            </ToggleButtonGroup>

            <Button
              variant="contained"
              startIcon={<DownloadIcon />}
              disabled={!module || exportMutation.isPending}
              onClick={() => exportMutation.mutate()}
            >
              {exportMutation.isPending ? t('export.downloading') : t('export.download')}
            </Button>
          </Stack>
        </Stack>
      </CardContent>
    </Card>
  );
}

function DataImportCard() {
  const { t } = useTranslation('reports');
  const { t: tc } = useTranslation('common');
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];
  const allowedModules = IMPORT_MODULES.filter((m) => permissions.includes(m.permission));
  const [module, setModule] = useState<ImportModule | ''>(allowedModules[0]?.value ?? '');
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [result, setResult] = useState<ImportResult | null>(null);

  const importMutation = useMutation({
    mutationFn: (file: File) => importReportData(module as ImportModule, file),
    onMutate: () => showToast(tc('toast.uploading'), 'info'),
    onSuccess: (data) => {
      setResult(data);
      showToast(tc('toast.uploaded'), 'success');
      if (module === 'customers') queryClient.invalidateQueries({ queryKey: ['customers'] });
      if (module === 'leads') queryClient.invalidateQueries({ queryKey: ['leads'] });
    },
  });

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      setResult(null);
      importMutation.mutate(file);
    }
    event.target.value = '';
  };

  if (allowedModules.length === 0) return null;

  return (
    <Card variant="outlined" className="no-print">
      <CardContent>
        <Stack spacing={2}>
          <Stack>
            <Typography variant="subtitle1" fontWeight={700}>
              {t('import.title')}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {t('import.subtitle')}
            </Typography>
          </Stack>

          {importMutation.isError && <Typography variant="body2" color="error.main">{t('import.error')}</Typography>}

          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} alignItems={{ sm: 'center' }}>
            <TextField
              select
              label={t('import.moduleLabel')}
              value={module}
              onChange={(e) => setModule(e.target.value as ImportModule)}
              sx={{ minWidth: 220 }}
            >
              {allowedModules.map((m) => (
                <MenuItem key={m.value} value={m.value}>
                  {t(m.labelKey)}
                </MenuItem>
              ))}
            </TextField>

            <Button
              variant="contained"
              startIcon={<AddIcon />}
              disabled={!module || importMutation.isPending}
              onClick={() => fileInputRef.current?.click()}
            >
              {importMutation.isPending ? t('import.importing') : t('import.selectFile')}
            </Button>
            <input ref={fileInputRef} type="file" accept=".csv,.xlsx,.xls" hidden onChange={handleFileChange} />
          </Stack>

          {module && (
            <Typography variant="caption" color="text.secondary">
              {t('import.template.label')}: {t(`import.template.${module}`)}
            </Typography>
          )}

          {result && (
            <Stack spacing={1}>
              <Alert severity={result.errors.length > 0 ? 'warning' : 'success'}>
                {t('import.result.created', { count: result.created })}
              </Alert>
              {result.errors.length > 0 && (
                <Alert severity="error">
                  <Typography variant="body2" fontWeight={700}>
                    {t('import.result.errorsTitle', { count: result.errors.length })}
                  </Typography>
                  <Stack component="ul" sx={{ pl: 2, m: 0 }}>
                    {result.errors.map((err) => (
                      <li key={err.row}>
                        <Typography variant="body2">
                          {t('import.result.row', { row: err.row })}: {err.messages.join(' ')}
                        </Typography>
                      </li>
                    ))}
                  </Stack>
                </Alert>
              )}
            </Stack>
          )}
        </Stack>
      </CardContent>
    </Card>
  );
}

interface ScheduleFormValues {
  name: string;
  module: ExportModule;
  format: ExportFormat;
  frequency: ScheduledReportFrequency;
  recipients: string;
  is_active: boolean;
}

function ScheduledReportsCard() {
  const { t } = useTranslation('reports');
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];
  const allowedModules = EXPORT_MODULES.filter((m) => permissions.includes(m.permission));
  const canManage = permissions.includes('reports.manage');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<{ id: number; name: string } | null>(null);

  const { data } = useQuery({ queryKey: ['reports', 'scheduled'], queryFn: fetchScheduledReports });

  const { register, control, handleSubmit, reset } = useForm<ScheduleFormValues>({
    defaultValues: {
      module: allowedModules[0]?.value ?? 'customers',
      format: 'csv',
      frequency: 'weekly',
      recipients: '',
      is_active: true,
    },
  });

  const createMutation = useMutation({
    mutationFn: (payload: ScheduledReportPayload) => createScheduledReport(payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reports', 'scheduled'] });
      setDialogOpen(false);
      showToast(t('schedule.toast.created'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteScheduledReport,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['reports', 'scheduled'] });
      setPendingDelete(null);
      showToast(t('schedule.toast.deleted'));
    },
  });

  const onSubmit = (values: ScheduleFormValues) => {
    createMutation.mutate({
      ...values,
      recipients: values.recipients
        .split(',')
        .map((email) => email.trim())
        .filter(Boolean),
    });
  };

  if (allowedModules.length === 0) return null;

  const rows = data?.data ?? [];

  return (
    <Card variant="outlined" className="no-print">
      <CardContent>
        <Stack spacing={2}>
          <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
            <Stack>
              <Typography variant="subtitle1" fontWeight={700}>
                {t('schedule.title')}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {t('schedule.subtitle')}
              </Typography>
            </Stack>
            {canManage && (
              <Button
                size="small"
                variant="outlined"
                startIcon={<AddIcon />}
                onClick={() => {
                  reset({
                    module: allowedModules[0]?.value ?? 'customers',
                    format: 'csv',
                    frequency: 'weekly',
                    recipients: '',
                    is_active: true,
                  });
                  setDialogOpen(true);
                }}
              >
                {t('schedule.new')}
              </Button>
            )}
          </Stack>

          {rows.length === 0 && (
            <Typography variant="body2" color="text.secondary">
              {t('schedule.empty')}
            </Typography>
          )}

          {rows.length > 0 && (
            <TableContainer component={Paper} variant="outlined">
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>{t('schedule.table.name')}</TableCell>
                    <TableCell>{t('schedule.table.module')}</TableCell>
                    <TableCell>{t('schedule.table.frequency')}</TableCell>
                    <TableCell>{t('schedule.table.recipients')}</TableCell>
                    <TableCell>{t('schedule.table.lastSent')}</TableCell>
                    <TableCell>{t('schedule.table.status')}</TableCell>
                    {canManage && <TableCell align="right" />}
                  </TableRow>
                </TableHead>
                <TableBody>
                  {rows.map((row) => (
                    <TableRow key={row.id}>
                      <TableCell>{row.name}</TableCell>
                      <TableCell>{t(`export.modules.${row.module}`)}</TableCell>
                      <TableCell>{t(`schedule.frequencies.${row.frequency}`)}</TableCell>
                      <TableCell>{row.recipients.join(', ')}</TableCell>
                      <TableCell>
                        {row.last_sent_at ? new Date(row.last_sent_at).toLocaleString() : t('schedule.neverSent')}
                      </TableCell>
                      <TableCell>
                        <Chip
                          size="small"
                          label={row.is_active ? t('schedule.active') : t('schedule.paused')}
                          color={row.is_active ? 'success' : 'default'}
                        />
                      </TableCell>
                      {canManage && (
                        <TableCell align="right">
                          <Tooltip title={t('schedule.deleteDialog.title') as string}>
                            <IconButton size="small" onClick={() => setPendingDelete({ id: row.id, name: row.name })}>
                              <DeleteIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        </TableCell>
                      )}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </Stack>
      </CardContent>

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('schedule.dialog.title')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onSubmit)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField label={t('schedule.dialog.name')} fullWidth {...register('name', { required: true })} />
              <TextField label={t('export.moduleLabel')} select fullWidth {...register('module')}>
                {allowedModules.map((m) => (
                  <MenuItem key={m.value} value={m.value}>
                    {t(m.labelKey)}
                  </MenuItem>
                ))}
              </TextField>
              <TextField label={t('schedule.table.frequency')} select fullWidth {...register('frequency')}>
                <MenuItem value="daily">{t('schedule.frequencies.daily')}</MenuItem>
                <MenuItem value="weekly">{t('schedule.frequencies.weekly')}</MenuItem>
                <MenuItem value="monthly">{t('schedule.frequencies.monthly')}</MenuItem>
              </TextField>
              <TextField label="Format" select fullWidth {...register('format')}>
                <MenuItem value="csv">CSV</MenuItem>
                <MenuItem value="xlsx">Excel</MenuItem>
              </TextField>
              <TextField
                label={t('schedule.dialog.recipients')}
                fullWidth
                multiline
                minRows={2}
                {...register('recipients', { required: true })}
              />
              <FormControlLabel
                control={
                  <Controller
                    name="is_active"
                    control={control}
                    render={({ field }) => (
                      <Checkbox checked={!!field.value} onChange={(e) => field.onChange(e.target.checked)} />
                    )}
                  />
                }
                label={t('schedule.dialog.isActive')}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending}>
              {t('schedule.new')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('schedule.deleteDialog.title')}
        message={t('schedule.deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel="Delete"
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Card>
  );
}

export function ReportsPage() {
  const { t } = useTranslation('reports');
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];
  const canViewReports = permissions.includes('reports.view');
  const [branchId, setBranchId] = useState<number | ''>('');
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches });
  const { data, isLoading } = useQuery({
    queryKey: ['reports', 'overview', branchId],
    queryFn: () => fetchReportsOverview(branchId),
  });
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany });
  const { data: profit } = useQuery({
    queryKey: ['reports', 'profit', branchId],
    queryFn: () => fetchProfitReport(branchId),
    enabled: canViewReports,
  });
  const { data: customs } = useQuery({ queryKey: ['reports', 'customs'], queryFn: fetchCustomsReport, enabled: canViewReports });
  const { data: tax } = useQuery({ queryKey: ['reports', 'tax'], queryFn: fetchTaxReport, enabled: canViewReports });

  return (
    <Stack spacing={3}>
      <Box sx={{ display: 'none', '@media print': { display: 'block', mb: 2 } }}>
        <Typography variant="h6" fontWeight={700}>
          {company?.name}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {t('reportGeneratedAt', { date: new Date().toLocaleString() })}
        </Typography>
      </Box>

      <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
        <Stack>
          <Typography variant="h5" fontWeight={700}>
            {t('title')}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {t('subtitle')}
          </Typography>
        </Stack>
        <Stack direction="row" spacing={2} alignItems="center" className="no-print">
          {branches && branches.length > 1 && (
            <TextField
              select
              size="small"
              label={t('branchFilter.label')}
              value={branchId}
              onChange={(e) => setBranchId(e.target.value === '' ? '' : Number(e.target.value))}
              sx={{ minWidth: 200 }}
            >
              <MenuItem value="">{t('branchFilter.allBranches')}</MenuItem>
              {branches.map((branch) => (
                <MenuItem key={branch.id} value={branch.id}>
                  {branch.name}
                </MenuItem>
              ))}
            </TextField>
          )}
          <Button variant="outlined" startIcon={<PrintIcon />} onClick={() => window.print()}>
            {t('printReport')}
          </Button>
        </Stack>
      </Stack>

      <DataExportCard />
      <DataImportCard />
      <ScheduledReportsCard />

      {isLoading && <CircularProgress />}

      {data && (
        <>
          <Grid container spacing={2}>
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.customers')} value={data.crm.customers_total} />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.leads')} value={data.crm.leads_total} />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.revenueCollected')} value={formatCurrency(data.finance.paid_amount, company?.currency)} />
            </Grid>
            <Grid size={{ xs: 12, sm: 6, md: 3 }}>
              <StatWidgetCard label={t('widgets.outstanding')} value={formatCurrency(data.finance.outstanding_amount, company?.currency)} />
            </Grid>
          </Grid>

          <Grid container spacing={2}>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.quotations')} total={data.quotations.total} byStatus={data.quotations.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.shipments')} total={data.shipments.total} byStatus={data.shipments.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.clearingFiles')} total={data.clearing.total} byStatus={data.clearing.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.freightBookings')} total={data.freight.total} byStatus={data.freight.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.containers')} total={data.containers.total} byStatus={data.containers.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.warehouseItems')} total={data.warehouse.total} byStatus={data.warehouse.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.fleetVehicles')} total={data.fleet.total} byStatus={data.fleet.by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard title={t('breakdown.invoices')} total={data.finance.invoices_total} byStatus={data.finance.invoices_by_status} />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <BreakdownCard
                title={t('breakdown.journalEntries')}
                total={Object.values(data.accounting.journal_entries_by_status).reduce((a, b) => a + b, 0)}
                byStatus={data.accounting.journal_entries_by_status}
              />
            </Grid>
            <Grid size={{ xs: 12, md: 6 }}>
              <Card variant="outlined" sx={{ height: '100%' }}>
                <CardContent>
                  <Stack spacing={2}>
                    <Typography variant="subtitle1" fontWeight={700}>
                      {t('chartOfAccounts.title')}
                    </Typography>
                    <Stack direction="row" justifyContent="space-between">
                      <Typography variant="body2" color="text.secondary">
                        {t('chartOfAccounts.accountsConfigured')}
                      </Typography>
                      <Typography variant="body2" fontWeight={600}>
                        {data.accounting.accounts_total}
                      </Typography>
                    </Stack>
                    <Stack direction="row" justifyContent="space-between">
                      <Typography variant="body2" color="text.secondary">
                        {t('chartOfAccounts.documentsArchived')}
                      </Typography>
                      <Typography variant="body2" fontWeight={600}>
                        {data.documents.total}
                      </Typography>
                    </Stack>
                  </Stack>
                </CardContent>
              </Card>
            </Grid>
          </Grid>
        </>
      )}

      {(profit || customs || tax) && (
        <Stack spacing={2}>
          <Typography variant="h6" fontWeight={700}>
            {t('advanced.sectionTitle')}
          </Typography>

          <Grid container spacing={2}>
            {customs && (
              <Grid size={{ xs: 12, sm: 6, md: 3 }}>
                <StatWidgetCard label={t('advanced.customs.totalDeclarations')} value={customs.total_declarations} />
              </Grid>
            )}
            {customs && (
              <Grid size={{ xs: 12, sm: 6, md: 3 }}>
                <StatWidgetCard
                  label={t('advanced.customs.avgClearanceDays')}
                  value={customs.avg_clearance_days === null ? '—' : t('advanced.customs.daysValue', { count: customs.avg_clearance_days })}
                />
              </Grid>
            )}
            {tax && (
              <Grid size={{ xs: 12, sm: 6, md: 3 }}>
                <StatWidgetCard label={t('advanced.tax.vatCollected')} value={formatCurrency(tax.totals.vat_collected, company?.currency)} />
              </Grid>
            )}
            {tax && (
              <Grid size={{ xs: 12, sm: 6, md: 3 }}>
                <StatWidgetCard label={t('advanced.tax.dutyPaid')} value={formatCurrency(tax.totals.duty_paid, company?.currency)} />
              </Grid>
            )}
          </Grid>

          {profit && profit.rows.length > 0 && (
            <Card variant="outlined">
              <CardContent>
                <Stack spacing={2}>
                  <Stack direction="row" justifyContent="space-between" alignItems="baseline">
                    <Typography variant="subtitle1" fontWeight={700}>
                      {t('advanced.profit.title')}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {t('advanced.profit.totalProfit', { amount: formatCurrency(profit.totals.profit, company?.currency) })}
                    </Typography>
                  </Stack>
                  <TableContainer component={Paper} variant="outlined">
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>{t('advanced.profit.table.shipment')}</TableCell>
                          <TableCell>{t('advanced.profit.table.customer')}</TableCell>
                          <TableCell align="right">{t('advanced.profit.table.revenue')}</TableCell>
                          <TableCell align="right">{t('advanced.profit.table.cost')}</TableCell>
                          <TableCell align="right">{t('advanced.profit.table.profit')}</TableCell>
                          <TableCell align="right">{t('advanced.profit.table.margin')}</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {profit.rows.map((row) => (
                          <TableRow key={row.shipment_id}>
                            <TableCell>{row.shipment_number ?? '—'}</TableCell>
                            <TableCell>{row.customer ?? '—'}</TableCell>
                            <TableCell align="right">{formatCurrency(row.revenue, company?.currency)}</TableCell>
                            <TableCell align="right">{formatCurrency(row.cost, company?.currency)}</TableCell>
                            <TableCell align="right">{formatCurrency(row.profit, company?.currency)}</TableCell>
                            <TableCell align="right">{row.margin_percent === null ? '—' : `${row.margin_percent}%`}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                </Stack>
              </CardContent>
            </Card>
          )}
        </Stack>
      )}
    </Stack>
  );
}
