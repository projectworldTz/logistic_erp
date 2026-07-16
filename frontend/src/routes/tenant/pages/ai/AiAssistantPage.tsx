import { Avatar, Box, Button, Card, CircularProgress, Stack, TextField, Typography } from '@mui/material';
import SendIcon from '@mui/icons-material/Send';
import SmartToyIcon from '@mui/icons-material/SmartToy';
import { useMutation } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { sendAssistantMessage, type AssistantMessage } from '../../../../api/endpoints/ai';

export function AiAssistantPage() {
  const { t } = useTranslation('ai');
  const [messages, setMessages] = useState<AssistantMessage[]>([]);
  const [input, setInput] = useState('');
  const bottomRef = useRef<HTMLDivElement>(null);

  const mutation = useMutation({
    mutationFn: (message: string) => sendAssistantMessage(message, messages),
    onSuccess: (reply, message) => {
      setMessages((prev) => [...prev, { role: 'user', content: message }, { role: 'assistant', content: reply }]);
    },
  });

  useEffect(() => {
    bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSend = () => {
    if (!input.trim() || mutation.isPending) return;
    mutation.mutate(input.trim());
    setInput('');
  };

  return (
    <Stack spacing={2} sx={{ height: '100%' }}>
      <Stack>
        <Typography variant="h5" fontWeight={700}>
          {t('assistant.title')}
        </Typography>
        <Typography variant="body2" color="text.secondary">
          {t('assistant.subtitle')}
        </Typography>
      </Stack>

      <Card variant="outlined" sx={{ flex: 1, display: 'flex', flexDirection: 'column', minHeight: 480 }}>
        <Box sx={{ flex: 1, overflowY: 'auto', p: 2 }}>
          {messages.length === 0 && (
            <Typography variant="body2" color="text.secondary">
              {t('assistant.empty')}
            </Typography>
          )}
          <Stack spacing={1.5}>
            {messages.map((m, i) => (
              <Stack key={i} direction="row" spacing={1} justifyContent={m.role === 'user' ? 'flex-end' : 'flex-start'}>
                {m.role === 'assistant' && (
                  <Avatar sx={{ width: 28, height: 28, bgcolor: 'primary.main' }}>
                    <SmartToyIcon fontSize="small" />
                  </Avatar>
                )}
                <Box
                  sx={{
                    bgcolor: m.role === 'user' ? 'primary.main' : 'action.hover',
                    color: m.role === 'user' ? 'primary.contrastText' : 'text.primary',
                    borderRadius: 2,
                    px: 2,
                    py: 1,
                    maxWidth: '75%',
                    whiteSpace: 'pre-wrap',
                  }}
                >
                  <Typography variant="body2">{m.content}</Typography>
                </Box>
              </Stack>
            ))}
            {mutation.isPending && <CircularProgress size={20} />}
            {mutation.isError && (
              <Typography variant="body2" color="error.main">
                {t('assistant.error')}
              </Typography>
            )}
          </Stack>
          <div ref={bottomRef} />
        </Box>
        <Stack direction="row" spacing={1} sx={{ p: 2, borderTop: 1, borderColor: 'divider' }}>
          <TextField
            fullWidth
            placeholder={t('assistant.placeholder')}
            value={input}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleSend();
              }
            }}
          />
          <Button variant="contained" startIcon={<SendIcon />} disabled={!input.trim() || mutation.isPending} onClick={handleSend}>
            {t('assistant.send')}
          </Button>
        </Stack>
      </Card>
    </Stack>
  );
}
