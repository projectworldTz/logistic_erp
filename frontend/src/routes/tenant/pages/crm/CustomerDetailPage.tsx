import { zodResolver } from '@hookform/resolvers/zod';
import {
  Button,
  Checkbox,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControlLabel,
  Grid,
  IconButton,
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
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';
import PersonAddIcon from '@mui/icons-material/PersonAdd';
import SendIcon from '@mui/icons-material/Send';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import {
  createContact,
  deleteContact,
  fetchCustomer,
  fetchCustomerMessages,
  sendCustomerMessage,
  updateContact,
} from '../../../../api/endpoints/crm';
import { inviteUser } from '../../../../api/endpoints/users';
import type { Contact } from '../../../../types';
import { EmptyState } from '../../../../components/common/EmptyState';
import { ConfirmDialog } from '../../../../components/common/ConfirmDialog';
import { useToast } from '../../../../hooks/useToast';

function buildSchema(t: (key: string) => string, tc: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('customerDetail.validation.nameRequired')),
    email: z.string().email(tc('validation.invalidEmail')).optional().or(z.literal('')),
    phone: z.string().optional(),
    job_title: z.string().optional(),
    is_primary: z.boolean().optional(),
  });
}

type ContactFormValues = z.infer<ReturnType<typeof buildSchema>>;

export function CustomerDetailPage() {
  const { t } = useTranslation('crm');
  const { t: tc } = useTranslation('common');
  const { id } = useParams<{ id: string }>();
  const customerId = Number(id);
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { showToast } = useToast();
  const contactSchema = buildSchema(t, tc);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingContact, setEditingContact] = useState<Contact | null>(null);
  const [pendingDelete, setPendingDelete] = useState<Contact | null>(null);
  const [invitingContact, setInvitingContact] = useState<Contact | null>(null);
  const [invitePassword, setInvitePassword] = useState('');
  const [messageBody, setMessageBody] = useState('');

  const { data: customer, isLoading } = useQuery({
    queryKey: ['crm', 'customer', customerId],
    queryFn: () => fetchCustomer(customerId),
  });

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['crm', 'customer', customerId] });

  const createMutation = useMutation({
    mutationFn: (values: ContactFormValues) => createContact(customerId, values),
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      showToast(t('customerDetail.toast.created'));
    },
  });

  const updateMutation = useMutation({
    mutationFn: (values: ContactFormValues) =>
      updateContact(customerId, editingContact!.id, values),
    onSuccess: () => {
      invalidate();
      setDialogOpen(false);
      setEditingContact(null);
      showToast(t('customerDetail.toast.updated'));
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (contactId: number) => deleteContact(customerId, contactId),
    onSuccess: () => {
      invalidate();
      setPendingDelete(null);
      showToast(t('customerDetail.toast.deleted'));
    },
  });

  const inviteMutation = useMutation({
    mutationFn: (values: { email: string; password: string; name: string }) =>
      inviteUser({
        name: values.name,
        email: values.email,
        password: values.password,
        customer_id: customerId,
        role: 'Customer Portal User',
      }),
    onSuccess: () => {
      setInvitingContact(null);
      setInvitePassword('');
      showToast(t('customerDetail.invitePortal.toast'));
    },
  });

  const { data: messages } = useQuery({
    queryKey: ['crm', 'customer', customerId, 'messages'],
    queryFn: () => fetchCustomerMessages(customerId),
  });

  const sendMessageMutation = useMutation({
    mutationFn: (body: string) => sendCustomerMessage(customerId, body),
    onSuccess: () => {
      setMessageBody('');
      queryClient.invalidateQueries({ queryKey: ['crm', 'customer', customerId, 'messages'] });
    },
  });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<ContactFormValues>({ resolver: zodResolver(contactSchema) });

  const openCreateDialog = () => {
    setEditingContact(null);
    reset({ name: '', email: '', phone: '', job_title: '', is_primary: false });
    setDialogOpen(true);
  };

  const openEditDialog = (contact: Contact) => {
    setEditingContact(contact);
    reset({
      name: contact.name,
      email: contact.email ?? '',
      phone: contact.phone ?? '',
      job_title: contact.job_title ?? '',
      is_primary: contact.is_primary,
    });
    setDialogOpen(true);
  };

  const onSubmit = (values: ContactFormValues) => {
    const payload = { ...values, email: values.email || undefined };
    if (editingContact) {
      updateMutation.mutate(payload);
    } else {
      createMutation.mutate(payload);
    }
  };

  if (isLoading || !customer) {
    return <CircularProgress />;
  }

  return (
    <Stack spacing={3}>
      <Button onClick={() => navigate('/app/crm/customers')} sx={{ alignSelf: 'flex-start' }}>
        {t('customerDetail.backToCustomers')}
      </Button>

      <Stack direction="row" alignItems="center" spacing={1.5}>
        <Typography variant="h5" fontWeight={700}>
          {customer.company_name}
        </Typography>
        <Chip label={t(`statuses.${customer.status}`)} size="small" color={customer.status === 'active' ? 'success' : 'default'} />
      </Stack>

      <Paper variant="outlined" sx={{ p: 3 }}>
        <Grid container spacing={2}>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('customerDetail.fields.industry')}</Typography>
            <Typography variant="body1">{customer.industry ?? '—'}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('customerDetail.fields.email')}</Typography>
            <Typography variant="body1">{customer.email ?? '—'}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('customerDetail.fields.phone')}</Typography>
            <Typography variant="body1">{customer.phone ?? '—'}</Typography>
          </Grid>
          <Grid size={{ xs: 12, sm: 6 }}>
            <Typography variant="body2" color="text.secondary">{t('customerDetail.fields.location')}</Typography>
            <Typography variant="body1">
              {[customer.city, customer.country].filter(Boolean).join(', ') || '—'}
            </Typography>
          </Grid>
        </Grid>
      </Paper>

      <Stack direction="row" justifyContent="space-between" alignItems="center">
        <Typography variant="h6" fontWeight={700}>
          {t('customerDetail.contactsTitle')}
        </Typography>
        <Button variant="outlined" size="small" startIcon={<AddIcon />} onClick={openCreateDialog}>
          {t('customerDetail.addContact')}
        </Button>
      </Stack>

      {(!customer.contacts || customer.contacts.length === 0) && (
        <EmptyState title={t('customerDetail.empty.title')} description={t('customerDetail.empty.description')} />
      )}

      {customer.contacts && customer.contacts.length > 0 && (
        <Paper variant="outlined">
          <TableContainer>
            <Table>
              <TableHead>
                <TableRow>
                  <TableCell>{t('customerDetail.table.name')}</TableCell>
                  <TableCell>{t('customerDetail.table.jobTitle')}</TableCell>
                  <TableCell>{t('customerDetail.table.email')}</TableCell>
                  <TableCell>{t('customerDetail.table.phone')}</TableCell>
                  <TableCell>{t('customerDetail.table.primary')}</TableCell>
                  <TableCell align="right">{tc('actions.actions')}</TableCell>
                </TableRow>
              </TableHead>
              <TableBody>
                {customer.contacts.map((contact) => (
                  <TableRow key={contact.id}>
                    <TableCell>{contact.name}</TableCell>
                    <TableCell>{contact.job_title ?? '—'}</TableCell>
                    <TableCell>{contact.email ?? '—'}</TableCell>
                    <TableCell>{contact.phone ?? '—'}</TableCell>
                    <TableCell>{contact.is_primary && <Chip label={t('customerDetail.primaryChip')} size="small" color="primary" />}</TableCell>
                    <TableCell align="right">
                      {contact.email && (
                        <IconButton
                          size="small"
                          title={t('customerDetail.invitePortal.action')}
                          onClick={() => {
                            setInvitingContact(contact);
                            setInvitePassword('');
                          }}
                        >
                          <PersonAddIcon fontSize="small" />
                        </IconButton>
                      )}
                      <IconButton size="small" onClick={() => openEditDialog(contact)}>
                        <EditIcon fontSize="small" />
                      </IconButton>
                      <IconButton size="small" onClick={() => setPendingDelete(contact)}>
                        <DeleteIcon fontSize="small" />
                      </IconButton>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </TableContainer>
        </Paper>
      )}

      <Dialog open={dialogOpen} onClose={() => setDialogOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{editingContact ? t('customerDetail.dialog.editTitle') : t('customerDetail.dialog.addTitle')}</DialogTitle>
        <Stack component="form" onSubmit={handleSubmit(onSubmit)}>
          <DialogContent>
            <Stack spacing={2}>
              <TextField label={t('customerDetail.form.name')} fullWidth {...register('name')} error={!!errors.name} helperText={errors.name?.message} />
              <TextField label={t('customerDetail.form.jobTitle')} fullWidth {...register('job_title')} />
              <TextField label={t('customerDetail.form.email')} fullWidth {...register('email')} error={!!errors.email} helperText={errors.email?.message} />
              <TextField label={t('customerDetail.form.phone')} fullWidth {...register('phone')} />
              <FormControlLabel control={<Checkbox {...register('is_primary')} />} label={t('customerDetail.form.primaryContact')} />
            </Stack>
          </DialogContent>
          <DialogActions>
            <Button onClick={() => setDialogOpen(false)}>{tc('actions.cancel')}</Button>
            <Button type="submit" variant="contained" disabled={createMutation.isPending || updateMutation.isPending}>
              {editingContact ? tc('actions.save') : tc('actions.add')}
            </Button>
          </DialogActions>
        </Stack>
      </Dialog>

      <ConfirmDialog
        open={!!pendingDelete}
        title={t('customerDetail.deleteDialog.title')}
        message={t('customerDetail.deleteDialog.message', { name: pendingDelete?.name ?? '' })}
        confirmLabel={tc('actions.delete')}
        loading={deleteMutation.isPending}
        onConfirm={() => pendingDelete && deleteMutation.mutate(pendingDelete.id)}
        onCancel={() => setPendingDelete(null)}
      />

      <Dialog open={!!invitingContact} onClose={() => setInvitingContact(null)} fullWidth maxWidth="xs">
        <DialogTitle>{t('customerDetail.invitePortal.dialogTitle')}</DialogTitle>
        <DialogContent>
          <Stack spacing={2} sx={{ pt: 1 }}>
            <Typography variant="body2" color="text.secondary">
              {t('customerDetail.invitePortal.description')}
            </Typography>
            <TextField
              label={t('customerDetail.invitePortal.form.email')}
              fullWidth
              value={invitingContact?.email ?? ''}
              disabled
            />
            <TextField
              label={t('customerDetail.invitePortal.form.password')}
              type="text"
              fullWidth
              value={invitePassword}
              onChange={(e) => setInvitePassword(e.target.value)}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setInvitingContact(null)}>{tc('actions.cancel')}</Button>
          <Button
            variant="contained"
            disabled={!invitePassword || inviteMutation.isPending}
            onClick={() =>
              invitingContact?.email &&
              inviteMutation.mutate({ name: invitingContact.name, email: invitingContact.email, password: invitePassword })
            }
          >
            {t('customerDetail.invitePortal.action')}
          </Button>
        </DialogActions>
      </Dialog>

      <Stack spacing={1.5}>
        <Typography variant="h6" fontWeight={700}>
          {t('customerDetail.messages.title')}
        </Typography>

        {(!messages || messages.data.length === 0) && (
          <EmptyState title={t('customerDetail.messages.empty')} />
        )}

        {messages && messages.data.length > 0 && (
          <Paper variant="outlined" sx={{ p: 2, maxHeight: 400, overflowY: 'auto' }}>
            <Stack spacing={1.5}>
              {messages.data.map((message) => (
                <Stack
                  key={message.id}
                  sx={{
                    alignSelf: message.is_from_customer ? 'flex-start' : 'flex-end',
                    bgcolor: message.is_from_customer ? 'action.hover' : 'primary.main',
                    color: message.is_from_customer ? 'text.primary' : 'primary.contrastText',
                    borderRadius: 2,
                    px: 2,
                    py: 1,
                    maxWidth: '70%',
                  }}
                >
                  <Typography variant="body2">{message.body}</Typography>
                  <Typography variant="caption" sx={{ opacity: 0.7 }}>
                    {new Date(message.created_at).toLocaleString()}
                  </Typography>
                </Stack>
              ))}
            </Stack>
          </Paper>
        )}

        <Stack direction="row" spacing={1}>
          <TextField
            fullWidth
            placeholder={t('customerDetail.messages.placeholder')}
            value={messageBody}
            onChange={(e) => setMessageBody(e.target.value)}
          />
          <Button
            variant="contained"
            startIcon={<SendIcon />}
            disabled={!messageBody.trim() || sendMessageMutation.isPending}
            onClick={() => sendMessageMutation.mutate(messageBody.trim())}
          >
            {t('customerDetail.messages.send')}
          </Button>
        </Stack>
      </Stack>
    </Stack>
  );
}
