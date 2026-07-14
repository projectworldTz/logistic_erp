import { Button, Chip, CircularProgress, Divider, Grid, Paper, Stack, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams } from 'react-router-dom';
import { fetchPortalShipment, fetchPortalShipmentTrackingQr } from '../../../api/endpoints/portal';
import { EmptyState } from '../../../components/common/EmptyState';
import { TrackingQrCode } from '../../../components/common/TrackingQrCode';

const STATUS_COLOR: Record<string, 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  booked: 'default',
  in_transit: 'warning',
  arrived: 'info',
  cleared: 'success',
  delivered: 'success',
  cancelled: 'error',
};

export function PortalShipmentDetailPage() {
  const { t } = useTranslation('portal');
  const { id } = useParams<{ id: string }>();
  const shipmentId = Number(id);
  const navigate = useNavigate();

  const { data: shipment, isLoading } = useQuery({
    queryKey: ['portal', 'shipment', shipmentId],
    queryFn: () => fetchPortalShipment(shipmentId),
  });

  if (isLoading || !shipment) {
    return <CircularProgress />;
  }

  return (
    <Stack spacing={3}>
      <Button onClick={() => navigate('/portal/shipments')} sx={{ alignSelf: 'flex-start' }}>
        {t('shipments.detail.backToShipments')}
      </Button>

      <Stack direction="row" alignItems="center" spacing={1.5}>
        <Typography variant="h5" fontWeight={700}>
          {shipment.shipment_number}
        </Typography>
        <Chip label={shipment.status} size="small" color={STATUS_COLOR[shipment.status]} />
      </Stack>

      <Paper variant="outlined" sx={{ p: 3 }}>
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('shipments.detail.trackingCodeLabel')}</Typography>
            <Typography variant="body1" sx={{ fontFamily: 'monospace' }}>{shipment.tracking_code ?? '—'}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('shipments.table.directionMode')}</Typography>
            <Typography variant="body1">{shipment.direction} / {shipment.mode}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('shipments.table.route')}</Typography>
            <Typography variant="body1">{shipment.origin_port ?? '—'} → {shipment.destination_port ?? '—'}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">ETA</Typography>
            <Typography variant="body1">{shipment.eta ?? '—'}</Typography>
          </Grid>
        </Grid>
      </Paper>

      <TrackingQrCode
        queryKey={['portal', 'shipment', shipmentId, 'tracking-qr']}
        fetchQr={() => fetchPortalShipmentTrackingQr(shipmentId)}
        trackingCode={shipment.tracking_code ?? null}
        alt={t('shipments.detail.qr.alt')}
        caption={t('shipments.detail.qr.caption')}
        downloadLabel={t('shipments.detail.qr.download')}
      />

      <Typography variant="h6" fontWeight={700}>
        {t('shipments.detail.timelineTitle')}
      </Typography>

      {(!shipment.milestones || shipment.milestones.length === 0) && (
        <EmptyState title={t('shipments.detail.empty')} />
      )}

      {shipment.milestones && shipment.milestones.length > 0 && (
        <Paper variant="outlined" sx={{ p: 3 }}>
          <Stack spacing={0} divider={<Divider />}>
            {shipment.milestones.map((milestone) => (
              <Stack key={milestone.id} direction="row" spacing={2} alignItems="flex-start" sx={{ py: 1.5 }}>
                <Chip label={milestone.event_type} size="small" color="primary" variant="outlined" sx={{ minWidth: 140 }} />
                <Typography variant="body2">
                  {new Date(milestone.occurred_at).toLocaleString()}
                  {milestone.location ? ` — ${milestone.location}` : ''}
                </Typography>
              </Stack>
            ))}
          </Stack>
        </Paper>
      )}
    </Stack>
  );
}
