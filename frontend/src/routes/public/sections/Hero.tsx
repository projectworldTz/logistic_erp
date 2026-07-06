import { Box, Button, Chip, Container, Stack, Typography } from '@mui/material';
import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import { Link as RouterLink } from 'react-router-dom';
import type { HeroContent } from '../../../types';

interface HeroProps {
  content: HeroContent;
}

export function Hero({ content }: HeroProps) {
  return (
    <Box sx={{ py: { xs: 8, md: 12 }, bgcolor: 'background.default' }}>
      <Container maxWidth="md">
        <Stack spacing={3} alignItems="center" textAlign="center">
          <Chip label={content.eyebrow_text} color="primary" variant="outlined" />
          <Typography variant="h2" fontWeight={800} sx={{ fontSize: { xs: '2.25rem', md: '3.25rem' } }}>
            {content.headline}
          </Typography>
          <Typography variant="h6" color="text.secondary" fontWeight={400} sx={{ maxWidth: 640 }}>
            {content.subheadline}
          </Typography>
          {content.image_url && (
            <Box
              component="img"
              src={content.image_url}
              alt=""
              sx={{ maxWidth: '100%', width: 640, borderRadius: 2, mt: 2 }}
            />
          )}
          <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2} sx={{ pt: 1 }}>
            <Button
              component={RouterLink}
              to={content.primary_cta_link}
              variant="contained"
              size="large"
              endIcon={<ArrowForwardIcon />}
            >
              {content.primary_cta_label}
            </Button>
            <Button component="a" href={content.secondary_cta_link} variant="outlined" size="large">
              {content.secondary_cta_label}
            </Button>
          </Stack>
          <Typography variant="caption" color="text.secondary">
            {content.microcopy}
          </Typography>
        </Stack>
      </Container>
    </Box>
  );
}
