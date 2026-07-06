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
import { activateTenant, fetchTenants, suspendTenant } from '../../../api/endpoints/platform';
import type { Tenant } from '../../../types';

const STATUS_COLOR: Record<Tenant['status'], 'default' | 'success' | 'warning' | 'error'> = {
  trial: 'default',
  active: 'success',
  suspended: 'warning',
  cancelled: 'error',
};

export function TenantsListPage() {
  const { t } = useTranslation('superAdmin');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({ queryKey: ['platform', 'tenants'], queryFn: () => fetchTenants() });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['platform', 'tenants'] });
  const suspendMutation = useMutation({ mutationFn: suspendTenant, onSuccess: invalidate });
  const activateMutation = useMutation({ mutationFn: activateTenant, onSuccess: invalidate });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('tenantsList.title')}
      </Typography>

      {isLoading && <CircularProgress />}

      {data && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('tenantsList.table.company')}</TableCell>
                  <TableCell>{t('tenantsList.table.plan')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  <TableCell>{t('tenantsList.table.created')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {data.data.map((tenant) => (
                  <TableRow key={tenant.id}>
                    <TableCell>{tenant.name}</TableCell>
                    <TableCell>{tenant.subscription?.plan?.name ?? '—'}</TableCell>
                    <TableCell>
                      <Chip
                        label={t(`tenantsList.statuses.${tenant.status}`)}
                        size="small"
                        color={STATUS_COLOR[tenant.status]}
                      />
                    </TableCell>
                    <TableCell>{new Date(tenant.created_at).toLocaleDateString()}</TableCell>
                    <TableCell align="right">
                      {tenant.status === 'suspended' ? (
                        <Button size="small" onClick={() => activateMutation.mutate(tenant.id)}>
                          {t('tenantsList.actions.activate')}
                        </Button>
                      ) : (
                        <Button size="small" color="warning" onClick={() => suspendMutation.mutate(tenant.id)}>
                          {t('tenantsList.actions.suspend')}
                        </Button>
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
