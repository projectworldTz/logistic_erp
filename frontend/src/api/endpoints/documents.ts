import { api } from '../axios';
import type { Document, Paginated } from '../../types';

export async function fetchDocuments(page = 1): Promise<Paginated<Document>> {
  const { data } = await api.get<Paginated<Document>>('/documents/files', { params: { page } });
  return data;
}

interface UploadDocumentPayload {
  file: File;
  category?: string;
  customer_id?: number;
  shipment_id?: number;
  parent_document_id?: number;
  description?: string;
}

export async function uploadDocument(payload: UploadDocumentPayload): Promise<Document> {
  const form = new FormData();
  form.append('file', payload.file);
  if (payload.category) form.append('category', payload.category);
  if (payload.customer_id) form.append('customer_id', String(payload.customer_id));
  if (payload.shipment_id) form.append('shipment_id', String(payload.shipment_id));
  if (payload.parent_document_id) form.append('parent_document_id', String(payload.parent_document_id));
  if (payload.description) form.append('description', payload.description);

  const { data } = await api.post<{ data: Document }>('/documents/files', form);
  return data.data;
}

export async function deleteDocument(id: number): Promise<void> {
  await api.delete(`/documents/files/${id}`);
}

export async function fetchDocumentVersions(id: number): Promise<{ data: Document[] }> {
  const { data } = await api.get<{ data: Document[] }>(`/documents/files/${id}/versions`);
  return data;
}
