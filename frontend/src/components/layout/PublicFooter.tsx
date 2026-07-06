import { Box, Container, Divider, Grid, Stack, Typography } from '@mui/material';
import { useTranslation } from 'react-i18next';

export function PublicFooter() {
  const { t } = useTranslation('landing');

  return (
    <Box component="footer" sx={{ bgcolor: 'background.paper', borderTop: 1, borderColor: 'divider', py: 6, mt: 8 }}>
      <Container maxWidth="lg">
        <Grid container spacing={4}>
          <Grid size={{ xs: 12, md: 4 }}>
            <Typography variant="h6" fontWeight={700} gutterBottom>
              {t('brand')}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {t('footer.tagline')}
            </Typography>
          </Grid>
          <Grid size={{ xs: 6, md: 2 }}>
            <Typography variant="subtitle2" gutterBottom>
              {t('footer.product')}
            </Typography>
            <Stack spacing={1}>
              <Typography component="a" href="#features" variant="body2" color="text.secondary" sx={{ textDecoration: 'none' }}>
                {t('nav.features')}
              </Typography>
              <Typography component="a" href="#pricing" variant="body2" color="text.secondary" sx={{ textDecoration: 'none' }}>
                {t('nav.pricing')}
              </Typography>
              <Typography component="a" href="#industries" variant="body2" color="text.secondary" sx={{ textDecoration: 'none' }}>
                {t('nav.industries')}
              </Typography>
            </Stack>
          </Grid>
          <Grid size={{ xs: 6, md: 2 }}>
            <Typography variant="subtitle2" gutterBottom>
              {t('footer.company')}
            </Typography>
            <Stack spacing={1}>
              <Typography component="a" href="#about" variant="body2" color="text.secondary" sx={{ textDecoration: 'none' }}>
                {t('nav.about')}
              </Typography>
              <Typography component="a" href="#contact" variant="body2" color="text.secondary" sx={{ textDecoration: 'none' }}>
                {t('nav.contact')}
              </Typography>
              <Typography component="a" href="#faq" variant="body2" color="text.secondary" sx={{ textDecoration: 'none' }}>
                {t('nav.faq')}
              </Typography>
            </Stack>
          </Grid>
          <Grid size={{ xs: 12, md: 4 }}>
            <Typography variant="subtitle2" gutterBottom>
              {t('footer.getStarted')}
            </Typography>
            <Typography variant="body2" color="text.secondary">
              {t('footer.getStartedBody')}
            </Typography>
          </Grid>
        </Grid>
        <Divider sx={{ my: 4 }} />
        <Typography variant="body2" color="text.secondary" textAlign="center">
          {t('footer.copyright', { year: new Date().getFullYear() })}
        </Typography>
      </Container>
    </Box>
  );
}
