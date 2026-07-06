import {
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Container,
  Grid,
  Stack,
  Typography,
} from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import { useQuery } from '@tanstack/react-query';
import { Link as RouterLink } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { fetchPlans } from '../../../api/endpoints/plans';

export function Pricing() {
  const { t } = useTranslation('landing');
  const { data: plans, isLoading } = useQuery({ queryKey: ['public', 'plans'], queryFn: fetchPlans });

  return (
    <Box id="pricing" sx={{ py: 10, bgcolor: 'background.paper' }}>
      <Container maxWidth="lg">
        <Stack spacing={1} textAlign="center" sx={{ mb: 6 }}>
          <Typography variant="h4" fontWeight={700}>
            {t('pricing.heading')}
          </Typography>
          <Typography variant="body1" color="text.secondary">
            {t('pricing.subheading')}
          </Typography>
        </Stack>

        {isLoading && (
          <Stack alignItems="center" py={6}>
            <CircularProgress />
          </Stack>
        )}

        <Grid container spacing={3} justifyContent="center">
          {plans?.map((plan, index) => (
            <Grid key={plan.code} size={{ xs: 12, sm: 6, md: 4 }}>
              <Card
                variant={index === 1 ? 'elevation' : 'outlined'}
                elevation={index === 1 ? 6 : 0}
                sx={{ height: '100%', border: index === 1 ? 2 : 1, borderColor: index === 1 ? 'primary.main' : 'divider' }}
              >
                <CardContent>
                  <Stack spacing={2}>
                    <Stack direction="row" alignItems="center" spacing={1}>
                      <Typography variant="h6" fontWeight={700}>
                        {plan.name}
                      </Typography>
                      {index === 1 && <Chip label={t('pricing.mostPopular')} color="primary" size="small" />}
                    </Stack>
                    <Typography variant="body2" color="text.secondary">
                      {plan.description}
                    </Typography>
                    <Typography variant="h3" fontWeight={800}>
                      ${plan.price_monthly}
                      <Typography component="span" variant="body1" color="text.secondary">
                        {t('pricing.perMonth')}
                      </Typography>
                    </Typography>
                    <Stack spacing={1}>
                      {plan.features.map((feature) => (
                        <Stack key={feature} direction="row" spacing={1} alignItems="center">
                          <CheckCircleIcon fontSize="small" color="success" />
                          <Typography variant="body2">{feature}</Typography>
                        </Stack>
                      ))}
                    </Stack>
                    <Button
                      component={RouterLink}
                      to={`/register?plan=${plan.code}`}
                      variant={index === 1 ? 'contained' : 'outlined'}
                      fullWidth
                    >
                      {t('pricing.getStarted')}
                    </Button>
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
