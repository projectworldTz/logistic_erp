import { Button, Paper, Stack, Typography } from '@mui/material';
import DownloadIcon from '@mui/icons-material/Download';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';

interface TrackingQrCodeProps {
  queryKey: readonly unknown[];
  fetchQr: () => Promise<Blob>;
  trackingCode: string | null;
  alt: string;
  caption: string;
  downloadLabel: string;
  /** Prefix for the downloaded file name (default "tracking-qr") — set a distinct value when a page renders more than one QR code, so downloads don't collide. */
  filenamePrefix?: string;
}

export function TrackingQrCode({
  queryKey,
  fetchQr,
  trackingCode,
  alt,
  caption,
  downloadLabel,
  filenamePrefix = 'tracking-qr',
}: TrackingQrCodeProps) {
  const [objectUrl, setObjectUrl] = useState<string | null>(null);

  const { data: blob } = useQuery({ queryKey, queryFn: fetchQr, enabled: !!trackingCode });

  useEffect(() => {
    if (!blob) return;
    const url = URL.createObjectURL(blob);
    setObjectUrl(url);
    return () => URL.revokeObjectURL(url);
  }, [blob]);

  if (!trackingCode || !objectUrl) return null;

  const handleDownload = () => {
    const link = document.createElement('a');
    link.href = objectUrl;
    link.download = `${filenamePrefix}-${trackingCode}.svg`;
    link.click();
  };

  return (
    <Paper variant="outlined" sx={{ p: 2, display: 'inline-block' }}>
      <Stack spacing={1} alignItems="center">
        <img src={objectUrl} alt={alt} width={160} height={160} />
        <Typography variant="caption" color="text.secondary">
          {caption}
        </Typography>
        <Button size="small" startIcon={<DownloadIcon />} onClick={handleDownload}>
          {downloadLabel}
        </Button>
      </Stack>
    </Paper>
  );
}
