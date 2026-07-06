import { Alert, Avatar, Button, CircularProgress, Grid, Stack, TextField, Typography } from '@mui/material';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { fetchCompany, updateCompany } from '../../../api/endpoints/dashboard';
import type { Company } from '../../../types';

type FormValues = Pick<
  Company,
  | 'name'
  | 'country'
  | 'city'
  | 'address'
  | 'currency'
  | 'timezone'
  | 'industry'
  | 'registration_number'
  | 'tax_number'
  | 'phone'
  | 'email'
  | 'website'
>;

export function CompanySettingsPage() {
  const { t } = useTranslation('settings');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { data: company, isLoading } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany });

  const { register, handleSubmit, reset } = useForm<FormValues>();

  useEffect(() => {
    if (company) {
      reset({
        name: company.name,
        country: company.country,
        city: company.city,
        address: company.address,
        currency: company.currency,
        timezone: company.timezone,
        industry: company.industry,
        registration_number: company.registration_number ?? '',
        tax_number: company.tax_number ?? '',
        phone: company.phone ?? '',
        email: company.email ?? '',
        website: company.website ?? '',
      });
    }
  }, [company, reset]);

  const mutation = useMutation({
    mutationFn: updateCompany,
    onSuccess: (updated) => {
      queryClient.setQueryData(['tenant', 'company'], updated);
    },
  });

  if (isLoading) return <CircularProgress />;

  return (
    <Stack spacing={3} maxWidth={640}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      {mutation.isSuccess && <Alert severity="success">{t('saveSuccess')}</Alert>}

      {company?.logo_url && (
        <Stack direction="row" spacing={2} alignItems="center">
          <Avatar src={company.logo_url} variant="rounded" sx={{ width: 64, height: 64 }} />
          <Typography variant="body2" color="text.secondary">
            {t('logoCaption')}
          </Typography>
        </Stack>
      )}

      <Stack component="form" spacing={2} onSubmit={handleSubmit((values) => mutation.mutate(values))}>
        <Grid container spacing={2}>
          <Grid size={12}>
            <TextField label={t('fields.companyName')} fullWidth {...register('name')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <TextField label={t('fields.country')} fullWidth {...register('country')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <TextField label={t('fields.city')} fullWidth {...register('city')} />
          </Grid>
          <Grid size={12}>
            <TextField label={t('fields.address')} fullWidth {...register('address')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 4 }}>
            <TextField label={tc('labels.currency')} fullWidth {...register('currency')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 4 }}>
            <TextField label={t('fields.timezone')} fullWidth {...register('timezone')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 4 }}>
            <TextField label={t('fields.industry')} fullWidth {...register('industry')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <TextField label={t('fields.registrationNumber')} fullWidth {...register('registration_number')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <TextField label={t('fields.taxNumber')} fullWidth {...register('tax_number')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 4 }}>
            <TextField label={tc('labels.phone')} fullWidth {...register('phone')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 4 }}>
            <TextField label={tc('labels.email')} type="email" fullWidth {...register('email')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 4 }}>
            <TextField label={t('fields.website')} fullWidth {...register('website')} />
          </Grid>
        </Grid>
        <Stack direction="row" justifyContent="flex-end">
          <Button type="submit" variant="contained" disabled={mutation.isPending}>
            {mutation.isPending ? t('saving') : t('saveChanges')}
          </Button>
        </Stack>
      </Stack>
    </Stack>
  );
}
