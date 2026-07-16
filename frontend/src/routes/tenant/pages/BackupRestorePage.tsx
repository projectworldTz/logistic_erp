import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Dialog,
  DialogActions,
  DialogContent,
  DialogContentText,
  DialogTitle,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import CloudDownloadIcon from '@mui/icons-material/CloudDownload';
import RestoreIcon from '@mui/icons-material/Restore';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { downloadBackup, restoreBackup } from '../../../api/endpoints/backup';
import { fetchCompany } from '../../../api/endpoints/dashboard';
import { useToast } from '../../../hooks/useToast';

export function BackupRestorePage() {
  const { t } = useTranslation('settings');
  const { showToast } = useToast();
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany });
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [pendingFile, setPendingFile] = useState<File | null>(null);
  const [confirmText, setConfirmText] = useState('');

  const exportMutation = useMutation({
    mutationFn: downloadBackup,
    onSuccess: (blob) => {
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `backup-${new Date().toISOString().slice(0, 10)}.json`;
      link.click();
      window.URL.revokeObjectURL(url);
      showToast(t('backup.toast.exported'));
    },
  });

  const restoreMutation = useMutation({
    mutationFn: restoreBackup,
    onSuccess: () => {
      closeDialog();
      showToast(t('backup.toast.restored'));
    },
  });

  const closeDialog = () => {
    setPendingFile(null);
    setConfirmText('');
  };

  const handleFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) setPendingFile(file);
    event.target.value = '';
  };

  const confirmMatches = confirmText.trim().toUpperCase() === 'RESTORE';

  return (
    <Stack spacing={3} maxWidth={720}>
      <Typography variant="h5" fontWeight={700}>
        {t('backup.title')}
      </Typography>

      {restoreMutation.isError && <Alert severity="error">{t('backup.restore.error')}</Alert>}

      <Card variant="outlined">
        <CardContent>
          <Stack spacing={2}>
            <Typography variant="subtitle1" fontWeight={700}>
              {t('backup.export.title')}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {t('backup.export.description')}
            </Typography>
            <Box>
              <Button
                variant="contained"
                startIcon={<CloudDownloadIcon />}
                disabled={exportMutation.isPending}
                onClick={() => exportMutation.mutate()}
              >
                {exportMutation.isPending ? t('backup.export.downloading') : t('backup.export.download')}
              </Button>
            </Box>
          </Stack>
        </CardContent>
      </Card>

      <Card variant="outlined">
        <CardContent>
          <Stack spacing={2}>
            <Typography variant="subtitle1" fontWeight={700} color="error.main">
              {t('backup.restore.title')}
            </Typography>
            <Alert severity="warning">{t('backup.restore.warning')}</Alert>
            <Typography variant="body2" color="text.secondary">
              {t('backup.restore.description')}
            </Typography>
            <Box>
              <Button
                variant="outlined"
                color="error"
                startIcon={<RestoreIcon />}
                onClick={() => fileInputRef.current?.click()}
              >
                {t('backup.restore.selectFile')}
              </Button>
              <input ref={fileInputRef} type="file" accept="application/json" hidden onChange={handleFileChange} />
            </Box>
          </Stack>
        </CardContent>
      </Card>

      <Dialog open={!!pendingFile} onClose={closeDialog} fullWidth maxWidth="xs">
        <DialogTitle>{t('backup.restore.confirmDialog.title')}</DialogTitle>
        <DialogContent>
          <Stack spacing={2}>
            <DialogContentText>
              {t('backup.restore.confirmDialog.message', { company: company?.name ?? '' })}
            </DialogContentText>
            <TextField
              autoFocus
              fullWidth
              label={t('backup.restore.confirmDialog.typeToConfirm')}
              value={confirmText}
              onChange={(e) => setConfirmText(e.target.value)}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={closeDialog} disabled={restoreMutation.isPending}>
            {t('backup.restore.confirmDialog.cancel')}
          </Button>
          <Button
            color="error"
            variant="contained"
            disabled={!confirmMatches || restoreMutation.isPending}
            onClick={() => pendingFile && restoreMutation.mutate(pendingFile)}
          >
            {restoreMutation.isPending ? t('backup.restore.confirmDialog.restoring') : t('backup.restore.confirmDialog.confirm')}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
