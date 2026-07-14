import { api } from '../axios';
import type { AuthResponse, LoginResult, User } from '../../types';

export interface LoginPayload {
  email: string;
  password: string;
}

export interface TwoFactorVerifyPayload {
  challenge_token: string;
  code: string;
}

export interface EnableTwoFactorPayload {
  secret: string;
  code: string;
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

export async function login(payload: LoginPayload): Promise<LoginResult> {
  const { data } = await api.post<LoginResult>('/auth/login', payload);
  return data;
}

export async function verifyTwoFactor(payload: TwoFactorVerifyPayload): Promise<AuthResponse> {
  const { data } = await api.post<AuthResponse>('/auth/2fa/verify', payload);
  return data;
}

export async function setupTwoFactor(): Promise<{ secret: string; qr_svg: string }> {
  const { data } = await api.post<{ secret: string; qr_svg: string }>('/auth/2fa/setup');
  return data;
}

export async function enableTwoFactor(payload: EnableTwoFactorPayload): Promise<{ recovery_codes: string[] }> {
  const { data } = await api.post<{ recovery_codes: string[] }>('/auth/2fa/enable', payload);
  return data;
}

export async function disableTwoFactor(password: string): Promise<{ message: string }> {
  const { data } = await api.post<{ message: string }>('/auth/2fa/disable', { password });
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
