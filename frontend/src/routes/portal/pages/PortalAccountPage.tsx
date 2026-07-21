import { Stack, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';
import { ChangePasswordForm } from '../../../components/common/ChangePasswordForm';

export function PortalAccountPage() {
  const { t } = useTranslation('portal');

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('account.title')}
      </Typography>

      <ChangePasswordForm namespace="portal" />
    </Stack>
  );
}
