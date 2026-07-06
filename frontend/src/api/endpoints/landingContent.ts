import { api } from '../axios';
import type { LandingContent } from '../../types';

export async function fetchPublicLandingContent(): Promise<LandingContent> {
  const { data } = await api.get<{ data: LandingContent }>('/landing-content');
  return data.data;
}
