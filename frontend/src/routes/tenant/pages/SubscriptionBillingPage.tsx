import {
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid,
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
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { fetchPlans } from '../../../api/endpoints/plans';
import {
  changeSubscriptionPlan,
  fetchBillingProfile,
  fetchSubscription,
  fetchSubscriptionInvoices,
  updateBillingProfile,
} from '../../../api/endpoints/subscription';
import type { BillingProfile } from '../../../types';
import { useAuthStore } from '../../../hooks/useAuth';
import { useToast } from '../../../hooks/useToast';
import { formatCurrency } from '../../../utils/currency';

const STATUS_COLOR: Record<string, 'success' | 'warning' | 'error' | 'default'> = {
  active: 'success',
  trialing: 'warning',
  past_due: 'error',
  canceled: 'default',
  pending: 'warning',
  paid: 'success',
  overdue: 'error',
  void: 'default',
};

type BillingProfileForm = Pick<BillingProfile, 'billing_name' | 'billing_email' | 'billing_phone' | 'billing_address' | 'tax_id'>;

export function SubscriptionBillingPage() {
  const { t } = useTranslation('settings');
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];
  const canManage = permissions.includes('core.company.manage');
  const [changePlanOpen, setChangePlanOpen] = useState(false);

  const { data: subscription, isLoading } = useQuery({ queryKey: ['tenant', 'subscription'], queryFn: fetchSubscription });
  const { data: invoices } = useQuery({ queryKey: ['tenant', 'subscription', 'invoices'], queryFn: fetchSubscriptionInvoices });
  const { data: billingProfile } = useQuery({ queryKey: ['tenant', 'billing-profile'], queryFn: fetchBillingProfile });
  const { data: plans } = useQuery({ queryKey: ['plans'], queryFn: fetchPlans, enabled: changePlanOpen });

  const { register, handleSubmit, reset } = useForm<BillingProfileForm>();

  useEffect(() => {
    if (billingProfile) {
      reset({
        billing_name: billingProfile.billing_name,
        billing_email: billingProfile.billing_email ?? '',
        billing_phone: billingProfile.billing_phone ?? '',
        billing_address: billingProfile.billing_address ?? '',
        tax_id: billingProfile.tax_id ?? '',
      });
    }
  }, [billingProfile, reset]);

  const billingMutation = useMutation({
    mutationFn: updateBillingProfile,
    onSuccess: (data) => {
      queryClient.setQueryData(['tenant', 'billing-profile'], data);
      showToast(t('subscription.toast.billingSaved'));
    },
  });

  const [planCode, setPlanCode] = useState('');
  const [billingCycle, setBillingCycle] = useState<'monthly' | 'yearly'>('monthly');

  const changePlanMutation = useMutation({
    mutationFn: changeSubscriptionPlan,
    onSuccess: (data) => {
      queryClient.setQueryData(['tenant', 'subscription'], data);
      setChangePlanOpen(false);
      showToast(t('subscription.toast.planChanged'));
    },
  });

  if (isLoading) return <CircularProgress />;

  return (
    <Stack spacing={3} maxWidth={800}>
      <Typography variant="h5" fontWeight={700}>
        {t('subscription.title')}
      </Typography>

      <Card variant="outlined">
        <CardContent>
          <Stack spacing={2}>
            <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
              <Stack>
                <Typography variant="subtitle1" fontWeight={700}>
                  {subscription?.plan?.name}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {subscription && formatCurrency(
                    Number(subscription.billing_cycle === 'yearly' ? subscription.plan?.price_yearly : subscription.plan?.price_monthly),
                    subscription.plan?.currency
                  )}
                  {' / '}
                  {t(`subscription.cycle.${subscription?.billing_cycle}`)}
                </Typography>
              </Stack>
              <Chip
                size="small"
                label={t(`subscription.status.${subscription?.status}`)}
                color={STATUS_COLOR[subscription?.status ?? ''] ?? 'default'}
              />
            </Stack>

            <Grid container spacing={2}>
              {subscription?.trial_ends_at && (
                <Grid size={{ xs: 6, sm: 4 }}>
                  <Typography variant="caption" color="text.secondary">{t('subscription.trialEnds')}</Typography>
                  <Typography variant="body2" fontWeight={600}>{new Date(subscription.trial_ends_at).toLocaleDateString()}</Typography>
                </Grid>
              )}
              <Grid size={{ xs: 6, sm: 4 }}>
                <Typography variant="caption" color="text.secondary">{t('subscription.startedAt')}</Typography>
                <Typography variant="body2" fontWeight={600}>
                  {subscription && new Date(subscription.starts_at).toLocaleDateString()}
                </Typography>
              </Grid>
            </Grid>

            {canManage && (
              <Stack direction="row">
                <Button variant="outlined" onClick={() => setChangePlanOpen(true)}>
                  {t('subscription.changePlan')}
                </Button>
              </Stack>
            )}
          </Stack>
        </CardContent>
      </Card>

      <Card variant="outlined">
        <CardContent>
          <Stack spacing={2}>
            <Typography variant="subtitle1" fontWeight={700}>
              {t('subscription.billingProfile.title')}
            </Typography>
            <Stack
              component="form"
              spacing={2}
              onSubmit={handleSubmit((values) => billingMutation.mutate(values))}
            >
              <Grid container spacing={2}>
                <Grid size={12}>
                  <TextField
                    label={t('subscription.billingProfile.billingName')}
                    fullWidth
                    disabled={!canManage}
                    slotProps={{ inputLabel: { shrink: true } }}
                    {...register('billing_name')}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label={t('subscription.billingProfile.billingEmail')}
                    type="email"
                    fullWidth
                    disabled={!canManage}
                    slotProps={{ inputLabel: { shrink: true } }}
                    {...register('billing_email')}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label={t('subscription.billingProfile.billingPhone')}
                    fullWidth
                    disabled={!canManage}
                    slotProps={{ inputLabel: { shrink: true } }}
                    {...register('billing_phone')}
                  />
                </Grid>
                <Grid size={12}>
                  <TextField
                    label={t('subscription.billingProfile.billingAddress')}
                    fullWidth
                    disabled={!canManage}
                    slotProps={{ inputLabel: { shrink: true } }}
                    {...register('billing_address')}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label={t('subscription.billingProfile.taxId')}
                    fullWidth
                    disabled={!canManage}
                    slotProps={{ inputLabel: { shrink: true } }}
                    {...register('tax_id')}
                  />
                </Grid>
              </Grid>
              {canManage && (
                <Stack direction="row" justifyContent="flex-end">
                  <Button type="submit" variant="contained" disabled={billingMutation.isPending}>
                    {t('saveChanges')}
                  </Button>
                </Stack>
              )}
            </Stack>
          </Stack>
        </CardContent>
      </Card>

      <Card variant="outlined">
        <CardContent>
          <Stack spacing={2}>
            <Typography variant="subtitle1" fontWeight={700}>
              {t('subscription.invoices.title')}
            </Typography>
            {(!invoices || invoices.length === 0) && (
              <Typography variant="body2" color="text.secondary">
                {t('subscription.invoices.empty')}
              </Typography>
            )}
            {invoices && invoices.length > 0 && (
              <TableContainer component={Paper} variant="outlined">
                <Table size="small">
                  <TableHead>
                    <TableRow>
                      <TableCell>{t('subscription.invoices.period')}</TableCell>
                      <TableCell align="right">{t('subscription.invoices.amount')}</TableCell>
                      <TableCell>{t('subscription.invoices.dueDate')}</TableCell>
                      <TableCell>{t('subscription.invoices.status')}</TableCell>
                    </TableRow>
                  </TableHead>
                  <TableBody>
                    {invoices.map((invoice) => (
                      <TableRow key={invoice.id}>
                        <TableCell>
                          {new Date(invoice.period_start).toLocaleDateString()} – {new Date(invoice.period_end).toLocaleDateString()}
                        </TableCell>
                        <TableCell align="right">{formatCurrency(Number(invoice.amount), invoice.currency)}</TableCell>
                        <TableCell>{new Date(invoice.due_date).toLocaleDateString()}</TableCell>
                        <TableCell>
                          <Chip size="small" label={t(`subscription.status.${invoice.status}`)} color={STATUS_COLOR[invoice.status] ?? 'default'} />
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </TableContainer>
            )}
          </Stack>
        </CardContent>
      </Card>

      <Dialog open={changePlanOpen} onClose={() => setChangePlanOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('subscription.changePlanDialog.title')}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ mt: 1 }}>
            <TextField
              select
              label={t('subscription.changePlanDialog.plan')}
              value={planCode}
              onChange={(e) => setPlanCode(e.target.value)}
              fullWidth
            >
              {plans?.map((plan) => (
                <MenuItem key={plan.code} value={plan.code}>
                  {plan.name}
                </MenuItem>
              ))}
            </TextField>
            <TextField
              select
              label={t('subscription.changePlanDialog.cycle')}
              value={billingCycle}
              onChange={(e) => setBillingCycle(e.target.value as 'monthly' | 'yearly')}
              fullWidth
            >
              <MenuItem value="monthly">{t('subscription.cycle.monthly')}</MenuItem>
              <MenuItem value="yearly">{t('subscription.cycle.yearly')}</MenuItem>
            </TextField>
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setChangePlanOpen(false)}>{t('subscription.changePlanDialog.cancel')}</Button>
          <Button
            variant="contained"
            disabled={!planCode || changePlanMutation.isPending}
            onClick={() => changePlanMutation.mutate({ plan_code: planCode, billing_cycle: billingCycle })}
          >
            {t('subscription.changePlanDialog.confirm')}
          </Button>
        </DialogActions>
      </Dialog>
    </Stack>
  );
}
