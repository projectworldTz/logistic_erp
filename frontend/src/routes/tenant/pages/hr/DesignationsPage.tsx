import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
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
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { createDesignation, deleteDesignation, fetchDesignations } from '../../../../api/endpoints/hr';
import type { Designation, DesignationCategory } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

const CATEGORY_OPTIONS: DesignationCategory[] = [
  'management',
  'clearing_and_customs',
  'forwarding_and_logistics',
  'transport_and_fleet',
  'warehouse_and_cargo',
  'finance_and_accounts',
  'sales_and_crm',
  'administration_and_support',
  'other',
];

function buildSchema(t: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    category: z.enum(CATEGORY_OPTIONS as [DesignationCategory, ...DesignationCategory[]]),
    description: z.string().optional(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function DesignationsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Designation | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'designations'], queryFn: () => fetchDesignations(true) });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'designations'] });

  const createMutation = useMutation({
    mutationFn: createDesignation,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.designationCreated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteDesignation,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.designationDeleted'));
    },
  });

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { category: 'other' } });

  const onCreate = (values: FormValues) => createMutation.mutate(values);

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('designations.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset({ category: 'other' });
            setDialogOpen(true);
          }}
        >
          {t('designations.newDesignation')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('designations.empty.title')} description={t('designations.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('designations.table.name')}</TableCell>
                  <TableCell>{t('designations.table.category')}</TableCell>
                  <TableCell align="right">{t('designations.table.employees')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((designation) => (
                  <TableRow key={designation.id}>
                    <TableCell>{designation.name}</TableCell>
                    <TableCell>{t(`designationCategories.${designation.category}`)}</TableCell>
                    <TableCell align="right">{designation.employees_count ?? 0}</TableCell>
                    <TableCell>
                      <Chip
                        size="small"
                        label={designation.is_active ? tc('labels.active') : tc('labels.inactive')}
                        color={designation.is_active ? 'success' : 'default'}
                      />
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(designation)}>
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
        <DialogTitle>{t('designations.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('designations.form.name')}
                fullWidth
                {...register('name')}
                error={!!errors.name}
                helperText={errors.name?.message}
              />
              <Controller
                name="category"
                control={control}
                render={({ field }) => (
                  <TextField label={t('designations.form.category')} select fullWidth {...field}>
                    {CATEGORY_OPTIONS.map((category) => (
                      <MenuItem key={category} value={category}>
                        {t(`designationCategories.${category}`)}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField
                label={t('designations.form.description')}
                fullWidth
                multiline
                minRows={2}
                {...register('description')}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('designations.deleteDialog.title')}
        message={t('designations.deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
