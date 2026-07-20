import { Box, Card, CardContent, Stack, Typography } from '@mui/material';
import { SparkLineChart } from '@mui/x-charts/SparkLineChart';
import type { ReactNode } from 'react';

interface StatWidgetCardProps {
  label: string;
  value: string | number;
  icon?: ReactNode;
  /** Colors the value text to draw attention (e.g. a non-zero "delayed" count). */
  tone?: 'error' | 'warning' | 'success';
  /** Tints the icon's circular backdrop — purely cosmetic, falls back to a neutral tint when omitted. */
  accentColor?: string;
  /** Recent trend values, oldest first — renders a small inline sparkline when provided. */
  sparklineData?: number[];
}

export function StatWidgetCard({ label, value, icon, tone, accentColor, sparklineData }: StatWidgetCardProps) {
  return (
    <Card variant="outlined" sx={{ height: '100%' }}>
      <CardContent>
        <Stack direction="row" alignItems="flex-start" justifyContent="space-between">
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
          {icon && (
            <Box
              sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                width: 40,
                height: 40,
                borderRadius: '50%',
                flexShrink: 0,
                color: accentColor ?? 'primary.main',
                bgcolor: accentColor ? `${accentColor}1f` : 'action.selected',
              }}
            >
              {icon}
            </Box>
          )}
        </Stack>
        {sparklineData && sparklineData.length > 1 && (
          <Box sx={{ mt: 1, height: 32 }}>
            <SparkLineChart data={sparklineData} height={32} showTooltip={false} showHighlight={false} color={accentColor} />
          </Box>
        )}
      </CardContent>
    </Card>
  );
}
