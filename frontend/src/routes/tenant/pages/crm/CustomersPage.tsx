import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { createCustomer, fetchCustomers } from '../../../../api/endpoints/crm';
import type { Customer } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { CrmTabs } from './CrmTabs';

const STATUS_COLOR: Record<Customer['status'], 'success' | 'default'> = {
  active: 'success',
  inactive: 'default',
};

function buildSchema(t: (key: string) => string, tc: (key: string) => string) {
  return z.object({
    company_name: z.string().min(1, t('customers.validation.companyNameRequired')),
    industry: z.string().optional(),
    email: z.string().email(tc('validation.invalidEmail')).optional().or(z.literal('')),
    phone: z.string().optional(),
    address: z.string().optional(),
    city: z.string().optional(),
    country: z.string().optional(),
    currency: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function CustomersPage() {
  const { t } = useTranslation('crm');
  const { t: tc } = useTranslation('common');
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const schema = buildSchema(t, tc);
  const [dialogOpen, setDialogOpen] = useState(false);
  const { data, isLoading } = useQuery({ queryKey: ['crm', 'customers'], queryFn: () => fetchCustomers() });

  const createMutation = useMutation({
    mutationFn: createCustomer,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['crm', 'customers'] });
      setDialogOpen(false);
    },
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const onCreate = (values: FormValues) =>
    createMutation.mutate({ ...values, email: values.email || undefined });

  return (
    <Stack spacing={3}>
      <CrmTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('customers.title')}
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset();
            setDialogOpen(true);
          }}
        >
          {t('customers.newCustomer')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && (
        <EmptyState title={t('customers.empty.title')} description={t('customers.empty.description')} />
      )}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('customers.table.company')}</TableCell>
                  <TableCell>{t('customers.table.industry')}</TableCell>
                  <TableCell>{tc('labels.email')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((customer) => (
                  <TableRow
                    key={customer.id}
                    hover
                    sx={{ cursor: 'pointer' }}
                    onClick={() => navigate(`/app/crm/customers/${customer.id}`)}
                  >
                    <TableCell>{customer.company_name}</TableCell>
                    <TableCell>{customer.industry ?? '—'}</TableCell>
                    <TableCell>{customer.email ?? '—'}</TableCell>
                    <TableCell>
                      <Chip label={t(`statuses.${customer.status}`)} size="small" color={STATUS_COLOR[customer.status]} />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('customers.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField label={t('customers.form.companyName')} fullWidth {...register('company_name')} error={!!errors.company_name} helperText={errors.company_name?.message} />
              <TextField label={t('customers.form.industry')} fullWidth {...register('industry')} />
              <TextField label={t('customers.form.email')} fullWidth {...register('email')} error={!!errors.email} helperText={errors.email?.message} />
              <TextField label={t('customers.form.phone')} fullWidth {...register('phone')} />
              <TextField label={t('customers.form.address')} fullWidth {...register('address')} />
              <TextField label={t('customers.form.city')} fullWidth {...register('city')} />
              <TextField label={t('customers.form.country')} fullWidth {...register('country')} />
              <TextField label={t('customers.form.currency')} fullWidth {...register('currency')} />
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
