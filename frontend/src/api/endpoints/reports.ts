import { api } from '../axios';
import type { ReportsOverview } from '../../types';

export async function fetchReportsOverview(): Promise<ReportsOverview> {
  const { data } = await api.get<ReportsOverview>('/reports/overview');
  return data;
}
