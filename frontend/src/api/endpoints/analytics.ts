import { api } from '../axios';
import type { AnalyticsOverview } from '../../types';

export async function fetchAnalyticsOverview(params?: { from?: string; to?: string }): Promise<AnalyticsOverview> {
  const { data } = await api.get<AnalyticsOverview>('/analytics/overview', { params });
  return data;
}
