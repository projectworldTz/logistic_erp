import { Box } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import { PublicNavbar } from '../../components/layout/PublicNavbar';
import { PublicFooter } from '../../components/layout/PublicFooter';
import { fetchPublicLandingContent } from '../../api/endpoints/landingContent';
import { Hero } from './sections/Hero';
import { Features } from './sections/Features';
import { Pricing } from './sections/Pricing';
import { Industries } from './sections/Industries';
import { Testimonials } from './sections/Testimonials';
import { About } from './sections/About';
import { FAQ } from './sections/FAQ';
import { ContactSection } from './sections/ContactSection';
import { DEFAULT_LANDING_CONTENT } from './sections/landingContentDefaults';

export function LandingPage() {
  const { data } = useQuery({ queryKey: ['public', 'landing-content'], queryFn: fetchPublicLandingContent });
  const content = data ?? DEFAULT_LANDING_CONTENT;

  return (
    <Box>
      <PublicNavbar />
      <Hero content={content.hero} />
      <Features content={content.features} />
      <Pricing />
      <Industries content={content.industries} />
      <Testimonials content={content.testimonials} />
      <About content={content.about} />
      <FAQ content={content.faqs} />
      <ContactSection />
      <PublicFooter />
    </Box>
  );
}
