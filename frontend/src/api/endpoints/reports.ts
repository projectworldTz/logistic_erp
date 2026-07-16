import { api } from '../axios';
import type { CustomsReport, ProfitReport, ReportsOverview, TaxReport } from '../../types';

export async function fetchReportsOverview(branchId?: number | ''): Promise<ReportsOverview> {
  const { data } = await api.get<ReportsOverview>('/reports/overview', {
    params: branchId ? { branch_id: branchId } : undefined,
  });
  return data;
}

export async function fetchProfitReport(branchId?: number | ''): Promise<ProfitReport> {
  const { data } = await api.get<ProfitReport>('/reports/profit', {
    params: branchId ? { branch_id: branchId } : undefined,
  });
  return data;
}

export async function fetchCustomsReport(): Promise<CustomsReport> {
  const { data } = await api.get<CustomsReport>('/reports/customs');
  return data;
}

export async function fetchTaxReport(): Promise<TaxReport> {
  const { data } = await api.get<TaxReport>('/reports/tax');
  return data;
}

export type ExportModule = 'customers' | 'leads' | 'quotations' | 'shipments' | 'invoices' | 'expenses' | 'profit';
export type ExportFormat = 'csv' | 'xlsx';

export async function downloadReportExport(module: ExportModule, format: ExportFormat): Promise<Blob> {
  const { data } = await api.get(`/reports/export/${module}`, {
    params: { format },
    responseType: 'blob',
  });
  return data;
}

export type ImportModule = 'customers' | 'leads';

export interface ImportRowError {
  row: number;
  messages: string[];
}

export interface ImportResult {
  created: number;
  errors: ImportRowError[];
}

export async function importReportData(module: ImportModule, file: File): Promise<ImportResult> {
  const form = new FormData();
  form.append('file', file);

  const { data } = await api.post<ImportResult>(`/reports/import/${module}`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data;
}

export type ScheduledReportFrequency = 'daily' | 'weekly' | 'monthly';

export interface ScheduledReport {
  id: number;
  name: string;
  module: ExportModule;
  format: ExportFormat;
  frequency: ScheduledReportFrequency;
  recipients: string[];
  is_active: boolean;
  last_sent_at: string | null;
  created_at: string;
}

export interface ScheduledReportPayload {
  name: string;
  module: ExportModule;
  format: ExportFormat;
  frequency: ScheduledReportFrequency;
  recipients: string[];
  is_active?: boolean;
}

export async function fetchScheduledReports(): Promise<{ data: ScheduledReport[] }> {
  const { data } = await api.get<{ data: ScheduledReport[] }>('/reports/scheduled');
  return data;
}

export async function createScheduledReport(payload: ScheduledReportPayload): Promise<ScheduledReport> {
  const { data } = await api.post<{ data: ScheduledReport }>('/reports/scheduled', payload);
  return data.data;
}

export async function updateScheduledReport(id: number, payload: Partial<ScheduledReportPayload>): Promise<ScheduledReport> {
  const { data } = await api.put<{ data: ScheduledReport }>(`/reports/scheduled/${id}`, payload);
  return data.data;
}

export async function deleteScheduledReport(id: number): Promise<void> {
  await api.delete(`/reports/scheduled/${id}`);
}
