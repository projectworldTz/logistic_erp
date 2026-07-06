import { Card, CardContent, Stack, Typography } from '@mui/material';
import type { ReactNode } from 'react';

interface StatWidgetCardProps {
  label: string;
  value: string | number;
  icon?: ReactNode;
}

export function StatWidgetCard({ label, value, icon }: StatWidgetCardProps) {
  return (
    <Card variant="outlined" sx={{ height: '100%' }}>
      <CardContent>
        <Stack direction="row" alignItems="center" justifyContent="space-between">
          <Stack spacing={0.5}>
            <Typography variant="body2" color="text.secondary">
              {label}
            </Typography>
            <Typography variant="h4" fontWeight={600}>
              {value}
            </Typography>
          </Stack>
          {icon}
        </Stack>
      </CardContent>
    </Card>
  );
}
