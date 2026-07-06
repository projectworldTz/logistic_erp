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
import SyncAltIcon from '@mui/icons-material/SyncAlt';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { convertLead, createLead, fetchLeads } from '../../../../api/endpoints/crm';
import type { Lead } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { CrmTabs } from './CrmTabs';

const STATUS_COLOR: Record<Lead['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  new: 'default',
  contacted: 'info',
  qualified: 'warning',
  converted: 'success',
  lost: 'error',
};

function buildSchema(t: (key: string) => string, tc: (key: string) => string) {
  return z.object({
    company_name: z.string().min(1, t('leads.validation.companyNameRequired')),
    contact_name: z.string().min(1, t('leads.validation.contactNameRequired')),
    email: z.string().email(tc('validation.invalidEmail')).optional().or(z.literal('')),
    phone: z.string().optional(),
    source: z.enum(['website', 'referral', 'cold_call', 'social', 'other']),
    notes: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function LeadsPage() {
  const { t } = useTranslation('crm');
  const { t: tc } = useTranslation('common');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const schema = buildSchema(t, tc);
  const [dialogOpen, setDialogOpen] = useState(false);
  const { data, isLoading } = useQuery({ queryKey: ['crm', 'leads'], queryFn: () => fetchLeads() });

  const invalidateLeads = () => queryClient.invalidateQueries({ queryKey: ['crm', 'leads'] });

  const createMutation = useMutation({
    mutationFn: createLead,
    onSuccess: () => {
      invalidateLeads();
      setDialogOpen(false);
    },
  });

  const convertMutation = useMutation({
    mutationFn: convertLead,
    onSuccess: (customer) => {
      invalidateLeads();
      queryClient.invalidateQueries({ queryKey: ['crm', 'customers'] });
      navigate(`/app/crm/customers/${customer.id}`);
    },
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { source: 'other' } });

  const onCreate = (values: FormValues) =>
    createMutation.mutate({ ...values, email: values.email || undefined });

  return (
    <Stack spacing={3}>
      <CrmTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('leads.title')}
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset();
            setDialogOpen(true);
          }}
        >
          {t('leads.newLead')}
        </Button>
      </Stack>

      {convertMutation.isError && <Alert severity="error">{t('leads.convertError')}</Alert>}

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && (
        <EmptyState title={t('leads.empty.title')} description={t('leads.empty.description')} />
      )}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('leads.table.company')}</TableCell>
                  <TableCell>{t('leads.table.contact')}</TableCell>
                  <TableCell>{t('leads.table.source')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((lead) => (
                  <TableRow key={lead.id}>
                    <TableCell>{lead.company_name}</TableCell>
                    <TableCell>{lead.contact_name}</TableCell>
                    <TableCell>{t(`leads.source.${lead.source}`)}</TableCell>
                    <TableCell>
                      <Chip label={t(`statuses.${lead.status}`)} size="small" color={STATUS_COLOR[lead.status]} />
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title={lead.status === 'converted' ? t('leads.tooltip.alreadyConverted') : t('leads.tooltip.convertToCustomer')}>
                        <span>
                          <IconButton
                            size="small"
                            disabled={lead.status === 'converted' || convertMutation.isPending}
                            onClick={() => convertMutation.mutate(lead.id)}
                          >
                            <SyncAltIcon fontSize="small" />
                          </IconButton>
                        </span>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('leads.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField label={t('leads.form.companyName')} fullWidth {...register('company_name')} error={!!errors.company_name} helperText={errors.company_name?.message} />
              <TextField label={t('leads.form.contactName')} fullWidth {...register('contact_name')} error={!!errors.contact_name} helperText={errors.contact_name?.message} />
              <TextField label={t('leads.form.email')} fullWidth {...register('email')} error={!!errors.email} helperText={errors.email?.message} />
              <TextField label={t('leads.form.phone')} fullWidth {...register('phone')} />
              <TextField label={t('leads.form.source')} select fullWidth defaultValue="other" {...register('source')}>
                <MenuItem value="website">{t('leads.source.website')}</MenuItem>
                <MenuItem value="referral">{t('leads.source.referral')}</MenuItem>
                <MenuItem value="cold_call">{t('leads.source.cold_call')}</MenuItem>
                <MenuItem value="social">{t('leads.source.social')}</MenuItem>
                <MenuItem value="other">{t('leads.source.other')}</MenuItem>
              </TextField>
              <TextField label={t('leads.form.notes')} fullWidth multiline rows={3} {...register('notes')} />
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
