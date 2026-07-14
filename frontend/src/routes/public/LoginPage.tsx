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
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useNavigate } from 'react-router-dom';
import { z } from 'zod';
import { login, verifyTwoFactor } from '../../api/endpoints/auth';
import { useAuthStore } from '../../hooks/useAuth';
import { isTwoFactorChallenge } from '../../types';

function buildSchema(t: (key: string) => string, tc: (key: string) => string) {
  return z.object({
    email: z.string().email(tc('validation.invalidEmail')),
    password: z.string().min(1, t('login.validation.passwordRequired')),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

interface CodeFormValues {
  code: string;
}

function extractFieldError(error: unknown, field: string): string | undefined {
  if (typeof error !== 'object' || error === null || !('response' in error)) return undefined;
  const response = (error as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
  return response?.data?.errors?.[field]?.[0];
}

export function LoginPage() {
  const { t } = useTranslation('auth');
  const { t: tc } = useTranslation('common');
  const navigate = useNavigate();
  const setSession = useAuthStore((s) => s.setSession);
  const schema = buildSchema(t, tc);
  const [challengeToken, setChallengeToken] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema) });

  const {
    register: registerCode,
    handleSubmit: handleCodeSubmit,
    formState: { errors: codeErrors },
  } = useForm<CodeFormValues>();

  const redirectAfterLogin = (user: Parameters<typeof setSession>[1]) => {
    if (user.is_super_admin) {
      navigate('/platform');
    } else if (user.customer_id) {
      navigate('/portal/dashboard');
    } else {
      navigate('/app/dashboard');
    }
  };

  const mutation = useMutation({
    mutationFn: login,
    onSuccess: (result) => {
      if (isTwoFactorChallenge(result)) {
        setChallengeToken(result.challenge_token);
        return;
      }
      setSession(result.token, result.user);
      redirectAfterLogin(result.user);
    },
  });

  const verifyMutation = useMutation({
    mutationFn: verifyTwoFactor,
    onSuccess: ({ token, user }) => {
      setSession(token, user);
      redirectAfterLogin(user);
    },
  });

  const onSubmit = (values: FormValues) => mutation.mutate(values);
  const onCodeSubmit = (values: CodeFormValues) =>
    verifyMutation.mutate({ challenge_token: challengeToken!, code: values.code });

  const lockoutMessage = extractFieldError(mutation.error, 'email');

  if (challengeToken) {
    return (
      <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', bgcolor: 'background.default' }}>
        <Container maxWidth="xs">
          <Paper variant="outlined" sx={{ p: 4 }}>
            <Stack spacing={3}>
              <Stack spacing={0.5}>
                <Typography variant="h5" fontWeight={700}>
                  {t('login.twoFactor.title')}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {t('login.twoFactor.subtitle')}
                </Typography>
              </Stack>

              {verifyMutation.isError && <Alert severity="error">{t('login.twoFactor.invalidCode')}</Alert>}

              <Box component="form" onSubmit={handleCodeSubmit(onCodeSubmit)} noValidate>
                <Stack spacing={2}>
                  <TextField
                    label={t('login.twoFactor.codeLabel')}
                    fullWidth
                    autoFocus
                    inputProps={{ inputMode: 'numeric', maxLength: 12 }}
                    {...registerCode('code', { required: t('login.twoFactor.codeRequired') })}
                    error={!!codeErrors.code}
                    helperText={codeErrors.code?.message}
                  />
                  <Button type="submit" variant="contained" size="large" disabled={verifyMutation.isPending}>
                    {verifyMutation.isPending ? t('login.actions.loggingIn') : t('login.twoFactor.verify')}
                  </Button>
                  <Button variant="text" onClick={() => setChallengeToken(null)}>
                    {t('login.twoFactor.backToLogin')}
                  </Button>
                </Stack>
              </Box>
            </Stack>
          </Paper>
        </Container>
      </Box>
    );
  }

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
                {lockoutMessage ?? t('login.invalidCredentials')}
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
