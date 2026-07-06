import { useRef, useState } from 'react';
import { Alert, Avatar, Box, Button, Stack } from '@mui/material';
import UploadIcon from '@mui/icons-material/Upload';
import { useTranslation } from 'react-i18next';
import { uploadLandingImage } from '../../../../api/endpoints/platform';

interface ImageUploadButtonProps {
  label: string;
  purpose: 'hero' | 'avatar';
  currentUrl: string | null;
  onUploaded: (url: string) => void;
}

export function ImageUploadButton({ label, purpose, currentUrl, onUploaded }: ImageUploadButtonProps) {
  const { t } = useTranslation('superAdmin');
  const inputRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleChange = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    setUploading(true);
    setError(null);
    try {
      const url = await uploadLandingImage(file, purpose);
      onUploaded(url);
    } catch {
      setError(t('landingContent.imageUpload.uploadFailed'));
    } finally {
      setUploading(false);
      event.target.value = '';
    }
  };

  return (
    <Stack spacing={1} alignItems="flex-start">
      <Stack direction="row" spacing={2} alignItems="center">
        {purpose === 'avatar' ? (
          <Avatar src={currentUrl ?? undefined} sx={{ width: 48, height: 48 }} />
        ) : (
          currentUrl && (
            <Box component="img" src={currentUrl} alt="" sx={{ maxWidth: 200, maxHeight: 100, borderRadius: 1 }} />
          )
        )}
        <Button
          size="small"
          variant="outlined"
          startIcon={<UploadIcon />}
          disabled={uploading}
          onClick={() => inputRef.current?.click()}
        >
          {uploading ? t('landingContent.imageUpload.uploading') : label}
        </Button>
        <input ref={inputRef} type="file" accept="image/*" hidden onChange={handleChange} />
      </Stack>
      {error && <Alert severity="error">{error}</Alert>}
    </Stack>
  );
}
