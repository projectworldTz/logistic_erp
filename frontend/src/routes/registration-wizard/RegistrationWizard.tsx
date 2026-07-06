import { Box, Container, Paper, Step, StepLabel, Stepper, Stack, Typography, Link as MuiLink } from '@mui/material';
import { Link as RouterLink } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useRegistrationStore } from './registrationStore';
import { StepChoosePlan } from './steps/StepChoosePlan';
import { StepOwnerAccount } from './steps/StepOwnerAccount';
import { StepCompanyDetails } from './steps/StepCompanyDetails';
import { StepReviewSubmit } from './steps/StepReviewSubmit';

export function RegistrationWizard() {
  const { t } = useTranslation('registration');
  const { step, setStep } = useRegistrationStore();

  const STEPS = [
    t('wizard.steps.choosePlan'),
    t('wizard.steps.ownerAccount'),
    t('wizard.steps.companyDetails'),
    t('wizard.steps.reviewSubmit'),
  ];

  return (
    <Box sx={{ minHeight: '100vh', bgcolor: 'background.default', py: 6 }}>
      <Container maxWidth="sm">
        <Stack spacing={1} textAlign="center" sx={{ mb: 4 }}>
          <Typography variant="h4" fontWeight={700}>
            {t('wizard.title')}
          </Typography>
          <Typography variant="body2" color="text.secondary">
            {t('wizard.alreadyHaveCompany')}{' '}
            <MuiLink component={RouterLink} to="/login">
              {t('wizard.logIn')}
            </MuiLink>
          </Typography>
        </Stack>

        <Stepper activeStep={step} sx={{ mb: 4 }} alternativeLabel>
          {STEPS.map((label) => (
            <Step key={label}>
              <StepLabel>{label}</StepLabel>
            </Step>
          ))}
        </Stepper>

        <Paper variant="outlined" sx={{ p: { xs: 3, sm: 4 } }}>
          {step === 0 && <StepChoosePlan onNext={() => setStep(1)} />}
          {step === 1 && <StepOwnerAccount onNext={() => setStep(2)} onBack={() => setStep(0)} />}
          {step === 2 && <StepCompanyDetails onNext={() => setStep(3)} onBack={() => setStep(1)} />}
          {step === 3 && <StepReviewSubmit onBack={() => setStep(2)} />}
        </Paper>
      </Container>
    </Box>
  );
}
