import {
  Chip,
  CircularProgress,
  Link,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Typography,
} from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router-dom';
import { fetchPortalShipments } from '../../../api/endpoints/portal';
import type { Shipment } from '../../../types';
import { EmptyState } from '../../../components/common/EmptyState';

const STATUS_COLOR: Record<Shipment['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  booked: 'default',
  in_transit: 'warning',
  arrived: 'info',
  cleared: 'success',
  delivered: 'success',
  cancelled: 'error',
};

export function PortalShipmentsPage() {
  const { t } = useTranslation('portal');
  const { data, isLoading } = useQuery({ queryKey: ['portal', 'shipments'], queryFn: () => fetchPortalShipments() });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('shipments.title')}
      </Typography>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && <EmptyState title={t('shipments.empty')} />}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('shipments.table.shipmentNo')}</TableCell>
                  <TableCell>{t('shipments.table.directionMode')}</TableCell>
                  <TableCell>{t('shipments.table.route')}</TableCell>
                  <TableCell>{t('shipments.table.status')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((shipment) => (
                  <TableRow key={shipment.id}>
                    <TableCell>
                      <Link component={RouterLink} to={`/portal/shipments/${shipment.id}`} underline="hover">
                        {shipment.shipment_number ?? '—'}
                      </Link>
                    </TableCell>
                    <TableCell>{shipment.direction} / {shipment.mode}</TableCell>
                    <TableCell>{shipment.origin_port ?? '—'} → {shipment.destination_port ?? '—'}</TableCell>
                    <TableCell>
                      <Chip label={shipment.status} size="small" color={STATUS_COLOR[shipment.status]} />
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}
    </Stack>
  );
}
