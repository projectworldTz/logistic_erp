import { Alert, Box, Chip, CircularProgress, Container, Link as MuiLink, Paper, Stack, Typography } from '@mui/material';
import VerifiedIcon from '@mui/icons-material/Verified';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useParams } from 'react-router-dom';
import { fetchReleaseOrderVerification } from '../../api/endpoints/verification';

export function VerifyReleaseOrderPage() {
  const { t } = useTranslation('tracking');
  const { token } = useParams<{ token: string }>();

  const { data, isLoading, isError } = useQuery({
    queryKey: ['public', 'verify', 'release-order', token],
    queryFn: () => fetchReleaseOrderVerification(token!),
    enabled: !!token,
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
                    {t('verify.releaseOrderTitle')}
                  </Typography>
                </Stack>
                <Chip label={t('verify.genuine')} color="success" size="small" sx={{ alignSelf: 'flex-start' }} />

                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.releaseOrderNumber')}</Typography>
                  <Typography variant="body1" fontWeight={600}>{data.release_order_number ?? '—'}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.reference')}</Typography>
                  <Typography variant="body1">{data.reference_no ?? '—'}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.status')}</Typography>
                  <Typography variant="body1">{data.status}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.customsOffice')}</Typography>
                  <Typography variant="body1">{data.customs_office ?? '—'}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.clearedDate')}</Typography>
                  <Typography variant="body1">{data.cleared_date ?? '—'}</Typography>
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
