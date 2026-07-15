import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Divider,
  FormControlLabel,
  Checkbox,
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
import AddIcon from '@mui/icons-material/Add';
import { PieChart } from '@mui/x-charts/PieChart';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  addShipmentMilestone,
  fetchDeliveryNoteQr,
  fetchShipment,
  fetchShipmentCostSummary,
  fetchShipmentTrackingQr,
} from '../../../../api/endpoints/shipments';
import type { TrackingEventType } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { StatWidgetCard } from '../../../../components/common/StatWidgetCard';
import { TrackingQrCode } from '../../../../components/common/TrackingQrCode';
import { useAuthStore } from '../../../../hooks/useAuth';
import { useToast } from '../../../../hooks/useToast';
import { formatCurrency } from '../../../../utils/currency';

// Validated categorical slots from the design system's reference palette (dataviz skill).
const CATEGORY_COLORS = ['#2a78d6', '#008300', '#e87ba4', '#eda100', '#1baf7a', '#eb6834', '#4a3aa7', '#e34948'];

const STATUS_COLOR: Record<string, 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  booked: 'default',
  in_transit: 'warning',
  arrived: 'info',
  cleared: 'success',
  delivered: 'success',
  cancelled: 'error',
};

const EVENT_TYPES: TrackingEventType[] = [
  'booked',
  'gate_in',
  'loaded',
  'departed',
  'in_transit',
  'customs_hold',
  'customs_cleared',
  'arrived',
  'gate_out',
  'out_for_delivery',
  'delivered',
  'exception',
];

function buildSchema(t: (key: string) => string) {
  return z.object({
    event_type: z.enum([
      'booked', 'gate_in', 'loaded', 'departed', 'in_transit', 'customs_hold',
      'customs_cleared', 'arrived', 'gate_out', 'out_for_delivery', 'delivered', 'exception',
    ]),
    location: z.string().optional(),
    occurred_at: z.string().min(1, t('validation.selectCustomer')),
    notes: z.string().optional(),
    is_customer_visible: z.boolean(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

function toLocalDateTimeInputValue(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function ShipmentDetailPage() {
  const { t } = useTranslation('shipments');
  const { t: tc } = useTranslation('common');
  const { id } = useParams<{ id: string }>();
  const shipmentId = Number(id);
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t);
  const [dialogOpen, setDialogOpen] = useState(false);
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];
  const canViewCosts = permissions.includes('shipments.costs.view');

  const { data: shipment, isLoading } = useQuery({
    queryKey: ['shipments', 'item', shipmentId],
    queryFn: () => fetchShipment(shipmentId),
  });

  const { data: costSummary, isLoading: costsLoading } = useQuery({
    queryKey: ['shipments', 'item', shipmentId, 'cost-summary'],
    queryFn: () => fetchShipmentCostSummary(shipmentId),
    enabled: canViewCosts,
  });

  const addMilestoneMutation = useMutation({
    mutationFn: (values: FormValues) => addShipmentMilestone(shipmentId, values),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['shipments', 'item', shipmentId] });
      setDialogOpen(false);
      showToast(t('detail.toast.milestoneAdded'));
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
    defaultValues: { event_type: 'in_transit', is_customer_visible: true, occurred_at: toLocalDateTimeInputValue(new Date()) },
  });

  const onCreate = (values: FormValues) => addMilestoneMutation.mutate(values);

  if (isLoading || !shipment) {
    return <CircularProgress />;
  }

  return (
    <Stack spacing={3}>
      <Button onClick={() => navigate('/app/shipments')} sx={{ alignSelf: 'flex-start' }}>
        {t('detail.backToShipments')}
      </Button>

      <Stack direction="row" alignItems="center" spacing={1.5} flexWrap="wrap">
        <Typography variant="h5" fontWeight={700}>
          {shipment.shipment_number}
        </Typography>
        <Chip label={t(`statuses.${shipment.status}`)} size="small" color={STATUS_COLOR[shipment.status]} />
        {shipment.is_at_risk && <Chip label={t('riskBadge.atRisk')} size="small" color="error" variant="outlined" />}
      </Stack>

      <Paper variant="outlined" sx={{ p: 3 }}>
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{tc('labels.name')}</Typography>
            <Typography variant="body1">{shipment.customer?.company_name ?? '—'}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('detail.trackingCodeLabel')}</Typography>
            <Typography variant="body1" sx={{ fontFamily: 'monospace' }}>{shipment.tracking_code ?? '—'}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('detail.fields.directionMode')}</Typography>
            <Typography variant="body1">{t(`direction.${shipment.direction}`)} / {t(`mode.${shipment.mode}`)}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('detail.fields.route')}</Typography>
            <Typography variant="body1">{shipment.origin_port ?? '—'} → {shipment.destination_port ?? '—'}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('detail.fields.etd')}</Typography>
            <Typography variant="body1">{shipment.etd ?? '—'}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('detail.fields.eta')}</Typography>
            <Typography variant="body1">{shipment.eta ?? '—'}</Typography>
          </Grid>
        </Grid>
      </Paper>

      {canViewCosts && (
        <Stack spacing={2}>
          <Typography variant="h6" fontWeight={700}>
            {t('detail.costs.title')}
          </Typography>

          {costsLoading && <CircularProgress size={28} />}

          {!costsLoading && costSummary && (
            <>
              <Grid container spacing={2}>
                <Grid size={{ xs: 12, sm: 6, md: 3 }}>
                  <StatWidgetCard
                    label={t('detail.costs.billedRevenue')}
                    value={formatCurrency(costSummary.revenue.billed, costSummary.currency)}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6, md: 3 }}>
                  <StatWidgetCard
                    label={t('detail.costs.confirmedCost')}
                    value={formatCurrency(costSummary.cost.confirmed, costSummary.currency)}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6, md: 3 }}>
                  <StatWidgetCard
                    label={t('detail.costs.profit')}
                    value={formatCurrency(costSummary.profit, costSummary.currency)}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6, md: 3 }}>
                  <StatWidgetCard
                    label={t('detail.costs.margin')}
                    value={costSummary.margin_percent === null ? '—' : `${costSummary.margin_percent}%`}
                  />
                </Grid>
              </Grid>

              {costSummary.cost_breakdown.length === 0 && (
                <EmptyState
                  title={t('detail.costs.empty.title')}
                  description={t('detail.costs.empty.description')}
                />
              )}

              {costSummary.cost_breakdown.length > 0 && (
                <Paper variant="outlined" sx={{ p: 2 }}>
                  <Typography variant="subtitle2" sx={{ mb: 1 }}>
                    {t('detail.costs.breakdownTitle')}
                  </Typography>
                  <PieChart
                    series={[
                      {
                        data: costSummary.cost_breakdown.map((item, index) => ({
                          id: item.category,
                          value: item.amount,
                          label: t(`categories.${item.category}`, { ns: 'expenses' }),
                          color: CATEGORY_COLORS[index % CATEGORY_COLORS.length],
                        })),
                        innerRadius: 40,
                        highlightScope: { fade: 'global', highlight: 'item' },
                      },
                    ]}
                    height={220}
                  />
                </Paper>
              )}

              {costSummary.invoices.length > 0 && (
                <Paper variant="outlined">
                  <Typography variant="subtitle2" sx={{ p: 2, pb: 0 }}>
                    {t('detail.costs.invoicesTitle')}
                  </Typography>
                  <TableContainer>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>{t('detail.costs.table.invoiceNumber')}</TableCell>
                          <TableCell>{tc('labels.status')}</TableCell>
                          <TableCell align="right">{t('detail.costs.table.amount')}</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {costSummary.invoices.map((invoice) => (
                          <TableRow key={invoice.id}>
                            <TableCell>{invoice.invoice_number ?? '—'}</TableCell>
                            <TableCell>
                              <Chip label={invoice.status} size="small" />
                            </TableCell>
                            <TableCell align="right">{formatCurrency(invoice.total_amount, invoice.currency)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                </Paper>
              )}

              {costSummary.expenses.length > 0 && (
                <Paper variant="outlined">
                  <Typography variant="subtitle2" sx={{ p: 2, pb: 0 }}>
                    {t('detail.costs.expensesTitle')}
                  </Typography>
                  <TableContainer>
                    <Table size="small">
                      <TableHead>
                        <TableRow>
                          <TableCell>{t('detail.costs.table.description')}</TableCell>
                          <TableCell>{tc('labels.status')}</TableCell>
                          <TableCell align="right">{t('detail.costs.table.amount')}</TableCell>
                        </TableRow>
                      </TableHead>
                      <TableBody>
                        {costSummary.expenses.map((expense) => (
                          <TableRow key={expense.id}>
                            <TableCell>{expense.description}</TableCell>
                            <TableCell>
                              <Chip label={expense.status} size="small" />
                            </TableCell>
                            <TableCell align="right">{formatCurrency(expense.amount, expense.currency)}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </TableContainer>
                </Paper>
              )}
            </>
          )}
        </Stack>
      )}

      <TrackingQrCode
        queryKey={['shipments', 'item', shipmentId, 'tracking-qr']}
        fetchQr={() => fetchShipmentTrackingQr(shipmentId)}
        trackingCode={shipment.tracking_code ?? null}
        alt={t('detail.qr.alt')}
        caption={t('detail.qr.caption')}
        downloadLabel={t('detail.qr.download')}
      />

      {shipment.status === 'delivered' && (
        <TrackingQrCode
          queryKey={['shipments', 'item', shipmentId, 'delivery-note-qr']}
          fetchQr={() => fetchDeliveryNoteQr(shipmentId)}
          trackingCode={shipment.tracking_code ?? null}
          alt={t('detail.deliveryNoteQr.alt')}
          caption={t('detail.deliveryNoteQr.caption')}
          downloadLabel={t('detail.deliveryNoteQr.download')}
          filenamePrefix="delivery-note-qr"
        />
      )}

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6" fontWeight={700}>
          {t('detail.timelineTitle')}
        </Typography>
        <Button variant="outlined" size="small" startIcon={<AddIcon />} onClick={() => { reset(); setDialogOpen(true); }}>
          {t('detail.addMilestone')}
        </Button>
      </Stack>

      {(!shipment.milestones || shipment.milestones.length === 0) && (
        <EmptyState title={t('detail.empty.title')} description={t('detail.empty.description')} />
      )}

      {shipment.milestones && shipment.milestones.length > 0 && (
        <Paper variant="outlined" sx={{ p: 3 }}>
          <Stack spacing={0} divider={<Divider />}>
            {shipment.milestones.map((milestone) => (
              <Stack key={milestone.id} direction="row" spacing={2} alignItems="flex-start" sx={{ py: 1.5 }}>
                <Chip label={t(`eventTypes.${milestone.event_type}`)} size="small" color="primary" variant="outlined" sx={{ minWidth: 140 }} />
                <Stack spacing={0.25} sx={{ flex: 1 }}>
                  <Typography variant="body2" fontWeight={600}>
                    {new Date(milestone.occurred_at).toLocaleString()}
                    {milestone.location ? ` — ${milestone.location}` : ''}
                  </Typography>
                  {milestone.notes && (
                    <Typography variant="body2" color="text.secondary">{milestone.notes}</Typography>
                  )}
                </Stack>
              </Stack>
            ))}
          </Stack>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('detail.dialog.title')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onCreate)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField label={t('detail.form.eventType')} select fullWidth defaultValue="in_transit" {...register('event_type')}>
                {EVENT_TYPES.map((type) => (
                  <MenuItem key={type} value={type}>{t(`eventTypes.${type}`)}</MenuItem>
                ))}
              </TextField>
              <TextField label={t('detail.form.location')} fullWidth {...register('location')} />
              <TextField
                label={t('detail.form.occurredAt')}
                type="datetime-local"
                fullWidth
                InputLabelProps={{ shrink: true }}
                {...register('occurred_at')}
                error={!!errors.occurred_at}
                helperText={errors.occurred_at?.message}
              />
              <TextField label={t('detail.form.notes')} fullWidth multiline rows={2} {...register('notes')} />
              <Controller
                name="is_customer_visible"
                control={control}
                render={({ field }) => (
                  <FormControlLabel
                    control={<Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)} />}
                    label={t('detail.form.customerVisible')}
                  />
                )}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={addMilestoneMutation.isPending}>
              {tc('actions.add')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>
    </Stack>
  );
}
