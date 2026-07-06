import { Box, Container, Grid, Stack, Typography } from '@mui/material';
import type { AboutContent } from '../../../types';

interface AboutProps {
  content: AboutContent;
}

export function About({ content }: AboutProps) {
  return (
    <Box id="about" sx={{ py: 10 }}>
      <Container maxWidth="lg">
        <Grid container spacing={6} alignItems="center">
          <Grid size={{ xs: 12, md: 6 }}>
            <Stack spacing={2}>
              <Typography variant="h4" fontWeight={700}>
                {content.heading}
              </Typography>
              <Typography variant="body1" color="text.secondary">
                {content.paragraph_1}
              </Typography>
              <Typography variant="body1" color="text.secondary">
                {content.paragraph_2}
              </Typography>
            </Stack>
          </Grid>
          <Grid size={{ xs: 12, md: 6 }}>
            <Grid container spacing={3}>
              {content.stats.map((item) => (
                <Grid key={item.label} size={6}>
                  <Stack>
                    <Typography variant="h3" fontWeight={800} color="primary.main">
                      {item.stat}
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {item.label}
                    </Typography>
                  </Stack>
                </Grid>
              ))}
            </Grid>
          </Grid>
        </Grid>
      </Container>
    </Box>
  );
}
