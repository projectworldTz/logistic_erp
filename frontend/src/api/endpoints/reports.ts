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
