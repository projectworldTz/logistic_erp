import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
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
  Tooltip,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, type Resolver } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createStatutoryContributionRule,
  createStatutoryRuleSet,
  createStatutoryTaxBand,
  deleteStatutoryContributionRule,
  deleteStatutoryRuleSet,
  deleteStatutoryTaxBand,
  fetchStatutoryRuleSets,
} from '../../../../api/endpoints/hr';
import type { StatutoryRuleSet } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { StatusChip } from '../../../../components/common/StatusChip';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

function buildRuleSetSchema(t: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    country_code: z.string().length(2, t('statutoryRules.validation.countryCode')),
    description: z.string().optional(),
    is_default: z.boolean().default(false),
  });
}

type RuleSetFormValues = z.infer<ReturnType<typeof buildRuleSetSchema>>;

function buildTaxBandSchema() {
  return z.object({
    lower_bound: z.coerce.number().min(0),
    upper_bound: z.coerce.number().min(0).optional(),
    rate: z.coerce.number().min(0).max(100),
    band_order: z.coerce.number().min(1),
  });
}

type TaxBandFormValues = z.infer<ReturnType<typeof buildTaxBandSchema>>;

function buildContributionRuleSchema(t: (key: string) => string) {
  return z.object({
    code: z.string().min(1, t('validation.nameRequired')).max(100),
    name: z.string().min(1, t('validation.nameRequired')).max(255),
    employee_rate: z.coerce.number().min(0).max(100).optional(),
    employer_rate: z.coerce.number().min(0).max(100).optional(),
  });
}

type ContributionRuleFormValues = z.infer<ReturnType<typeof buildContributionRuleSchema>>;

export function StatutoryRulesPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const ruleSetSchema = buildRuleSetSchema(t);
  const taxBandSchema = buildTaxBandSchema();
  const contributionRuleSchema = buildContributionRuleSchema(t);

  const [ruleSetDialogOpen, setRuleSetDialogOpen] = useState(false);
  const [pendingDeleteRuleSet, setPendingDeleteRuleSet] = useState<StatutoryRuleSet | null>(null);
  const [bandDialogFor, setBandDialogFor] = useState<StatutoryRuleSet | null>(null);
  const [contributionDialogFor, setContributionDialogFor] = useState<StatutoryRuleSet | null>(null);

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'statutory-rule-sets'], queryFn: fetchStatutoryRuleSets });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['hr', 'statutory-rule-sets'] });

  const createRuleSetMutation = useMutation({
    mutationFn: createStatutoryRuleSet,
    onSuccess: () => {
      invalidate();
      setRuleSetDialogOpen(false);
      showToast(t('toast.statutoryRuleSetCreated'));
    },
  });

  const deleteRuleSetMutation = useMutation({
    mutationFn: deleteStatutoryRuleSet,
    onSuccess: () => {
      invalidate();
      setPendingDeleteRuleSet(null);
      showToast(t('toast.statutoryRuleSetDeleted'));
    },
  });

  const createBandMutation = useMutation({
    mutationFn: ({ ruleSetId, payload }: { ruleSetId: number; payload: TaxBandFormValues }) =>
      createStatutoryTaxBand(ruleSetId, {
        ...payload,
        lower_bound: String(payload.lower_bound),
        upper_bound: payload.upper_bound !== undefined ? String(payload.upper_bound) : undefined,
        rate: String(payload.rate),
      }),
    onSuccess: () => {
      invalidate();
      setBandDialogFor(null);
      showToast(t('toast.taxBandCreated'));
    },
  });

  const deleteBandMutation = useMutation({
    mutationFn: ({ ruleSetId, bandId }: { ruleSetId: number; bandId: number }) => deleteStatutoryTaxBand(ruleSetId, bandId),
    onSuccess: () => {
      invalidate();
      showToast(t('toast.taxBandDeleted'));
    },
  });

  const createContributionMutation = useMutation({
    mutationFn: ({ ruleSetId, payload }: { ruleSetId: number; payload: ContributionRuleFormValues }) =>
      createStatutoryContributionRule(ruleSetId, {
        ...payload,
        employee_rate: payload.employee_rate !== undefined ? String(payload.employee_rate) : undefined,
        employer_rate: payload.employer_rate !== undefined ? String(payload.employer_rate) : undefined,
      }),
    onSuccess: () => {
      invalidate();
      setContributionDialogFor(null);
      showToast(t('toast.contributionRuleCreated'));
    },
  });

  const deleteContributionMutation = useMutation({
    mutationFn: ({ ruleSetId, ruleId }: { ruleSetId: number; ruleId: number }) => deleteStatutoryContributionRule(ruleSetId, ruleId),
    onSuccess: () => {
      invalidate();
      showToast(t('toast.contributionRuleDeleted'));
    },
  });

  const ruleSetForm = useForm<RuleSetFormValues>({
    resolver: zodResolver(ruleSetSchema) as Resolver<RuleSetFormValues>,
    defaultValues: { is_default: false },
  });
  const bandForm = useForm<TaxBandFormValues>({ resolver: zodResolver(taxBandSchema) as Resolver<TaxBandFormValues>, defaultValues: { band_order: 1 } });
  const contributionForm = useForm<ContributionRuleFormValues>({ resolver: zodResolver(contributionRuleSchema) as Resolver<ContributionRuleFormValues> });

  const ruleSets = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6">{t('statutoryRules.title')}</Typography>
        <Button
          variant="contained"
          startIcon={<AddIcon />}
          onClick={() => {
            ruleSetForm.reset({ is_default: false });
            setRuleSetDialogOpen(true);
          }}
        >
          {t('statutoryRules.newRuleSet')}
        </Button>
      </Stack>

      <Typography variant="body2" color="text.secondary">
        {t('statutoryRules.disclaimer')}
      </Typography>

      {isLoading && <CircularProgress />}

      {ruleSets.length === 0 && !isLoading && (
        <EmptyState title={t('statutoryRules.empty.title')} description={t('statutoryRules.empty.description')} />
      )}

      {ruleSets.map((ruleSet) => (
        <Paper key={ruleSet.id} variant="outlined" sx={{ p: 2 }}>
          <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 2 }}>
            <Stack direction="row" spacing={1} alignItems="center">
              <Typography variant="subtitle1" fontWeight={600}>
                {ruleSet.name}
              </Typography>
              <StatusChip status={ruleSet.country_code} label={ruleSet.country_code} />
              {ruleSet.is_default && <StatusChip status="active" label={tc('labels.default')} />}
            </Stack>
            <Tooltip title={tc('actions.delete')}>
              <IconButton size="small" onClick={() => setPendingDeleteRuleSet(ruleSet)}>
                <DeleteIcon fontSize="small" />
              </IconButton>
            </Tooltip>
          </Stack>

          <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1 }}>
            <Typography variant="body2" fontWeight={600}>
              {t('statutoryRules.taxBands')}
            </Typography>
            <Button
              size="small"
              startIcon={<AddIcon />}
              onClick={() => {
                bandForm.reset({ band_order: (ruleSet.tax_bands?.length ?? 0) + 1 });
                setBandDialogFor(ruleSet);
              }}
            >
              {t('statutoryRules.addTaxBand')}
            </Button>
          </Stack>
          {(ruleSet.tax_bands?.length ?? 0) > 0 && (
            <TableContainer sx={{ mb: 2 }}>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>{t('statutoryRules.table.lowerBound')}</TableCell>
                    <TableCell>{t('statutoryRules.table.upperBound')}</TableCell>
                    <TableCell align="right">{t('statutoryRules.table.rate')}</TableCell>
                    <TableCell align="right">{tc('actions.actions')}</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {ruleSet.tax_bands?.map((band) => (
                    <TableRow key={band.id}>
                      <TableCell>{band.lower_bound}</TableCell>
                      <TableCell>{band.upper_bound ?? '∞'}</TableCell>
                      <TableCell align="right">{band.rate}%</TableCell>
                      <TableCell align="right">
                        <IconButton size="small" onClick={() => deleteBandMutation.mutate({ ruleSetId: ruleSet.id, bandId: band.id })}>
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}

          <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ mb: 1 }}>
            <Typography variant="body2" fontWeight={600}>
              {t('statutoryRules.contributionRules')}
            </Typography>
            <Button
              size="small"
              startIcon={<AddIcon />}
              onClick={() => {
                contributionForm.reset({});
                setContributionDialogFor(ruleSet);
              }}
            >
              {t('statutoryRules.addContributionRule')}
            </Button>
          </Stack>
          {(ruleSet.contribution_rules?.length ?? 0) > 0 && (
            <TableContainer>
              <Table size="small">
                <TableHead>
                  <TableRow>
                    <TableCell>{t('statutoryRules.table.code')}</TableCell>
                    <TableCell>{t('statutoryRules.table.name')}</TableCell>
                    <TableCell align="right">{t('statutoryRules.table.employeeRate')}</TableCell>
                    <TableCell align="right">{t('statutoryRules.table.employerRate')}</TableCell>
                    <TableCell align="right">{tc('actions.actions')}</TableCell>
                  </TableRow>
                </TableHead>
                <TableBody>
                  {ruleSet.contribution_rules?.map((rule) => (
                    <TableRow key={rule.id}>
                      <TableCell>{rule.code}</TableCell>
                      <TableCell>{rule.name}</TableCell>
                      <TableCell align="right">{rule.employee_rate ? `${rule.employee_rate}%` : '—'}</TableCell>
                      <TableCell align="right">{rule.employer_rate ? `${rule.employer_rate}%` : '—'}</TableCell>
                      <TableCell align="right">
                        <IconButton
                          size="small"
                          onClick={() => deleteContributionMutation.mutate({ ruleSetId: ruleSet.id, ruleId: rule.id })}
                        >
                          <DeleteIcon fontSize="small" />
                        </IconButton>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </TableContainer>
          )}
        </Paper>
      ))}

      <Dialog open={ruleSetDialogOpen} onClose={() => setRuleSetDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('statutoryRules.newRuleSet')}</DialogTitle>
        <Stack component="form" onSubmit={ruleSetForm.handleSubmit((values) => createRuleSetMutation.mutate(values))}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={t('statutoryRules.table.name')}
                fullWidth
                {...ruleSetForm.register('name')}
                error={!!ruleSetForm.formState.errors.name}
                helperText={ruleSetForm.formState.errors.name?.message}
              />
              <TextField
                label={t('statutoryRules.form.countryCode')}
                fullWidth
                {...ruleSetForm.register('country_code')}
                error={!!ruleSetForm.formState.errors.country_code}
                helperText={ruleSetForm.formState.errors.country_code?.message}
              />
              <TextField label={t('statutoryRules.form.description')} fullWidth multiline minRows={2} {...ruleSetForm.register('description')} />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setRuleSetDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createRuleSetMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={!!bandDialogFor} onClose={() => setBandDialogFor(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('statutoryRules.addTaxBand')}</DialogTitle>
        <Stack
          component="form"
          onSubmit={bandForm.handleSubmit((values) => bandDialogFor && createBandMutation.mutate({ ruleSetId: bandDialogFor.id, payload: values }))}
        >
          <DialogContent>
            <Stack spacing={2}>
              <TextField label={t('statutoryRules.table.lowerBound')} type="number" fullWidth {...bandForm.register('lower_bound')} />
              <TextField label={t('statutoryRules.table.upperBound')} type="number" fullWidth {...bandForm.register('upper_bound')} />
              <TextField label={t('statutoryRules.table.rate')} type="number" fullWidth {...bandForm.register('rate')} />
              <TextField label={t('statutoryRules.form.bandOrder')} type="number" fullWidth {...bandForm.register('band_order')} />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setBandDialogFor(null)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createBandMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <Dialog open={!!contributionDialogFor} onClose={() => setContributionDialogFor(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('statutoryRules.addContributionRule')}</DialogTitle>
        <Stack
          component="form"
          onSubmit={contributionForm.handleSubmit(
            (values) => contributionDialogFor && createContributionMutation.mutate({ ruleSetId: contributionDialogFor.id, payload: values }),
          )}
        >
          <DialogContent>
            <Stack spacing={2}>
              <TextField label={t('statutoryRules.table.code')} fullWidth {...contributionForm.register('code')} />
              <TextField label={t('statutoryRules.table.name')} fullWidth {...contributionForm.register('name')} />
              <TextField label={t('statutoryRules.table.employeeRate')} type="number" fullWidth {...contributionForm.register('employee_rate')} />
              <TextField label={t('statutoryRules.table.employerRate')} type="number" fullWidth {...contributionForm.register('employer_rate')} />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setContributionDialogFor(null)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createContributionMutation.isPending}>
              {tc('actions.create')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDeleteRuleSet}
        title={t('statutoryRules.deleteDialog.title')}
        message={t('statutoryRules.deleteDialog.message', { name: pendingDeleteRuleSet?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteRuleSetMutation.isPending}
        onConfirm={() => pendingDeleteRuleSet && deleteRuleSetMutation.mutate(pendingDeleteRuleSet.id)}
        onCancel={() => setPendingDeleteRuleSet(null)}
      />
    </Stack>
  );
}
