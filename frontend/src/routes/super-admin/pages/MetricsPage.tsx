import { CircularProgress, Grid, Stack, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchPlatformMetrics } from '../../../api/endpoints/platform';
import { StatWidgetCard } from '../../../components/common/StatWidgetCard';

export function MetricsPage() {
  const { t } = useTranslation('superAdmin');
  const { data, isLoading } = useQuery({ queryKey: ['platform', 'metrics'], queryFn: fetchPlatformMetrics });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('metrics.title')}
      </Typography>

      {isLoading && <CircularProgress />}

      {data && (
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <StatWidgetCard label={t('metrics.cards.totalTenants')} value={data.tenant_count} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <StatWidgetCard label={t('metrics.cards.activeTenants')} value={data.active_tenant_count} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <StatWidgetCard label={t('metrics.cards.trialTenants')} value={data.trial_tenant_count} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <StatWidgetCard label={t('metrics.cards.activeUsers')} value={data.active_users} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <StatWidgetCard label={t('metrics.cards.revenueMtd')} value={`$${data.revenue_mtd}`} />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 4 }}>
            <StatWidgetCard label={t('metrics.cards.storageUsed')} value={`${data.storage_used_mb} MB`} />
          </Grid>
        </Grid>
      )}
    </Stack>
  );
}
