import {
  Alert,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  Link,
  MenuItem,
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
import UploadFileIcon from '@mui/icons-material/UploadFile';
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { deleteDocument, fetchDocuments, uploadDocument } from '../../../../api/endpoints/documents';
import type { Document } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

const CATEGORY_COLOR: Record<Document['category'], 'default' | 'info' | 'warning' | 'success'> = {
  invoice: 'success',
  bill_of_lading: 'info',
  customs_declaration: 'warning',
  contract: 'info',
  id_document: 'default',
  other: 'default',
};

const CATEGORY_OPTIONS: Document['category'][] = [
  'invoice',
  'bill_of_lading',
  'customs_declaration',
  'contract',
  'id_document',
  'other',
];

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function DocumentsPage() {
  const { t } = useTranslation('documents');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Document | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [category, setCategory] = useState<Document['category']>('other');
  const { data, isLoading } = useQuery({ queryKey: ['documents', 'files'], queryFn: () => fetchDocuments() });

  const invalidateDocuments = () => queryClient.invalidateQueries({ queryKey: ['documents', 'files'] });

  const uploadMutation = useMutation({
    mutationFn: uploadDocument,
    onSuccess: () => {
      invalidateDocuments();
      setDialogOpen(false);
      setFile(null);
      setCategory('other');
      showToast(t('toast.created'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteDocument,
    onSuccess: () => {
      invalidateDocuments();
      setPendingDelete(null);
      showToast(t('toast.deleted'));
    },
  });

  const onUpload = () => {
    if (!file) return;
    uploadMutation.mutate({ file, category });
  };

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('title')}
        </Typography>
        <Button variant="contained" startIcon={<UploadFileIcon />} onClick={() => setDialogOpen(true)}>
          {t('uploadDocument')}
        </Button>
      </Stack>

      {uploadMutation.isError && <Alert severity="error">{t('errors.uploadFailed')}</Alert>}

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && (
        <EmptyState title={t('empty.title')} description={t('empty.description')} />
      )}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('table.fileName')}</TableCell>
                  <TableCell>{t('table.category')}</TableCell>
                  <TableCell>{t('table.size')}</TableCell>
                  <TableCell>{t('table.uploaded')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((document) => (
                  <TableRow key={document.id}>
                    <TableCell>
                      <Link href={document.url} target="_blank" rel="noopener noreferrer">
                        {document.file_name}
                      </Link>
                    </TableCell>
                    <TableCell>
                      <Chip label={t(`categories.${document.category}`)} size="small" color={CATEGORY_COLOR[document.category]} />
                    </TableCell>
                    <TableCell>{formatBytes(document.file_size)}</TableCell>
                    <TableCell>{new Date(document.created_at).toLocaleDateString()}</TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(document)}>
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('uploadDocument')}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            <Button component="label" variant="outlined">
              {file ? file.name : t('form.chooseFile')}
              <input
                type="file"
                hidden
                onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
              />
            </Button>
            <TextField
              label={t('table.category')}
              select
              fullWidth
              value={category}
              onChange={(e) => setCategory(e.target.value as Document['category'])}
            >
              {CATEGORY_OPTIONS.map((option) => (
                <MenuItem key={option} value={option}>
                  {t(`categories.${option}`)}
                </MenuItem>
              ))}
            </TextField>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
          <Button variant="contained" onClick={onUpload} disabled={!file || uploadMutation.isPending}>
            {tc('actions.upload')}
          </Button>
        </DialogActions>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('deleteDialog.title')}
        message={t('deleteDialog.message', { name: pendingDelete?.file_name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
