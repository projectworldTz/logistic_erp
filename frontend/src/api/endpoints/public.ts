import { api } from '../axios';

export interface ContactPayload {
  name: string;
  email: string;
  company?: string;
  message: string;
}

export interface DemoRequestPayload {
  name: string;
  email: string;
  company: string;
  phone?: string;
  preferred_time?: string;
}

export async function submitContact(payload: ContactPayload): Promise<void> {
  await api.post('/contact', payload);
}

export async function submitDemoRequest(payload: DemoRequestPayload): Promise<void> {
  await api.post('/demo-requests', payload);
}
