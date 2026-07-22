import {
  Alert,
  Avatar,
  Box,
  Button,
  Checkbox,
  CircularProgress,
  FormControlLabel,
  Grid,
  MenuItem,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import UploadIcon from '@mui/icons-material/Upload';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { fetchCompany, updateCompany, uploadCompanyLogo } from '../../../api/endpoints/dashboard';
import type { Company } from '../../../types';

type FormValues = Pick<
  Company,
  | 'name'
  | 'country'
  | 'city'
  | 'address'
  | 'currency'
  | 'usd_to_tzs_rate'
  | 'timezone'
  | 'industry'
  | 'registration_number'
  | 'tax_number'
  | 'phone'
  | 'email'
  | 'website'
  | 'primary_color'
  | 'secondary_color'
  | 'email_footer_text'
  | 'email_reply_to'
  | 'notify_email_enabled'
  | 'notify_sms_enabled'
  | 'notify_whatsapp_enabled'
  | 'require_identity_verification_before_payroll'
>;

const DEFAULT_PRIMARY = '#1a56db';
const DEFAULT_SECONDARY = '#0f766e';

export function CompanySettingsPage() {
  const { t } = useTranslation('settings');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { data: company, isLoading } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany });
  const logoInputRef = useRef<HTMLInputElement>(null);
  const [logoUploading, setLogoUploading] = useState(false);
  const [logoError, setLogoError] = useState<string | null>(null);

  const { register, control, handleSubmit, reset } = useForm<FormValues>();

  useEffect(() => {
    if (company) {
      reset({
        name: company.name,
        country: company.country,
        city: company.city,
        address: company.address,
        currency: company.currency,
        usd_to_tzs_rate: company.usd_to_tzs_rate,
        timezone: company.timezone,
        industry: company.industry,
        registration_number: company.registration_number ?? '',
        tax_number: company.tax_number ?? '',
        phone: company.phone ?? '',
        email: company.email ?? '',
        website: company.website ?? '',
        primary_color: company.primary_color ?? DEFAULT_PRIMARY,
        secondary_color: company.secondary_color ?? DEFAULT_SECONDARY,
        email_footer_text: company.email_footer_text ?? '',
        email_reply_to: company.email_reply_to ?? '',
        notify_email_enabled: company.notify_email_enabled,
        notify_sms_enabled: company.notify_sms_enabled,
        notify_whatsapp_enabled: company.notify_whatsapp_enabled,
        require_identity_verification_before_payroll: company.require_identity_verification_before_payroll,
      });
    }
  }, [company, reset]);

  const mutation = useMutation({
    mutationFn: updateCompany,
    onSuccess: (updated) => {
      queryClient.setQueryData(['tenant', 'company'], updated);
    },
  });

  const logoMutation = useMutation({
    mutationFn: uploadCompanyLogo,
    onSuccess: (updated) => {
      queryClient.setQueryData(['tenant', 'company'], updated);
    },
  });

  const handleLogoChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setLogoUploading(true);
    setLogoError(null);
    try {
      await logoMutation.mutateAsync(file);
    } catch {
      setLogoError(t('branding.logoUploadFailed'));
    } finally {
      setLogoUploading(false);
      event.target.value = '';
    }
  };

  if (isLoading) return <CircularProgress />;

  return (
    <Stack spacing={3} maxWidth={640}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      {mutation.isSuccess && <Alert severity="success">{t('saveSuccess')}</Alert>}

      <Typography variant="h6">{t('branding.title')}</Typography>

      <Stack direction="row" spacing={2} alignItems="center">
        {company?.logo_url ? (
          <Avatar src={company.logo_url} variant="rounded" sx={{ width: 64, height: 64 }} />
        ) : (
          <Box
            sx={{
              width: 64,
              height: 64,
              borderRadius: 1,
              border: '1px dashed',
              borderColor: 'divider',
            }}
          />
        )}
        <Button
          size="small"
          variant="outlined"
          startIcon={<UploadIcon />}
          disabled={logoUploading}
          onClick={() => logoInputRef.current?.click()}
        >
          {logoUploading ? t('branding.uploading') : t('branding.uploadLogo')}
        </Button>
        <input ref={logoInputRef} type="file" accept="image/*" hidden onChange={handleLogoChange} />
      </Stack>
      {logoError && <Alert severity="error">{logoError}</Alert>}

      <Stack component="form" spacing={2} onSubmit={handleSubmit((values) => mutation.mutate(values))}>
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, sm: 6 }}>
            <TextField
              label={t('branding.primaryColor')}
              type="color"
              fullWidth
              {...register('primary_color')}
              slotProps={{ htmlInput: { style: { height: 40 } } }}
            />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <TextField
              label={t('branding.secondaryColor')}
              type="color"
              fullWidth
              {...register('secondary_color')}
              slotProps={{ htmlInput: { style: { height: 40 } } }}
            />
          </Grid>
        </Grid>

        <Typography variant="h6">{t('emailTemplate.title')}</Typography>
        <Typography variant="caption" color="text.secondary">
          {t('emailTemplate.help')}
        </Typography>
        <Grid container spacing={2}>
          <Grid size={12}>
            <TextField
              label={t('emailTemplate.replyTo')}
              type="email"
              fullWidth
              {...register('email_reply_to')}
            />
          </Grid>
          <Grid size={12}>
            <TextField
              label={t('emailTemplate.footerText')}
              fullWidth
              multiline
              minRows={2}
              {...register('email_footer_text')}
            />
          </Grid>
        </Grid>

        <Typography variant="h6">{t('notifications.title')}</Typography>
        <Stack direction="row" spacing={2} flexWrap="wrap">
          <FormControlLabel
            control={
              <Controller
                name="notify_email_enabled"
                control={control}
                render={({ field }) => (
                  <Checkbox checked={!!field.value} onChange={(e) => field.onChange(e.target.checked)} />
                )}
              />
            }
            label={t('notifications.email')}
          />
          <FormControlLabel
            control={
              <Controller
                name="notify_sms_enabled"
                control={control}
                render={({ field }) => (
                  <Checkbox checked={!!field.value} onChange={(e) => field.onChange(e.target.checked)} />
                )}
              />
            }
            label={t('notifications.sms')}
          />
          <FormControlLabel
            control={
              <Controller
                name="notify_whatsapp_enabled"
                control={control}
                render={({ field }) => (
                  <Checkbox checked={!!field.value} onChange={(e) => field.onChange(e.target.checked)} />
                )}
              />
            }
            label={t('notifications.whatsapp')}
          />
        </Stack>
        <Typography variant="caption" color="text.secondary">
          {t('notifications.help')}
        </Typography>

        <Typography variant="h6">{t('identity.title')}</Typography>
        <Stack direction="row" spacing={2} flexWrap="wrap">
          <FormControlLabel
            control={
              <Controller
                name="require_identity_verification_before_payroll"
                control={control}
                render={({ field }) => (
                  <Checkbox checked={!!field.value} onChange={(e) => field.onChange(e.target.checked)} />
                )}
              />
            }
            label={t('identity.requireBeforePayroll')}
          />
        </Stack>
        <Typography variant="caption" color="text.secondary">
          {t('identity.help')}
        </Typography>

        <Typography variant="h6">{t('currency.title')}</Typography>
        <Typography variant="caption" color="text.secondary">
          {t('currency.help')}
        </Typography>
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Controller
              name="currency"
              control={control}
              render={({ field }) => (
                <TextField select label={t('currency.systemCurrency')} fullWidth {...field} value={field.value ?? ''}>
                  <MenuItem value="TZS">TZS — Tanzanian Shilling</MenuItem>
                  <MenuItem value="USD">USD — US Dollar</MenuItem>
                </TextField>
              )}
            />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <TextField
              label={t('currency.rate')}
              type="number"
              fullWidth
              inputProps={{ step: '0.0001', min: '0.0001' }}
              helperText={t('currency.rateHelp')}
              {...register('usd_to_tzs_rate', { valueAsNumber: true })}
            />
          </Grid>
        </Grid>

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
          <Grid size={{ xs: 12, sm: 6 }}>
            <TextField label={t('fields.timezone')} fullWidth {...register('timezone')} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
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
