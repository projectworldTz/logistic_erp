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
import type { TestimonialsContent } from '../../../../types';
import { ImageUploadButton } from './ImageUploadButton';

function buildSchema(t: (key: string, options?: Record<string, unknown>) => string) {
  return z.object({
    heading: z.string().min(1, t('landingContent.validation.required')),
    items: z
      .array(
        z.object({
          quote: z.string().min(1, t('landingContent.validation.required')),
          name: z.string().min(1, t('landingContent.validation.required')),
          role: z.string().min(1, t('landingContent.validation.required')),
          avatar_url: z.string().nullable(),
        }),
      )
      .min(1, t('landingContent.validation.addAtLeastOne', { item: t('landingContent.testimonials.testimonialItem') })),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

interface TestimonialsFormProps {
  initialContent: TestimonialsContent;
}

export function TestimonialsForm({ initialContent }: TestimonialsFormProps) {
  const { t } = useTranslation('superAdmin');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const [saved, setSaved] = useState(false);
  const schema = buildSchema(t);

  const { register, handleSubmit, control, setValue, watch, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    values: initialContent,
  });
  const { fields, append, remove } = useFieldArray({ control, name: 'items' });

  const mutation = useMutation({
    mutationFn: (content: TestimonialsContent) => updateLandingContentSection('testimonials', content),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform', 'landing-content'] });
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    },
  });

  return (
    <Stack component="form" spacing={2} onSubmit={handleSubmit((values) => mutation.mutate(values as TestimonialsContent))} sx={{ maxWidth: 640 }}>
      {saved && <Alert severity="success">{t('landingContent.testimonials.saved')}</Alert>}
      <TextField label={t('landingContent.testimonials.heading')} {...register('heading')} error={!!errors.heading} helperText={errors.heading?.message} />

      <Typography variant="subtitle2">{t('landingContent.testimonials.sectionTitle')}</Typography>
      {fields.map((field, index) => (
        <Paper key={field.id} variant="outlined" sx={{ p: 2 }}>
          <Stack spacing={1.5}>
            <Stack direction="row" justifyContent="space-between" alignItems="flex-start">
              <ImageUploadButton
                label={t('landingContent.testimonials.uploadAvatar')}
                purpose="avatar"
                currentUrl={watch(`items.${index}.avatar_url`)}
                onUploaded={(url) => setValue(`items.${index}.avatar_url`, url)}
              />
              <IconButton onClick={() => remove(index)} size="small">
                <DeleteIcon fontSize="small" />
              </IconButton>
            </Stack>
            <TextField label={t('landingContent.testimonials.quote')} multiline minRows={2} {...register(`items.${index}.quote`)} />
            <TextField label={tc('labels.name')} {...register(`items.${index}.name`)} />
            <TextField label={t('landingContent.testimonials.role')} {...register(`items.${index}.role`)} />
          </Stack>
        </Paper>
      ))}
      <Button
        startIcon={<AddIcon />}
        onClick={() => append({ quote: '', name: '', role: '', avatar_url: null })}
        sx={{ alignSelf: 'flex-start' }}
      >
        {t('landingContent.testimonials.addTestimonial')}
      </Button>

      <Button type="submit" variant="contained" disabled={mutation.isPending} sx={{ alignSelf: 'flex-start' }}>
        {tc('actions.save')}
      </Button>
    </Stack>
  );
}
