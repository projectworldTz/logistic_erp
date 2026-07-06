import { Alert, Button, Divider, Stack, Typography } from '@mui/material';
import { useMutation } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { registerTenant } from '../../../api/endpoints/tenants';
import { useAuthStore } from '../../../hooks/useAuth';
import { useRegistrationStore } from '../registrationStore';

interface StepReviewSubmitProps {
  onBack: () => void;
}

function ReviewRow({ label, value }: { label: string; value: string }) {
  return (
    <Stack direction="row" justifyContent="space-between">
      <Typography variant="body2" color="text.secondary">
        {label}
      </Typography>
      <Typography variant="body2" fontWeight={500}>
        {value}
      </Typography>
    </Stack>
  );
}

export function StepReviewSubmit({ onBack }: StepReviewSubmitProps) {
  const { t } = useTranslation('registration');
  const { t: tc } = useTranslation('common');
  const { planCode, owner, company, logo, reset } = useRegistrationStore();
  const setSession = useAuthStore((s) => s.setSession);
  const navigate = useNavigate();

  const mutation = useMutation({
    mutationFn: registerTenant,
    onSuccess: ({ token, user }) => {
      setSession(token, user);
      reset();
      navigate('/app/dashboard');
    },
  });

  const handleSubmit = () => {
    if (!planCode) return;

    mutation.mutate({
      plan_code: planCode,
      owner: { name: owner.name, email: owner.email, phone: owner.phone || undefined, password: owner.password },
      company: {
        name: company.name,
        registration_number: company.registration_number || undefined,
        tax_number: company.tax_number || undefined,
        country: company.country,
        city: company.city,
        address: company.address,
        currency: company.currency,
        timezone: company.timezone,
        industry: company.industry,
      },
      logo,
    });
  };

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('reviewSubmit.heading')}
      </Typography>

      {mutation.isError && (
        <Alert severity="error">
          {t('reviewSubmit.error')}
        </Alert>
      )}

      <Stack spacing={1}>
        <Typography variant="subtitle2">{t('reviewSubmit.sections.plan')}</Typography>
        <ReviewRow label={t('reviewSubmit.fields.selectedPlan')} value={planCode ?? '—'} />
      </Stack>

      <Divider />

      <Stack spacing={1}>
        <Typography variant="subtitle2">{t('reviewSubmit.sections.ownerAccount')}</Typography>
        <ReviewRow label={t('reviewSubmit.fields.name')} value={owner.name} />
        <ReviewRow label={t('reviewSubmit.fields.email')} value={owner.email} />
        {owner.phone && <ReviewRow label={t('reviewSubmit.fields.phone')} value={owner.phone} />}
      </Stack>

      <Divider />

      <Stack spacing={1}>
        <Typography variant="subtitle2">{t('reviewSubmit.sections.company')}</Typography>
        <ReviewRow label={t('reviewSubmit.fields.name')} value={company.name} />
        <ReviewRow label={t('reviewSubmit.fields.country')} value={company.country} />
        <ReviewRow label={t('reviewSubmit.fields.city')} value={company.city} />
        <ReviewRow label={t('reviewSubmit.fields.currency')} value={company.currency} />
        <ReviewRow label={t('reviewSubmit.fields.timezone')} value={company.timezone} />
        <ReviewRow label={t('reviewSubmit.fields.industry')} value={company.industry} />
        {logo && <ReviewRow label={t('reviewSubmit.fields.logo')} value={logo.name} />}
      </Stack>

      <Stack direction="row" justifyContent="space-between">
        <Button onClick={onBack} disabled={mutation.isPending}>
          {tc('actions.back')}
        </Button>
        <Button variant="contained" size="large" onClick={handleSubmit} disabled={mutation.isPending}>
          {mutation.isPending ? t('reviewSubmit.submitting') : t('reviewSubmit.submit')}
        </Button>
      </Stack>
    </Stack>
  );
}
