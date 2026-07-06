import { Avatar, Box, Card, CardContent, Container, Grid, Stack, Typography } from '@mui/material';
import FormatQuoteIcon from '@mui/icons-material/FormatQuote';
import type { TestimonialsContent } from '../../../types';

interface TestimonialsProps {
  content: TestimonialsContent;
}

export function Testimonials({ content }: TestimonialsProps) {
  return (
    <Box sx={{ py: 10, bgcolor: 'background.paper' }}>
      <Container maxWidth="lg">
        <Stack spacing={1} textAlign="center" sx={{ mb: 6 }}>
          <Typography variant="h4" fontWeight={700}>
            {content.heading}
          </Typography>
        </Stack>
        <Grid container spacing={3}>
          {content.items.map((testimonial) => (
            <Grid key={testimonial.name} size={{ xs: 12, md: 4 }}>
              <Card variant="outlined" sx={{ height: '100%' }}>
                <CardContent>
                  <Stack spacing={2}>
                    <FormatQuoteIcon color="primary" fontSize="large" />
                    <Typography variant="body1">{testimonial.quote}</Typography>
                    <Stack direction="row" spacing={1.5} alignItems="center">
                      <Avatar src={testimonial.avatar_url ?? undefined}>
                        {testimonial.avatar_url ? null : testimonial.name.charAt(0)}
                      </Avatar>
                      <Stack>
                        <Typography variant="subtitle2" fontWeight={600}>
                          {testimonial.name}
                        </Typography>
                        <Typography variant="caption" color="text.secondary">
                          {testimonial.role}
                        </Typography>
                      </Stack>
                    </Stack>
                  </Stack>
                </CardContent>
              </Card>
            </Grid>
          ))}
        </Grid>
      </Container>
    </Box>
  );
}
