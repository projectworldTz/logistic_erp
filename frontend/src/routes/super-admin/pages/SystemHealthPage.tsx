import { Card, CardContent, Chip, CircularProgress, Grid, Stack, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { fetchSystemHealth } from '../../../api/endpoints/platform';

const STATUS_COLOR: Record<string, 'success' | 'warning' | 'error' | 'default'> = {
  ok: 'success',
  warning: 'warning',
  down: 'error',
  unknown: 'default',
};

interface HealthCardProps {
  title: string;
  status: string;
  rows: { label: string; value: string | number }[];
}

function HealthCard({ title, status, rows }: HealthCardProps) {
  return (
    <Card variant="outlined" sx={{ height: '100%' }}>
      <CardContent>
        <Stack spacing={1.5}>
          <Stack direction="row" justifyContent="space-between" alignItems="center">
            <Typography variant="subtitle1" fontWeight={700}>
              {title}
            </Typography>
            <Chip size="small" label={status.toUpperCase()} color={STATUS_COLOR[status] ?? 'default'} />
          </Stack>
          <Stack spacing={0.75}>
            {rows.map((row) => (
              <Stack key={row.label} direction="row" justifyContent="space-between">
                <Typography variant="body2" color="text.secondary">
                  {row.label}
                </Typography>
                <Typography variant="body2" fontWeight={600} fontFamily="monospace">
                  {row.value}
                </Typography>
              </Stack>
            ))}
          </Stack>
        </Stack>
      </CardContent>
    </Card>
  );
}

export function SystemHealthPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['platform', 'system-health'],
    queryFn: fetchSystemHealth,
    refetchInterval: 30000,
  });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        System Health
      </Typography>

      {isLoading && <CircularProgress />}

      {data && (
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <HealthCard
              title="Database"
              status={data.database.status}
              rows={[{ label: 'Response time', value: `${data.database.response_ms ?? '—'} ms` }]}
            />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <HealthCard
              title="Cache"
              status={data.cache.status}
              rows={[{ label: 'Driver', value: data.cache.driver ?? '—' }]}
            />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <HealthCard
              title="Queue"
              status={data.queue.status}
              rows={[
                { label: 'Pending jobs', value: data.queue.pending_jobs ?? '—' },
                { label: 'Failed jobs', value: data.queue.failed_jobs ?? '—' },
              ]}
            />
          </Grid>
          <Grid size={{ xs: 12, sm: 6, md: 3 }}>
            <HealthCard
              title="Storage"
              status={data.storage.status}
              rows={[
                { label: 'Used', value: `${data.storage.used_percent ?? '—'}%` },
                { label: 'Free', value: `${data.storage.free_gb ?? '—'} GB` },
                { label: 'Total', value: `${data.storage.total_gb ?? '—'} GB` },
              ]}
            />
          </Grid>
          <Grid size={12}>
            <Card variant="outlined">
              <CardContent>
                <Stack spacing={1.5}>
                  <Typography variant="subtitle1" fontWeight={700}>
                    Application
                  </Typography>
                  <Grid container spacing={2}>
                    <Grid size={{ xs: 6, sm: 4, md: 2 }}>
                      <Typography variant="caption" color="text.secondary">Environment</Typography>
                      <Typography variant="body2" fontWeight={600}>{data.app.environment}</Typography>
                    </Grid>
                    <Grid size={{ xs: 6, sm: 4, md: 2 }}>
                      <Typography variant="caption" color="text.secondary">PHP</Typography>
                      <Typography variant="body2" fontWeight={600}>{data.app.php_version}</Typography>
                    </Grid>
                    <Grid size={{ xs: 6, sm: 4, md: 2 }}>
                      <Typography variant="caption" color="text.secondary">Laravel</Typography>
                      <Typography variant="body2" fontWeight={600}>{data.app.laravel_version}</Typography>
                    </Grid>
                    <Grid size={{ xs: 6, sm: 4, md: 2 }}>
                      <Typography variant="caption" color="text.secondary">Debug Mode</Typography>
                      <Chip
                        size="small"
                        label={data.app.debug_mode ? 'ON' : 'OFF'}
                        color={data.app.debug_mode ? 'warning' : 'success'}
                      />
                    </Grid>
                    <Grid size={{ xs: 6, sm: 4, md: 2 }}>
                      <Typography variant="caption" color="text.secondary">Mailer</Typography>
                      <Typography variant="body2" fontWeight={600}>{data.app.mailer}</Typography>
                    </Grid>
                    <Grid size={{ xs: 6, sm: 4, md: 2 }}>
                      <Typography variant="caption" color="text.secondary">Queue Connection</Typography>
                      <Typography variant="body2" fontWeight={600}>{data.app.queue_connection}</Typography>
                    </Grid>
                  </Grid>
                </Stack>
              </CardContent>
            </Card>
          </Grid>
        </Grid>
      )}
    </Stack>
  );
}
