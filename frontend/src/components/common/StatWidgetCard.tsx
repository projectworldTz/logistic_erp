import { Card, CardContent, Stack, Typography } from '@mui/material';
import type { ReactNode } from 'react';

interface StatWidgetCardProps {
  label: string;
  value: string | number;
  icon?: ReactNode;
  /** Colors the value text to draw attention (e.g. a non-zero "delayed" count). */
  tone?: 'error' | 'warning' | 'success';
}

export function StatWidgetCard({ label, value, icon, tone }: StatWidgetCardProps) {
  return (
    <Card variant="outlined" sx={{ height: '100%' }}>
      <CardContent>
        <Stack direction="row" alignItems="center" justifyContent="space-between">
          <Stack spacing={0.5}>
            <Typography variant="body2" color="text.secondary">
              {label}
            </Typography>
            <Typography
              variant="h5"
              fontWeight={600}
              sx={{ overflowWrap: 'break-word', color: tone ? `${tone}.main` : undefined }}
            >
              {value}
            </Typography>
          </Stack>
          {icon}
        </Stack>
      </CardContent>
    </Card>
  );
}
