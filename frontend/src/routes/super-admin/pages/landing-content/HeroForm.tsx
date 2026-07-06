import { zodResolver } from '@hookform/resolvers/zod';
import { Alert, Button, Stack, TextField } from '@mui/material';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { updateLandingContentSection } from '../../../../api/endpoints/platform';
import type { HeroContent } from '../../../../types';
import { ImageUploadButton } from './ImageUploadButton';

function buildSchema(t: (key: string) => string) {
  return z.object({
    eyebrow_text: z.string().min(1, t('landingContent.validation.required')),
    headline: z.string().min(1, t('landingContent.validation.required')),
    subheadline: z.string().min(1, t('landingContent.validation.required')),
    image_url: z.string().nullable(),
    primary_cta_label: z.string().min(1, t('landingContent.validation.required')),
    primary_cta_link: z.string().min(1, t('landingContent.validation.required')),
    secondary_cta_label: z.string().min(1, t('landingContent.validation.required')),
    secondary_cta_link: z.string().min(1, t('landingContent.validation.required')),
    microcopy: z.string(),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

interface HeroFormProps {
  initialContent: HeroContent;
}

export function HeroForm({ initialContent }: HeroFormProps) {
  const { t } = useTranslation('superAdmin');
  const { t: tc } = useTranslation('common');
  const queryClient = useQueryClient();
  const [saved, setSaved] = useState(false);
  const schema = buildSchema(t);

  const { register, handleSubmit, watch, setValue, formState: { errors } } = useForm<FormValues>({
    resolver: zodResolver(schema),
    values: initialContent,
  });

  const mutation = useMutation({
    mutationFn: (content: HeroContent) => updateLandingContentSection('hero', content),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform', 'landing-content'] });
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    },
  });

  return (
    <Stack component="form" spacing={2} onSubmit={handleSubmit((values) => mutation.mutate(values as HeroContent))} sx={{ maxWidth: 640 }}>
      {saved && <Alert severity="success">{t('landingContent.hero.saved')}</Alert>}
      <TextField label={t('landingContent.hero.eyebrowText')} {...register('eyebrow_text')} error={!!errors.eyebrow_text} helperText={errors.eyebrow_text?.message} />
      <TextField label={t('landingContent.hero.headline')} {...register('headline')} error={!!errors.headline} helperText={errors.headline?.message} />
      <TextField label={t('landingContent.hero.subheadline')} multiline minRows={2} {...register('subheadline')} error={!!errors.subheadline} helperText={errors.subheadline?.message} />
      <ImageUploadButton
        label={t('landingContent.hero.uploadHeroImage')}
        purpose="hero"
        currentUrl={watch('image_url')}
        onUploaded={(url) => setValue('image_url', url)}
      />
      <TextField label={t('landingContent.hero.primaryCtaLabel')} {...register('primary_cta_label')} error={!!errors.primary_cta_label} helperText={errors.primary_cta_label?.message} />
      <TextField label={t('landingContent.hero.primaryCtaLink')} {...register('primary_cta_link')} error={!!errors.primary_cta_link} helperText={errors.primary_cta_link?.message} />
      <TextField label={t('landingContent.hero.secondaryCtaLabel')} {...register('secondary_cta_label')} error={!!errors.secondary_cta_label} helperText={errors.secondary_cta_label?.message} />
      <TextField label={t('landingContent.hero.secondaryCtaLink')} {...register('secondary_cta_link')} error={!!errors.secondary_cta_link} helperText={errors.secondary_cta_link?.message} />
      <TextField label={t('landingContent.hero.microcopy')} {...register('microcopy')} />
      <Button type="submit" variant="contained" disabled={mutation.isPending} sx={{ alignSelf: 'flex-start' }}>
        {tc('actions.save')}
      </Button>
    </Stack>
  );
}
