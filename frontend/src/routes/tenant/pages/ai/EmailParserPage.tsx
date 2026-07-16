import { Button, Card, CardContent, Grid, MenuItem, Stack, TextField, Typography } from '@mui/material';
import AutoAwesomeIcon from '@mui/icons-material/AutoAwesome';
import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { parseEmail, type ParsedEmailFields } from '../../../../api/endpoints/ai';
import { createLead } from '../../../../api/endpoints/crm';
import { useToast } from '../../../../hooks/useToast';

export function EmailParserPage() {
  const { t } = useTranslation('ai');
  const navigate = useNavigate();
  const { showToast } = useToast();
  const [emailText, setEmailText] = useState('');
  const [fields, setFields] = useState<ParsedEmailFields | null>(null);

  const parseMutation = useMutation({
    mutationFn: () => parseEmail(emailText),
    onSuccess: (data) => setFields(data),
  });

  const createMutation = useMutation({
    mutationFn: () =>
      createLead({
        company_name: fields?.customer_name ?? '',
        contact_name: fields?.customer_name ?? '',
        email: fields?.customer_email || undefined,
        source: 'other',
        notes: [
          fields?.cargo_description,
          fields?.origin_port ? `Origin: ${fields.origin_port}` : null,
          fields?.destination_port ? `Destination: ${fields.destination_port}` : null,
          fields?.mode ? `Mode: ${fields.mode}` : null,
          fields?.direction ? `Direction: ${fields.direction}` : null,
          fields?.notes,
        ]
          .filter(Boolean)
          .join(' | '),
      }),
    onSuccess: () => {
      showToast(t('emailParser.toast.leadCreated'));
      navigate('/app/crm');
    },
  });

  return (
    <Stack spacing={3}>
      <Stack>
        <Typography variant="h5" fontWeight={700}>
          {t('emailParser.title')}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {t('emailParser.subtitle')}
        </Typography>
      </Stack>

      <Card variant="outlined">
        <CardContent>
          <Stack spacing={2}>
            <TextField
              label={t('emailParser.pasteLabel')}
              multiline
              minRows={8}
              fullWidth
              value={emailText}
              onChange={(e) => setEmailText(e.target.value)}
            />
            <Button
              variant="contained"
              startIcon={<AutoAwesomeIcon />}
              disabled={!emailText.trim() || parseMutation.isPending}
              onClick={() => parseMutation.mutate()}
              sx={{ alignSelf: 'flex-start' }}
            >
              {parseMutation.isPending ? t('emailParser.extracting') : t('emailParser.extract')}
            </Button>
            {parseMutation.isError && (
              <Typography variant="body2" color="error.main">
                {t('emailParser.error')}
              </Typography>
            )}
          </Stack>
        </CardContent>
      </Card>

      {fields && (
        <Card variant="outlined">
          <CardContent>
            <Stack spacing={2}>
              <Typography variant="subtitle1" fontWeight={700}>
                {t('emailParser.reviewTitle')}
              </Typography>
              <Grid container spacing={2}>
                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label={t('emailParser.fields.customerName')}
                    fullWidth
                    value={fields.customer_name ?? ''}
                    onChange={(e) => setFields({ ...fields, customer_name: e.target.value })}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label={t('emailParser.fields.customerEmail')}
                    fullWidth
                    value={fields.customer_email ?? ''}
                    onChange={(e) => setFields({ ...fields, customer_email: e.target.value })}
                  />
                </Grid>
                <Grid size={{ xs: 12 }}>
                  <TextField
                    label={t('emailParser.fields.cargoDescription')}
                    fullWidth
                    value={fields.cargo_description ?? ''}
                    onChange={(e) => setFields({ ...fields, cargo_description: e.target.value })}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label={t('emailParser.fields.originPort')}
                    fullWidth
                    value={fields.origin_port ?? ''}
                    onChange={(e) => setFields({ ...fields, origin_port: e.target.value })}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    label={t('emailParser.fields.destinationPort')}
                    fullWidth
                    value={fields.destination_port ?? ''}
                    onChange={(e) => setFields({ ...fields, destination_port: e.target.value })}
                  />
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    select
                    label={t('emailParser.fields.mode')}
                    fullWidth
                    value={fields.mode ?? ''}
                    onChange={(e) => setFields({ ...fields, mode: e.target.value as ParsedEmailFields['mode'] })}
                  >
                    <MenuItem value="sea">{t('emailParser.modes.sea')}</MenuItem>
                    <MenuItem value="air">{t('emailParser.modes.air')}</MenuItem>
                    <MenuItem value="land">{t('emailParser.modes.land')}</MenuItem>
                  </TextField>
                </Grid>
                <Grid size={{ xs: 12, sm: 6 }}>
                  <TextField
                    select
                    label={t('emailParser.fields.direction')}
                    fullWidth
                    value={fields.direction ?? ''}
                    onChange={(e) => setFields({ ...fields, direction: e.target.value as ParsedEmailFields['direction'] })}
                  >
                    <MenuItem value="import">{t('emailParser.directions.import')}</MenuItem>
                    <MenuItem value="export">{t('emailParser.directions.export')}</MenuItem>
                  </TextField>
                </Grid>
                <Grid size={{ xs: 12 }}>
                  <TextField
                    label={t('emailParser.fields.notes')}
                    fullWidth
                    multiline
                    rows={2}
                    value={fields.notes ?? ''}
                    onChange={(e) => setFields({ ...fields, notes: e.target.value })}
                  />
                </Grid>
              </Grid>
              <Button
                variant="contained"
                disabled={!fields.customer_name || createMutation.isPending}
                onClick={() => createMutation.mutate()}
                sx={{ alignSelf: 'flex-start' }}
              >
                {t('emailParser.createLead')}
              </Button>
            </Stack>
          </CardContent>
        </Card>
      )}
    </Stack>
  );
}
