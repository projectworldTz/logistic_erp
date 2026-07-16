import { api } from '../axios';
import type {
  CustomerApiKey,
  CustomerMessage,
  Document,
  Invoice,
  Paginated,
  PortalDashboardSummary,
  ProofOfDelivery,
  Quotation,
  Shipment,
} from '../../types';

export async function fetchPortalDashboardSummary(): Promise<PortalDashboardSummary> {
  const { data } = await api.get<PortalDashboardSummary>('/portal/dashboard/summary');
  return data;
}

export async function fetchPortalShipments(page = 1): Promise<Paginated<Shipment>> {
  const { data } = await api.get<Paginated<Shipment>>('/portal/shipments', { params: { page } });
  return data;
}

export async function fetchPortalShipment(id: number): Promise<Shipment> {
  const { data } = await api.get<{ data: Shipment }>(`/portal/shipments/${id}`);
  return data.data;
}

export async function fetchPortalShipmentTrackingQr(id: number): Promise<Blob> {
  const { data } = await api.get(`/portal/shipments/${id}/tracking-qr`, { responseType: 'blob' });
  return data;
}

export async function fetchPortalProofOfDelivery(id: number): Promise<ProofOfDelivery | null> {
  try {
    const { data } = await api.get<{ data: ProofOfDelivery }>(`/portal/shipments/${id}/proof-of-delivery`);
    return data.data;
  } catch (error) {
    if ((error as { response?: { status?: number } }).response?.status === 404) return null;
    throw error;
  }
}

export async function fetchPortalInvoices(page = 1): Promise<Paginated<Invoice>> {
  const { data } = await api.get<Paginated<Invoice>>('/portal/invoices', { params: { page } });
  return data;
}

export async function downloadPortalInvoicePdf(id: number): Promise<Blob> {
  const { data } = await api.get(`/portal/invoices/${id}/pdf`, { responseType: 'blob' });
  return data;
}

export async function fetchPortalQuotations(page = 1): Promise<Paginated<Quotation>> {
  const { data } = await api.get<Paginated<Quotation>>('/portal/quotations', { params: { page } });
  return data;
}

export async function approvePortalQuotation(id: number): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>(`/portal/quotations/${id}/approve`);
  return data.data;
}

export async function rejectPortalQuotation(id: number): Promise<Quotation> {
  const { data } = await api.post<{ data: Quotation }>(`/portal/quotations/${id}/reject`);
  return data.data;
}

export async function fetchPortalDocuments(page = 1): Promise<Paginated<Document>> {
  const { data } = await api.get<Paginated<Document>>('/portal/documents', { params: { page } });
  return data;
}

export async function uploadPortalDocument(payload: FormData): Promise<Document> {
  const { data } = await api.post<{ data: Document }>('/portal/documents', payload, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.data;
}

export async function fetchPortalMessages(): Promise<{ data: CustomerMessage[] }> {
  const { data } = await api.get<{ data: CustomerMessage[] }>('/portal/messages');
  return data;
}

export async function sendPortalMessage(body: string): Promise<CustomerMessage> {
  const { data } = await api.post<{ data: CustomerMessage }>('/portal/messages', { body });
  return data.data;
}

export async function fetchPortalApiKeys(): Promise<CustomerApiKey[]> {
  const { data } = await api.get<{ data: CustomerApiKey[] }>('/portal/api-keys');
  return data.data;
}

export async function createPortalApiKey(name: string): Promise<{ apiKey: CustomerApiKey; plaintextKey: string }> {
  const { data } = await api.post<{ data: CustomerApiKey; api_key: string }>('/portal/api-keys', { name });
  return { apiKey: data.data, plaintextKey: data.api_key };
}

export async function revokePortalApiKey(id: number): Promise<void> {
  await api.delete(`/portal/api-keys/${id}`);
}
