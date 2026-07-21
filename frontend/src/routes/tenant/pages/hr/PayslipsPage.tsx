import {
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
import DownloadRoundedIcon from '@mui/icons-material/DownloadRounded';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { downloadPayslipPdf, fetchPayslips } from '../../../../api/endpoints/hr';
import type { Payslip } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { formatCurrency } from '../../../../utils/currency';
import { downloadBlobAsFile } from '../../../../utils/downloadFile';
import { HrTabs } from './HrTabs';

export function PayslipsPage() {
  const { t } = useTranslation('hr');
  const { t: tc } = useTranslation('common');

  const { data, isLoading } = useQuery({ queryKey: ['hr', 'payslips'], queryFn: () => fetchPayslips() });

  const handleDownload = (payslip: Payslip) =>
    downloadBlobAsFile(() => downloadPayslipPdf(payslip.id), `payslip-${payslip.payslip_number ?? payslip.id}.pdf`);

  const rows = data?.data ?? [];

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      <HrTabs />

      <Typography variant="h6">{t('payslips.title')}</Typography>

      {rows.length === 0 && !isLoading && (
        <EmptyState title={t('payslips.empty.title')} description={t('payslips.empty.description')} />
      )}

      {rows.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('payslips.table.number')}</TableCell>
                  <TableCell>{t('payslips.table.employee')}</TableCell>
                  <TableCell>{t('payslips.table.period')}</TableCell>
                  <TableCell align="right">{t('payslips.table.gross')}</TableCell>
                  <TableCell align="right">{t('payslips.table.net')}</TableCell>
                  <TableCell align="right">{t('payslips.table.ytdNet')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {rows.map((payslip) => (
                  <TableRow key={payslip.id}>
                    <TableCell>{payslip.payslip_number ?? '—'}</TableCell>
                    <TableCell>{payslip.employee?.name ?? '—'}</TableCell>
                    <TableCell>{payslip.period?.name ?? '—'}</TableCell>
                    <TableCell align="right">{formatCurrency(Number(payslip.gross_pay))}</TableCell>
                    <TableCell align="right">{formatCurrency(Number(payslip.net_pay))}</TableCell>
                    <TableCell align="right">{formatCurrency(Number(payslip.ytd_net))}</TableCell>
                    <TableCell align="right">
                      <Tooltip title={t('payslips.download')}>
                        <IconButton size="small" onClick={() => handleDownload(payslip)}>
                          <DownloadRoundedIcon fontSize="small" />
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
