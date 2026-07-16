import { Box, Button, Stack, Typography } from '@mui/material';
import { useEffect, useRef } from 'react';

interface SignaturePadProps {
  label?: string;
  clearLabel: string;
  onChange: (file: File | null) => void;
}

/**
 * Hand-rolled canvas signature capture — no external signature-pad
 * dependency. Fixed pixel dimensions (no CSS stretch) so pointer
 * coordinates map 1:1 onto the canvas without a scale-factor bug.
 */
export function SignaturePad({ label, clearLabel, onChange }: SignaturePadProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const isDrawing = useRef(false);
  const hasDrawn = useRef(false);

  useEffect(() => {
    const ctx = canvasRef.current?.getContext('2d');
    if (!ctx) return;
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    ctx.strokeStyle = '#1f2937';
    ctx.lineWidth = 2;
    ctx.lineJoin = 'round';
    ctx.lineCap = 'round';
  }, []);

  const position = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const rect = e.currentTarget.getBoundingClientRect();
    return { x: e.clientX - rect.left, y: e.clientY - rect.top };
  };

  const handlePointerDown = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const ctx = canvasRef.current?.getContext('2d');
    if (!ctx) return;
    isDrawing.current = true;
    const { x, y } = position(e);
    ctx.beginPath();
    ctx.moveTo(x, y);
    e.currentTarget.setPointerCapture(e.pointerId);
  };

  const handlePointerMove = (e: React.PointerEvent<HTMLCanvasElement>) => {
    if (!isDrawing.current) return;
    const ctx = canvasRef.current?.getContext('2d');
    if (!ctx) return;
    const { x, y } = position(e);
    ctx.lineTo(x, y);
    ctx.stroke();
    hasDrawn.current = true;
  };

  const handlePointerUp = () => {
    if (!isDrawing.current) return;
    isDrawing.current = false;
    exportSignature();
  };

  const exportSignature = () => {
    const canvas = canvasRef.current;
    if (!canvas || !hasDrawn.current) {
      onChange(null);
      return;
    }
    canvas.toBlob((blob) => {
      onChange(blob ? new File([blob], 'signature.png', { type: 'image/png' }) : null);
    }, 'image/png');
  };

  const handleClear = () => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) return;
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    hasDrawn.current = false;
    onChange(null);
  };

  return (
    <Stack spacing={1}>
      {label && (
        <Typography variant="body2" color="text.secondary">
          {label}
        </Typography>
      )}
      <Box sx={{ border: '1px solid', borderColor: 'divider', borderRadius: 1, display: 'inline-block', lineHeight: 0 }}>
        <canvas
          ref={canvasRef}
          width={360}
          height={150}
          style={{ touchAction: 'none', cursor: 'crosshair' }}
          onPointerDown={handlePointerDown}
          onPointerMove={handlePointerMove}
          onPointerUp={handlePointerUp}
          onPointerLeave={handlePointerUp}
        />
      </Box>
      <Button size="small" onClick={handleClear} sx={{ alignSelf: 'flex-start' }}>
        {clearLabel}
      </Button>
    </Stack>
  );
}
