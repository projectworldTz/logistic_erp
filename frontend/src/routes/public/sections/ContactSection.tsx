import { zodResolver } from '@hookform/resolvers/zod';
import {
  Alert,
  Box,
  Button,
  Container,
  Stack,
  Tab,
  Tabs,
  TextField,
  Typography,
} from '@mui/material';
import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { submitContact, submitDemoRequest } from '../../../api/endpoints/public';

function buildContactSchema(t: (key: string) => string, tc: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('contact.contactForm.validation.nameRequired')),
    email: z.string().email(tc('validation.invalidEmail')),
    company: z.string().optional(),
    message: z.string().min(1, t('contact.contactForm.validation.messageRequired')),
  });
}

function buildDemoSchema(t: (key: string) => string, tc: (key: string) => string) {
  return z.object({
    name: z.string().min(1, t('contact.demoForm.validation.nameRequired')),
    email: z.string().email(tc('validation.invalidEmail')),
    company: z.string().min(1, t('contact.demoForm.validation.companyRequired')),
    phone: z.string().optional(),
    preferred_time: z.string().optional(),
  });
}

type ContactValues = z.infer<ReturnType<typeof buildContactSchema>>;
type DemoValues = z.infer<ReturnType<typeof buildDemoSchema>>;

function ContactForm() {
  const { t } = useTranslation('landing');
  const { t: tc } = useTranslation('common');
  const contactSchema = buildContactSchema(t, tc);
  const { register, handleSubmit, reset, formState: { errors } } = useForm<ContactValues>({
    resolver: zodResolver(contactSchema),
  });
  const mutation = useMutation({ mutationFn: submitContact, onSuccess: () => reset() });

  return (
    <Box component="form" onSubmit={handleSubmit((v) => mutation.mutate(v))} noValidate>
      <Stack spacing={2}>
        {mutation.isSuccess && <Alert severity="success">{t('contact.contactForm.successAlert')}</Alert>}
        <TextField label={t('contact.contactForm.name')} fullWidth {...register('name')} error={!!errors.name} helperText={errors.name?.message} />
        <TextField label={t('contact.contactForm.email')} type="email" fullWidth {...register('email')} error={!!errors.email} helperText={errors.email?.message} />
        <TextField label={t('contact.contactForm.companyOptional')} fullWidth {...register('company')} />
        <TextField label={t('contact.contactForm.message')} fullWidth multiline rows={4} {...register('message')} error={!!errors.message} helperText={errors.message?.message} />
        <Button type="submit" variant="contained" size="large" disabled={mutation.isPending}>
          {mutation.isPending ? t('contact.contactForm.sending') : t('contact.contactForm.send')}
        </Button>
      </Stack>
    </Box>
  );
}

function DemoForm() {
  const { t } = useTranslation('landing');
  const { t: tc } = useTranslation('common');
  const demoSchema = buildDemoSchema(t, tc);
  const { register, handleSubmit, reset, formState: { errors } } = useForm<DemoValues>({
    resolver: zodResolver(demoSchema),
  });
  const mutation = useMutation({ mutationFn: submitDemoRequest, onSuccess: () => reset() });

  return (
    <Box component="form" onSubmit={handleSubmit((v) => mutation.mutate(v))} noValidate>
      <Stack spacing={2}>
        {mutation.isSuccess && <Alert severity="success">{t('contact.demoForm.successAlert')}</Alert>}
        <TextField label={t('contact.demoForm.name')} fullWidth {...register('name')} error={!!errors.name} helperText={errors.name?.message} />
        <TextField label={t('contact.demoForm.email')} type="email" fullWidth {...register('email')} error={!!errors.email} helperText={errors.email?.message} />
        <TextField label={t('contact.demoForm.company')} fullWidth {...register('company')} error={!!errors.company} helperText={errors.company?.message} />
        <TextField label={t('contact.demoForm.phoneOptional')} fullWidth {...register('phone')} />
        <TextField label={t('contact.demoForm.preferredTimeOptional')} fullWidth {...register('preferred_time')} />
        <Button type="submit" variant="contained" size="large" disabled={mutation.isPending}>
          {mutation.isPending ? t('contact.demoForm.sending') : t('contact.demoForm.send')}
        </Button>
      </Stack>
    </Box>
  );
}

export function ContactSection() {
  const { t } = useTranslation('landing');
  const [tab, setTab] = useState(0);

  return (
    <Box id="contact" sx={{ py: 10 }}>
      <Container maxWidth="sm">
        <Stack spacing={1} textAlign="center" sx={{ mb: 4 }}>
          <Typography variant="h4" fontWeight={700}>
            {t('contact.heading')}
          </Typography>
          <Typography variant="body1" color="text.secondary">
            {t('contact.subheading')}
          </Typography>
        </Stack>
        <Tabs value={tab} onChange={(_, v) => setTab(v)} centered sx={{ mb: 3 }}>
          <Tab label={t('contact.tabs.contactUs')} />
          <Tab label={t('contact.tabs.bookDemo')} />
        </Tabs>
        {tab === 0 ? <ContactForm /> : <DemoForm />}
      </Container>
    </Box>
  );
}
