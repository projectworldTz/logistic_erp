import { api } from '../axios';
import type { Paginated, Vehicle, VehicleLog } from '../../types';

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

export interface VehicleLogPayload {
  type: VehicleLog['type'];
  log_date: string;
  description: string;
  cost?: number;
  currency?: string;
  odometer_km?: number;
  liters?: number;
  policy_number?: string;
  expiry_date?: string;
  driver_id?: number;
  origin?: string;
  destination?: string;
  distance_km?: number;
  notes?: string;
}

export async function fetchVehicleLogs(vehicleId: number): Promise<Paginated<VehicleLog>> {
  const { data } = await api.get<Paginated<VehicleLog>>(`/fleet/vehicles/${vehicleId}/logs`);
  return data;
}

export async function createVehicleLog(vehicleId: number, payload: VehicleLogPayload): Promise<VehicleLog> {
  const { data } = await api.post<{ data: VehicleLog }>(`/fleet/vehicles/${vehicleId}/logs`, payload);
  return data.data;
}

export async function deleteVehicleLog(vehicleId: number, logId: number): Promise<void> {
  await api.delete(`/fleet/vehicles/${vehicleId}/logs/${logId}`);
}
