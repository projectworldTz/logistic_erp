import { Box, Button, Paper, Stack, TextField, Typography } from '@mui/material';
import SendIcon from '@mui/icons-material/Send';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { fetchPortalMessages, sendPortalMessage } from '../../../api/endpoints/portal';
import { EmptyState } from '../../../components/common/EmptyState';

export function PortalMessagesPage() {
  const { t } = useTranslation('portal');
  const queryClient = useQueryClient();
  const [body, setBody] = useState('');
  const { data, isLoading } = useQuery({ queryKey: ['portal', 'messages'], queryFn: fetchPortalMessages });

  const sendMutation = useMutation({
    mutationFn: sendPortalMessage,
    onSuccess: () => {
      setBody('');
      queryClient.invalidateQueries({ queryKey: ['portal', 'messages'] });
    },
  });

  const onSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (body.trim()) sendMutation.mutate(body.trim());
  };

  return (
    <Stack spacing={3} sx={{ height: '100%' }}>
      <Typography variant="h5" fontWeight={700}>
        {t('messages.title')}
      </Typography>

      {!isLoading && data && data.data.length === 0 && <EmptyState title={t('messages.empty')} />}

      {data && data.data.length > 0 && (
        <Paper variant="outlined" sx={{ p: 2, maxHeight: 480, overflowY: 'auto' }}>
          <Stack spacing={1.5}>
            {data.data.map((message) => (
              <Box
                key={message.id}
                sx={{
                  alignSelf: message.is_from_customer ? 'flex-end' : 'flex-start',
                  bgcolor: message.is_from_customer ? 'primary.main' : 'action.hover',
                  color: message.is_from_customer ? 'primary.contrastText' : 'text.primary',
                  borderRadius: 2,
                  px: 2,
                  py: 1,
                  maxWidth: '70%',
                }}
              >
                <Typography variant="body2">{message.body}</Typography>
                <Typography variant="caption" sx={{ opacity: 0.7 }}>
                  {new Date(message.created_at).toLocaleString()}
                </Typography>
              </Box>
            ))}
          </Stack>
        </Paper>
      )}

      <Box component="form" onSubmit={onSubmit}>
        <Stack direction="row" spacing={1}>
          <TextField
            fullWidth
            placeholder={t('messages.placeholder')}
            value={body}
            onChange={(e) => setBody(e.target.value)}
          />
          <Button type="submit" variant="contained" startIcon={<SendIcon />} disabled={sendMutation.isPending}>
            {t('messages.send')}
          </Button>
        </Stack>
      </Box>
    </Stack>
  );
}
