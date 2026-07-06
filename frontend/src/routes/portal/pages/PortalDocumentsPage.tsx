import {
  Button,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
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
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { fetchPortalDocuments, uploadPortalDocument } from '../../../api/endpoints/portal';
import { EmptyState } from '../../../components/common/EmptyState';
import { useToast } from '../../../hooks/useToast';

const CATEGORIES = ['invoice', 'bill_of_lading', 'customs_declaration', 'contract', 'id_document', 'other'];

interface FormValues {
  file: FileList;
  category: string;
  description?: string;
}

export function PortalDocumentsPage() {
  const { t } = useTranslation('portal');
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const [dialogOpen, setDialogOpen] = useState(false);
  const { data, isLoading } = useQuery({ queryKey: ['portal', 'documents'], queryFn: () => fetchPortalDocuments() });

  const uploadMutation = useMutation({
    mutationFn: uploadPortalDocument,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['portal', 'documents'] });
      setDialogOpen(false);
      showToast(t('documents.toast.uploaded'));
    },
  });

  const { register, control, handleSubmit, reset } = useForm<FormValues>({ defaultValues: { category: 'other' } });

  const onSubmit = (values: FormValues) => {
    const formData = new FormData();
    formData.append('file', values.file[0]);
    formData.append('category', values.category);
    if (values.description) formData.append('description', values.description);
    uploadMutation.mutate(formData);
  };

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('documents.title')}
        </Typography>
        <Button variant="contained" startIcon={<AddIcon />} onClick={() => { reset(); setDialogOpen(true); }}>
          {t('documents.upload')}
        </Button>
      </Stack>

      {isLoading && <EmptyState title="" />}

      {data && data.data.length === 0 && <EmptyState title={t('documents.empty')} />}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>File</TableCell>
                  <TableCell>Category</TableCell>
                  <TableCell>Description</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((document) => (
                  <TableRow key={document.id}>
                    <TableCell>
                      <Link href={document.url} target="_blank" rel="noopener noreferrer" underline="hover">
                        {document.file_name}
                      </Link>
                    </TableCell>
                    <TableCell>{document.category}</TableCell>
                    <TableCell>{document.description ?? '—'}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('documents.dialog.title')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onSubmit)}>
          <DialogContent>
            <Stack spacing={2}>
              <Controller
                name="file"
                control={control}
                rules={{ required: true }}
                render={({ field: { onChange, ...field } }) => (
                  <TextField
                    type="file"
                    fullWidth
                    label={t('documents.form.file')}
                    InputLabelProps={{ shrink: true }}
                    onChange={(e) => onChange((e.target as HTMLInputElement).files)}
                    {...field}
                    value={undefined}
                  />
                )}
              />
              <TextField label={t('documents.form.category')} select fullWidth defaultValue="other" {...register('category')}>
                {CATEGORIES.map((category) => (
                  <MenuItem key={category} value={category}>{category}</MenuItem>
                ))}
              </TextField>
              <TextField label={t('documents.form.description')} fullWidth {...register('description')} />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>Cancel</Button>
            <Button type="submit" variant="contained" disabled={uploadMutation.isPending}>
              {t('documents.upload')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>
    </Stack>
  );
}
