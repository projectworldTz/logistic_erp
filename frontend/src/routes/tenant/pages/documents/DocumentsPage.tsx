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
import VisibilityIcon from '@mui/icons-material/Visibility';
import HistoryIcon from '@mui/icons-material/History';
import NoteAddIcon from '@mui/icons-material/NoteAdd';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  deleteDocument,
  fetchDocumentVersions,
  fetchDocuments,
  uploadDocument,
} from '../../../../api/endpoints/documents';
import { fetchShipments } from '../../../../api/endpoints/shipments';
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
  packing_list: 'info',
  certificate_of_origin: 'success',
  insurance_certificate: 'success',
  delivery_note: 'default',
  release_order: 'warning',
  other: 'default',
};

const CATEGORY_OPTIONS: Document['category'][] = [
  'invoice',
  'bill_of_lading',
  'customs_declaration',
  'contract',
  'id_document',
  'packing_list',
  'certificate_of_origin',
  'insurance_certificate',
  'delivery_note',
  'release_order',
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
  const [previewDocument, setPreviewDocument] = useState<Document | null>(null);
  const [versionsDocument, setVersionsDocument] = useState<Document | null>(null);
  const [newVersionOf, setNewVersionOf] = useState<Document | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [category, setCategory] = useState<Document['category']>('other');
  const [shipmentId, setShipmentId] = useState<number | ''>('');
  const { data, isLoading } = useQuery({ queryKey: ['documents', 'files'], queryFn: () => fetchDocuments() });
  const { data: shipments } = useQuery({ queryKey: ['shipments', 'items'], queryFn: () => fetchShipments() });

  const { data: versionsData, isLoading: versionsLoading } = useQuery({
    queryKey: ['documents', 'files', versionsDocument?.id, 'versions'],
    queryFn: () => fetchDocumentVersions(versionsDocument!.id),
    enabled: !!versionsDocument,
  });

  const invalidateDocuments = () => queryClient.invalidateQueries({ queryKey: ['documents', 'files'] });

  const uploadMutation = useMutation({
    mutationFn: uploadDocument,
    onSuccess: () => {
      invalidateDocuments();
      setDialogOpen(false);
      setFile(null);
      setCategory('other');
      setShipmentId('');
      setNewVersionOf(null);
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
    uploadMutation.mutate({
      file,
      category,
      shipment_id: shipmentId === '' ? undefined : shipmentId,
      parent_document_id: newVersionOf?.id,
    });
  };

  const openUploadDialog = () => {
    setNewVersionOf(null);
    setFile(null);
    setCategory('other');
    setShipmentId('');
    setDialogOpen(true);
  };

  const openNewVersionDialog = (document: Document) => {
    setNewVersionOf(document);
    setFile(null);
    setCategory(document.category);
    setShipmentId(document.shipment_id ?? '');
    setDialogOpen(true);
  };

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('title')}
        </Typography>
        <Button variant="contained" startIcon={<UploadFileIcon />} onClick={openUploadDialog}>
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
                  <TableCell>{t('table.shipment')}</TableCell>
                  <TableCell>{t('table.size')}</TableCell>
                  <TableCell>{t('table.uploaded')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((document) => (
                  <TableRow key={document.id}>
                    <TableCell>
                      <Stack direction="row" spacing={0.75} alignItems="center">
                        <Link href={document.url} target="_blank" rel="noopener noreferrer">
                          {document.file_name}
                        </Link>
                        <Chip label={`v${document.version}`} size="small" variant="outlined" />
                      </Stack>
                    </TableCell>
                    <TableCell>
                      <Chip label={t(`categories.${document.category}`)} size="small" color={CATEGORY_COLOR[document.category]} />
                    </TableCell>
                    <TableCell>{document.shipment?.shipment_number ?? '—'}</TableCell>
                    <TableCell>{formatBytes(document.file_size)}</TableCell>
                    <TableCell>{new Date(document.created_at).toLocaleDateString()}</TableCell>
                    <TableCell align="right">
                      {document.is_previewable && (
                        <Tooltip title={t('actions.preview')}>
                          <IconButton size="small" onClick={() => setPreviewDocument(document)}>
                            <VisibilityIcon fontSize="small" />
                          </IconButton>
                        </Tooltip>
                      )}
                      <Tooltip title={t('actions.versions')}>
                        <IconButton size="small" onClick={() => setVersionsDocument(document)}>
                          <HistoryIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
                      <Tooltip title={t('actions.newVersion')}>
                        <IconButton size="small" onClick={() => openNewVersionDialog(document)}>
                          <NoteAddIcon fontSize="small" />
                        </IconButton>
                      </Tooltip>
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
        <DialogTitle>{newVersionOf ? t('form.newVersionTitle', { name: newVersionOf.file_name }) : t('uploadDocument')}</DialogTitle>
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
              disabled={!!newVersionOf}
            >
              {CATEGORY_OPTIONS.map((option) => (
                <MenuItem key={option} value={option}>
                  {t(`categories.${option}`)}
                </MenuItem>
              ))}
            </TextField>
            <TextField
              label={t('form.shipment')}
              select
              fullWidth
              value={shipmentId}
              onChange={(e) => setShipmentId(e.target.value === '' ? '' : Number(e.target.value))}
              disabled={!!newVersionOf}
            >
              <MenuItem value="">{t('form.noShipment')}</MenuItem>
              {shipments?.data.map((shipment) => (
                <MenuItem key={shipment.id} value={shipment.id}>
                  {shipment.shipment_number}
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

      <Dialog open={!!previewDocument} onClose={() => setPreviewDocument(null)} fullWidth maxWidth="md">
        <DialogTitle>{previewDocument?.file_name}</DialogTitle>
        <DialogContent>
          {previewDocument?.mime_type === 'application/pdf' ? (
            <iframe src={previewDocument.url} title={previewDocument.file_name} style={{ width: '100%', height: '70vh', border: 'none' }} />
          ) : (
            previewDocument && (
              <img src={previewDocument.url} alt={previewDocument.file_name} style={{ maxWidth: '100%', display: 'block', margin: '0 auto' }} />
            )
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPreviewDocument(null)}>{tc('actions.close')}</Button>
        </DialogActions>
      </Dialog>

      <Dialog open={!!versionsDocument} onClose={() => setVersionsDocument(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('versionsDialog.title')}</DialogTitle>
        <DialogContent>
          {versionsLoading && <CircularProgress size={24} />}
          {versionsData && (
            <Stack spacing={1}>
              {versionsData.data.map((version) => (
                <Stack key={version.id} direction="row" justifyContent="space-between" alignItems="center">
                  <Link href={version.url} target="_blank" rel="noopener noreferrer">
                    v{version.version} — {version.file_name}
                  </Link>
                  <Typography variant="caption" color="text.secondary">
                    {new Date(version.created_at).toLocaleDateString()}
                  </Typography>
                </Stack>
              ))}
            </Stack>
          )}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setVersionsDocument(null)}>{tc('actions.close')}</Button>
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
