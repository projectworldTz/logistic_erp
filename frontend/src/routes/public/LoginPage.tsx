import { zodResolver } from '@hookform/resolvers/zod';
import {
  Alert,
  Box,
  Button,
  Container,
  Link as MuiLink,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { useMutation } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { login } from '../../api/endpoints/auth';
import { useAuthStore } from '../../hooks/useAuth';

function buildSchema(t: (key: string) => string, tc: (key: string) => string) {
  return z.object({
    email: z.string().email(tc('validation.invalidEmail')),
    password: z.string().min(1, t('login.validation.passwordRequired')),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function LoginPage() {
  const { t } = useTranslation('auth');
  const { t: tc } = useTranslation('common');
  const navigate = useNavigate();
  const setSession = useAuthStore((s) => s.setSession);
  const schema = buildSchema(t, tc);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const mutation = useMutation({
    mutationFn: login,
    onSuccess: ({ token, user }) => {
      setSession(token, user);
      if (user.is_super_admin) {
        navigate('/platform');
      } else if (user.customer_id) {
        navigate('/portal/dashboard');
      } else {
        navigate('/app/dashboard');
      }
    },
  });

  const onSubmit = (values: FormValues) => mutation.mutate(values);

  return (
    <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', bgcolor: 'background.default' }}>
      <Container maxWidth="xs">
        <Paper variant="outlined" sx={{ p: 4 }}>
          <Stack spacing={3}>
            <Stack spacing={0.5}>
              <Typography variant="h5" fontWeight={700}>
                {t('login.title')}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {t('login.subtitle')}
              </Typography>
            </Stack>

            {mutation.isError && (
              <Alert severity="error">
                {t('login.invalidCredentials')}
              </Alert>
            )}

            <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
              <Stack spacing={2}>
                <TextField
                  label={t('login.form.email')}
                  type="email"
                  fullWidth
                  autoFocus
                  {...register('email')}
                  error={!!errors.email}
                  helperText={errors.email?.message}
                />
                <TextField
                  label={t('login.form.password')}
                  type="password"
                  fullWidth
                  {...register('password')}
                  error={!!errors.password}
                  helperText={errors.password?.message}
                />
                <Typography variant="body2" textAlign="right">
                  <MuiLink component={RouterLink} to="/forgot-password">
                    {t('login.forgotPassword')}
                  </MuiLink>
                </Typography>
                <Button type="submit" variant="contained" size="large" disabled={mutation.isPending}>
                  {mutation.isPending ? t('login.actions.loggingIn') : t('login.actions.logIn')}
                </Button>
              </Stack>
            </Box>

            <Typography variant="body2" textAlign="center">
              {t('login.noCompanyYet')}{' '}
              <MuiLink component={RouterLink} to="/register">
                {t('login.startFreeTrial')}
              </MuiLink>
            </Typography>
            <Typography variant="body2" textAlign="center">
              <MuiLink component={RouterLink} to="/">
                {t('login.backToHome')}
              </MuiLink>
            </Typography>
          </Stack>
        </Paper>
      </Container>
    </Box>
  );
}
