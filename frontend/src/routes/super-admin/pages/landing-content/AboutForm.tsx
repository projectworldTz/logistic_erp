import { zodResolver } from '@hookform/resolvers/zod';
import { Alert, Button, IconButton, Stack, TextField, Typography } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useFieldArray, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { updateLandingContentSection } from '../../../../api/endpoints/platform';
import type { AboutContent } from '../../../../types';

function buildSchema(t: (key: string, options?: Record<string, unknown>) => string) {
  return z.object({
    heading: z.string().min(1, t('landingContent.validation.required')),
    paragraph_1: z.string().min(1, t('landingContent.validation.required')),
    paragraph_2: z.string().min(1, t('landingContent.validation.required')),
    stats: z
      .array(
        z.object({
          stat: z.string().min(1, t('landingContent.validation.required')),
          label: z.string().min(1, t('landingContent.validation.required')),
        }),
      )
      .min(1, t('landingContent.validation.addAtLeastOne', { item: t('landingContent.about.statItem') })),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

interface AboutFormProps {
  initialContent: AboutContent;
}

export function AboutForm({ initialContent }: AboutFormProps) {
  const { t } = useTranslation('superAdmin');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const [saved, setSaved] = useState(false);
  const schema = buildSchema(t);

  const { register, handleSubmit, control, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    values: initialContent,
  });
  const { fields, append, remove } = useFieldArray({ control, name: 'stats' });

  const mutation = useMutation({
    mutationFn: (content: FormValues) => updateLandingContentSection('about', content),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform', 'landing-content'] });
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    },
  });

  return (
    <Stack component="form" spacing={2} onSubmit={handleSubmit((values) => mutation.mutate(values))} sx={{ maxWidth: 640 }}>
      {saved && <Alert severity="success">{t('landingContent.about.saved')}</Alert>}
      <TextField label={t('landingContent.about.heading')} {...register('heading')} error={!!errors.heading} helperText={errors.heading?.message} />
      <TextField label={t('landingContent.about.paragraph1')} multiline minRows={3} {...register('paragraph_1')} error={!!errors.paragraph_1} helperText={errors.paragraph_1?.message} />
      <TextField label={t('landingContent.about.paragraph2')} multiline minRows={2} {...register('paragraph_2')} error={!!errors.paragraph_2} helperText={errors.paragraph_2?.message} />

      <Typography variant="subtitle2">{t('landingContent.about.statsSectionTitle')}</Typography>
      {fields.map((field, index) => (
        <Stack direction="row" spacing={1} key={field.id} alignItems="center">
          <TextField label={t('landingContent.about.stat')} {...register(`stats.${index}.stat`)} sx={{ width: 120 }} />
          <TextField label={t('landingContent.about.statLabel')} fullWidth {...register(`stats.${index}.label`)} />
          <IconButton onClick={() => remove(index)} size="small">
            <DeleteIcon fontSize="small" />
          </IconButton>
        </Stack>
      ))}
      <Button startIcon={<AddIcon />} onClick={() => append({ stat: '', label: '' })} sx={{ alignSelf: 'flex-start' }}>
        {t('landingContent.about.addStat')}
      </Button>

      <Button type="submit" variant="contained" disabled={mutation.isPending} sx={{ alignSelf: 'flex-start' }}>
        {tc('actions.save')}
      </Button>
    </Stack>
  );
}
