import { Button, Card, CardActionArea, CardContent, CircularProgress, Grid, Stack, Typography } from '@mui/material';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import { useQuery } from '@tanstack/react-query';
import { useSearchParams } from 'react-router-dom';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { fetchPlans } from '../../../api/endpoints/plans';
import { useRegistrationStore } from '../registrationStore';

interface StepChoosePlanProps {
  onNext: () => void;
}

export function StepChoosePlan({ onNext }: StepChoosePlanProps) {
  const { t } = useTranslation('registration');
  const { data: plans, isLoading } = useQuery({ queryKey: ['public', 'plans'], queryFn: fetchPlans });
  const { planCode, setPlanCode } = useRegistrationStore();
  const [searchParams] = useSearchParams();

  useEffect(() => {
    const preselected = searchParams.get('plan');
    if (preselected && !planCode) setPlanCode(preselected);
  }, [searchParams, planCode, setPlanCode]);

  if (isLoading) {
    return (
      <Stack alignItems="center" py={6}>
        <CircularProgress />
      </Stack>
    );
  }

  return (
    <Stack spacing={3}>
      <Typography variant="h5" fontWeight={700}>
        {t('plan.heading')}
      </Typography>
      <Grid container spacing={2}>
        {plans?.map((plan) => (
          <Grid key={plan.code} size={{ xs: 12, sm: 4 }}>
            <Card
              variant="outlined"
              sx={{
                borderColor: planCode === plan.code ? 'primary.main' : 'divider',
                borderWidth: planCode === plan.code ? 2 : 1,
              }}
            >
              <CardActionArea onClick={() => setPlanCode(plan.code)}>
                <CardContent>
                  <Stack spacing={1}>
                    <Stack direction="row" justifyContent="space-between" alignItems="center">
                      <Typography variant="subtitle1" fontWeight={700}>
                        {plan.name}
                      </Typography>
                      {planCode === plan.code && <CheckCircleIcon color="primary" fontSize="small" />}
                    </Stack>
                    <Typography variant="h5" fontWeight={800}>
                      ${plan.price_monthly}
                      <Typography component="span" variant="body2" color="text.secondary">
                        {t('plan.perMonth')}
                      </Typography>
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                      {plan.description}
                    </Typography>
                  </Stack>
                </CardContent>
              </CardActionArea>
            </Card>
          </Grid>
        ))}
      </Grid>
      <Stack direction="row" justifyContent="flex-end">
        <Button variant="contained" size="large" disabled={!planCode} onClick={onNext}>
          {t('actions.continue')}
        </Button>
      </Stack>
    </Stack>
  );
}
