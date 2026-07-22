import {
  Alert,
  Avatar,
  Button,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  Grid,
  MenuItem,
  Skeleton,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import VerifiedRoundedIcon from '@mui/icons-material/VerifiedRounded';
import ErrorOutlineRoundedIcon from '@mui/icons-material/ErrorOutlineRounded';
import RefreshRoundedIcon from '@mui/icons-material/RefreshRounded';
import GavelRoundedIcon from '@mui/icons-material/GavelRounded';
import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  confirmIdentityVerification,
  rejectIdentityVerification,
  submitIdentityManualReview,
  verifyIdentity,
} from '../../../../../api/endpoints/identity';
import type { EmployeeIdentityVerification, IdentityDocumentTypeValue } from '../../../../../types';
import { StatusChip } from '../../../../../components/common/StatusChip';
import { useToast } from '../../../../../hooks/useToast';

const DOCUMENT_TYPES: IdentityDocumentTypeValue[] = ['national_id', 'passport', 'other'];

interface IdentityVerificationPanelProps {
  /** Fired once HR confirms a verified match — parent auto-fills its form from verification.person. */
  onConfirmed: (verification: EmployeeIdentityVerification) => void;
  /** Fired when the panel is reset (rejected / started over) so the parent can unlock its fields. */
  onCleared: () => void;
  disabled?: boolean;
}

export function IdentityVerificationPanel({ onConfirmed, onCleared, disabled }: IdentityVerificationPanelProps) {
  const { t } = useTranslation('hr');
  const { showToast } = useToast();

  const [documentType, setDocumentType] = useState<IdentityDocumentTypeValue>('national_id');
  const [identityNumber, setIdentityNumber] = useState('');
  const [countryCode, setCountryCode] = useState('TZ');
  const [verification, setVerification] = useState<EmployeeIdentityVerification | null>(null);
  const [reviewOpen, setReviewOpen] = useState(false);
  const [reviewReason, setReviewReason] = useState('');

  const payload = { document_type: documentType, identity_number: identityNumber, country_code: countryCode };

  const verifyMutation = useMutation({
    mutationFn: () => verifyIdentity(payload),
    onSuccess: (v) => setVerification(v),
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      showToast(message ?? t('identity.errors.provider_unavailable'), 'error');
    },
  });

  const confirmMutation = useMutation({
    mutationFn: () => confirmIdentityVerification(verification!.id),
    onSuccess: (v) => {
      setVerification(v);
      onConfirmed(v);
      showToast(t('identity.confirmed'));
    },
  });

  const rejectMutation = useMutation({
    mutationFn: () => rejectIdentityVerification(verification!.id),
    onSuccess: () => {
      setVerification(null);
      setIdentityNumber('');
      onCleared();
      showToast(t('identity.rejected'));
    },
  });

  const manualReviewMutation = useMutation({
    mutationFn: () => submitIdentityManualReview(verification!.id, { reason: reviewReason }),
    onSuccess: () => {
      setReviewOpen(false);
      setReviewReason('');
      showToast(t('identity.manualReview.submitted'));
    },
  });

  const startOver = () => {
    setVerification(null);
    setIdentityNumber('');
    onCleared();
  };

  const isConfirmed = !!verification?.confirmed_at;

  return (
    <Card variant="outlined" sx={{ borderStyle: 'dashed', borderWidth: 2, borderColor: 'primary.light', bgcolor: 'action.hover' }}>
      <CardContent>
        <Stack spacing={2}>
          <Stack direction="row" alignItems="center" spacing={1}>
            <VerifiedRoundedIcon color="primary" />
            <Typography variant="subtitle1" fontWeight={700}>
              {t('identity.sectionTitle')}
            </Typography>
          </Stack>
          <Typography variant="caption" color="text.secondary">
            {t('identity.sectionHelp')}
          </Typography>

          {!verification && (
            <Stack spacing={2}>
              <Grid container spacing={2}>
                <Grid size={{ xs: 12, sm: 5 }}>
                  <TextField
                    select
                    label={t('identity.documentType')}
                    fullWidth
                    size="small"
                    value={documentType}
                    onChange={(e) => setDocumentType(e.target.value as IdentityDocumentTypeValue)}
                    disabled={disabled}
                  >
                    {DOCUMENT_TYPES.map((type) => (
                      <MenuItem key={type} value={type}>
                        {t(`identity.documentTypes.${type}`)}
                      </MenuItem>
                    ))}
                  </TextField>
                </Grid>
                <Grid size={{ xs: 12, sm: 4 }}>
                  <TextField
                    label={t('identity.identityNumber')}
                    fullWidth
                    size="small"
                    value={identityNumber}
                    onChange={(e) => setIdentityNumber(e.target.value)}
                    disabled={disabled}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 3 }}>
                  <TextField
                    label={t('identity.country')}
                    fullWidth
                    size="small"
                    value={countryCode}
                    onChange={(e) => setCountryCode(e.target.value.toUpperCase().slice(0, 2))}
                    disabled={disabled}
                  />
                </Grid>
              </Grid>
              <Button
                variant="contained"
                onClick={() => verifyMutation.mutate()}
                disabled={disabled || !identityNumber || !countryCode || verifyMutation.isPending}
                startIcon={verifyMutation.isPending ? <CircularProgress size={16} color="inherit" /> : <VerifiedRoundedIcon />}
                sx={{ alignSelf: 'flex-start' }}
              >
                {verifyMutation.isPending ? t('identity.verifying') : t('identity.verify')}
              </Button>
              {verifyMutation.isPending && (
                <Stack spacing={1}>
                  <Skeleton variant="rounded" height={24} width="60%" />
                  <Skeleton variant="rounded" height={80} />
                </Stack>
              )}
            </Stack>
          )}

          {verification && !verification.verified && (
            <Stack spacing={1.5}>
              <Alert severity="warning" icon={<ErrorOutlineRoundedIcon />}>
                <Stack spacing={0.5}>
                  <StatusChip status={verification.status} label={t(`identity.statuses.${verification.status}`)} />
                  <Typography variant="body2">
                    {verification.result_message || t(`identity.errors.${verification.status}`, { defaultValue: verification.result_message ?? '' })}
                  </Typography>
                </Stack>
              </Alert>
              <Stack direction="row" spacing={1} flexWrap="wrap">
                <Button size="small" variant="outlined" startIcon={<RefreshRoundedIcon />} onClick={startOver}>
                  {t('identity.retry')}
                </Button>
                <Button size="small" variant="outlined" color="secondary" startIcon={<GavelRoundedIcon />} onClick={() => setReviewOpen(true)}>
                  {t('identity.sendForReview')}
                </Button>
              </Stack>
            </Stack>
          )}

          {verification && verification.verified && verification.person && (
            <Card variant="outlined">
              <CardContent>
                <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
                  <Avatar
                    src={verification.person.photo_url ?? undefined}
                    variant="rounded"
                    sx={{ width: 96, height: 96 }}
                  >
                    {verification.person.first_name.charAt(0)}
                  </Avatar>
                  <Stack spacing={1} flex={1}>
                    <Stack direction="row" spacing={1} alignItems="center" flexWrap="wrap">
                      <Typography variant="h6">{verification.person.full_name}</Typography>
                      <Chip
                        size="small"
                        color="success"
                        icon={<VerifiedRoundedIcon />}
                        label={t('identity.verifiedBadge')}
                      />
                      <Chip size="small" variant="outlined" label={t('identity.testProviderBadge')} />
                    </Stack>
                    <Grid container spacing={1}>
                      <Grid size={{ xs: 6, sm: 4 }}>
                        <Typography variant="caption" color="text.secondary">{t('identity.card.dateOfBirth')}</Typography>
                        <Typography variant="body2">{verification.person.date_of_birth ?? '—'}</Typography>
                      </Grid>
                      <Grid size={{ xs: 6, sm: 4 }}>
                        <Typography variant="caption" color="text.secondary">{t('identity.card.gender')}</Typography>
                        <Typography variant="body2">{verification.person.gender ?? '—'}</Typography>
                      </Grid>
                      <Grid size={{ xs: 6, sm: 4 }}>
                        <Typography variant="caption" color="text.secondary">{t('identity.card.nationality')}</Typography>
                        <Typography variant="body2">{verification.person.nationality ?? '—'}</Typography>
                      </Grid>
                      <Grid size={{ xs: 6, sm: 4 }}>
                        <Typography variant="caption" color="text.secondary">{t('identity.card.identityNumber')}</Typography>
                        <Typography variant="body2">{verification.identity_number_masked}</Typography>
                      </Grid>
                      <Grid size={{ xs: 6, sm: 4 }}>
                        <Typography variant="caption" color="text.secondary">{t('identity.card.provider')}</Typography>
                        <Typography variant="body2">{verification.provider}</Typography>
                      </Grid>
                      <Grid size={{ xs: 6, sm: 4 }}>
                        <Typography variant="caption" color="text.secondary">{t('identity.card.reference')}</Typography>
                        <Typography variant="body2" sx={{ wordBreak: 'break-all' }}>{verification.provider_reference ?? '—'}</Typography>
                      </Grid>
                    </Grid>

                    {isConfirmed ? (
                      <Alert severity="success" sx={{ mt: 1 }}>{t('identity.confirmed')}</Alert>
                    ) : (
                      <Stack direction="row" spacing={1} flexWrap="wrap" sx={{ mt: 1 }}>
                        <Button
                          size="small"
                          variant="contained"
                          color="success"
                          onClick={() => confirmMutation.mutate()}
                          disabled={confirmMutation.isPending}
                        >
                          {confirmMutation.isPending ? t('identity.confirming') : t('identity.confirm')}
                        </Button>
                        <Button
                          size="small"
                          variant="outlined"
                          color="error"
                          onClick={() => rejectMutation.mutate()}
                          disabled={rejectMutation.isPending}
                        >
                          {rejectMutation.isPending ? t('identity.rejecting') : t('identity.reject')}
                        </Button>
                        <Button size="small" onClick={startOver}>
                          {t('identity.verifyAgain')}
                        </Button>
                      </Stack>
                    )}
                  </Stack>
                </Stack>
              </CardContent>
            </Card>
          )}
        </Stack>
      </CardContent>

      <Dialog open={reviewOpen} onClose={() => setReviewOpen(false)} fullWidth maxWidth="xs">
        <DialogTitle>{t('identity.manualReview.title')}</DialogTitle>
        <DialogContent>
          <TextField
            autoFocus
            label={t('identity.manualReview.reason')}
            fullWidth
            multiline
            minRows={2}
            value={reviewReason}
            onChange={(e) => setReviewReason(e.target.value)}
            sx={{ mt: 1 }}
          />
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setReviewOpen(false)}>{t('identity.retry')}</Button>
          <Button
            variant="contained"
            disabled={!reviewReason.trim() || manualReviewMutation.isPending}
            onClick={() => manualReviewMutation.mutate()}
          >
            {t('identity.manualReview.submit')}
          </Button>
        </DialogActions>
      </Dialog>
    </Card>
  );
}
