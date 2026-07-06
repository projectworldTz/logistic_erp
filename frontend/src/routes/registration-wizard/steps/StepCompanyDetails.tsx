import { zodResolver } from '@hookform/resolvers/zod';
import { Button, Grid, Stack, TextField, Typography } from '@mui/material';
import UploadFileIcon from '@mui/icons-material/UploadFile';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { useRegistrationStore } from '../registrationStore';

function buildSchema(t: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('companyDetails.validation.nameRequired')),
    registration_number: z.string().optional(),
    tax_number: z.string().optional(),
    country: z.string().min(1, t('companyDetails.validation.countryRequired')),
    city: z.string().min(1, t('companyDetails.validation.cityRequired')),
    address: z.string().min(1, t('companyDetails.validation.addressRequired')),
    currency: z.string().length(3, t('companyDetails.validation.currencyFormat')),
    timezone: z.string().min(1, t('companyDetails.validation.timezoneRequired')),
    industry: z.string().min(1, t('companyDetails.validation.industryRequired')),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

interface StepCompanyDetailsProps {
  onNext: () => void;
  onBack: () => void;
}

export function StepCompanyDetails({ onNext, onBack }: StepCompanyDetailsProps) {
  const { t } = useTranslation('registration');
  const { t: tc } = useTranslation('common');
  const schema = buildSchema(t);
  const { company, setCompany, logo, setLogo } = useRegistrationStore();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: company,
  });

  const onSubmit = (values: FormValues) => {
    setCompany({ ...values, registration_number: values.registration_number ?? '', tax_number: values.tax_number ?? '' });
    onNext();
  };

  return (
    <Stack component="form" spacing={3} onSubmit={handleSubmit(onSubmit)} noValidate>
      <Typography variant="h5" fontWeight={700}>
        {t('companyDetails.heading')}
      </Typography>
      <Grid container spacing={2}>
        <Grid size={12}>
          <TextField label={t('companyDetails.form.companyName')} fullWidth {...register('name')} error={!!errors.name} helperText={errors.name?.message} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6 }}>
          <TextField label={t('companyDetails.form.registrationNumberOptional')} fullWidth {...register('registration_number')} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6 }}>
          <TextField label={t('companyDetails.form.taxNumberOptional')} fullWidth {...register('tax_number')} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6 }}>
          <TextField label={t('companyDetails.form.country')} fullWidth {...register('country')} error={!!errors.country} helperText={errors.country?.message} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6 }}>
          <TextField label={t('companyDetails.form.city')} fullWidth {...register('city')} error={!!errors.city} helperText={errors.city?.message} />
        </Grid>
        <Grid size={12}>
          <TextField label={t('companyDetails.form.address')} fullWidth {...register('address')} error={!!errors.address} helperText={errors.address?.message} />
        </Grid>
        <Grid size={{ xs: 12, sm: 4 }}>
          <TextField
            label={t('companyDetails.form.currency')}
            fullWidth
            {...register('currency')}
            error={!!errors.currency}
            helperText={errors.currency?.message}
          />
        </Grid>
        <Grid size={{ xs: 12, sm: 4 }}>
          <TextField label={t('companyDetails.form.timezone')} fullWidth {...register('timezone')} error={!!errors.timezone} helperText={errors.timezone?.message} />
        </Grid>
        <Grid size={{ xs: 12, sm: 4 }}>
          <TextField label={t('companyDetails.form.industry')} fullWidth {...register('industry')} error={!!errors.industry} helperText={errors.industry?.message} />
        </Grid>
        <Grid size={12}>
          <Button component="label" variant="outlined" startIcon={<UploadFileIcon />}>
            {logo ? logo.name : t('companyDetails.form.uploadLogo')}
            <input
              type="file"
              accept="image/*"
              hidden
              onChange={(e) => setLogo(e.target.files?.[0] ?? null)}
            />
          </Button>
        </Grid>
      </Grid>
      <Stack direction="row" justifyContent="space-between">
        <Button onClick={onBack}>{tc('actions.back')}</Button>
        <Button type="submit" variant="contained" size="large">
          {t('actions.continue')}
        </Button>
      </Stack>
    </Stack>
  );
}
