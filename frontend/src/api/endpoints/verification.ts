import { api } from '../axios';

export interface ReleaseOrderVerification {
  reference_no: string | null;
  release_order_number: string | null;
  status: string;
  assessment_status: string;
  customs_office: string | null;
  cleared_date: string | null;
}

export interface DeliveryNoteVerification {
  shipment_number: string | null;
  tracking_code: string;
  destination_port: string | null;
  status: string;
  delivered_at: string | null;
}

export async function fetchReleaseOrderVerification(token: string): Promise<ReleaseOrderVerification> {
  const { data } = await api.get<{ data: ReleaseOrderVerification }>(`/public/verify/release-order/${token}`);
  return data.data;
}

export async function fetchDeliveryNoteVerification(trackingCode: string): Promise<DeliveryNoteVerification> {
  const { data } = await api.get<{ data: DeliveryNoteVerification }>(`/public/verify/delivery-note/${trackingCode}`);
  return data.data;
}

export interface PayslipVerification {
  payslip_number: string | null;
  employee_name: string | null;
  period_name: string | null;
  net_pay: string;
  currency: string;
  generated_at: string;
}

export async function fetchPayslipVerification(code: string): Promise<PayslipVerification> {
  const { data } = await api.get<{ data: PayslipVerification }>(`/public/verify/payslip/${code}`);
  return data.data;
}
