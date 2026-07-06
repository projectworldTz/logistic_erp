import { zodResolver } from '@hookform/resolvers/zod';
import {
  Alert,
  Button,
  IconButton,
  MenuItem,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import AddIcon from '@mui/icons-material/Add';
import DeleteIcon from '@mui/icons-material/Delete';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { Controller, useFieldArray, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { z } from 'zod';
import { updateLandingContentSection } from '../../../../api/endpoints/platform';
import type { FeaturesContent } from '../../../../types';
import { ICON_MAP, ICON_OPTIONS } from '../../../public/sections/iconMap';

function buildSchema(t: (key: string, options?: Record<string, unknown>) => string) {
  return z.object({
    heading: z.string().min(1, t('landingContent.validation.required')),
    subheading: z.string().min(1, t('landingContent.validation.required')),
    items: z
      .array(
        z.object({
          icon_key: z.string().min(1, t('landingContent.validation.required')),
          title: z.string().min(1, t('landingContent.validation.required')),
          description: z.string().min(1, t('landingContent.validation.required')),
        }),
      )
      .min(1, t('landingContent.validation.addAtLeastOne', { item: t('landingContent.features.featureItem') })),
  });
}

type FormValues = z.infer<ReturnType<typeof buildSchema>>;

interface FeaturesFormProps {
  initialContent: FeaturesContent;
}

export function FeaturesForm({ initialContent }: FeaturesFormProps) {
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
    mutationFn: (content: FormValues) => updateLandingContentSection('features', content),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['platform', 'landing-content'] });
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    },
  });

  return (
    <Stack component="form" spacing={2} onSubmit={handleSubmit((values) => mutation.mutate(values))} sx={{ maxWidth: 720 }}>
      {saved && <Alert severity="success">{t('landingContent.features.saved')}</Alert>}
      <TextField label={t('landingContent.features.heading')} {...register('heading')} error={!!errors.heading} helperText={errors.heading?.message} />
      <TextField label={t('landingContent.features.subheading')} {...register('subheading')} error={!!errors.subheading} helperText={errors.subheading?.message} />

      <Typography variant="subtitle2">{t('landingContent.features.sectionTitle')}</Typography>
      {fields.map((field, index) => (
        <Paper key={field.id} variant="outlined" sx={{ p: 2 }}>
          <Stack spacing={1.5}>
            <Stack direction="row" spacing={1} alignItems="center">
              <Controller
                control={control}
                name={`items.${index}.icon_key`}
                render={({ field: iconField }) => (
                  <TextField select label={t('landingContent.features.icon')} sx={{ width: 200 }} {...iconField}>
                    {ICON_OPTIONS.map((key) => {
                      const Icon = ICON_MAP[key];
                      return (
                        <MenuItem key={key} value={key}>
                          <Stack direction="row" spacing={1} alignItems="center">
                            <Icon fontSize="small" />
                            <span>{key}</span>
                          </Stack>
                        </MenuItem>
                      );
                    })}
                  </TextField>
                )}
              />
              <IconButton onClick={() => remove(index)} size="small">
                <DeleteIcon fontSize="small" />
              </IconButton>
            </Stack>
            <TextField label={t('landingContent.features.itemTitle')} {...register(`items.${index}.title`)} />
            <TextField label={tc('labels.description')} multiline minRows={2} {...register(`items.${index}.description`)} />
          </Stack>
        </Paper>
      ))}
      <Button
        startIcon={<AddIcon />}
        onClick={() => append({ icon_key: ICON_OPTIONS[0], title: '', description: '' })}
        sx={{ alignSelf: 'flex-start' }}
      >
        {t('landingContent.features.addFeature')}
      </Button>

      <Button type="submit" variant="contained" disabled={mutation.isPending} sx={{ alignSelf: 'flex-start' }}>
        {tc('actions.save')}
      </Button>
    </Stack>
  );
}
