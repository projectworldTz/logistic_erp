import { zodResolver } from '@hookform/resolvers/zod';
import { Alert, Button, IconButton, Paper, Stack, TextField, Typography } from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useFieldArray, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { updateLandingContentSection } from '../../../../api/endpoints/platform';
import type { IndustriesContent } from '../../../../types';

function buildSchema(t: (key: string, options?: Record<string, unknown>) => string) {
  return z.object({
    heading: z.string().min(1, t('landingContent.validation.required')),
    subheading: z.string().min(1, t('landingContent.validation.required')),
    items: z
      .array(
        z.object({
          title: z.string().min(1, t('landingContent.validation.required')),
          description: z.string().min(1, t('landingContent.validation.required')),
        }),
      )
      .min(1, t('landingContent.validation.addAtLeastOne', { item: t('landingContent.industries.industryItem') })),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

interface IndustriesFormProps {
  initialContent: IndustriesContent;
}

export function IndustriesForm({ initialContent }: IndustriesFormProps) {
  const { t } = useTranslation('superAdmin');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const [saved, setSaved] = useState(false);
  const schema = buildSchema(t);

  const { register, handleSubmit, control, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    values: initialContent,
  });
  const { fields, append, remove } = useFieldArray({ control, name: 'items' });

  const mutation = useMutation({
    mutationFn: (content: FormValues) => updateLandingContentSection('industries', content),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform', 'landing-content'] });
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    },
  });

  return (
    <Stack component="form" spacing={2} onSubmit={handleSubmit((values) => mutation.mutate(values))} sx={{ maxWidth: 640 }}>
      {saved && <Alert severity="success">{t('landingContent.industries.saved')}</Alert>}
      <TextField label={t('landingContent.industries.heading')} {...register('heading')} error={!!errors.heading} helperText={errors.heading?.message} />
      <TextField label={t('landingContent.industries.subheading')} {...register('subheading')} error={!!errors.subheading} helperText={errors.subheading?.message} />

      <Typography variant="subtitle2">{t('landingContent.industries.sectionTitle')}</Typography>
      {fields.map((field, index) => (
        <Paper key={field.id} variant="outlined" sx={{ p: 2 }}>
          <Stack spacing={1.5}>
            <Stack direction="row" justifyContent="flex-end">
              <IconButton onClick={() => remove(index)} size="small">
                <DeleteIcon fontSize="small" />
              </IconButton>
            </Stack>
            <TextField label={t('landingContent.industries.itemTitle')} {...register(`items.${index}.title`)} />
            <TextField label={tc('labels.description')} multiline minRows={2} {...register(`items.${index}.description`)} />
          </Stack>
        </Paper>
      ))}
      <Button startIcon={<AddIcon />} onClick={() => append({ title: '', description: '' })} sx={{ alignSelf: 'flex-start' }}>
        {t('landingContent.industries.addIndustry')}
      </Button>

      <Button type="submit" variant="contained" disabled={mutation.isPending} sx={{ alignSelf: 'flex-start' }}>
        {tc('actions.save')}
      </Button>
    </Stack>
  );
}
