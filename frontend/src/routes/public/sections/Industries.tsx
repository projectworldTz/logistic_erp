import { Box, Container, Grid, Paper, Stack, Typography } from '@mui/material';
import type { IndustriesContent } from '../../../types';

interface IndustriesProps {
  content: IndustriesContent;
}

export function Industries({ content }: IndustriesProps) {
  return (
    <Box id="industries" sx={{ py: 10 }}>
      <Container maxWidth="lg">
        <Stack spacing={1} textAlign="center" sx={{ mb: 6 }}>
          <Typography variant="h4" fontWeight={700}>
            {content.heading}
          </Typography>
          <Typography variant="body1" color="text.secondary">
            {content.subheading}
          </Typography>
        </Stack>
        <Grid container spacing={3}>
          {content.items.map((industry) => (
            <Grid key={industry.title} size={{ xs: 12, sm: 6, md: 3 }}>
              <Paper variant="outlined" sx={{ p: 3, height: '100%' }}>
                <Typography variant="subtitle1" fontWeight={600} gutterBottom>
                  {industry.title}
                </Typography>
                <Typography variant="body2" color="text.secondary">
                  {industry.description}
                </Typography>
              </Paper>
            </Grid>
          ))}
        </Grid>
      </Container>
    </Box>
  );
}
