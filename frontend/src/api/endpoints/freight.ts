import { api } from '../axios';
import type { FreightBooking, Paginated } from '../../types';

export async function fetchFreightBookings(page = 1): Promise<Paginated<FreightBooking>> {
  const { data } = await api.get<Paginated<FreightBooking>>('/freight/bookings', { params: { page } });
  return data;
}

export async function createFreightBooking(payload: Partial<FreightBooking>): Promise<FreightBooking> {
  const { data } = await api.post<{ data: FreightBooking }>('/freight/bookings', payload);
  return data.data;
}

export async function updateFreightBooking(id: number, payload: Partial<FreightBooking>): Promise<FreightBooking> {
  const { data } = await api.put<{ data: FreightBooking }>(`/freight/bookings/${id}`, payload);
  return data.data;
}

export async function deleteFreightBooking(id: number): Promise<void> {
  await api.delete(`/freight/bookings/${id}`);
}
