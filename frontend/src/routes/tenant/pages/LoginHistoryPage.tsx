import {
  Chip,
  CircularProgress,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchLoginHistory } from '../../../api/endpoints/dashboard';
import { EmptyState } from '../../../components/common/EmptyState';

export function LoginHistoryPage() {
  const { t } = useTranslation('security');
  const { data, isLoading } = useQuery({ queryKey: ['tenant', 'login-history'], queryFn: () => fetchLoginHistory() });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('loginHistory.title')}
      </Typography>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && (
        <EmptyState title={t('loginHistory.empty.title')} description={t('loginHistory.empty.description')} />
      )}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('loginHistory.table.email')}</TableCell>
                  <TableCell>{t('loginHistory.table.status')}</TableCell>
                  <TableCell>{t('loginHistory.table.reason')}</TableCell>
                  <TableCell>{t('loginHistory.table.ipAddress')}</TableCell>
                  <TableCell>{t('loginHistory.table.when')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((attempt) => (
                  <TableRow key={attempt.id}>
                    <TableCell>{attempt.email}</TableCell>
                    <TableCell>
                      <Chip
                        size="small"
                        label={attempt.successful ? t('loginHistory.status.success') : t('loginHistory.status.failed')}
                        color={attempt.successful ? 'success' : 'error'}
                      />
                    </TableCell>
                    <TableCell>{attempt.reason ?? '—'}</TableCell>
                    <TableCell>{attempt.ip_address ?? '—'}</TableCell>
                    <TableCell>{new Date(attempt.created_at).toLocaleString()}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}
    </Stack>
  );
}
