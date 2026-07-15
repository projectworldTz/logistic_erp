import { api } from '../axios';
import type { ClearingFile, Paginated } from '../../types';

export async function fetchClearingFiles(page = 1): Promise<Paginated<ClearingFile>> {
  const { data } = await api.get<Paginated<ClearingFile>>('/clearing/files', { params: { page } });
  return data;
}

export async function createClearingFile(payload: Partial<ClearingFile>): Promise<ClearingFile> {
  const { data } = await api.post<{ data: ClearingFile }>('/clearing/files', payload);
  return data.data;
}

export async function updateClearingFile(id: number, payload: Partial<ClearingFile>): Promise<ClearingFile> {
  const { data } = await api.put<{ data: ClearingFile }>(`/clearing/files/${id}`, payload);
  return data.data;
}

export async function deleteClearingFile(id: number): Promise<void> {
  await api.delete(`/clearing/files/${id}`);
}

export async function fetchReleaseOrderQr(id: number): Promise<Blob> {
  const { data } = await api.get(`/clearing/files/${id}/release-order-qr`, { responseType: 'blob' });
  return data;
}
