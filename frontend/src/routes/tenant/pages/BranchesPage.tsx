import {
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
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchBranchRollup, fetchBranches, fetchCompany } from '../../../api/endpoints/dashboard';
import { formatCurrency } from '../../../utils/currency';

function BranchRollupSection() {
  const { t } = useTranslation('branches');
  const { data: rollup, isLoading } = useQuery({ queryKey: ['tenant', 'branches', 'rollup'], queryFn: fetchBranchRollup });
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany });

  if (isLoading) return <CircularProgress />;
  if (!rollup) return null;

  return (
    <Stack spacing={2}>
      <Typography variant="h6" fontWeight={700}>
        {t('rollup.title')}
      </Typography>
      <Paper variant="outlined">
        <TableContainer>
          <Table size="small">
            <TableHead>
              <TableRow>
                <TableCell>{t('rollup.table.branch')}</TableCell>
                <TableCell align="right">{t('rollup.table.employees')}</TableCell>
                <TableCell align="right">{t('rollup.table.vehicles')}</TableCell>
                <TableCell align="right">{t('rollup.table.warehouseItems')}</TableCell>
                <TableCell align="right">{t('rollup.table.shipments')}</TableCell>
                <TableCell align="right">{t('rollup.table.invoices')}</TableCell>
                <TableCell align="right">{t('rollup.table.revenuePaid')}</TableCell>
                <TableCell align="right">{t('rollup.table.revenueOutstanding')}</TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {rollup.map((row) => (
                <TableRow key={row.branch_id ?? 'unassigned'}>
                  <TableCell>
                    <Stack direction="row" spacing={1} alignItems="center">
                      <span>{row.branch_name}</span>
                      {row.is_default && <Chip label={t('defaultChip')} size="small" color="primary" />}
                    </Stack>
                  </TableCell>
                  <TableCell align="right">{row.employees_total}</TableCell>
                  <TableCell align="right">{row.vehicles_total}</TableCell>
                  <TableCell align="right">{row.warehouse_items_total}</TableCell>
                  <TableCell align="right">{row.shipments_total}</TableCell>
                  <TableCell align="right">{row.invoices_total}</TableCell>
                  <TableCell align="right">{formatCurrency(row.revenue_paid, company?.currency)}</TableCell>
                  <TableCell align="right">{formatCurrency(row.revenue_outstanding, company?.currency)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </TableContainer>
      </Paper>
    </Stack>
  );
}

export function BranchesPage() {
  const { t } = useTranslation('branches');
  const { t: tc } = useTranslation('common');
  const { data: branches, isLoading } = useQuery({ queryKey: ['tenant', 'branches'], queryFn: fetchBranches });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('title')}
      </Typography>

      {isLoading && <CircularProgress />}

      {branches && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{tc('labels.name')}</TableCell>
                  <TableCell>{t('table.code')}</TableCell>
                  <TableCell>{t('table.city')}</TableCell>
                  <TableCell>{t('table.country')}</TableCell>
                  <TableCell>{t('table.default')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {branches.map((b) => (
                  <TableRow key={b.id}>
                    <TableCell>{b.name}</TableCell>
                    <TableCell>{b.code}</TableCell>
                    <TableCell>{b.city}</TableCell>
                    <TableCell>{b.country}</TableCell>
                    <TableCell>{b.is_default && <Chip label={t('defaultChip')} size="small" color="primary" />}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <BranchRollupSection />
    </Stack>
  );
}
