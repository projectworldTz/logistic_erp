import { Stack, Typography } from '@mui/material';

interface StatusBreakdownBarProps {
  label: string;
  count: number;
  total: number;
  color: string;
}

export function StatusBreakdownBar({ label, count, total, color }: StatusBreakdownBarProps) {
  const percent = total > 0 ? Math.round((count / total) * 100) : 0;

  return (
    <Stack spacing={0.5}>
      <Stack direction="row" justifyContent="space-between">
        <Typography variant="body2" color="text.secondary">
          {label}
        </Typography>
        <Typography variant="body2" fontWeight={600}>
          {count}
        </Typography>
      </Stack>
      <Stack
        sx={{
          height: 8,
          borderRadius: 4,
          bgcolor: 'action.hover',
          overflow: 'hidden',
        }}
      >
        <Stack
          sx={{
            height: '100%',
            width: `${percent}%`,
            bgcolor: color,
            borderRadius: 4,
            transition: 'width 0.3s ease',
          }}
        />
      </Stack>
    </Stack>
  );
}
