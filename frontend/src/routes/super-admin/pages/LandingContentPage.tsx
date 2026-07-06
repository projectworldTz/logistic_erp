import { Box, CircularProgress, Stack, Tab, Tabs, Typography } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { fetchPlatformLandingContent } from '../../../api/endpoints/platform';
import type {
  AboutContent,
  FaqsContent,
  FeaturesContent,
  HeroContent,
  IndustriesContent,
  LandingContentKey,
  TestimonialsContent,
} from '../../../types';
import { AboutForm } from './landing-content/AboutForm';
import { FaqsForm } from './landing-content/FaqsForm';
import { FeaturesForm } from './landing-content/FeaturesForm';
import { HeroForm } from './landing-content/HeroForm';
import { IndustriesForm } from './landing-content/IndustriesForm';
import { TestimonialsForm } from './landing-content/TestimonialsForm';

const TAB_KEYS: LandingContentKey[] = ['hero', 'about', 'features', 'industries', 'testimonials', 'faqs'];

export function LandingContentPage() {
  const { t } = useTranslation('superAdmin');
  const [tab, setTab] = useState<LandingContentKey>('hero');
  const { data: sections, isLoading } = useQuery({
    queryKey: ['platform', 'landing-content'],
    queryFn: fetchPlatformLandingContent,
  });

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('landingContent.title')}
      </Typography>

      <Tabs value={tab} onChange={(_, value: LandingContentKey) => setTab(value)}>
        {TAB_KEYS.map((key) => (
          <Tab key={key} value={key} label={t(`landingContent.tabs.${key}`)} />
        ))}
      </Tabs>

      {isLoading && <CircularProgress />}

      {sections && (
        <Box>
          {tab === 'hero' && (
            <HeroForm initialContent={sections.find((s) => s.key === 'hero')!.content as HeroContent} />
          )}
          {tab === 'about' && (
            <AboutForm initialContent={sections.find((s) => s.key === 'about')!.content as AboutContent} />
          )}
          {tab === 'features' && (
            <FeaturesForm initialContent={sections.find((s) => s.key === 'features')!.content as FeaturesContent} />
          )}
          {tab === 'industries' && (
            <IndustriesForm
              initialContent={sections.find((s) => s.key === 'industries')!.content as IndustriesContent}
            />
          )}
          {tab === 'testimonials' && (
            <TestimonialsForm
              initialContent={sections.find((s) => s.key === 'testimonials')!.content as TestimonialsContent}
            />
          )}
          {tab === 'faqs' && (
            <FaqsForm initialContent={sections.find((s) => s.key === 'faqs')!.content as FaqsContent} />
          )}
        </Box>
      )}
    </Stack>
  );
}
