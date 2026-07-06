import { api } from '../axios';
import type { Paginated, WarehouseItem } from '../../types';

export async function fetchWarehouseItems(page = 1): Promise<Paginated<WarehouseItem>> {
  const { data } = await api.get<Paginated<WarehouseItem>>('/warehouse/items', { params: { page } });
  return data;
}

export async function createWarehouseItem(payload: Partial<WarehouseItem>): Promise<WarehouseItem> {
  const { data } = await api.post<{ data: WarehouseItem }>('/warehouse/items', payload);
  return data.data;
}

export async function updateWarehouseItem(id: number, payload: Partial<WarehouseItem>): Promise<WarehouseItem> {
  const { data } = await api.put<{ data: WarehouseItem }>(`/warehouse/items/${id}`, payload);
  return data.data;
}

export async function deleteWarehouseItem(id: number): Promise<void> {
  await api.delete(`/warehouse/items/${id}`);
}
