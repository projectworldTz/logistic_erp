import { api } from '../axios';
import type { Container, Paginated } from '../../types';

export async function fetchContainers(page = 1): Promise<Paginated<Container>> {
  const { data } = await api.get<Paginated<Container>>('/containers/items', { params: { page } });
  return data;
}

export async function createContainer(payload: Partial<Container>): Promise<Container> {
  const { data } = await api.post<{ data: Container }>('/containers/items', payload);
  return data.data;
}

export async function updateContainer(id: number, payload: Partial<Container>): Promise<Container> {
  const { data } = await api.put<{ data: Container }>(`/containers/items/${id}`, payload);
  return data.data;
}

export async function deleteContainer(id: number): Promise<void> {
  await api.delete(`/containers/items/${id}`);
}
