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
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { createPlan, deletePlan, fetchPlatformPlans } from '../../../api/endpoints/platform';
import { ConfirmDialog } from '../../../components/common/ConfirmDialog';
import { useToast } from '../../../hooks/useToast';
import type { Plan } from '../../../types';

function buildSchema(t: (key: string) => string) {
  return z.object({
    code: z.string().min(1, t('plans.validation.codeRequired')),
    name: z.string().min(1, t('plans.validation.nameRequired')),
    price_monthly: z.coerce.number().min(0),
    price_yearly: z.coerce.number().min(0),
  });
}

type FormInput = z.input<ReturnType<typeof buildSchema>>;
type FormOutput = z.output<ReturnType<typeof buildSchema>>;

export function PlansPage() {
  const { t } = useTranslation('superAdmin');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<Plan | null>(null);
  const { data: plans, isLoading } = useQuery({ queryKey: ['platform', 'plans'], queryFn: fetchPlatformPlans });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['platform', 'plans'] });
  const createMutation = useMutation({
    mutationFn: createPlan,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('plans.toast.created'));
    },
  });
  const deleteMutation = useMutation({
    mutationFn: deletePlan,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('plans.toast.deleted'));
    },
  });

  const { register, handleSubmit, reset, formState: { errors } } = useForm<FormInput, unknown, FormOutput>({
    resolver: zodResolver(schema),
  });

  const onCreate = (values: FormOutput) =>
    createMutation.mutate({
      ...values,
      price_monthly: String(values.price_monthly),
      price_yearly: String(values.price_yearly),
    });

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('plans.title')}
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset();
            setDialogOpen(true);
          }}
        >
          {t('plans.newPlan')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {plans && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{tc('labels.name')}</TableCell>
                  <TableCell>{t('plans.table.code')}</TableCell>
                  <TableCell>{t('plans.table.monthly')}</TableCell>
                  <TableCell>{t('plans.table.yearly')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {plans.map((plan) => (
                  <TableRow key={plan.id}>
                    <TableCell>{plan.name}</TableCell>
                    <TableCell>{plan.code}</TableCell>
                    <TableCell>${plan.price_monthly}</TableCell>
                    <TableCell>${plan.price_yearly}</TableCell>
                    <TableCell>
                      <Chip
                        label={plan.is_active ? t('plans.status.active') : t('plans.status.inactive')}
                        size="small"
                        color={plan.is_active ? 'success' : 'default'}
                      />
                    </TableCell>
                    <TableCell align="right">
                      <IconButton size="small" onClick={() => setPendingDelete(plan)}>
                        <DeleteIcon fontSize="small" />
                      </IconButton>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('plans.form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField label={t('plans.form.code')} fullWidth {...register('code')} error={!!errors.code} helperText={errors.code?.message} />
              <TextField label={tc('labels.name')} fullWidth {...register('name')} error={!!errors.name} helperText={errors.name?.message} />
              <TextField label={t('plans.form.monthlyPrice')} type="number" fullWidth {...register('price_monthly')} />
              <TextField label={t('plans.form.yearlyPrice')} type="number" fullWidth {...register('price_yearly')} />
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
        title={t('plans.deleteDialog.title')}
        message={t('plans.deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
