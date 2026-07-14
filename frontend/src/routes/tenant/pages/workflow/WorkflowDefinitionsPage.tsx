import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  Checkbox,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
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
import { Controller, useFieldArray, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createApprovalWorkflow,
  deleteApprovalWorkflow,
  fetchApprovalWorkflows,
} from '../../../../api/endpoints/workflows';
import { fetchRoles } from '../../../../api/endpoints/users';
import type { ApprovalWorkflow } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

function buildSchema(t: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    subject_type: z.literal('expense'),
    min_amount: z.number().nullable().optional(),
    is_active: z.boolean().optional(),
    steps: z
      .array(z.object({ approver_role: z.string().min(1, t('validation.roleRequired')) }))
      .min(1, t('validation.atLeastOneStep')),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function WorkflowDefinitionsPage() {
  const { t } = useTranslation('workflows');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingDelete, setPendingDelete] = useState<ApprovalWorkflow | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['workflows', 'definitions'], queryFn: fetchApprovalWorkflows });
  const { data: roles } = useQuery({ queryKey: ['roles'], queryFn: fetchRoles });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['workflows', 'definitions'] });

  const createMutation = useMutation({
    mutationFn: createApprovalWorkflow,
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('toast.created'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: deleteApprovalWorkflow,
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('toast.deleted'));
    },
  });

  const {
    register,
    control,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { subject_type: 'expense', is_active: true, steps: [{ approver_role: '' }] },
  });

  const { fields, append, remove } = useFieldArray({ control, name: 'steps' });

  const onCreate = (values: FormValues) =>
    createMutation.mutate({
      ...values,
      min_amount: values.min_amount ?? null,
    });

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('title')}
        </Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            reset({ subject_type: 'expense', is_active: true, steps: [{ approver_role: '' }] });
            setDialogOpen(true);
          }}
        >
          {t('newWorkflow')}
        </Button>
      </Stack>

      {isLoading && <CircularProgress />}

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('empty.title')} description={t('empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('table.name')}</TableCell>
                  <TableCell>{t('table.subjectType')}</TableCell>
                  <TableCell align="right">{t('table.minAmount')}</TableCell>
                  <TableCell>{t('table.steps')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((workflow) => (
                  <TableRow key={workflow.id}>
                    <TableCell>{workflow.name}</TableCell>
                    <TableCell>{t(`subjectTypes.${workflow.subject_type}`)}</TableCell>
                    <TableCell align="right">{workflow.min_amount ?? t('table.anyAmount')}</TableCell>
                    <TableCell>
                      <Stack direction="row" spacing={0.5} flexWrap="wrap">
                        {workflow.steps.map((step) => (
                          <Chip key={step.id} label={`${step.position}. ${step.approver_role}`} size="small" />
                        ))}
                      </Stack>
                    </TableCell>
                    <TableCell>
                      <Chip
                        label={workflow.is_active ? tc('labels.active') : tc('labels.inactive')}
                        size="small"
                        color={workflow.is_active ? 'success' : 'default'}
                      />
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title={tc('actions.delete')}>
                        <IconButton size="small" onClick={() => setPendingDelete(workflow)}>
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

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="sm">
        <DialogTitle>{t('form.dialogTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('form.name')}
                fullWidth
                {...register('name')}
                error={!!errors.name}
                helperText={errors.name?.message}
              />
              <TextField label={t('form.subjectType')} select fullWidth defaultValue="expense" {...register('subject_type')}>
                <MenuItem value="expense">{t('subjectTypes.expense')}</MenuItem>
              </TextField>
              <Controller
                name="min_amount"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('form.minAmount')}
                    type="number"
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value === '' ? null : Number(e.target.value))}
                    helperText={t('form.minAmountHelp')}
                  />
                )}
              />
              <FormControlLabel
                control={
                  <Controller
                    name="is_active"
                    control={control}
                    render={({ field }) => (
                      <Checkbox checked={!!field.value} onChange={(e) => field.onChange(e.target.checked)} />
                    )}
                  />
                }
                label={t('form.isActive')}
              />

              <Typography variant="subtitle2">{t('form.stepsTitle')}</Typography>
              {fields.map((field, index) => (
                <Stack direction="row" spacing={1} alignItems="center" key={field.id}>
                  <Typography variant="body2" sx={{ minWidth: 24 }}>
                    {index + 1}.
                  </Typography>
                  <Controller
                    name={`steps.${index}.approver_role`}
                    control={control}
                    render={({ field: f }) => (
                      <TextField
                        label={t('form.approverRole')}
                        select
                        fullWidth
                        size="small"
                        value={f.value ?? ''}
                        onChange={(e) => f.onChange(e.target.value)}
                        error={!!errors.steps?.[index]?.approver_role}
                      >
                        <MenuItem value="" disabled>
                          {t('form.selectRole')}
                        </MenuItem>
                        {roles?.map((role) => (
                          <MenuItem key={role} value={role}>
                            {role}
                          </MenuItem>
                        ))}
                      </TextField>
                    )}
                  />
                  <IconButton size="small" onClick={() => remove(index)} disabled={fields.length === 1}>
                    <DeleteIcon fontSize="small" />
                  </IconButton>
                </Stack>
              ))}
              <Button
                size="small"
                startIcon={<AddIcon />}
                onClick={() => append({ approver_role: '' })}
                sx={{ alignSelf: 'flex-start' }}
              >
                {t('form.addStep')}
              </Button>
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
        title={t('deleteDialog.title')}
        message={t('deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />
    </Stack>
  );
}
