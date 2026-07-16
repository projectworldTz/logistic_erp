import { api } from '../axios';

export async function downloadBackup(): Promise<Blob> {
  const { data } = await api.get('/backup/export', { responseType: 'blob' });
  return data;
}

export async function restoreBackup(file: File): Promise<void> {
  const form = new FormData();
  form.append('file', file);

  await api.post('/backup/restore', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
}
