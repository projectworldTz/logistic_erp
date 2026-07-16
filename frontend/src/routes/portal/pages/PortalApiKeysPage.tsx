import {
  Alert,
  Box,
  Button,
  Chip,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { createPortalApiKey, fetchPortalApiKeys, revokePortalApiKey } from '../../../api/endpoints/portal';
import { ConfirmDialog } from '../../../components/common/ConfirmDialog';
import { EmptyState } from '../../../components/common/EmptyState';
import { useToast } from '../../../hooks/useToast';

interface FormValues {
  name: string;
}

export function PortalApiKeysPage() {
  const { t } = useTranslation('portal');
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingRevoke, setPendingRevoke] = useState<{ id: number; name: string } | null>(null);
  const [newKey, setNewKey] = useState<string | null>(null);

  const { data: keys, isLoading } = useQuery({ queryKey: ['portal', 'api-keys'], queryFn: fetchPortalApiKeys });

  const { register, handleSubmit, reset } = useForm<FormValues>();

  const createMutation = useMutation({
    mutationFn: (values: FormValues) => createPortalApiKey(values.name),
    onSuccess: ({ plaintextKey }) => {
      queryClient.invalidateQueries({ queryKey: ['portal', 'api-keys'] });
      setDialogOpen(false);
      setNewKey(plaintextKey);
    },
  });

  const revokeMutation = useMutation({
    mutationFn: revokePortalApiKey,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal', 'api-keys'] });
      setPendingRevoke(null);
      showToast(t('apiKeys.toast.revoked'));
    },
  });

  const copyKey = async () => {
    if (!newKey) return;
    await navigator.clipboard.writeText(newKey);
    showToast(t('apiKeys.toast.copied'));
  };

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('apiKeys.title')}
        </Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={() => { reset(); setDialogOpen(true); }}>
          {t('apiKeys.generate')}
        </Button>
      </Stack>

      <Typography variant="body2" color="text.secondary">
        {t('apiKeys.subtitle')}
      </Typography>

      <Paper variant="outlined" sx={{ p: 2 }}>
        <Typography variant="subtitle2" gutterBottom>
          {t('apiKeys.gettingStarted.title')}
        </Typography>
        <Typography variant="body2" color="text.secondary" gutterBottom>
          {t('apiKeys.gettingStarted.description')}
        </Typography>
        <Box
          component="pre"
          sx={{
            bgcolor: 'action.hover',
            p: 1.5,
            borderRadius: 1,
            fontSize: 12,
            overflowX: 'auto',
            m: 0,
          }}
        >
          {`curl -H "Authorization: Bearer <your-api-key>" \\\n  ${window.location.origin}/api/v1/client-api/shipments`}
        </Box>
      </Paper>

      {isLoading && <EmptyState title="" />}

      {keys && keys.length === 0 && <EmptyState title={t('apiKeys.empty')} />}

      {keys && keys.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('apiKeys.fields.name')}</TableCell>
                  <TableCell>{t('apiKeys.fields.key')}</TableCell>
                  <TableCell>{t('apiKeys.fields.lastUsed')}</TableCell>
                  <TableCell>{t('apiKeys.fields.status')}</TableCell>
                  <TableCell align="right" />
                </TableRow>
              </TableHead>
              <TableBody>
                {keys.map((key) => (
                  <TableRow key={key.id}>
                    <TableCell>{key.name}</TableCell>
                    <TableCell>
                      <code>{key.key_prefix}…</code>
                    </TableCell>
                    <TableCell>
                      {key.last_used_at ? new Date(key.last_used_at).toLocaleString() : t('apiKeys.neverUsed')}
                    </TableCell>
                    <TableCell>
                      {key.revoked_at ? (
                        <Chip size="small" label={t('apiKeys.revoked')} color="default" />
                      ) : (
                        <Chip size="small" label={t('apiKeys.active')} color="success" />
                      )}
                    </TableCell>
                    <TableCell align="right">
                      {!key.revoked_at && (
                        <Tooltip title={t('apiKeys.revoke') as string}>
                          <IconButton size="small" onClick={() => setPendingRevoke({ id: key.id, name: key.name })}>
                            <DeleteIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('apiKeys.dialog.title')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit((values) => createMutation.mutate(values))}>
          <DialogContent>
            <TextField
              autoFocus
              fullWidth
              label={t('apiKeys.fields.name')}
              placeholder={t('apiKeys.dialog.namePlaceholder') as string}
              {...register('name', { required: true })}
            />
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{t('apiKeys.dialog.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending}>
              {t('apiKeys.generate')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={!!newKey} onClose={() => setNewKey(null)} fullWidth maxWidth="sm">
        <DialogTitle>{t('apiKeys.revealDialog.title')}</DialogTitle>
        <DialogContent>
          <Alert severity="warning" sx={{ mb: 2 }}>
            {t('apiKeys.revealDialog.warning')}
          </Alert>
          <Stack direction="row" spacing={1} alignItems="center">
            <Box
              component="code"
              sx={{
                flexGrow: 1,
                bgcolor: 'action.hover',
                p: 1.5,
                borderRadius: 1,
                fontSize: 13,
                wordBreak: 'break-all',
              }}
            >
              {newKey}
            </Box>
            <Tooltip title={t('apiKeys.revealDialog.copy') as string}>
              <IconButton onClick={copyKey}>
                <ContentCopyIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button variant="contained" onClick={() => setNewKey(null)}>
            {t('apiKeys.revealDialog.done')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingRevoke}
        title={t('apiKeys.revokeDialog.title')}
        message={t('apiKeys.revokeDialog.message', { name: pendingRevoke?.name ?? '' })}
        confirmLabel={t('apiKeys.revoke')}
        loading={revokeMutation.isPending}
        onConfirm={() => pendingRevoke && revokeMutation.mutate(pendingRevoke.id)}
        onCancel={() => setPendingRevoke(null)}
      />
    </Stack>
  );
}
