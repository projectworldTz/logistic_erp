import { Chip, CircularProgress, Paper, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchBranches } from '../../../api/endpoints/dashboard';

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
    </Stack>
  );
}
