import { CircularProgress, Paper, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchPlatformAuditLogs } from '../../../api/endpoints/platform';
import { EmptyState } from '../../../components/common/EmptyState';

export function AuditLogPage() {
  const { t } = useTranslation('superAdmin');
  const { data, isLoading } = useQuery({ queryKey: ['platform', 'audit-logs'], queryFn: () => fetchPlatformAuditLogs() });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('auditLog.title')}
      </Typography>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && <EmptyState title={t('auditLog.empty.title')} />}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('auditLog.table.action')}</TableCell>
                  <TableCell>{t('auditLog.table.user')}</TableCell>
                  <TableCell>{t('auditLog.table.ipAddress')}</TableCell>
                  <TableCell>{t('auditLog.table.when')}</TableCell>
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
