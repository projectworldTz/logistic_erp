import {
  Chip,
  CircularProgress,
  IconButton,
  Paper,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Tooltip,
  Typography,
} from '@mui/material';
import PictureAsPdfIcon from '@mui/icons-material/PictureAsPdf';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { downloadPortalInvoicePdf, fetchPortalInvoices } from '../../../api/endpoints/portal';
import type { Invoice } from '../../../types';
import { EmptyState } from '../../../components/common/EmptyState';

const STATUS_COLOR: Record<Invoice['status'], 'default' | 'info' | 'warning' | 'success' | 'error'> = {
  draft: 'default',
  sent: 'info',
  paid: 'success',
  overdue: 'error',
  cancelled: 'error',
};

export function PortalInvoicesPage() {
  const { t } = useTranslation('portal');
  const { data, isLoading } = useQuery({ queryKey: ['portal', 'invoices'], queryFn: () => fetchPortalInvoices() });

  const handleDownload = async (invoice: Invoice) => {
    const blob = await downloadPortalInvoicePdf(invoice.id);
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `invoice-${invoice.invoice_number ?? invoice.id}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  };

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('invoices.title')}
      </Typography>

      {isLoading && <CircularProgress />}

      {data && data.data.length === 0 && <EmptyState title={t('invoices.empty')} />}

      {data && data.data.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('invoices.table.invoiceNo')}</TableCell>
                  <TableCell>{t('invoices.table.dueDate')}</TableCell>
                  <TableCell>{t('invoices.table.total')}</TableCell>
                  <TableCell>{t('invoices.table.status')}</TableCell>
                  <TableCell align="right" />
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((invoice) => (
                  <TableRow key={invoice.id}>
                    <TableCell>{invoice.invoice_number ?? '—'}</TableCell>
                    <TableCell>{invoice.due_date}</TableCell>
                    <TableCell>{invoice.currency} {invoice.total_amount}</TableCell>
                    <TableCell>
                      <Chip label={invoice.status} size="small" color={STATUS_COLOR[invoice.status]} />
                    </TableCell>
                    <TableCell align="right">
                      <Tooltip title={t('invoices.download')}>
                        <IconButton size="small" onClick={() => handleDownload(invoice)}>
                          <PictureAsPdfIcon fontSize="small" />
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
    </Stack>
  );
}
