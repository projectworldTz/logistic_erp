import { Alert, Box, Chip, CircularProgress, Container, Link as MuiLink, Paper, Stack, Typography } from '@mui/material';
import VerifiedIcon from '@mui/icons-material/Verified';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useParams } from 'react-router-dom';
import { fetchPayslipVerification } from '../../api/endpoints/verification';
import { formatCurrency } from '../../utils/currency';

export function VerifyPayslipPage() {
  const { t } = useTranslation('tracking');
  const { code } = useParams<{ code: string }>();

  const { data, isLoading, isError } = useQuery({
    queryKey: ['public', 'verify', 'payslip', code],
    queryFn: () => fetchPayslipVerification(code!),
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
                    {t('verify.payslipTitle')}
                  </Typography>
                </Stack>
                <Chip label={t('verify.genuine')} color="success" size="small" sx={{ alignSelf: 'flex-start' }} />

                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.payslipNumber')}</Typography>
                  <Typography variant="body1" fontWeight={600}>{data.payslip_number ?? '—'}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.employee')}</Typography>
                  <Typography variant="body1">{data.employee_name ?? '—'}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.period')}</Typography>
                  <Typography variant="body1">{data.period_name ?? '—'}</Typography>
                </Stack>
                <Stack spacing={0.5}>
                  <Typography variant="body2" color="text.secondary">{t('verify.netPay')}</Typography>
                  <Typography variant="body1" fontWeight={600}>{formatCurrency(Number(data.net_pay), data.currency)}</Typography>
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
