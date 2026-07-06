import { CircularProgress, Paper, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchAuditLogs } from '../../../api/endpoints/dashboard';
import { EmptyState } from '../../../components/common/EmptyState';

export function AuditLogPage() {
  const { t } = useTranslation('auditLog');
  const { data, isLoading } = useQuery({ queryKey: ['tenant', 'audit-logs'], queryFn: () => fetchAuditLogs() });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && <EmptyState title={t('empty.title')} description={t('empty.description')} />}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('table.action')}</TableCell>
                  <TableCell>{t('table.user')}</TableCell>
                  <TableCell>{t('table.ipAddress')}</TableCell>
                  <TableCell>{t('table.when')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((log) => (
                  <TableRow key={log.id}>
                    <TableCell>{log.action}</TableCell>
                    <TableCell>{log.user?.name ?? '—'}</TableCell>
                    <TableCell>{log.ip_address ?? '—'}</TableCell>
                    <TableCell>{new Date(log.created_at).toLocaleString()}</TableCell>
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
