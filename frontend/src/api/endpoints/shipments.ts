import { api } from '../axios';
import type { Paginated, ProofOfDelivery, PublicShipmentTracking, Shipment, ShipmentCostSummary, ShipmentDelayRisk, TrackingEvent } from '../../types';

export async function fetchShipments(page = 1): Promise<Paginated<Shipment>> {
  const { data } = await api.get<Paginated<Shipment>>('/shipments/items', { params: { page } });
  return data;
}

export async function fetchShipment(id: number): Promise<Shipment> {
  const { data } = await api.get<{ data: Shipment }>(`/shipments/items/${id}`);
  return data.data;
}

export async function addShipmentMilestone(
  shipmentId: number,
  payload: { event_type: string; location?: string; occurred_at: string; notes?: string; is_customer_visible?: boolean },
): Promise<TrackingEvent> {
  const { data } = await api.post<{ data: TrackingEvent }>(`/shipments/items/${shipmentId}/milestones`, payload);
  return data.data;
}

export async function fetchPublicTracking(trackingCode: string): Promise<PublicShipmentTracking> {
  const { data } = await api.get<{ data: PublicShipmentTracking }>(`/public/track/${trackingCode}`);
  return data.data;
}

export async function createShipment(payload: Partial<Shipment>): Promise<Shipment> {
  const { data } = await api.post<{ data: Shipment }>('/shipments/items', payload);
  return data.data;
}

export async function updateShipment(id: number, payload: Partial<Shipment>): Promise<Shipment> {
  const { data } = await api.put<{ data: Shipment }>(`/shipments/items/${id}`, payload);
  return data.data;
}

export async function deleteShipment(id: number): Promise<void> {
  await api.delete(`/shipments/items/${id}`);
}

export async function fetchShipmentTrackingQr(id: number): Promise<Blob> {
  const { data } = await api.get(`/shipments/items/${id}/tracking-qr`, { responseType: 'blob' });
  return data;
}

export async function fetchDeliveryNoteQr(id: number): Promise<Blob> {
  const { data } = await api.get(`/shipments/items/${id}/delivery-note-qr`, { responseType: 'blob' });
  return data;
}

export async function fetchShipmentCostSummary(id: number): Promise<ShipmentCostSummary> {
  const { data } = await api.get<{ data: ShipmentCostSummary }>(`/shipments/items/${id}/cost-summary`);
  return data.data;
}

export async function checkShipmentSla(): Promise<{ delayed_alerted: number; near_deadline_alerted: number }> {
  const { data } = await api.post('/shipments/sla-check');
  return data;
}

export async function fetchShipmentDelayRisk(id: number): Promise<ShipmentDelayRisk> {
  const { data } = await api.get<ShipmentDelayRisk>(`/shipments/items/${id}/delay-risk`);
  return data;
}

export async function fetchProofOfDelivery(shipmentId: number): Promise<ProofOfDelivery | null> {
  try {
    const { data } = await api.get<{ data: ProofOfDelivery }>(`/shipments/items/${shipmentId}/proof-of-delivery`);
    return data.data;
  } catch (error) {
    if ((error as { response?: { status?: number } }).response?.status === 404) return null;
    throw error;
  }
}

export interface ProofOfDeliveryPayload {
  received_by_name: string;
  signature: File;
  photo?: File | null;
  latitude?: number | null;
  longitude?: number | null;
  notes?: string;
}

export async function submitProofOfDelivery(shipmentId: number, payload: ProofOfDeliveryPayload): Promise<ProofOfDelivery> {
  const form = new FormData();
  form.append('received_by_name', payload.received_by_name);
  form.append('signature', payload.signature);
  if (payload.photo) form.append('photo', payload.photo);
  if (payload.latitude != null) form.append('latitude', String(payload.latitude));
  if (payload.longitude != null) form.append('longitude', String(payload.longitude));
  if (payload.notes) form.append('notes', payload.notes);

  const { data } = await api.post<{ data: ProofOfDelivery }>(`/shipments/items/${shipmentId}/proof-of-delivery`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.data;
}
