import { api } from '../axios';
import type { Paginated, Vehicle } from '../../types';

export async function fetchVehicles(page = 1): Promise<Paginated<Vehicle>> {
  const { data } = await api.get<Paginated<Vehicle>>('/fleet/vehicles', { params: { page } });
  return data;
}

export async function createVehicle(payload: Partial<Vehicle>): Promise<Vehicle> {
  const { data } = await api.post<{ data: Vehicle }>('/fleet/vehicles', payload);
  return data.data;
}

export async function updateVehicle(id: number, payload: Partial<Vehicle>): Promise<Vehicle> {
  const { data } = await api.put<{ data: Vehicle }>(`/fleet/vehicles/${id}`, payload);
  return data.data;
}

export async function deleteVehicle(id: number): Promise<void> {
  await api.delete(`/fleet/vehicles/${id}`);
}
