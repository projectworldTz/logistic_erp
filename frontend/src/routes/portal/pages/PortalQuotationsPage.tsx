import {
  Button,
  Chip,
  CircularProgress,
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
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { approvePortalQuotation, fetchPortalQuotations, rejectPortalQuotation } from '../../../api/endpoints/portal';
import type { Quotation } from '../../../types';
import { EmptyState } from '../../../components/common/EmptyState';
import { useToast } from '../../../hooks/useToast';

const STATUS_COLOR: Record<Quotation['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  draft: 'default',
  sent: 'info',
  accepted: 'success',
  rejected: 'error',
  expired: 'warning',
};

export function PortalQuotationsPage() {
  const { t } = useTranslation('portal');
  const { showToast } = useToast();
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({ queryKey: ['portal', 'quotations'], queryFn: () => fetchPortalQuotations() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['portal', 'quotations'] });

  const approveMutation = useMutation({
    mutationFn: approvePortalQuotation,
    onSuccess: () => {
      invalidate();
      showToast(t('quotations.toast.approved'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: rejectPortalQuotation,
    onSuccess: () => {
      invalidate();
      showToast(t('quotations.toast.rejected'));
    },
  });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('quotations.title')}
      </Typography>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && <EmptyState title={t('quotations.empty')} />}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('quotations.table.quotationNo')}</TableCell>
                  <TableCell>{t('quotations.table.validUntil')}</TableCell>
                  <TableCell>{t('quotations.table.total')}</TableCell>
                  <TableCell>{t('quotations.table.status')}</TableCell>
                  <TableCell align="right" />
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((quotation) => (
                  <TableRow key={quotation.id}>
                    <TableCell>{quotation.quotation_number ?? '—'}</TableCell>
                    <TableCell>{quotation.valid_until}</TableCell>
                    <TableCell>{quotation.currency} {quotation.total_amount}</TableCell>
                    <TableCell>
                      <Chip label={quotation.status} size="small" color={STATUS_COLOR[quotation.status]} />
                    </TableCell>
                    <TableCell align="right">
                      {quotation.status === 'sent' && (
                        <Stack direction="row" spacing={1} justifyContent="flex-end">
                          <Button size="small" variant="contained" onClick={() => approveMutation.mutate(quotation.id)}>
                            {t('quotations.approve')}
                          </Button>
                          <Button size="small" color="error" onClick={() => rejectMutation.mutate(quotation.id)}>
                            {t('quotations.reject')}
                          </Button>
                        </Stack>
                      )}
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
