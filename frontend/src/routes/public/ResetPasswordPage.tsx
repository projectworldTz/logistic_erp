import { zodResolver } from '@hookform/resolvers/zod';
import { Alert, Box, Button, Container, Link as MuiLink, Paper, Stack, TextField, Typography } from '@mui/material';
import { useMutation } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useNavigate, useSearchParams } from 'react-router-dom';
import { z } from 'zod';
import { resetPassword } from '../../api/endpoints/auth';
import { useToast } from '../../hooks/useToast';

function buildSchema(t: (key: string, opts?: Record<string, unknown>) => string) {
  return z
    .object({
      password: z.string().min(8, t('resetPassword.validation.passwordMin', { count: 8 })),
      password_confirmation: z.string().min(1, t('resetPassword.validation.confirmRequired')),
    })
    .refine((data) => data.password === data.password_confirmation, {
      message: t('resetPassword.validation.mustMatch'),
      path: ['password_confirmation'],
    });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function ResetPasswordPage() {
  const { t } = useTranslation('auth');
  const { showToast } = useToast();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const email = searchParams.get('email') ?? '';
  const token = searchParams.get('token') ?? '';
  const schema = buildSchema(t);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const mutation = useMutation({
    mutationFn: resetPassword,
    onSuccess: () => {
      showToast(t('resetPassword.success'));
      navigate('/login');
    },
  });

  const onSubmit = (values: FormValues) => mutation.mutate({ email, token, ...values });

  return (
    <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', bgcolor: 'background.default' }}>
      <Container maxWidth="xs">
        <Paper variant="outlined" sx={{ p: 4 }}>
          <Stack spacing={3}>
            <Stack spacing={0.5}>
              <Typography variant="h5" fontWeight={700}>
                {t('resetPassword.title')}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {t('resetPassword.subtitle')}
              </Typography>
            </Stack>

            {mutation.isError && (
              <Alert severity="error">
                {(mutation.error as { response?: { data?: { errors?: { email?: string[] } } } })?.response?.data
                  ?.errors?.email?.[0] ?? t('resetPassword.genericError')}
              </Alert>
            )}

            <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
              <Stack spacing={2}>
                <TextField
                  label={t('resetPassword.form.password')}
                  type="password"
                  fullWidth
                  autoFocus
                  {...register('password')}
                  error={!!errors.password}
                  helperText={errors.password?.message}
                />
                <TextField
                  label={t('resetPassword.form.confirmPassword')}
                  type="password"
                  fullWidth
                  {...register('password_confirmation')}
                  error={!!errors.password_confirmation}
                  helperText={errors.password_confirmation?.message}
                />
                <Button type="submit" variant="contained" size="large" disabled={mutation.isPending}>
                  {mutation.isPending ? t('resetPassword.actions.resetting') : t('resetPassword.actions.reset')}
                </Button>
              </Stack>
            </Box>

            <Typography variant="body2" textAlign="center">
              <MuiLink component={RouterLink} to="/login">
                {t('resetPassword.backToLogin')}
              </MuiLink>
            </Typography>
          </Stack>
        </Paper>
      </Container>
    </Box>
  );
}
