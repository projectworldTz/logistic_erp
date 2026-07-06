import { Box, Card, CardContent, Container, Grid, Stack, Typography } from '@mui/material';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import type { FeaturesContent } from '../../../types';
import { ICON_MAP } from './iconMap';

interface FeaturesProps {
  content: FeaturesContent;
}

export function Features({ content }: FeaturesProps) {
  return (
    <Box id="features" sx={{ py: 10 }}>
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
          {content.items.map((feature) => {
            const Icon = ICON_MAP[feature.icon_key] ?? LocalShippingIcon;
            return (
              <Grid key={feature.title} size={{ xs: 12, sm: 6, md: 4 }}>
                <Card variant="outlined" sx={{ height: '100%' }}>
                  <CardContent>
                    <Stack spacing={1.5}>
                      <Icon color="primary" fontSize="large" />
                      <Typography variant="h6" fontWeight={600}>
                        {feature.title}
                      </Typography>
                      <Typography variant="body2" color="text.secondary">
                        {feature.description}
                      </Typography>
                    </Stack>
                  </CardContent>
                </Card>
              </Grid>
            );
          })}
        </Grid>
      </Container>
    </Box>
  );
}
