import { api } from '../axios';
import type { AuthResponse } from '../../types';

export interface RegisterTenantPayload {
  plan_code: string;
  owner: { name: string; email: string; phone?: string; password: string };
  company: {
    name: string;
    registration_number?: string;
    tax_number?: string;
    country: string;
    city: string;
    address: string;
    currency: string;
    timezone: string;
    industry: string;
  };
  logo?: File | null;
}

export async function registerTenant(payload: RegisterTenantPayload): Promise<AuthResponse> {
  const form = new FormData();
  form.append('plan_code', payload.plan_code);

  Object.entries(payload.owner).forEach(([key, value]) => {
    if (value !== undefined && value !== '') form.append(`owner[${key}]`, value);
  });

  Object.entries(payload.company).forEach(([key, value]) => {
    if (value !== undefined && value !== '') form.append(`company[${key}]`, value);
  });

  if (payload.logo) {
    form.append('logo', payload.logo);
  }

  const { data } = await api.post<AuthResponse>('/tenants/register', form, {
    headers: { 'Content-Type': 'multipart/form-data' },
    skipErrorToast: true,
  });

  return data;
}
