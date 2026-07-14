import { api } from '../axios';
import type { ReportsOverview } from '../../types';

export async function fetchReportsOverview(): Promise<ReportsOverview> {
  const { data } = await api.get<ReportsOverview>('/reports/overview');
  return data;
}

export type ExportModule = 'customers' | 'leads' | 'quotations' | 'shipments' | 'invoices' | 'expenses';
export type ExportFormat = 'csv' | 'xlsx';

export async function downloadReportExport(module: ExportModule, format: ExportFormat): Promise<Blob> {
  const { data } = await api.get(`/reports/export/${module}`, {
    params: { format },
    responseType: 'blob',
  });
  return data;
}
