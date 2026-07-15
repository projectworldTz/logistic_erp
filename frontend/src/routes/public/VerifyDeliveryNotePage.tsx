import { Alert, Box, Chip, CircularProgress, Container, Link as MuiLink, Paper, Stack, Typography } from '@mui/material';
import VerifiedIcon from '@mui/icons-material/Verified';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useParams } from 'react-router-dom';
import { fetchDeliveryNoteVerification } from '../../api/endpoints/verification';

export function VerifyDeliveryNotePage() {
  const { t } = useTranslation('tracking');
  const { code } = useParams<{ code: string }>();

  const { data, isLoading, isError } = useQuery({
    queryKey: ['public', 'verify', 'delivery-note', code],
    queryFn: () => fetchDeliveryNoteVerification(code!),
    enabled: !!code,
    retry: false,
  });

  return (
    <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', bgcolor: 'background.default', py: 6 }}>
      <Container maxWidth="sm">
        <Paper variant="outlined" sx={{ p: 4 }}>
          <Stack spacing={3}>
            {isLoading && <CircularProgress />}

            {isError && <Alert severity="error">{t('verify.notFound')}</Alert>}

            {data && (
              <Stack spacing={2}>
                <Stack direction="row" alignItems="center" spacing={1.5}>
                  <VerifiedIcon color="success" fontSize="large" />
                  <Typography variant="h5" fontWeight={700}>
                    {t('verify.deliveryNoteTitle')}
                  </Typography>
                </Stack>
                <Chip label={t('verify.genuine')} color="success" size="small" sx={{ alignSelf: 'flex-start' }} />

                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('result.shipmentLabel')}</Typography>
                  <Typography variant="body1" fontWeight={600}>{data.shipment_number ?? '—'}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.destination')}</Typography>
                  <Typography variant="body1">{data.destination_port ?? '—'}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.deliveredAt')}</Typography>
                  <Typography variant="body1">{data.delivered_at ? new Date(data.delivered_at).toLocaleString() : '—'}</Typography>
                </Stack>
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
