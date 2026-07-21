import { Box, Button, Stack, TextField, Typography } from '@mui/material';
import { useMutation } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { type AxiosError } from 'axios';
import { changePassword } from '../../api/endpoints/auth';
import { useToast } from '../../hooks/useToast';

interface FormValues {
  current_password: string;
  password: string;
  password_confirmation: string;
}

interface ChangePasswordFormProps {
  /** i18n namespace that holds a `changePassword.*` key group — `security` for staff, `portal` for customer portal users. */
  namespace: string;
}

/**
 * Self-service password change, shared between the tenant staff "Account
 * Security" page and the customer portal's account page — both are the
 * same User model behind Sanctum, so the same form/endpoint serves both.
 */
export function ChangePasswordForm({ namespace }: ChangePasswordFormProps) {
  const { t } = useTranslation(namespace);
  const { showToast } = useToast();

  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors },
  } = useForm<FormValues>();

  const mutation = useMutation({
    mutationFn: changePassword,
    onSuccess: () => {
      reset();
      showToast(t('changePassword.success'));
    },
  });

  const serverError = (mutation.error as AxiosError<{ errors?: Record<string, string[]> }> | null)?.response?.data?.errors
    ?.current_password?.[0];

  const onSubmit = (values: FormValues) => mutation.mutate(values);

  return (
    <Stack spacing={2} maxWidth={420}>
      <Typography variant="h6">{t('changePassword.title')}</Typography>
      <Typography variant="body2" color="text.secondary">
        {t('changePassword.description')}
      </Typography>
      <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
        <Stack spacing={2}>
          <TextField
            label={t('changePassword.currentPassword')}
            type="password"
            fullWidth
            {...register('current_password', { required: true })}
            error={!!errors.current_password || !!serverError}
            helperText={serverError ?? (errors.current_password ? t('changePassword.currentPassword') : undefined)}
          />
          <TextField
            label={t('changePassword.newPassword')}
            type="password"
            fullWidth
            {...register('password', { required: true, minLength: 8 })}
            error={!!errors.password}
            helperText={errors.password ? t('changePassword.minLength') : undefined}
          />
          <TextField
            label={t('changePassword.confirmPassword')}
            type="password"
            fullWidth
            {...register('password_confirmation', {
              required: true,
              validate: (value) => value === watch('password') || (t('changePassword.passwordsDontMatch') as string),
            })}
            error={!!errors.password_confirmation}
            helperText={errors.password_confirmation?.message}
          />
          <Box>
            <Button type="submit" variant="contained" disabled={mutation.isPending}>
              {mutation.isPending ? t('changePassword.submitting') : t('changePassword.submit')}
            </Button>
          </Box>
        </Stack>
      </Box>
    </Stack>
  );
}
