import {
  Box,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  InputLabel,
  MenuItem,
  Pagination,
  Paper,
  Select,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Typography,
} from '@mui/material';
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { fetchErrorLogs, resolveErrorLog } from '../../../api/endpoints/platform';
import { EmptyState } from '../../../components/common/EmptyState';
import { useToast } from '../../../hooks/useToast';
import type { ErrorLogItem } from '../../../types';

type ResolvedFilter = 'all' | 'unresolved' | 'resolved';

export function ErrorLogsPage() {
  const { t } = useTranslation('superAdmin');
  const { showToast } = useToast();
  const queryClient = useQueryClient();

  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [resolvedFilter, setResolvedFilter] = useState<ResolvedFilter>('all');
  const [selected, setSelected] = useState<ErrorLogItem | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['platform', 'error-logs', page, search, resolvedFilter],
    queryFn: () =>
      fetchErrorLogs({
        page,
        q: search || undefined,
        resolved: resolvedFilter === 'all' ? undefined : resolvedFilter === 'resolved',
      }),
  });

  const resolveMutation = useMutation({
    mutationFn: resolveErrorLog,
    onSuccess: (updated) => {
      queryClient.invalidateQueries({ queryKey: ['platform', 'error-logs'] });
      setSelected(updated);
      showToast(t('errorLogs.toast.resolved'));
    },
  });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('errorLogs.title')}
      </Typography>

      <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
        <TextField
          size="small"
          label={t('errorLogs.filters.search')}
          value={search}
          onChange={(e) => {
            setPage(1);
            setSearch(e.target.value);
          }}
          sx={{ minWidth: 260 }}
        />
        <FormControl size="small" sx={{ minWidth: 200 }}>
          <InputLabel>{t('errorLogs.filters.status')}</InputLabel>
          <Select
            label={t('errorLogs.filters.status')}
            value={resolvedFilter}
            onChange={(e) => {
              setPage(1);
              setResolvedFilter(e.target.value as ResolvedFilter);
            }}
          >
            <MenuItem value="all">{t('errorLogs.filters.all')}</MenuItem>
            <MenuItem value="unresolved">{t('errorLogs.filters.unresolved')}</MenuItem>
            <MenuItem value="resolved">{t('errorLogs.filters.resolved')}</MenuItem>
          </Select>
        </FormControl>
      </Stack>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && (
        <EmptyState title={t('errorLogs.empty.title')} description={t('errorLogs.empty.description')} />
      )}

      {data && data.data.length > 0 && (
        <>
          <Paper variant="outlined">
            <TableContainer>
              <Table>
                <TableHead>
                  <TableRow>
                    <TableCell>{t('errorLogs.table.reference')}</TableCell>
                    <TableCell>{t('errorLogs.table.tenant')}</TableCell>
                    <TableCell>{t('errorLogs.table.exception')}</TableCell>
                    <TableCell>{t('errorLogs.table.message')}</TableCell>
                    <TableCell>{t('errorLogs.table.status')}</TableCell>
                    <TableCell>{t('errorLogs.table.when')}</TableCell>
                    <TableCell>{t('errorLogs.table.resolved')}</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {data.data.map((log) => (
                    <TableRow key={log.id} hover onClick={() => setSelected(log)} sx={{ cursor: 'pointer' }}>
                      <TableCell>
                        <Chip label={log.reference} size="small" variant="outlined" sx={{ fontFamily: 'monospace' }} />
                      </TableCell>
                      <TableCell>{log.tenant?.name ?? '—'}</TableCell>
                      <TableCell>{log.exception_class.split('\\').pop()}</TableCell>
                      <TableCell sx={{ maxWidth: 280 }}>
                        <Typography noWrap variant="body2">
                          {log.message}
                        </Typography>
                      </TableCell>
                      <TableCell>
                        <Chip label={log.status_code} size="small" color="error" />
                      </TableCell>
                      <TableCell>{new Date(log.created_at).toLocaleString()}</TableCell>
                      <TableCell>
                        <Chip
                          label={log.resolved_at ? t('errorLogs.resolvedYes') : t('errorLogs.resolvedNo')}
                          size="small"
                          color={log.resolved_at ? 'success' : 'default'}
                        />
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          </Paper>

          {data.meta.last_page > 1 && (
            <Box sx={{ display: 'flex', justifyContent: 'center' }}>
              <Pagination count={data.meta.last_page} page={page} onChange={(_, value) => setPage(value)} />
            </Box>
          )}
        </>
      )}

      <Dialog open={!!selected} onClose={() => setSelected(null)} maxWidth="md" fullWidth>
        {selected && (
          <>
            <DialogTitle>
              {t('errorLogs.detail.title')} — {selected.reference}
            </DialogTitle>
            <DialogContent dividers>
              <Stack spacing={2}>
                <Stack direction="row" spacing={4} flexWrap="wrap">
                  <Box>
                    <Typography variant="caption" color="text.secondary">
                      {t('errorLogs.table.tenant')}
                    </Typography>
                    <Typography variant="body2">{selected.tenant?.name ?? '—'}</Typography>
                  </Box>
                  <Box>
                    <Typography variant="caption" color="text.secondary">
                      {t('errorLogs.detail.user')}
                    </Typography>
                    <Typography variant="body2">
                      {selected.user ? `${selected.user.name} (${selected.user.email})` : '—'}
                    </Typography>
                  </Box>
                  <Box>
                    <Typography variant="caption" color="text.secondary">
                      {t('errorLogs.detail.request')}
                    </Typography>
                    <Typography variant="body2">
                      {selected.method} {selected.url}
                    </Typography>
                  </Box>
                  <Box>
                    <Typography variant="caption" color="text.secondary">
                      {t('errorLogs.detail.location')}
                    </Typography>
                    <Typography variant="body2">
                      {selected.file}:{selected.line}
                    </Typography>
                  </Box>
                  <Box>
                    <Typography variant="caption" color="text.secondary">
                      {t('errorLogs.detail.ipAddress')}
                    </Typography>
                    <Typography variant="body2">{selected.ip_address ?? '—'}</Typography>
                  </Box>
                </Stack>

                <Box>
                  <Typography variant="subtitle2" gutterBottom>
                    {selected.exception_class}
                  </Typography>
                  <Typography variant="body2" color="error.main">
                    {selected.message}
                  </Typography>
                </Box>

                {selected.trace && (
                  <Box>
                    <Typography variant="subtitle2" gutterBottom>
                      {t('errorLogs.detail.trace')}
                    </Typography>
                    <Box
                      component="pre"
                      sx={{
                        whiteSpace: 'pre-wrap',
                        overflowX: 'auto',
                        maxHeight: 300,
                        p: 1.5,
                        bgcolor: 'action.hover',
                        borderRadius: 1,
                        fontSize: '0.75rem',
                      }}
                    >
                      {selected.trace}
                    </Box>
                  </Box>
                )}

                {selected.request_payload && (
                  <Box>
                    <Typography variant="subtitle2" gutterBottom>
                      {t('errorLogs.detail.requestPayload')}
                    </Typography>
                    <Box
                      component="pre"
                      sx={{
                        whiteSpace: 'pre-wrap',
                        overflowX: 'auto',
                        maxHeight: 200,
                        p: 1.5,
                        bgcolor: 'action.hover',
                        borderRadius: 1,
                        fontSize: '0.75rem',
                      }}
                    >
                      {JSON.stringify(selected.request_payload, null, 2)}
                    </Box>
                  </Box>
                )}
              </Stack>
            </DialogContent>
            <DialogActions>
              <Button onClick={() => setSelected(null)}>{t('errorLogs.detail.close')}</Button>
              {!selected.resolved_at && (
                <Button
                  variant="contained"
                  disabled={resolveMutation.isPending}
                  onClick={() => resolveMutation.mutate(selected.id)}
                >
                  {t('errorLogs.detail.markResolved')}
                </Button>
              )}
            </DialogActions>
          </>
        )}
      </Dialog>
    </Stack>
  );
}
