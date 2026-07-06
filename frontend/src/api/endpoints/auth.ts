import { api } from '../axios';
import type { AuthResponse, User } from '../../types';

export interface LoginPayload {
  email: string;
  password: string;
}

export interface ForgotPasswordPayload {
  email: string;
}

export interface ResetPasswordPayload {
  email: string;
  token: string;
  password: string;
  password_confirmation: string;
}

export async function login(payload: LoginPayload): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/auth/login', payload);
  return data;
}

export async function forgotPassword(payload: ForgotPasswordPayload): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>('/auth/forgot-password', payload);
  return data;
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>('/auth/reset-password', payload);
  return data;
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout');
}

export async function fetchMe(): Promise<User> {
  const { data } = await api.get<{ data: User } | User>('/auth/me');
  return 'data' in data ? data.data : data;
}
