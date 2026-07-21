import { zodResolver } from '@hookform/resolvers/zod';
import { Button, CircularProgress, Divider, MenuItem, Paper, Stack, TextField, Typography } from '@mui/material';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import { Controller, useForm, type Resolver } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { fetchAccounts } from '../../../../api/endpoints/accounting';
import { fetchPayrollSettings, fetchStatutoryRuleSets, updatePayrollSettings } from '../../../../api/endpoints/hr';
import { useToast } from '../../../../hooks/useToast';
import { HrTabs } from './HrTabs';

const ACCOUNT_FIELDS = [
  'salary_expense_account_id',
  'allowance_expense_account_id',
  'overtime_expense_account_id',
  'bonus_expense_account_id',
  'employer_contribution_expense_account_id',
  'payroll_payable_account_id',
  'tax_payable_account_id',
  'statutory_contributions_payable_account_id',
  'loan_receivable_account_id',
  'advance_receivable_account_id',
  'other_deductions_payable_account_id',
  'bank_cash_account_id',
] as const;

function buildSchema() {
  const accountShape = Object.fromEntries(
    ACCOUNT_FIELDS.map((field) => [field, z.number().optional()]),
  ) as Record<(typeof ACCOUNT_FIELDS)[number], z.ZodOptional<z.ZodNumber>>;

  return z.object({
    statutory_rule_set_id: z.number().optional(),
    overtime_multiplier: z.coerce.number().min(1).max(10),
    standard_working_days_per_month: z.coerce.number().int().min(1).max(31),
    standard_hours_per_day: z.coerce.number().int().min(1).max(24),
    ...accountShape,
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function PayrollSettingsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema();

  const { data: settings, isLoading } = useQuery({ queryKey: ['hr', 'payroll-settings'], queryFn: fetchPayrollSettings });
  const { data: ruleSets } = useQuery({ queryKey: ['hr', 'statutory-rule-sets'], queryFn: fetchStatutoryRuleSets });
  const { data: accounts } = useQuery({ queryKey: ['accounting', 'accounts'], queryFn: () => fetchAccounts() });

  const updateMutation = useMutation({
    mutationFn: updatePayrollSettings,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['hr', 'payroll-settings'] });
      showToast(t('toast.payrollSettingsUpdated'));
    },
  });

  const { control, register, reset, handleSubmit } = useForm<FormValues>({
    resolver: zodResolver(schema) as Resolver<FormValues>,
    defaultValues: { overtime_multiplier: 1.5, standard_working_days_per_month: 26, standard_hours_per_day: 8 },
  });

  useEffect(() => {
    if (settings) {
      reset({
        statutory_rule_set_id: settings.statutory_rule_set_id ?? undefined,
        overtime_multiplier: Number(settings.overtime_multiplier),
        standard_working_days_per_month: settings.standard_working_days_per_month,
        standard_hours_per_day: settings.standard_hours_per_day,
        ...Object.fromEntries(ACCOUNT_FIELDS.map((field) => [field, settings[field] ?? undefined])),
      });
    }
  }, [settings, reset]);

  const accountRows = accounts?.data ?? [];
  const ruleSetRows = ruleSets?.data ?? [];

  const accountFieldLabel = (field: (typeof ACCOUNT_FIELDS)[number]) => t(`payrollSettings.fields.${field}`);

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Typography variant="h6">{t('payrollSettings.title')}</Typography>
      <Typography variant="body2" color="text.secondary">
        {t('payrollSettings.description')}
      </Typography>

      {isLoading && <CircularProgress />}

      {!isLoading && (
        <Paper variant="outlined" sx={{ p: 3 }}>
          <Stack
            component="form"
            spacing={3}
            onSubmit={handleSubmit((values) => updateMutation.mutate({ ...values, overtime_multiplier: String(values.overtime_multiplier) }))}
          >
            <Stack spacing={2}>
              <Typography variant="subtitle2">{t('payrollSettings.sections.general')}</Typography>
              <Controller
                name="statutory_rule_set_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('payrollSettings.fields.statutory_rule_set_id')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : undefined)}
                  >
                    <MenuItem value="">{tc('labels.none')}</MenuItem>
                    {ruleSetRows.map((ruleSet) => (
                      <MenuItem key={ruleSet.id} value={ruleSet.id}>
                        {ruleSet.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField
                label={t('payrollSettings.fields.overtime_multiplier')}
                type="number"
                fullWidth
                slotProps={{ htmlInput: { step: 0.1 } }}
                {...register('overtime_multiplier')}
              />
              <TextField
                label={t('payrollSettings.fields.standard_working_days_per_month')}
                type="number"
                fullWidth
                {...register('standard_working_days_per_month')}
              />
              <TextField
                label={t('payrollSettings.fields.standard_hours_per_day')}
                type="number"
                fullWidth
                {...register('standard_hours_per_day')}
              />
            </Stack>

            <Divider />

            <Stack spacing={2}>
              <Typography variant="subtitle2">{t('payrollSettings.sections.accounts')}</Typography>
              <Typography variant="caption" color="text.secondary">
                {t('payrollSettings.accountsHint')}
              </Typography>
              {ACCOUNT_FIELDS.map((field) => (
                <Controller
                  key={field}
                  name={field}
                  control={control}
                  render={({ field: controllerField }) => (
                    <TextField
                      label={accountFieldLabel(field)}
                      select
                      fullWidth
                      value={controllerField.value ?? ''}
                      onChange={(e) => controllerField.onChange(e.target.value ? Number(e.target.value) : undefined)}
                    >
                      <MenuItem value="">{tc('labels.none')}</MenuItem>
                      {accountRows.map((account) => (
                        <MenuItem key={account.id} value={account.id}>
                          {account.code} — {account.name}
                        </MenuItem>
                      ))}
                    </TextField>
                  )}
                />
              ))}
            </Stack>

            <Stack direction="row" justifyContent="flex-end">
              <Button type="submit" variant="contained" disabled={updateMutation.isPending}>
                {tc('actions.save')}
              </Button>
            </Stack>
          </Stack>
        </Paper>
      )}
    </Stack>
  );
}
