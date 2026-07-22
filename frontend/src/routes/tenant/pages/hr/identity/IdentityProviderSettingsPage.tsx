import { Card, CardContent, Chip, CircularProgress, Grid, Stack, Typography } from '@mui/material';
import VerifiedRoundedIcon from '@mui/icons-material/VerifiedRounded';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchIdentityProviderSettings } from '../../../../../api/endpoints/identity';
import { HrTabs } from '../HrTabs';

function StatTile({ label, value }: { label: string; value: string | number }) {
  return (
    <Grid size={{ xs: 6, sm: 4, md: 2 }}>
      <Card variant="outlined">
        <CardContent>
          <Typography variant="caption" color="text.secondary">
            {label}
          </Typography>
          <Typography variant="h5" fontWeight={700}>
            {value}
          </Typography>
        </CardContent>
      </Card>
    </Grid>
  );
}

export function IdentityProviderSettingsPage() {
  const { t } = useTranslation('hr');
  const { data, isLoading } = useQuery({ queryKey: ['identity', 'provider-settings'], queryFn: fetchIdentityProviderSettings });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Typography variant="h6">{t('identity.providerSettings.title')}</Typography>

      {isLoading && <CircularProgress />}

      {data && (
        <Stack spacing={3}>
          <Card variant="outlined">
            <CardContent>
              <Stack direction="row" alignItems="center" spacing={2} flexWrap="wrap">
                <VerifiedRoundedIcon color={data.is_live ? 'success' : 'disabled'} fontSize="large" />
                <Stack>
                  <Typography variant="subtitle1" fontWeight={700}>
                    {t('identity.providerSettings.activeProvider')}: {data.provider_name}
                  </Typography>
                  {!data.is_live && (
                    <Stack direction="row" spacing={1} alignItems="center">
                      <Chip size="small" label={t('identity.testProviderBadge')} color="warning" />
                      <Typography variant="caption" color="text.secondary">
                        {t('identity.testProviderNote')}
                      </Typography>
                    </Stack>
                  )}
                </Stack>
              </Stack>
            </CardContent>
          </Card>

          <Grid container spacing={2}>
            <StatTile label={t('identity.providerSettings.totalRequests')} value={data.stats.total_requests} />
            <StatTile label={t('identity.providerSettings.successfulRequests')} value={data.stats.successful_requests} />
            <StatTile label={t('identity.providerSettings.failedRequests')} value={data.stats.failed_requests} />
            <StatTile
              label={t('identity.providerSettings.averageResponseTime')}
              value={data.stats.average_response_seconds !== null ? `${data.stats.average_response_seconds} ${t('identity.providerSettings.seconds')}` : '—'}
            />
            <StatTile
              label={t('identity.providerSettings.lastSuccessful')}
              value={data.stats.last_successful_at ? new Date(data.stats.last_successful_at).toLocaleString() : '—'}
            />
            <StatTile
              label={t('identity.providerSettings.lastFailed')}
              value={data.stats.last_failed_at ? new Date(data.stats.last_failed_at).toLocaleString() : '—'}
            />
          </Grid>
        </Stack>
      )}
    </Stack>
  );
}
