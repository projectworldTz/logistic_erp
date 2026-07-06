import { api } from '../axios';
import type { User } from '../../types';

export interface InviteUserPayload {
  name: string;
  email: string;
  phone?: string;
  branch_id?: number | null;
  customer_id?: number | null;
  role: string;
  password: string;
}

export interface UpdateUserPayload {
  name?: string;
  phone?: string;
  branch_id?: number | null;
  role?: string;
}

export async function inviteUser(payload: InviteUserPayload): Promise<User> {
  const { data } = await api.post<{ data: User }>('/users', payload);
  return data.data;
}

export async function updateUser(id: number, payload: UpdateUserPayload): Promise<User> {
  const { data } = await api.put<{ data: User }>(`/users/${id}`, payload);
  return data.data;
}

export async function suspendUser(id: number): Promise<User> {
  const { data } = await api.post<{ data: User }>(`/users/${id}/suspend`);
  return data.data;
}

export async function activateUser(id: number): Promise<User> {
  const { data } = await api.post<{ data: User }>(`/users/${id}/activate`);
  return data.data;
}

export async function fetchRoles(): Promise<string[]> {
  const { data } = await api.get<{ data: string[] }>('/roles');
  return data.data;
}
