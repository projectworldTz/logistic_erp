import {
  Alert,
  Box,
  Button,
  Chip,
  CircularProgress,
  Container,
  Divider,
  Link as MuiLink,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useNavigate, useParams } from 'react-router-dom';
import { fetchPublicTracking } from '../../api/endpoints/shipments';

const STATUS_COLOR: Record<string, 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  booked: 'default',
  in_transit: 'warning',
  arrived: 'info',
  cleared: 'success',
  delivered: 'success',
  cancelled: 'error',
};

export function TrackShipmentPage() {
  const { t } = useTranslation('tracking');
  const navigate = useNavigate();
  const { code } = useParams<{ code: string }>();
  const [input, setInput] = useState(code ?? '');

  const { data, isLoading, isError, isFetched } = useQuery({
    queryKey: ['public', 'track', code],
    queryFn: () => fetchPublicTracking(code!),
    enabled: !!code,
    retry: false,
  });

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (input.trim()) navigate(`/track/${input.trim()}`);
  };

  return (
    <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', bgcolor: 'background.default', py: 6 }}>
      <Container maxWidth="sm">
        <Paper variant="outlined" sx={{ p: 4 }}>
          <Stack spacing={3}>
            <Stack spacing={0.5}>
              <Typography variant="h5" fontWeight={700}>
                {t('title')}
              </Typography>
              <Typography variant="body2" color="text.secondary">
                {t('subtitle')}
              </Typography>
            </Stack>

            <Box component="form" onSubmit={onSubmit}>
              <Stack direction="row" spacing={1}>
                <TextField
                  label={t('form.trackingCode')}
                  fullWidth
                  autoFocus
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                />
                <Button type="submit" variant="contained" sx={{ flexShrink: 0 }}>
                  {t('form.trackButton')}
                </Button>
              </Stack>
            </Box>

            {isLoading && <CircularProgress />}

            {isError && isFetched && <Alert severity="error">{t('notFound')}</Alert>}

            {data && (
              <Stack spacing={2}>
                <Divider />
                <Stack direction="row" alignItems="center" spacing={1.5} flexWrap="wrap">
                  <Typography variant="h6" fontWeight={700}>
                    {t('result.shipmentLabel')} {data.shipment_number}
                  </Typography>
                  <Chip label={t(`statuses.${data.status}`)} size="small" color={STATUS_COLOR[data.status]} />
                </Stack>

                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('result.directionMode')}</Typography>
                  <Typography variant="body1">{t(`direction.${data.direction}`)} / {t(`mode.${data.mode}`)}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('result.route')}</Typography>
                  <Typography variant="body1">{data.origin_port ?? '—'} → {data.destination_port ?? '—'}</Typography>
                </Stack>
                <Stack direction="row" spacing={4}>
                  <Stack spacing={0.5}>
                    <Typography variant="body2" color="text.secondary">{t('result.etd')}</Typography>
                    <Typography variant="body1">{data.etd ?? '—'}</Typography>
                  </Stack>
                  <Stack spacing={0.5}>
                    <Typography variant="body2" color="text.secondary">{t('result.eta')}</Typography>
                    <Typography variant="body1">{data.eta ?? '—'}</Typography>
                  </Stack>
                </Stack>

                <Typography variant="subtitle1" fontWeight={700} sx={{ mt: 1 }}>
                  {t('result.timelineTitle')}
                </Typography>

                {data.milestones.length === 0 && (
                  <Typography variant="body2" color="text.secondary">{t('result.empty')}</Typography>
                )}

                {data.milestones.length > 0 && (
                  <Stack spacing={0} divider={<Divider />}>
                    {data.milestones.map((milestone, index) => (
                      <Stack key={index} direction="row" spacing={2} alignItems="flex-start" sx={{ py: 1.25 }}>
                        <Chip label={t(`eventTypes.${milestone.event_type}`)} size="small" color="primary" variant="outlined" sx={{ minWidth: 140 }} />
                        <Typography variant="body2">
                          {new Date(milestone.occurred_at).toLocaleString()}
                          {milestone.location ? ` — ${milestone.location}` : ''}
                        </Typography>
                      </Stack>
                    ))}
                  </Stack>
                )}
              </Stack>
            )}

            <Typography variant="body2" textAlign="center">
              <MuiLink component={RouterLink} to="/">
                {t('backToHome')}
              </MuiLink>
            </Typography>
          </Stack>
        </Paper>
      </Container>
    </Box>
  );
}
