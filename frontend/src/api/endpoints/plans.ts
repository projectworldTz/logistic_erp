import { api } from '../axios';
import type { Plan } from '../../types';

export async function fetchPlans(): Promise<Plan[]> {
  const { data } = await api.get<{ data: Plan[] }>('/plans');
  return data.data;
}
