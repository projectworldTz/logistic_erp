import {
  Alert,
  Avatar,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid,
  IconButton,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  MenuItem,
  Stack,
  Tab,
  Tabs,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded';
import CloseRoundedIcon from '@mui/icons-material/CloseRounded';
import DeleteIcon from '@mui/icons-material/Delete';
import DescriptionRoundedIcon from '@mui/icons-material/DescriptionRounded';
import DownloadRoundedIcon from '@mui/icons-material/DownloadRounded';
import UploadFileRoundedIcon from '@mui/icons-material/UploadFileRounded';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom';
import {
  deleteEmployeeDocument,
  fetchEmployee,
  fetchEmployeeDocuments,
  fetchEmployeeSalary,
  rejectEmployeeDocument,
  uploadEmployeeDocument,
  verifyEmployeeDocument,
} from '../../../../api/endpoints/hr';
import type { Employee, EmployeeDocumentType } from '../../../../types';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { EmptyState } from '../../../../components/common/EmptyState';
import { StatusChip } from '../../../../components/common/StatusChip';
import { usePermission } from '../../../../hooks/usePermission';
import { useToast } from '../../../../hooks/useToast';
import { formatCurrency } from '../../../../utils/currency';

const DOCUMENT_TYPES: EmployeeDocumentType[] = [
  'employment_contract', 'national_id', 'passport', 'academic_certificate', 'professional_certificate',
  'driving_license', 'work_permit', 'medical_certificate', 'tax_document', 'pension_registration',
  'bank_information', 'warning_letter', 'promotion_letter', 'training_certificate', 'other',
];

/** API date/datetime-cast fields serialize with a full ISO timestamp — trim to the date part for display. */
function displayDate(value: string | null | undefined): string | null | undefined {
  return value ? value.slice(0, 10) : value;
}

function OverviewTab({ employee }: { employee: Employee }) {
  const { t } = useTranslation('hr');

  const rows: [string, string | null | undefined][] = [
    [t('employees.form.email'), employee.email],
    [t('employees.form.phone'), employee.phone],
    [t('employeeDetail.overview.altPhone'), employee.alternative_phone],
    [t('employeeDetail.overview.gender'), employee.gender],
    [t('employeeDetail.overview.dateOfBirth'), displayDate(employee.date_of_birth)],
    [t('employeeDetail.overview.nationality'), employee.nationality],
    [t('employeeDetail.overview.maritalStatus'), employee.marital_status],
    [t('employeeDetail.overview.address'), employee.residential_address],
    [t('employeeDetail.overview.emergencyContact'), employee.emergency_contact_name && employee.emergency_contact_phone
      ? `${employee.emergency_contact_name} (${employee.emergency_contact_phone})` : null],
  ];

  const employmentRows: [string, string | null | undefined][] = [
    [t('employees.table.department'), employee.department?.name],
    [t('employeeDetail.overview.designation'), employee.designation?.name],
    [t('employeeDetail.overview.reportingManager'), employee.reporting_manager?.name],
    [t('employeeDetail.overview.workLocation'), employee.work_location],
    [t('employeeDetail.overview.hireDate'), displayDate(employee.hire_date)],
    [t('employeeDetail.overview.confirmationDate'), displayDate(employee.confirmation_date)],
    [t('employeeDetail.overview.probationEnd'), displayDate(employee.probation_end_date)],
    [t('employeeDetail.overview.noticePeriod'), employee.notice_period_days ? `${employee.notice_period_days} days` : null],
  ];

  return (
    <Grid container spacing={2}>
      <Grid size={{ xs: 12, md: 6 }}>
        <Card variant="outlined">
          <CardContent>
            <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1.5 }}>
              {t('employeeDetail.overview.personalTitle')}
            </Typography>
            <Stack spacing={1}>
              {rows.map(([label, value]) => (
                <Stack key={label} direction="row" justifyContent="space-between">
                  <Typography variant="body2" color="text.secondary">{label}</Typography>
                  <Typography variant="body2">{value || '—'}</Typography>
                </Stack>
              ))}
            </Stack>
          </CardContent>
        </Card>
      </Grid>
      <Grid size={{ xs: 12, md: 6 }}>
        <Card variant="outlined">
          <CardContent>
            <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1.5 }}>
              {t('employeeDetail.overview.employmentTitle')}
            </Typography>
            <Stack spacing={1}>
              {employmentRows.map(([label, value]) => (
                <Stack key={label} direction="row" justifyContent="space-between">
                  <Typography variant="body2" color="text.secondary">{label}</Typography>
                  <Typography variant="body2">{value || '—'}</Typography>
                </Stack>
              ))}
            </Stack>
          </CardContent>
        </Card>
      </Grid>
      {employee.statutory_details && Object.keys(employee.statutory_details).length > 0 && (
        <Grid size={{ xs: 12 }}>
          <Card variant="outlined">
            <CardContent>
              <Typography variant="subtitle1" fontWeight={700} sx={{ mb: 1.5 }}>
                {t('employeeDetail.overview.statutoryTitle')}
              </Typography>
              <Grid container spacing={2}>
                {Object.entries(employee.statutory_details).map(([key, value]) => (
                  <Grid key={key} size={{ xs: 12, sm: 6, md: 4 }}>
                    <Typography variant="caption" color="text.secondary" sx={{ display: 'block' }}>{key}</Typography>
                    <Typography variant="body2">{value || '—'}</Typography>
                  </Grid>
                ))}
              </Grid>
            </CardContent>
          </Card>
        </Grid>
      )}
    </Grid>
  );
}

function SalaryTab({ employeeId }: { employeeId: number }) {
  const { t } = useTranslation('hr');
  const canViewSalary = usePermission('hr.employees.salary.view');
  const { data, isLoading, isError } = useQuery({
    queryKey: ['hr', 'employees', employeeId, 'salary'],
    queryFn: () => fetchEmployeeSalary(employeeId),
    enabled: canViewSalary,
  });

  if (!canViewSalary) {
    return <Alert severity="info">{t('employeeDetail.salary.noPermission')}</Alert>;
  }

  if (isLoading) return <CircularProgress />;
  if (isError || !data) return <Alert severity="error">{t('employeeDetail.salary.loadError')}</Alert>;

  const rows: [string, string][] = [
    [t('employeeDetail.salary.salary'), data.salary ? formatCurrency(Number(data.salary), data.pay_currency ?? undefined) : '—'],
    [t('employeeDetail.salary.paymentMethod'), t(`paymentMethods.${data.preferred_payment_method}`)],
    [t('employeeDetail.salary.bankName'), data.bank_name ?? '—'],
    [t('employeeDetail.salary.bankAccountName'), data.bank_account_name ?? '—'],
    [t('employeeDetail.salary.bankAccountNumber'), data.bank_account_number ?? '—'],
    [t('employeeDetail.salary.bankBranch'), data.bank_branch_name ?? '—'],
    [t('employeeDetail.salary.mobileMoneyProvider'), data.mobile_money_provider ?? '—'],
    [t('employeeDetail.salary.mobileMoneyNumber'), data.mobile_money_number ?? '—'],
    [t('employeeDetail.salary.nationalId'), data.national_id_number ?? '—'],
  ];

  return (
    <Card variant="outlined" sx={{ maxWidth: 480 }}>
      <CardContent>
        <Stack spacing={1.25}>
          {rows.map(([label, value]) => (
            <Stack key={label} direction="row" justifyContent="space-between">
              <Typography variant="body2" color="text.secondary">{label}</Typography>
              <Typography variant="body2" fontWeight={600}>{value}</Typography>
            </Stack>
          ))}
        </Stack>
      </CardContent>
    </Card>
  );
}

function DocumentsTab({ employee }: { employee: Employee }) {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const canManage = usePermission('hr.employees.documents.manage');
  const [uploadOpen, setUploadOpen] = useState(false);
  const [rejectTarget, setRejectTarget] = useState<number | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [pendingDelete, setPendingDelete] = useState<number | null>(null);
  const [documentType, setDocumentType] = useState<EmployeeDocumentType>('other');
  const [expiryDate, setExpiryDate] = useState('');
  const [file, setFile] = useState<File | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['hr', 'employees', employee.id, 'documents'],
    queryFn: () => fetchEmployeeDocuments(employee.id),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'employees', employee.id, 'documents'] });

  const uploadMutation = useMutation({
    mutationFn: () => uploadEmployeeDocument(employee.id, { document_type: documentType, file: file!, expiry_date: expiryDate || undefined }),
    onMutate: () => showToast(tc('toast.uploading'), 'info'),
    onSuccess: () => {
      invalidate();
      setUploadOpen(false);
      setFile(null);
      setExpiryDate('');
      showToast(t('toast.documentUploaded'));
    },
  });

  const verifyMutation = useMutation({
    mutationFn: verifyEmployeeDocument,
    onSuccess: () => {
      invalidate();
      showToast(t('toast.documentVerified'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, reason }: { id: number; reason: string }) => rejectEmployeeDocument(id, reason),
    onSuccess: () => {
      invalidate();
      setRejectTarget(null);
      setRejectReason('');
      showToast(t('toast.documentRejected'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteEmployeeDocument,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.documentDeleted'));
    },
  });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={2}>
      <Stack direction="row" justifyContent="flex-end">
        {canManage && (
          <Button variant="contained" startIcon={<UploadFileRoundedIcon />} onClick={() => setUploadOpen(true)}>
            {t('employeeDetail.documents.upload')}
          </Button>
        )}
      </Stack>

      {isLoading && <CircularProgress />}
      {!isLoading && rows.length === 0 && (
        <EmptyState title={t('employeeDetail.documents.empty.title')} description={t('employeeDetail.documents.empty.description')} />
      )}

      {rows.length > 0 && (
        <List disablePadding component={Card} variant="outlined">
          {rows.map((doc) => (
            <ListItem
              key={doc.id}
              divider
              secondaryAction={
                <Stack direction="row" spacing={0.5}>
                  <Tooltip title={tc('actions.download')}>
                    <IconButton size="small" component="a" href={doc.download_url} target="_blank" rel="noreferrer">
                      <DownloadRoundedIcon fontSize="small" />
                    </IconButton>
                  </Tooltip>
                  {canManage && doc.status === 'pending_verification' && (
                    <>
                      <Tooltip title={t('employeeDetail.documents.verify')}>
                        <IconButton size="small" color="success" onClick={() => verifyMutation.mutate(doc.id)}>
                          <CheckCircleRoundedIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={t('employeeDetail.documents.reject')}>
                        <IconButton size="small" color="error" onClick={() => setRejectTarget(doc.id)}>
                          <CloseRoundedIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </>
                  )}
                  {canManage && (
                    <Tooltip title={tc('actions.delete')}>
                      <IconButton size="small" onClick={() => setPendingDelete(doc.id)}>
                        <DeleteIcon fontSize="small" />
                      </IconButton>
                    </Tooltip>
                  )}
                </Stack>
              }
            >
              <ListItemIcon><DescriptionRoundedIcon /></ListItemIcon>
              <ListItemText
                primary={
                  <Stack direction="row" spacing={1} alignItems="center">
                    <Typography variant="body2" fontWeight={600}>{doc.file_name}</Typography>
                    <StatusChip status={doc.status} label={t(`documentStatuses.${doc.status}`)} />
                  </Stack>
                }
                secondary={`${t(`documentTypes.${doc.document_type}`)}${doc.expiry_date ? ` · ${t('employeeDetail.documents.expires')} ${doc.expiry_date.slice(0, 10)}` : ''}`}
              />
            </ListItem>
          ))}
        </List>
      )}

      <Dialog open={uploadOpen} onClose={() => setUploadOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('employeeDetail.documents.upload')}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField
              label={t('employeeDetail.documents.type')}
              select
              fullWidth
              value={documentType}
              onChange={(e) => setDocumentType(e.target.value as EmployeeDocumentType)}
            >
              {DOCUMENT_TYPES.map((type) => (
                <MenuItem key={type} value={type}>{t(`documentTypes.${type}`)}</MenuItem>
              ))}
            </TextField>
            <TextField
              label={t('employeeDetail.documents.expiryDate')}
              type="date"
              fullWidth
              slotProps={{ inputLabel: { shrink: true } }}
              value={expiryDate}
              onChange={(e) => setExpiryDate(e.target.value)}
            />
            <Button component="label" variant="outlined" startIcon={<UploadFileRoundedIcon />}>
              {file ? file.name : t('employeeDetail.documents.chooseFile')}
              <input type="file" hidden onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
            </Button>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setUploadOpen(false)}>{tc('actions.cancel')}</Button>
          <Button variant="contained" disabled={!file || uploadMutation.isPending} onClick={() => uploadMutation.mutate()}>
            {tc('actions.upload')}
          </Button>
        </DialogActions>
      </Dialog>

      <Dialog open={!!rejectTarget} onClose={() => setRejectTarget(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('employeeDetail.documents.reject')}</DialogTitle>
        <DialogContent>
          <TextField
            label={t('employeeDetail.documents.rejectReason')}
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
            onClick={() => rejectTarget && rejectMutation.mutate({ id: rejectTarget, reason: rejectReason })}
          >
            {t('employeeDetail.documents.reject')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('employeeDetail.documents.deleteDialog.title')}
        message={t('employeeDetail.documents.deleteDialog.message')}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}

export function EmployeeDetailPage() {
  const { t } = useTranslation('hr');
  const { id } = useParams<{ id: string }>();
  const employeeId = Number(id);
  const navigate = useNavigate();
  const [tab, setTab] = useState(0);

  const { data: employee, isLoading } = useQuery({
    queryKey: ['hr', 'employee', employeeId],
    queryFn: () => fetchEmployee(employeeId),
  });

  if (isLoading || !employee) {
    return <CircularProgress />;
  }

  return (
    <Stack spacing={3}>
      <Card variant="outlined">
        <CardContent>
          <Stack direction="row" spacing={2} alignItems="center">
            <Avatar sx={{ width: 56, height: 56 }}>{employee.name.charAt(0)}</Avatar>
            <Stack spacing={0.5} sx={{ flexGrow: 1 }}>
              <Stack direction="row" spacing={1} alignItems="center">
                <Typography variant="h6" fontWeight={700}>{employee.name}</Typography>
                <StatusChip status={employee.status} label={t(`statuses.${employee.status}`)} />
              </Stack>
              <Typography variant="body2" color="text.secondary">
                {employee.employee_number} · {employee.designation?.name ?? employee.job_title ?? '—'}
                {employee.department?.name ? ` · ${employee.department.name}` : ''}
              </Typography>
            </Stack>
            <Chip label={t(`employmentTypes.${employee.employment_type}`)} variant="outlined" />
            <Button
              size="small"
              variant="outlined"
              onClick={() => navigate(`/app/hr/contracts?employee_id=${employee.id}`)}
            >
              {t('employeeDetail.viewContracts')}
            </Button>
            <Button size="small" component={RouterLink} to="/app/hr/employees">
              {t('employeeDetail.backToList')}
            </Button>
          </Stack>
        </CardContent>
      </Card>

      <Tabs value={tab} onChange={(_, next) => setTab(next)}>
        <Tab label={t('employeeDetail.tabs.overview')} />
        <Tab label={t('employeeDetail.tabs.documents')} />
        <Tab label={t('employeeDetail.tabs.salary')} />
      </Tabs>

      {tab === 0 && <OverviewTab employee={employee} />}
      {tab === 1 && <DocumentsTab employee={employee} />}
      {tab === 2 && <SalaryTab employeeId={employee.id} />}
    </Stack>
  );
}
