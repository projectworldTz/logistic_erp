import { CircularProgress, Grid, Stack, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchPortalDashboardSummary } from '../../../api/endpoints/portal';
import { fetchCompany } from '../../../api/endpoints/dashboard';
import { StatWidgetCard } from '../../../components/common/StatWidgetCard';
import { formatCurrency } from '../../../utils/currency';

export function PortalDashboardPage() {
  const { t } = useTranslation('portal');
  const { data, isLoading } = useQuery({
    queryKey: ['portal', 'dashboard', 'summary'],
    queryFn: fetchPortalDashboardSummary,
  });
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany, retry: false });

  if (isLoading || !data) {
    return <CircularProgress />;
  }

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('dashboard.title')}
      </Typography>

      <Grid container spacing={2}>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard label={t('dashboard.activeShipments')} value={data.active_shipments} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard label={t('dashboard.outstandingBalance')} value={formatCurrency(data.outstanding_balance, company?.currency)} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard label={t('dashboard.unreadMessages')} value={data.unread_messages} />
        </Grid>
        <Grid size={{ xs: 12, sm: 6, md: 3 }}>
          <StatWidgetCard label={t('dashboard.unreadNotifications')} value={data.unread_notifications} />
        </Grid>
      </Grid>
    </Stack>
  );
}
