import { zodResolver } from '@hookform/resolvers/zod';
import { Alert, Box, Button, Container, Link as MuiLink, Paper, Stack, TextField, Typography } from '@mui/material';
import { useMutation } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router-dom';
import { z } from 'zod';
import { forgotPassword } from '../../api/endpoints/auth';

function buildSchema(tc: (key: string) => string) {
  return z.object({
    email: z.string().email(tc('validation.invalidEmail')),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function ForgotPasswordPage() {
  const { t } = useTranslation('auth');
  const { t: tc } = useTranslation('common');
  const schema = buildSchema(tc);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const mutation = useMutation({ mutationFn: forgotPassword });

  const onSubmit = (values: FormValues) => mutation.mutate(values);

  return (
    <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', bgcolor: 'background.default' }}>
      <Container maxWidth="xs">
        <Paper variant="outlined" sx={{ p: 4 }}>
          <Stack spacing={3}>
            <Stack spacing={0.5}>
              <Typography variant="h5" fontWeight={700}>
                {t('forgotPassword.title')}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {t('forgotPassword.subtitle')}
              </Typography>
            </Stack>

            {mutation.isSuccess && <Alert severity="success">{mutation.data.message}</Alert>}

            {!mutation.isSuccess && (
              <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
                <Stack spacing={2}>
                  <TextField
                    label={t('forgotPassword.form.email')}
                    type="email"
                    fullWidth
                    autoFocus
                    {...register('email')}
                    error={!!errors.email}
                    helperText={errors.email?.message}
                  />
                  <Button type="submit" variant="contained" size="large" disabled={mutation.isPending}>
                    {mutation.isPending ? t('forgotPassword.actions.sending') : t('forgotPassword.actions.send')}
                  </Button>
                </Stack>
              </Box>
            )}

            <Typography variant="body2" textAlign="center">
              <MuiLink component={RouterLink} to="/login">
                {t('forgotPassword.backToLogin')}
              </MuiLink>
            </Typography>
          </Stack>
        </Paper>
      </Container>
    </Box>
  );
}
