import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { disableTwoFactor, enableTwoFactor, setupTwoFactor } from '../../../api/endpoints/auth';
import { useAuthStore } from '../../../hooks/useAuth';
import { useToast } from '../../../hooks/useToast';

interface EnableFormValues {
  code: string;
}

interface DisableFormValues {
  password: string;
}

export function AccountSecurityPage() {
  const { t } = useTranslation('security');
  const user = useAuthStore((s) => s.user);
  const updateUser = useAuthStore((s) => s.updateUser);
  const { showToast } = useToast();

  const [setupData, setSetupData] = useState<{ secret: string; qrSvg: string } | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  const [disableDialogOpen, setDisableDialogOpen] = useState(false);

  const {
    register: registerEnable,
    handleSubmit: handleEnableSubmit,
    reset: resetEnableForm,
    formState: { errors: enableErrors },
  } = useForm<EnableFormValues>();

  const {
    register: registerDisable,
    handleSubmit: handleDisableSubmit,
    reset: resetDisableForm,
    formState: { errors: disableErrors },
  } = useForm<DisableFormValues>();

  const setupMutation = useMutation({
    mutationFn: setupTwoFactor,
    onSuccess: (data) => setSetupData({ secret: data.secret, qrSvg: data.qr_svg }),
  });

  const enableMutation = useMutation({
    mutationFn: enableTwoFactor,
    onSuccess: (data) => {
      setRecoveryCodes(data.recovery_codes);
      setSetupData(null);
      resetEnableForm();
      if (user) updateUser({ ...user, two_factor_enabled: true });
    },
  });

  const disableMutation = useMutation({
    mutationFn: disableTwoFactor,
    onSuccess: () => {
      setDisableDialogOpen(false);
      resetDisableForm();
      showToast(t('disable.success'));
      if (user) updateUser({ ...user, two_factor_enabled: false });
    },
  });

  const onEnableSubmit = (values: EnableFormValues) => {
    if (!setupData) return;
    enableMutation.mutate({ secret: setupData.secret, code: values.code });
  };

  const onDisableSubmit = (values: DisableFormValues) => disableMutation.mutate(values.password);

  return (
    <Stack spacing={3} maxWidth={640}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <Stack spacing={2}>
        <Stack direction="row" spacing={1.5} alignItems="center">
          <Typography variant="h6">{t('twoFactor.title')}</Typography>
          <Chip
            size="small"
            label={user?.two_factor_enabled ? t('twoFactor.enabled') : t('twoFactor.disabled')}
            color={user?.two_factor_enabled ? 'success' : 'default'}
          />
        </Stack>
        <Typography variant="body2" color="text.secondary">
          {t('twoFactor.description')}
        </Typography>

        {recoveryCodes && (
          <Alert severity="warning">
            <Typography variant="subtitle2" gutterBottom>
              {t('recoveryCodes.title')}
            </Typography>
            <Typography variant="body2" gutterBottom>
              {t('recoveryCodes.description')}
            </Typography>
            <Stack direction="row" flexWrap="wrap" gap={1} sx={{ mt: 1 }}>
              {recoveryCodes.map((code) => (
                <Chip key={code} label={code} sx={{ fontFamily: 'monospace' }} />
              ))}
            </Stack>
            <Button size="small" sx={{ mt: 1 }} onClick={() => setRecoveryCodes(null)}>
              {t('recoveryCodes.dismiss')}
            </Button>
          </Alert>
        )}

        {!user?.two_factor_enabled && !setupData && !recoveryCodes && (
          <Box>
            <Button variant="contained" disabled={setupMutation.isPending} onClick={() => setupMutation.mutate()}>
              {setupMutation.isPending ? t('twoFactor.startingSetup') : t('twoFactor.enable')}
            </Button>
          </Box>
        )}

        {setupData && (
          <Stack spacing={2}>
            <Typography variant="body2">{t('setup.scanInstructions')}</Typography>
            <Box
              sx={{ width: 220, height: 220, '& svg': { width: '100%', height: '100%' } }}
              dangerouslySetInnerHTML={{ __html: setupData.qrSvg }}
            />
            <Typography variant="caption" color="text.secondary">
              {t('setup.manualEntry')}: <code>{setupData.secret}</code>
            </Typography>

            {enableMutation.isError && <Alert severity="error">{t('setup.invalidCode')}</Alert>}

            <Box component="form" onSubmit={handleEnableSubmit(onEnableSubmit)} noValidate>
              <Stack direction="row" spacing={2} alignItems="flex-start">
                <TextField
                  label={t('setup.codeLabel')}
                  {...registerEnable('code', { required: t('setup.codeRequired') })}
                  error={!!enableErrors.code}
                  helperText={enableErrors.code?.message}
                />
                <Button type="submit" variant="contained" disabled={enableMutation.isPending}>
                  {enableMutation.isPending ? t('setup.confirming') : t('setup.confirm')}
                </Button>
                <Button variant="text" onClick={() => setSetupData(null)}>
                  {t('setup.cancel')}
                </Button>
              </Stack>
            </Box>
          </Stack>
        )}

        {user?.two_factor_enabled && (
          <Box>
            <Button color="error" variant="outlined" onClick={() => setDisableDialogOpen(true)}>
              {t('twoFactor.disable')}
            </Button>
          </Box>
        )}
      </Stack>

      <Divider />

      <Dialog open={disableDialogOpen} onClose={() => setDisableDialogOpen(false)} maxWidth="xs" fullWidth>
        <DialogTitle>{t('disable.title')}</DialogTitle>
        <Box component="form" onSubmit={handleDisableSubmit(onDisableSubmit)} noValidate>
          <DialogContent>
            <Stack spacing={2}>
              <Typography variant="body2">{t('disable.description')}</Typography>
              {disableMutation.isError && <Alert severity="error">{t('disable.invalidPassword')}</Alert>}
              <TextField
                label={t('disable.passwordLabel')}
                type="password"
                fullWidth
                autoFocus
                {...registerDisable('password', { required: t('disable.passwordRequired') })}
                error={!!disableErrors.password}
                helperText={disableErrors.password?.message}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDisableDialogOpen(false)}>{t('disable.cancel')}</Button>
            <Button type="submit" color="error" variant="contained" disabled={disableMutation.isPending}>
              {disableMutation.isPending ? t('disable.disabling') : t('disable.confirm')}
            </Button>
          </DialogActions>
        </Box>
      </Dialog>
    </Stack>
  );
}
