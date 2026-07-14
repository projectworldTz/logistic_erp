import { zodResolver } from '@hookform/resolvers/zod';
import {
  Alert,
  Button,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  MenuItem,
  Paper,
  Select,
  Stack,
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import BlockIcon from '@mui/icons-material/Block';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { fetchBranches, fetchUsers } from '../../../api/endpoints/dashboard';
import {
  activateUser,
  fetchRoles,
  inviteUser,
  suspendUser,
  updateUser,
  type InviteUserPayload,
} from '../../../api/endpoints/users';
import { useAuthStore } from '../../../hooks/useAuth';
import { usePermission } from '../../../hooks/usePermission';
import { useToast } from '../../../hooks/useToast';
import { ConfirmDialog } from '../../../components/common/ConfirmDialog';
import type { User } from '../../../types';

function buildSchema(t: (key: string) => string, tc: (key: string, opts?: Record<string, unknown>) => string) {
  return z.object({
    name: z.string().min(1, t('validation.nameRequired')),
    email: z.string().email(tc('validation.invalidEmail')),
    phone: z.string().optional(),
    branch_id: z.number().nullable(),
    role: z.string().min(1, t('validation.roleRequired')),
    password: z.string().min(8, tc('validation.minLength', { count: 8 })),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

export function UsersPage() {
  const { t } = useTranslation('users');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const schema = buildSchema(t, tc);
  const currentUserId = useAuthStore((s) => s.user?.id);
  const canManage = usePermission('core.users.manage');
  const [dialogOpen, setDialogOpen] = useState(false);
  const [pendingSuspend, setPendingSuspend] = useState<User | null>(null);
  const [pendingRoleChange, setPendingRoleChange] = useState<{ user: User; role: string } | null>(null);

  const {
    data: users,
    isLoading,
    isError: isListError,
  } = useQuery({ queryKey: ['tenant', 'users'], queryFn: fetchUsers, retry: false });
  const { data: branches } = useQuery({ queryKey: ['tenant', 'branches'], queryFn: fetchBranches });
  const { data: roles } = useQuery({ queryKey: ['tenant', 'roles'], queryFn: fetchRoles, enabled: canManage });

  const invalidateUsers = () => queryClient.invalidateQueries({ queryKey: ['tenant', 'users'] });

  const inviteMutation = useMutation({
    mutationFn: (payload: InviteUserPayload) => inviteUser(payload),
    onSuccess: () => {
      invalidateUsers();
      setDialogOpen(false);
      showToast(t('toast.invited'));
    },
  });

  const roleMutation = useMutation({
    mutationFn: ({ id, role }: { id: number; role: string }) => updateUser(id, { role }),
    onSuccess: () => {
      invalidateUsers();
      setPendingRoleChange(null);
      showToast(t('toast.roleUpdated'));
    },
  });

  const suspendMutation = useMutation({
    mutationFn: suspendUser,
    onSuccess: () => {
      invalidateUsers();
      setPendingSuspend(null);
      showToast(t('toast.suspended'));
    },
  });
  const activateMutation = useMutation({
    mutationFn: activateUser,
    onSuccess: () => {
      invalidateUsers();
      showToast(t('toast.activated'));
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
    defaultValues: { branch_id: null },
  });

  const onInvite = (values: FormValues) => inviteMutation.mutate(values);

  return (
    <Stack spacing={3}>
      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h5" fontWeight={700}>
          {t('title')}
        </Typography>
        {canManage && (
          <Button
            variant="contained"
            startIcon={<AddIcon />}
            onClick={() => {
              reset({ branch_id: null });
              setDialogOpen(true);
            }}
          >
            {t('inviteUser')}
          </Button>
        )}
      </Stack>

      {inviteMutation.isError && <Alert severity="error">{t('errors.invite')}</Alert>}
      {roleMutation.isError && <Alert severity="error">{t('errors.roleUpdate')}</Alert>}
      {(suspendMutation.isError || activateMutation.isError) && (
        <Alert severity="error">{t('errors.statusUpdate')}</Alert>
      )}

      {isLoading && <CircularProgress />}

      {isListError && <Alert severity="error">{t('errors.noPermission')}</Alert>}

      {users && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{tc('labels.name')}</TableCell>
                  <TableCell>{tc('labels.email')}</TableCell>
                  <TableCell>{t('table.role')}</TableCell>
                  <TableCell>{tc('labels.status')}</TableCell>
                  {canManage && <TableCell align="right">{tc('actions.actions')}</TableCell>}
                </TableRow>
              </TableHead>
              <TableBody>
                {users.map((u) => (
                  <TableRow key={u.id}>
                    <TableCell>{u.name}</TableCell>
                    <TableCell>{u.email}</TableCell>
                    <TableCell>
                      {canManage && roles && u.id !== currentUserId ? (
                        <Select
                          size="small"
                          value={u.roles[0] ?? ''}
                          onChange={(e) => setPendingRoleChange({ user: u, role: e.target.value })}
                        >
                          {roles.map((role) => (
                            <MenuItem key={role} value={role}>
                              {role}
                            </MenuItem>
                          ))}
                        </Select>
                      ) : (
                        <Tooltip title={u.id === currentUserId ? t('tooltips.cantChangeOwnRole') : ''}>
                          <span>{u.roles.join(', ')}</span>
                        </Tooltip>
                      )}
                    </TableCell>
                    <TableCell>
                      <Chip label={t(`statuses.${u.status}`)} size="small" color={u.status === 'active' ? 'success' : 'default'} />
                    </TableCell>
                    {canManage && (
                      <TableCell align="right">
                        {u.status === 'active' ? (
                          <Tooltip title={u.id === currentUserId ? t('tooltips.cantSuspendSelf') : t('actions.suspend')}>
                            <span>
                              <IconButton
                                size="small"
                                disabled={u.id === currentUserId}
                                onClick={() => setPendingSuspend(u)}
                              >
                                <BlockIcon fontSize="small" />
                              </IconButton>
                            </span>
                          </Tooltip>
                        ) : (
                          <Tooltip title={t('actions.activate')}>
                            <IconButton size="small" onClick={() => activateMutation.mutate(u.id)}>
                              <CheckCircleIcon fontSize="small" />
                            </IconButton>
                          </Tooltip>
                        )}
                      </TableCell>
                    )}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('inviteDialog.title')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onInvite)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField
                label={tc('labels.name')}
                fullWidth
                {...register('name')}
                error={!!errors.name}
                helperText={errors.name?.message}
              />
              <TextField
                label={tc('labels.email')}
                type="email"
                fullWidth
                {...register('email')}
                error={!!errors.email}
                helperText={errors.email?.message}
              />
              <TextField label={tc('labels.phone')} fullWidth {...register('phone')} />
              <Controller
                name="branch_id"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('form.branch')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value ? Number(e.target.value) : null)}
                  >
                    <MenuItem value="">{t('form.noBranch')}</MenuItem>
                    {branches?.map((branch) => (
                      <MenuItem key={branch.id} value={branch.id}>
                        {branch.name}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <Controller
                name="role"
                control={control}
                render={({ field }) => (
                  <TextField
                    label={t('form.role')}
                    select
                    fullWidth
                    value={field.value ?? ''}
                    onChange={field.onChange}
                    error={!!errors.role}
                    helperText={errors.role?.message}
                  >
                    <MenuItem value="" disabled>
                      —
                    </MenuItem>
                    {roles?.map((role) => (
                      <MenuItem key={role} value={role}>
                        {role}
                      </MenuItem>
                    ))}
                  </TextField>
                )}
              />
              <TextField
                label={t('form.password')}
                type="password"
                fullWidth
                {...register('password')}
                error={!!errors.password}
                helperText={errors.password?.message ?? t('form.passwordHelper')}
              />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={inviteMutation.isPending}>
              {t('actions.invite')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingSuspend}
        title={t('suspendDialog.title')}
        message={t('suspendDialog.message', { name: pendingSuspend?.name ?? '' })}
        confirmLabel={t('actions.suspend')}
        loading={suspendMutation.isPending}
        onConfirm={() => pendingSuspend && suspendMutation.mutate(pendingSuspend.id)}
        onCancel={() => setPendingSuspend(null)}
      />

      <ConfirmDialog
        open={!!pendingRoleChange}
        title={t('roleDialog.title')}
        message={t('roleDialog.message', { name: pendingRoleChange?.user.name ?? '', role: pendingRoleChange?.role ?? '' })}
        confirmLabel={t('roleDialog.confirm')}
        danger={false}
        loading={roleMutation.isPending}
        onConfirm={() =>
          pendingRoleChange && roleMutation.mutate({ id: pendingRoleChange.user.id, role: pendingRoleChange.role })
        }
        onCancel={() => setPendingRoleChange(null)}
      />
    </Stack>
  );
}
