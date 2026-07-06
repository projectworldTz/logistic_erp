import { zodResolver } from '@hookform/resolvers/zod';
import { Button, Stack, TextField, Typography } from '@mui/material';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { useRegistrationStore } from '../registrationStore';

const PASSWORD_MIN_LENGTH = 8;

function buildSchema(t: (key: string, options?: Record<string, unknown>) => string, tc: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('ownerAccount.validation.nameRequired')),
    email: z.string().email(tc('validation.invalidEmail')),
    phone: z.string().optional(),
    password: z.string().min(PASSWORD_MIN_LENGTH, t('ownerAccount.validation.passwordMin', { count: PASSWORD_MIN_LENGTH })),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

interface StepOwnerAccountProps {
  onNext: () => void;
  onBack: () => void;
}

export function StepOwnerAccount({ onNext, onBack }: StepOwnerAccountProps) {
  const { t } = useTranslation('registration');
  const { t: tc } = useTranslation('common');
  const schema = buildSchema(t, tc);
  const { owner, setOwner } = useRegistrationStore();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: owner,
  });

  const onSubmit = (values: FormValues) => {
    setOwner({ ...values, phone: values.phone ?? '' });
    onNext();
  };

  return (
    <Stack component="form" spacing={3} onSubmit={handleSubmit(onSubmit)} noValidate>
      <Typography variant="h5" fontWeight={700}>
        {t('ownerAccount.heading')}
      </Typography>
      <TextField label={t('ownerAccount.form.fullName')} fullWidth {...register('name')} error={!!errors.name} helperText={errors.name?.message} />
      <TextField label={t('ownerAccount.form.email')} type="email" fullWidth {...register('email')} error={!!errors.email} helperText={errors.email?.message} />
      <TextField label={t('ownerAccount.form.phoneOptional')} fullWidth {...register('phone')} />
      <TextField
        label={t('ownerAccount.form.password')}
        type="password"
        fullWidth
        {...register('password')}
        error={!!errors.password}
        helperText={errors.password?.message}
      />
      <Stack direction="row" justifyContent="space-between">
        <Button onClick={onBack}>{tc('actions.back')}</Button>
        <Button type="submit" variant="contained" size="large">
          {t('actions.continue')}
        </Button>
      </Stack>
    </Stack>
  );
}
