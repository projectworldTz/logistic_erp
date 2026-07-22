export interface User {
  id: number;
  tenant_id: number | null;
  branch_id: number | null;
  customer_id: number | null;
  name: string;
  email: string;
  phone: string | null;
  is_super_admin: boolean;
  status: 'active' | 'invited' | 'suspended';
  two_factor_enabled: boolean;
  roles: string[];
  permissions: string[];
}

export interface Company {
  id: number;
  name: string;
  registration_number: string | null;
  tax_number: string | null;
  country: string;
  city: string;
  address: string;
  currency: string;
  usd_to_tzs_rate: number;
  timezone: string;
  industry: string;
  logo_url: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  email_footer_text: string | null;
  email_reply_to: string | null;
  notify_email_enabled: boolean;
  notify_sms_enabled: boolean;
  notify_whatsapp_enabled: boolean;
  phone: string | null;
  email: string | null;
  website: string | null;
  require_identity_verification_before_payroll: boolean;
}

export interface Branch {
  id: number;
  name: string;
  code: string;
  is_default: boolean;
  address: string | null;
  city: string | null;
  country: string | null;
  phone: string | null;
  email: string | null;
  timezone: string | null;
}

export interface BranchRollupRow {
  branch_id: number | null;
  branch_name: string;
  is_default: boolean;
  employees_total: number;
  vehicles_total: number;
  warehouse_items_total: number;
  shipments_total: number;
  shipments_by_status: Record<string, number>;
  invoices_total: number;
  revenue_paid: number;
  revenue_outstanding: number;
}

export interface Plan {
  id: number;
  code: string;
  name: string;
  description: string | null;
  price_monthly: string;
  price_yearly: string;
  currency: string;
  max_users: number | null;
  max_branches: number | null;
  features: string[];
  is_active: boolean;
}

export interface Subscription {
  id: number;
  status: 'trialing' | 'active' | 'past_due' | 'canceled';
  billing_cycle: 'monthly' | 'yearly';
  starts_at: string;
  trial_ends_at: string | null;
  ends_at: string | null;
  plan?: Plan;
  tenant?: { id: number; name: string };
}

export interface BillingProfile {
  id: number;
  billing_name: string;
  billing_email: string | null;
  billing_phone: string | null;
  billing_address: string | null;
  tax_id: string | null;
  payment_method_type: string | null;
  payment_reference: string | null;
}

export interface SubscriptionInvoice {
  id: number;
  plan_name: string;
  amount: string;
  currency: string;
  period_start: string;
  period_end: string;
  due_date: string;
  status: 'pending' | 'paid' | 'overdue' | 'void';
  paid_at: string | null;
}

export interface Tenant {
  id: number;
  name: string;
  slug: string;
  status: 'trial' | 'active' | 'suspended' | 'cancelled';
  timezone: string;
  currency: string;
  trial_ends_at: string | null;
  suspended_at: string | null;
  created_at: string;
  company?: Company;
  subscription?: Subscription;
}

export interface AuditLog {
  id: number;
  action: string;
  auditable_type: string | null;
  auditable_id: number | null;
  old_values: Record<string, unknown> | null;
  new_values: Record<string, unknown> | null;
  ip_address: string | null;
  user: { id: number; name: string; email: string } | null;
  created_at: string;
}

export interface ErrorLogItem {
  id: number;
  reference: string;
  tenant: { id: number; name: string } | null;
  user: { id: number; name: string; email: string } | null;
  exception_class: string;
  message: string;
  status_code: number;
  method: string | null;
  url: string | null;
  file: string | null;
  line: number | null;
  trace: string | null;
  request_payload: Record<string, unknown> | null;
  ip_address: string | null;
  user_agent: string | null;
  resolved_at: string | null;
  created_at: string;
}

export interface DashboardWidgets {
  daily_shipments?: number;
  pending_customs?: number;
  active_containers?: number;
  revenue?: number;
  expenses?: number;
  outstanding_invoices?: number;
  fleet_status?: { active: number; maintenance: number };
  warehouse_status?: { utilization_percent: number };
  shipment_intelligence?: {
    active: number;
    released: number;
    delayed: number;
    near_deadline: number;
    customers_served: number;
    avg_customs_clearance_days: number | null;
  };
}

export interface DashboardSummary {
  widgets: DashboardWidgets;
  updated_at: string;
}

export interface PlatformMetrics {
  tenant_count: number;
  active_tenant_count: number;
  trial_tenant_count: number;
  active_users: number;
  revenue_mtd: number;
  storage_used_mb: number;
}

export interface SystemHealth {
  database: { status: 'ok' | 'down'; response_ms?: number; message?: string };
  cache: { status: 'ok' | 'down'; driver?: string; message?: string };
  queue: { status: 'ok' | 'unknown'; pending_jobs?: number; failed_jobs?: number; message?: string };
  storage: { status: 'ok' | 'warning' | 'unknown'; total_gb?: number; free_gb?: number; used_percent?: number };
  app: {
    environment: string;
    php_version: string;
    laravel_version: string;
    debug_mode: boolean;
    mailer: string;
    queue_connection: string;
  };
}

export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface AuthResponse {
  token: string;
  user: User;
}

export interface TwoFactorChallenge {
  requires_2fa: true;
  challenge_token: string;
}

export type LoginResult = AuthResponse | TwoFactorChallenge;

export function isTwoFactorChallenge(result: LoginResult): result is TwoFactorChallenge {
  return 'requires_2fa' in result;
}

export interface LoginAttempt {
  id: number;
  email: string;
  ip_address: string | null;
  user_agent: string | null;
  successful: boolean;
  reason: string | null;
  user: { id: number; name: string } | null;
  created_at: string;
}

export interface Lead {
  id: number;
  company_name: string;
  contact_name: string;
  email: string | null;
  phone: string | null;
  source: 'website' | 'referral' | 'cold_call' | 'social' | 'other';
  status: 'new' | 'contacted' | 'qualified' | 'converted' | 'lost';
  assigned_to: number | null;
  assigned_to_user?: User | null;
  notes: string | null;
  converted_customer_id: number | null;
  created_at: string;
}

export interface Customer {
  id: number;
  company_name: string;
  industry: string | null;
  email: string | null;
  phone: string | null;
  address: string | null;
  city: string | null;
  country: string | null;
  currency: string | null;
  status: 'active' | 'inactive';
  assigned_to: number | null;
  assigned_to_user?: User | null;
  contacts?: Contact[];
  created_at: string;
}

export interface Contact {
  id: number;
  customer_id: number;
  name: string;
  email: string | null;
  phone: string | null;
  job_title: string | null;
  is_primary: boolean;
}

export type ComplianceDocumentType =
  | 'business_registration'
  | 'tax_certificate'
  | 'trading_license'
  | 'authorized_signatory_id'
  | 'other';

export interface ComplianceDocument {
  id: number;
  customer_id: number;
  document_type: ComplianceDocumentType;
  document_number: string | null;
  issue_date: string | null;
  expiry_date: string | null;
  status: 'valid' | 'expiring_soon' | 'expired' | 'no_expiry';
  file_url: string | null;
  notes: string | null;
  uploaded_by: number | null;
  uploaded_by_user?: User;
  created_at: string;
}

export interface ClearingFile {
  id: number;
  customer_id: number;
  customer?: Customer;
  reference_no: string | null;
  direction: 'import' | 'export';
  mode: 'sea' | 'air' | 'land';
  port_of_loading: string | null;
  port_of_discharge: string | null;
  bl_awb_number: string | null;
  customs_office: string | null;
  declaration_number: string | null;
  sad_number: string | null;
  hs_code: string | null;
  customs_value: string | null;
  cargo_description: string | null;
  status: 'pending' | 'documents_received' | 'under_clearance' | 'customs_hold' | 'cleared' | 'delivered' | 'cancelled';
  assigned_to: number | null;
  assigned_to_user?: User | null;
  duty_amount: string | null;
  vat_amount: string | null;
  other_charges: string | null;
  eta: string | null;
  cleared_date: string | null;
  release_order_number: string | null;
  assessment_status: 'pending' | 'assessed' | 'objected' | 'released';
  delivered_date: string | null;
  notes: string | null;
  created_at: string;
}

export interface Container {
  id: number;
  customer_id: number;
  customer?: Customer;
  clearing_file_id: number | null;
  freight_booking_id: number | null;
  container_number: string;
  container_type: 'dry_20' | 'dry_40' | 'dry_40_hc' | 'reefer_20' | 'reefer_40' | 'open_top' | 'flat_rack' | 'tank';
  shipping_line: string | null;
  vessel_name: string | null;
  voyage_number: string | null;
  port_of_loading: string | null;
  port_of_discharge: string | null;
  seal_number: string | null;
  status: 'at_port' | 'in_transit' | 'at_warehouse' | 'delivered' | 'returned' | 'empty_return';
  gross_weight_kg: string | null;
  location: string | null;
  gate_in_date: string | null;
  eta: string | null;
  ata: string | null;
  gate_out_date: string | null;
  empty_return_date: string | null;
  notes: string | null;
  created_at: string;
}

export interface WarehouseItem {
  id: number;
  customer_id: number;
  customer?: Customer;
  branch_id: number | null;
  branch?: Branch;
  reference_no: string | null;
  description: string;
  quantity: string;
  unit: string;
  bin_location: string | null;
  weight_kg: string | null;
  volume_cbm: string | null;
  status: 'received' | 'stored' | 'picked' | 'dispatched' | 'damaged';
  received_date: string | null;
  dispatched_date: string | null;
  notes: string | null;
  created_at: string;
}

export interface Vehicle {
  id: number;
  branch_id: number | null;
  branch?: Branch;
  registration_number: string;
  vehicle_type: 'truck' | 'van' | 'trailer' | 'forklift' | 'other';
  make: string | null;
  model: string | null;
  year: number | null;
  capacity_kg: string | null;
  status: 'active' | 'in_maintenance' | 'out_of_service' | 'retired';
  assigned_driver: number | null;
  assigned_driver_user?: User | null;
  last_service_date: string | null;
  next_service_due: string | null;
  notes: string | null;
  created_at: string;
}

export interface VehicleLog {
  id: number;
  vehicle_id: number;
  type: 'maintenance' | 'fuel' | 'insurance' | 'trip';
  log_date: string;
  description: string;
  cost: string | null;
  currency: string | null;
  odometer_km: string | null;
  liters: string | null;
  policy_number: string | null;
  expiry_date: string | null;
  driver_id: number | null;
  driver?: User | null;
  origin: string | null;
  destination: string | null;
  distance_km: string | null;
  notes: string | null;
  created_by: number | null;
  creator?: User | null;
  created_at: string;
}

export interface Invoice {
  id: number;
  customer_id: number;
  customer?: Customer;
  branch_id: number | null;
  branch?: Branch | null;
  shipment_id: number | null;
  invoice_number: string | null;
  issue_date: string;
  due_date: string;
  status: 'draft' | 'sent' | 'paid' | 'overdue' | 'cancelled';
  subtotal: string;
  tax_amount: string;
  total_amount: string;
  currency: string;
  notes: string | null;
  created_at: string;
}

export interface Expense {
  id: number;
  expense_number: string | null;
  customer_id: number | null;
  customer?: Customer;
  shipment_id: number | null;
  shipment?: Shipment;
  clearing_file_id: number | null;
  freight_booking_id: number | null;
  category: 'customs_duty' | 'trucking' | 'port_fees' | 'documentation' | 'warehousing' | 'insurance' | 'utilities' | 'office_supplies' | 'other';
  description: string;
  amount: string;
  currency: string;
  expense_date: string;
  is_billable: boolean;
  status: 'draft' | 'submitted' | 'approved' | 'rejected' | 'paid';
  created_by: number | null;
  creator?: User;
  approved_by: number | null;
  approver?: User;
  rejection_reason: string | null;
  paid_at: string | null;
  notes: string | null;
  approval_request?: ApprovalRequest | null;
  created_at: string;
}

export interface ApprovalWorkflowStep {
  id: number;
  position: number;
  approver_role: string;
}

export interface ApprovalWorkflow {
  id: number;
  name: string;
  subject_type: 'expense' | 'quotation';
  min_amount: string | null;
  is_active: boolean;
  steps: ApprovalWorkflowStep[];
  created_at: string;
}

export interface ApprovalDecision {
  id: number;
  step_position: number;
  approver_role: string;
  decided_by: number | null;
  decided_by_name: string | null;
  decision: 'approved' | 'rejected';
  comment: string | null;
  decided_at: string | null;
}

export interface ApprovalRequest {
  id: number;
  workflow_id: number | null;
  workflow_name: string | null;
  status: 'pending' | 'approved' | 'rejected';
  current_step_position: number;
  total_steps: number | null;
  current_step_role: string | null;
  decisions: ApprovalDecision[];
}

export interface Department {
  id: number;
  name: string;
  branch_id: number | null;
  branch?: Branch;
  description: string | null;
  employees_count?: number;
  created_at: string;
}

export type EmploymentType =
  | 'full_time'
  | 'part_time'
  | 'contract'
  | 'intern'
  | 'permanent'
  | 'temporary'
  | 'casual'
  | 'consultant'
  | 'driver'
  | 'commission_based'
  | 'daily_paid';

export type EmployeeStatusValue = 'active' | 'on_leave' | 'terminated' | 'probation' | 'suspended';

export type DesignationCategory =
  | 'management'
  | 'clearing_and_customs'
  | 'forwarding_and_logistics'
  | 'transport_and_fleet'
  | 'warehouse_and_cargo'
  | 'finance_and_accounts'
  | 'sales_and_crm'
  | 'administration_and_support'
  | 'other';

export interface Designation {
  id: number;
  name: string;
  category: DesignationCategory;
  description: string | null;
  is_active: boolean;
  employees_count?: number;
  created_at: string;
}

export interface Employee {
  id: number;
  employee_number: string | null;
  department_id: number | null;
  department?: Department;
  branch_id: number | null;
  branch?: Branch;
  user_id: number | null;
  user?: User;
  designation_id: number | null;
  designation?: Designation;
  reporting_manager_id: number | null;
  reporting_manager?: Employee;
  name: string;
  first_name: string | null;
  middle_name: string | null;
  last_name: string | null;
  gender: string | null;
  date_of_birth: string | null;
  nationality: string | null;
  marital_status: string | null;
  photo_path: string | null;
  email: string | null;
  phone: string | null;
  alternative_phone: string | null;
  residential_address: string | null;
  emergency_contact_name: string | null;
  emergency_contact_phone: string | null;
  job_title: string | null;
  employee_category: string | null;
  employment_type: EmploymentType;
  status: EmployeeStatusValue;
  hire_date: string;
  confirmation_date: string | null;
  probation_end_date: string | null;
  termination_date: string | null;
  work_location: string | null;
  payroll_eligible: boolean;
  notice_period_days: number | null;
  pay_currency: string | null;
  preferred_payment_method: 'bank_transfer' | 'mobile_money' | 'cash' | 'cheque';
  statutory_details: Record<string, string> | null;
  notes: string | null;
  created_at: string;
  identity_document_type: IdentityDocumentTypeValue | null;
  identity_country_code: string | null;
  identity_provider: string | null;
  identity_reference: string | null;
  identity_verification_status: IdentityVerificationStatusValue | null;
  identity_verified: boolean;
  identity_verified_at: string | null;
  identity_last_synced_at: string | null;
}

export type IdentityDocumentTypeValue = 'national_id' | 'passport' | 'other';

export type IdentityVerificationStatusValue =
  | 'not_verified'
  | 'pending'
  | 'verified'
  | 'failed'
  | 'not_found'
  | 'inactive'
  | 'expired'
  | 'provider_unavailable'
  | 'rejected'
  | 'manually_overridden'
  | 'requires_review'
  | 'manually_verified'
  | 'rate_limited';

export interface VerifiedPerson {
  first_name: string;
  middle_name: string | null;
  last_name: string;
  full_name: string;
  date_of_birth: string | null;
  gender: string | null;
  nationality: string | null;
  country_code: string;
  photo_url: string | null;
}

export interface VerifiedDocument {
  type: IdentityDocumentTypeValue;
  number_masked: string;
  status: string;
  expiry_date: string | null;
}

export interface EmployeeIdentityVerification {
  id: number;
  employee_id: number | null;
  identity_document_type: IdentityDocumentTypeValue;
  identity_number_masked: string;
  identity_country_code: string | null;
  provider: string;
  provider_reference: string | null;
  status: IdentityVerificationStatusValue;
  result_message: string | null;
  failure_reason: string | null;
  verified: boolean;
  person: VerifiedPerson | null;
  document: VerifiedDocument | null;
  requested_by?: string;
  confirmed_by?: string;
  rejected_by?: string;
  requested_at: string;
  responded_at: string | null;
  confirmed_at: string | null;
  rejected_at: string | null;
  created_at: string;
}

export interface EmployeeIdentityManualReview {
  id: number;
  employee_id: number | null;
  verification_id: number | null;
  status: 'pending' | 'approved' | 'rejected';
  reason: string;
  notes: string | null;
  reviewer_notes: string | null;
  supporting_document_type: string | null;
  supporting_document_number: string | null;
  download_url: string | null;
  submitted_by?: string;
  reviewed_by?: string;
  submitted_at: string;
  reviewed_at: string | null;
  created_at: string;
}

export interface IdentityProviderSettings {
  provider_key: string;
  provider_name: string;
  is_live: boolean;
  require_identity_verification_before_payroll: boolean;
  stats: {
    total_requests: number;
    successful_requests: number;
    failed_requests: number;
    last_successful_at: string | null;
    last_failed_at: string | null;
    average_response_seconds: number | null;
  };
}

export interface EmployeeSalary {
  id: number;
  employee_id: number;
  salary: string | null;
  pay_currency: string | null;
  preferred_payment_method: string;
  bank_name: string | null;
  bank_account_name: string | null;
  bank_account_number: string | null;
  bank_branch_name: string | null;
  mobile_money_provider: string | null;
  mobile_money_number: string | null;
  national_id_number: string | null;
}

export type EmployeeDocumentType =
  | 'employment_contract'
  | 'national_id'
  | 'passport'
  | 'academic_certificate'
  | 'professional_certificate'
  | 'driving_license'
  | 'work_permit'
  | 'medical_certificate'
  | 'tax_document'
  | 'pension_registration'
  | 'bank_information'
  | 'warning_letter'
  | 'promotion_letter'
  | 'training_certificate'
  | 'other';

export type EmployeeDocumentStatus = 'pending_verification' | 'verified' | 'rejected' | 'valid' | 'expiring_soon' | 'expired';

export interface EmployeeDocument {
  id: number;
  employee_id: number;
  document_type: EmployeeDocumentType;
  file_name: string;
  file_size: number | null;
  mime_type: string | null;
  issue_date: string | null;
  expiry_date: string | null;
  status: EmployeeDocumentStatus;
  notes: string | null;
  uploaded_by?: User;
  verified_by?: User;
  verified_at: string | null;
  version: number;
  parent_document_id: number | null;
  download_url: string;
  created_at: string;
}

export type ContractStatus = 'draft' | 'pending_approval' | 'active' | 'expired' | 'terminated' | 'renewed';
export type PayFrequency = 'monthly' | 'biweekly' | 'weekly' | 'daily' | 'hourly';

export interface EmployeeContract {
  id: number;
  employee_id: number;
  employee?: Employee;
  contract_number: string | null;
  employment_type: EmploymentType;
  effective_date: string;
  expiry_date: string | null;
  basic_salary: string;
  pay_frequency: PayFrequency;
  working_hours_per_week: number | null;
  workdays: string[] | null;
  probation_period_days: number | null;
  notice_period_days: number | null;
  benefits: string | null;
  overtime_eligible: boolean;
  commission_eligible: boolean;
  leave_entitlement_days: number | null;
  status: ContractStatus;
  created_by?: User;
  approved_by?: User;
  renewed_from_contract_id: number | null;
  notes: string | null;
  approval_request?: ApprovalRequest;
  created_at: string;
}

export interface AttendanceRecord {
  id: number;
  employee_id: number;
  employee?: Employee;
  shift_id: number | null;
  shift?: Shift;
  date: string;
  status: 'present' | 'absent' | 'late' | 'on_leave' | 'half_day';
  source: 'manual' | 'import' | 'mobile' | 'biometric' | 'gps';
  check_in: string | null;
  check_out: string | null;
  late_minutes: number | null;
  early_departure_minutes: number | null;
  is_weekend: boolean;
  is_holiday: boolean;
  approved_by?: User;
  approved_at: string | null;
  notes: string | null;
  created_at: string;
}

export interface Shift {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  break_minutes: number;
  grace_minutes: number;
  overtime_threshold_hours: string | null;
  night_allowance_amount: string | null;
  weekend_rules: Record<string, unknown> | null;
  branch_id: number | null;
  branch?: Branch;
  department_id: number | null;
  department?: Department;
  is_active: boolean;
  created_at: string;
}

export interface EmployeeShift {
  id: number;
  employee_id: number;
  employee?: Employee;
  shift_id: number;
  shift?: Shift;
  effective_date: string;
  end_date: string | null;
  created_at: string;
}

export interface Timesheet {
  id: number;
  employee_id: number;
  employee?: Employee;
  date: string;
  start_time: string | null;
  end_time: string | null;
  total_hours: string;
  overtime_hours: string;
  customer_id: number | null;
  customer?: Customer;
  shipment_id: number | null;
  clearing_file_id: number | null;
  freight_booking_id: number | null;
  department_id: number | null;
  department?: Department;
  activity: string | null;
  notes: string | null;
  status: 'pending' | 'approved' | 'rejected';
  approved_by?: User;
  created_at: string;
}

export interface LeaveType {
  id: number;
  name: string;
  is_paid: boolean;
  accrual_rule: 'none' | 'monthly' | 'annual';
  default_annual_days: number | null;
  carry_forward_max_days: number | null;
  is_active: boolean;
  created_at: string;
}

export interface LeaveBalance {
  id: number;
  employee_id: number;
  employee?: Employee;
  leave_type_id: number;
  leave_type?: LeaveType;
  year: number;
  entitled_days: string;
  used_days: string;
  carried_forward_days: string;
  available_days: number;
}

export interface LeaveRequest {
  id: number;
  employee_id: number;
  employee?: Employee;
  leave_type_id: number;
  leave_type?: LeaveType;
  start_date: string;
  end_date: string;
  days: string;
  half_day: boolean;
  reason: string | null;
  status: 'pending' | 'approved' | 'rejected' | 'cancelled';
  attachment_path: string | null;
  created_by?: User;
  approved_by?: User;
  rejection_reason: string | null;
  approval_request?: ApprovalRequest;
  created_at: string;
}

export interface PublicHoliday {
  id: number;
  date: string;
  name: string;
  branch_id: number | null;
  branch?: Branch;
  created_at: string;
}

export interface PayrollComponent {
  id: number;
  code: string;
  name: string;
  type: 'earning' | 'deduction' | 'employer_contribution';
  calculation_method: 'fixed' | 'percentage' | 'formula';
  amount: string | null;
  percentage: string | null;
  percentage_base: 'basic_salary' | 'gross_pay' | null;
  formula_notes: string | null;
  is_taxable: boolean;
  is_pensionable: boolean;
  is_recurring: boolean;
  branch_id: number | null;
  branch?: Branch;
  department_id: number | null;
  department?: Department;
  designation_category: string | null;
  effective_date: string;
  end_date: string | null;
  is_active: boolean;
  sort_order: number;
  created_at: string;
}

export interface EmployeePayrollComponent {
  id: number;
  employee_id: number;
  employee?: Employee;
  payroll_component_id: number;
  payroll_component?: PayrollComponent;
  amount: string | null;
  percentage: string | null;
  effective_date: string;
  end_date: string | null;
  is_active: boolean;
  notes: string | null;
  created_at: string;
}

export interface StatutoryTaxBand {
  id: number;
  statutory_rule_set_id: number;
  lower_bound: string;
  upper_bound: string | null;
  rate: string;
  band_order: number;
}

export interface StatutoryContributionRule {
  id: number;
  statutory_rule_set_id: number;
  code: string;
  name: string;
  employee_rate: string | null;
  employer_rate: string | null;
  min_base: string | null;
  max_base: string | null;
  is_active: boolean;
  sort_order: number;
}

export interface StatutoryRuleSet {
  id: number;
  name: string;
  country_code: string;
  description: string | null;
  is_default: boolean;
  is_active: boolean;
  tax_bands?: StatutoryTaxBand[];
  contribution_rules?: StatutoryContributionRule[];
  created_at: string;
}

export interface PayrollSettings {
  id: number;
  statutory_rule_set_id: number | null;
  statutory_rule_set?: StatutoryRuleSet;
  default_pay_frequency: string;
  overtime_multiplier: string;
  standard_working_days_per_month: number;
  standard_hours_per_day: number;
  salary_expense_account_id: number | null;
  allowance_expense_account_id: number | null;
  overtime_expense_account_id: number | null;
  bonus_expense_account_id: number | null;
  employer_contribution_expense_account_id: number | null;
  payroll_payable_account_id: number | null;
  tax_payable_account_id: number | null;
  statutory_contributions_payable_account_id: number | null;
  loan_receivable_account_id: number | null;
  advance_receivable_account_id: number | null;
  other_deductions_payable_account_id: number | null;
  bank_cash_account_id: number | null;
}

export interface LoanSchedule {
  id: number;
  installment_number: number;
  due_date: string;
  amount: string;
  status: 'pending' | 'paid' | 'skipped';
  paid_in_payroll_run_id: number | null;
}

export interface EmployeeLoan {
  id: number;
  employee_id: number;
  employee?: Employee;
  loan_number: string | null;
  principal_amount: string;
  interest_rate: string;
  number_of_installments: number;
  installment_amount: string;
  start_date: string;
  status: 'draft' | 'pending_approval' | 'active' | 'completed' | 'rejected' | 'cancelled';
  reason: string | null;
  disbursed_at: string | null;
  schedules?: LoanSchedule[];
  approval_request?: ApprovalRequest;
  created_at: string;
}

export interface SalaryAdvance {
  id: number;
  employee_id: number;
  employee?: Employee;
  advance_number: string | null;
  amount: string;
  number_of_installments: number;
  installment_amount: string;
  request_date: string;
  status: 'draft' | 'pending_approval' | 'active' | 'completed' | 'rejected' | 'cancelled';
  reason: string | null;
  disbursed_at: string | null;
  schedules?: LoanSchedule[];
  approval_request?: ApprovalRequest;
  created_at: string;
}

export interface OvertimeRequest {
  id: number;
  employee_id: number;
  employee?: Employee;
  date: string;
  hours: string;
  reason: string | null;
  status: 'pending' | 'approved' | 'rejected';
  created_at: string;
}

export interface PayrollLineItem {
  id: number;
  label: string;
  amount: string;
  source?: string;
  type?: string;
  is_taxable?: boolean;
  is_pensionable?: boolean;
  payroll_component_id: number | null;
}

export interface PayrollRunEmployee {
  id: number;
  payroll_run_id: number;
  employee_id: number;
  employee?: Employee;
  basic_salary: string;
  gross_pay: string;
  total_deductions: string;
  total_employer_contributions: string;
  net_pay: string;
  status: 'included' | 'excluded' | 'exception';
  exception_notes: string | null;
  earnings?: PayrollLineItem[];
  deductions?: PayrollLineItem[];
  employer_contributions?: PayrollLineItem[];
}

export interface PayrollRun {
  id: number;
  payroll_period_id: number;
  period?: PayrollPeriod;
  run_number: number;
  status: 'draft' | 'calculated' | 'pending_approval' | 'approved' | 'rejected' | 'finalized';
  statutory_rule_set_id: number | null;
  total_gross: string;
  total_deductions: string;
  total_net: string;
  total_employer_contributions: string;
  total_employer_cost: string;
  calculated_at: string | null;
  approved_at: string | null;
  finalized_at: string | null;
  journal_entry_id: number | null;
  posted_at: string | null;
  payslip_count?: number;
  salary_payment_batch?: SalaryPaymentBatch | null;
  employee_count?: number;
  exception_count?: number;
  run_employees?: PayrollRunEmployee[];
  latest_approval_request?: ApprovalRequest;
  notes: string | null;
  created_at: string;
}

export interface Payslip {
  id: number;
  employee_id: number;
  employee?: Employee;
  payroll_run_id: number;
  payslip_number: string | null;
  gross_pay: string;
  total_deductions: string;
  net_pay: string;
  total_employer_contributions: string;
  ytd_gross: string;
  ytd_deductions: string;
  ytd_net: string;
  period?: { name: string; period_start: string; period_end: string; payment_date: string };
  created_at: string;
}

export interface SalaryPayment {
  id: number;
  employee_id: number;
  employee?: Employee;
  amount: string;
  payment_method: string;
  bank_name: string | null;
  bank_account_number: string | null;
  mobile_money_provider: string | null;
  mobile_money_number: string | null;
  status: 'pending' | 'paid' | 'failed';
  reference: string | null;
  paid_at: string | null;
}

export interface SalaryPaymentBatch {
  id: number;
  payroll_run_id: number;
  batch_number: string | null;
  payment_date: string;
  status: 'draft' | 'exported' | 'completed';
  total_amount: string;
  payments?: SalaryPayment[];
  created_at: string;
}

export interface HrDashboardSummary {
  headcount: {
    total: number;
    by_department: Record<string, number>;
    by_status: Record<string, number>;
  };
  attendance: {
    today: Record<string, number>;
  };
  leave: {
    pending_requests: number;
  };
  expiring: {
    contracts: number;
    documents: number;
  };
  payroll: {
    last_run: {
      period_name: string | null;
      total_gross: string;
      total_deductions: string;
      total_net: string;
      total_employer_cost: string;
    } | null;
    pending_approval_runs: number;
    trend: { period_name: string; total_net: string; total_employer_cost: string }[];
  };
  loans: {
    pending_loans: number;
    pending_advances: number;
    outstanding_loan_balance: string;
  };
  recruitment: {
    open_vacancies: number;
    candidates_in_pipeline: number;
    total_candidates: number;
  };
  exits: {
    in_progress: number;
    open_disciplinary: number;
  };
}

export interface JobVacancy {
  id: number;
  title: string;
  department_id: number | null;
  department?: Department;
  designation_id: number | null;
  designation?: Designation;
  branch_id: number | null;
  description: string | null;
  requirements: string | null;
  employment_type: string | null;
  number_of_openings: number;
  status: 'open' | 'on_hold' | 'filled' | 'closed';
  posted_date: string | null;
  closing_date: string | null;
  applications_count?: number;
  created_at: string;
}

export interface Candidate {
  id: number;
  first_name: string;
  last_name: string;
  name: string;
  email: string | null;
  phone: string | null;
  source: string | null;
  has_resume: boolean;
  notes: string | null;
  created_at: string;
}

export interface Interview {
  id: number;
  job_application_id: number;
  interviewer_id: number | null;
  interviewer?: { id: number; name: string };
  scheduled_at: string;
  mode: 'in_person' | 'phone' | 'video';
  location: string | null;
  status: 'scheduled' | 'completed' | 'cancelled' | 'no_show';
  feedback: string | null;
  rating: string | null;
  created_at: string;
}

export interface JobApplication {
  id: number;
  job_vacancy_id: number;
  vacancy?: JobVacancy;
  candidate_id: number;
  candidate?: Candidate;
  applied_date: string;
  status: 'applied' | 'screening' | 'interview' | 'offer' | 'hired' | 'rejected' | 'withdrawn';
  notes: string | null;
  converted_employee_id: number | null;
  interviews?: Interview[];
  created_at: string;
}

export interface OnboardingTask {
  id: number;
  title: string;
  description: string | null;
  is_completed: boolean;
  completed_at: string | null;
  due_date: string | null;
  assigned_to: number | null;
  sort_order: number;
}

export interface OnboardingChecklist {
  id: number;
  employee_id: number;
  employee?: Employee;
  status: 'in_progress' | 'completed';
  started_at: string | null;
  completed_at: string | null;
  tasks?: OnboardingTask[];
  progress?: number;
  created_at: string;
}

export interface PerformanceReview {
  id: number;
  employee_id: number;
  employee?: Employee;
  reviewer_id: number | null;
  reviewer?: { id: number; name: string };
  review_period_start: string;
  review_period_end: string;
  review_date: string;
  overall_rating: string | null;
  kpi_scores: Record<string, number> | null;
  strengths: string | null;
  areas_for_improvement: string | null;
  goals: string | null;
  comments: string | null;
  employee_comments: string | null;
  status: 'draft' | 'submitted' | 'acknowledged';
  acknowledged_at: string | null;
  created_at: string;
}

export interface DisciplinaryRecord {
  id: number;
  employee_id: number;
  employee?: Employee;
  incident_date: string;
  category: string;
  severity: string;
  description: string;
  action_taken: string | null;
  issued_by: number | null;
  issuedBy?: { id: number; name: string };
  status: 'draft' | 'issued' | 'acknowledged' | 'appealed' | 'resolved';
  employee_response: string | null;
  resolved_at: string | null;
  created_at: string;
}

export interface EmployeeAsset {
  id: number;
  employee_id: number;
  employee?: Employee;
  asset_type: string;
  asset_name: string;
  serial_number: string | null;
  assigned_date: string;
  return_date: string | null;
  condition_at_assignment: string | null;
  condition_at_return: string | null;
  status: 'assigned' | 'returned' | 'lost' | 'damaged';
  notes: string | null;
  created_at: string;
}

export interface ExitRecord {
  id: number;
  employee_id: number;
  employee?: Employee;
  exit_type: string;
  notice_date: string;
  last_working_date: string;
  reason: string | null;
  exit_interview_notes: string | null;
  status: 'initiated' | 'in_progress' | 'cleared' | 'completed';
  assets_cleared: boolean;
  handover_completed: boolean;
  unused_leave_days: string | null;
  leave_payout_amount: string | null;
  outstanding_loan_balance: string | null;
  outstanding_advance_balance: string | null;
  final_settlement_amount: string | null;
  completed_at: string | null;
  created_at: string;
}

export interface PayrollPeriod {
  id: number;
  name: string;
  period_start: string;
  period_end: string;
  payment_date: string;
  pay_frequency: string;
  is_locked: boolean;
  latest_run?: PayrollRun | null;
  created_at: string;
}

export interface DemurrageRateTier {
  id: number;
  position: number;
  from_day: number;
  to_day: number | null;
  daily_rate: number;
}

export interface DemurrageRateCard {
  id: number;
  name: string;
  container_type: Container['container_type'] | null;
  free_days: number;
  currency: string;
  is_default: boolean;
  tiers: DemurrageRateTier[];
  created_at: string;
}

export interface DemurrageChargeBreakdownItem {
  tier: number | 'overflow';
  days: number;
  daily_rate: number;
  amount: number;
}

export interface DemurrageCharge {
  id: number;
  container_id: number;
  container?: Container;
  customer_id: number;
  customer?: Customer;
  invoice_id: number | null;
  calculated_at: string;
  dwell_days: number;
  free_days: number;
  chargeable_days: number;
  amount: string;
  currency: string;
  breakdown: DemurrageChargeBreakdownItem[];
  status: 'pending' | 'invoiced' | 'waived';
  waived_reason: string | null;
  created_at: string;
}

export interface DemurrageDashboardRow {
  container_id: number;
  container_number: string;
  container_type: Container['container_type'];
  customer_id: number;
  customer?: Customer;
  gate_in_date: string;
  rate_card_id: number | null;
  rate_card_name: string | null;
  currency: string;
  dwell_days: number;
  free_days: number;
  free_days_remaining: number;
  chargeable_days: number;
  accrued_amount: number;
  risk_level: 'within_free' | 'at_risk' | 'accruing';
}

export interface DetentionRateTier {
  id: number;
  position: number;
  from_day: number;
  to_day: number | null;
  daily_rate: number;
}

export interface DetentionRateCard {
  id: number;
  name: string;
  container_type: Container['container_type'] | null;
  free_days: number;
  currency: string;
  is_default: boolean;
  tiers: DetentionRateTier[];
  created_at: string;
}

export interface DetentionChargeBreakdownItem {
  tier: number | 'overflow';
  days: number;
  daily_rate: number;
  amount: number;
}

export interface DetentionCharge {
  id: number;
  container_id: number;
  container?: Container;
  customer_id: number;
  customer?: Customer;
  invoice_id: number | null;
  calculated_at: string;
  detention_days: number;
  free_days: number;
  chargeable_days: number;
  amount: string;
  currency: string;
  breakdown: DetentionChargeBreakdownItem[];
  status: 'pending' | 'invoiced' | 'waived';
  waived_reason: string | null;
  created_at: string;
}

export interface DetentionDashboardRow {
  container_id: number;
  container_number: string;
  container_type: Container['container_type'];
  customer_id: number;
  customer?: Customer;
  gate_out_date: string;
  rate_card_id: number | null;
  rate_card_name: string | null;
  currency: string;
  detention_days: number;
  free_days: number;
  free_days_remaining: number;
  chargeable_days: number;
  accrued_amount: number;
  risk_level: 'within_free' | 'at_risk' | 'accruing';
}

export interface Account {
  id: number;
  parent_id: number | null;
  code: string;
  name: string;
  type: 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';
  is_active: boolean;
  description: string | null;
  created_at: string;
}

export interface ExchangeRate {
  id: number;
  base_currency: string;
  quote_currency: string;
  rate: string;
  rate_date: string;
  created_by: number | null;
  creator?: User | null;
  created_at: string;
}

export interface JournalEntryLine {
  id: number;
  account_id: number;
  account?: Account;
  debit: string;
  credit: string;
  description: string | null;
}

export interface JournalEntry {
  id: number;
  entry_number: string | null;
  entry_date: string;
  description: string | null;
  reference: string | null;
  status: 'draft' | 'posted' | 'voided';
  created_by: number | null;
  created_by_user?: User | null;
  posted_at: string | null;
  lines?: JournalEntryLine[];
  total_debit?: string;
  total_credit?: string;
  created_at: string;
}

export interface QuotationItem {
  id: number;
  position: number;
  description: string;
  quantity: string;
  unit_price: string;
  amount: string;
}

export interface Quotation {
  id: number;
  customer_id: number;
  customer?: Customer;
  quotation_number: string | null;
  direction: 'import' | 'export';
  mode: 'sea' | 'air' | 'land';
  origin_port: string | null;
  destination_port: string | null;
  issue_date: string;
  valid_until: string;
  status: 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired';
  subtotal: string;
  tax_amount: string;
  total_amount: string;
  currency: string;
  notes: string | null;
  items?: QuotationItem[];
  has_shipment: boolean;
  approval_request?: ApprovalRequest | null;
  created_at: string;
}

export type TrackingEventType =
  | 'booked'
  | 'gate_in'
  | 'loaded'
  | 'departed'
  | 'in_transit'
  | 'customs_hold'
  | 'customs_cleared'
  | 'arrived'
  | 'gate_out'
  | 'out_for_delivery'
  | 'delivered'
  | 'exception';

export interface TrackingEvent {
  id: number;
  event_type: TrackingEventType;
  location: string | null;
  occurred_at: string;
  notes: string | null;
  is_customer_visible: boolean;
  recorded_by: number | null;
  recorded_by_user?: User | null;
  created_at: string;
}

export interface Shipment {
  id: number;
  customer_id: number;
  customer?: Customer;
  branch_id: number | null;
  branch?: Branch | null;
  quotation_id: number | null;
  clearing_file_id: number | null;
  freight_booking_id: number | null;
  shipment_number: string | null;
  tracking_code: string | null;
  direction: 'import' | 'export';
  mode: 'sea' | 'air' | 'land';
  origin_port: string | null;
  destination_port: string | null;
  bl_awb_number: string | null;
  status: 'booked' | 'in_transit' | 'arrived' | 'cleared' | 'delivered' | 'cancelled';
  is_at_risk: boolean;
  etd: string | null;
  eta: string | null;
  notes: string | null;
  milestones?: TrackingEvent[];
  created_at: string;
}

export interface ShipmentDelayRisk {
  risk_score: number | null;
  risk_level: 'low' | 'medium' | 'high' | 'insufficient_data';
  sample_size: number;
  basis: 'route' | 'mode_direction' | 'insufficient_data';
}

export interface ShipmentCostSummary {
  currency: string;
  revenue: { billed: number; collected: number };
  cost: { confirmed: number; pending: number };
  profit: number;
  margin_percent: number | null;
  cost_breakdown: { category: Expense['category']; amount: number }[];
  invoices: Invoice[];
  expenses: Expense[];
}

export interface ProofOfDelivery {
  id: number;
  shipment_id: number;
  received_by_name: string;
  signature_url: string;
  photo_url: string | null;
  latitude: number | null;
  longitude: number | null;
  notes: string | null;
  captured_by: number | null;
  captured_by_user?: User;
  created_at: string;
}

export interface PublicShipmentTracking {
  shipment_number: string | null;
  tracking_code: string;
  direction: 'import' | 'export';
  mode: 'sea' | 'air' | 'land';
  origin_port: string | null;
  destination_port: string | null;
  status: Shipment['status'];
  etd: string | null;
  eta: string | null;
  milestones: { event_type: TrackingEventType; location: string | null; occurred_at: string }[];
}

export interface ReportsOverview {
  branch_id: number | null;
  crm: {
    leads_total: number;
    leads_by_status: Record<string, number>;
    customers_total: number;
  };
  quotations: { total: number; by_status: Record<string, number> };
  shipments: { total: number; by_status: Record<string, number> };
  clearing: { total: number; by_status: Record<string, number> };
  freight: { total: number; by_status: Record<string, number> };
  containers: { total: number; by_status: Record<string, number> };
  warehouse: { total: number; by_status: Record<string, number> };
  fleet: { total: number; by_status: Record<string, number> };
  finance: {
    invoices_total: number;
    invoices_by_status: Record<string, number>;
    outstanding_amount: number;
    paid_amount: number;
  };
  accounting: {
    accounts_total: number;
    journal_entries_by_status: Record<string, number>;
  };
  documents: { total: number };
}

export interface ProfitReport {
  rows: {
    shipment_id: number;
    shipment_number: string | null;
    customer: string | null;
    revenue: number;
    cost: number;
    profit: number;
    margin_percent: number | null;
  }[];
  totals: { revenue: number; cost: number; profit: number };
}

export interface CustomsReport {
  total_declarations: number;
  avg_clearance_days: number | null;
  total_duty: number;
  total_vat: number;
  total_customs_value: number;
  by_customs_office: Record<string, number>;
  by_assessment_status: Record<string, number>;
}

export interface TaxReport {
  range: { from: string; to: string };
  vat_collected_by_month: Record<string, number>;
  duty_paid_by_month: Record<string, number>;
  totals: { vat_collected: number; duty_paid: number };
}

export interface FreightBooking {
  id: number;
  customer_id: number;
  customer?: Customer;
  reference_no: string | null;
  direction: 'import' | 'export';
  mode: 'sea' | 'air' | 'land';
  carrier: string | null;
  vessel_flight_no: string | null;
  booking_number: string | null;
  origin_port: string | null;
  destination_port: string | null;
  cargo_description: string | null;
  weight_kg: string | null;
  volume_cbm: string | null;
  freight_charges: string | null;
  status: 'booked' | 'cargo_received' | 'in_transit' | 'arrived' | 'delivered' | 'cancelled';
  assigned_to: number | null;
  assigned_to_user?: User | null;
  etd: string | null;
  eta: string | null;
  notes: string | null;
  created_at: string;
}

export interface Document {
  id: number;
  customer_id: number | null;
  customer?: Customer;
  shipment_id: number | null;
  shipment?: Shipment;
  category:
    | 'invoice'
    | 'bill_of_lading'
    | 'customs_declaration'
    | 'contract'
    | 'id_document'
    | 'packing_list'
    | 'certificate_of_origin'
    | 'insurance_certificate'
    | 'delivery_note'
    | 'release_order'
    | 'other';
  file_name: string;
  file_size: number;
  mime_type: string;
  is_previewable: boolean;
  url: string;
  version: number;
  parent_document_id: number | null;
  root_document_id: number | null;
  uploaded_by: number | null;
  uploaded_by_user?: User | null;
  description: string | null;
  created_at: string;
}

export interface UserNotification {
  id: number;
  type: string;
  title: string;
  message: string;
  notifiable_type: string | null;
  notifiable_id: number | null;
  read_at: string | null;
  created_at: string;
}

export interface HeroContent {
  eyebrow_text: string;
  headline: string;
  subheadline: string;
  image_url: string | null;
  primary_cta_label: string;
  primary_cta_link: string;
  secondary_cta_label: string;
  secondary_cta_link: string;
  microcopy: string;
}

export interface AboutStat {
  stat: string;
  label: string;
}

export interface AboutContent {
  heading: string;
  paragraph_1: string;
  paragraph_2: string;
  stats: AboutStat[];
}

export interface FeatureItem {
  icon_key: string;
  title: string;
  description: string;
}

export interface FeaturesContent {
  heading: string;
  subheading: string;
  items: FeatureItem[];
}

export interface IndustryItem {
  title: string;
  description: string;
}

export interface IndustriesContent {
  heading: string;
  subheading: string;
  items: IndustryItem[];
}

export interface TestimonialItem {
  quote: string;
  name: string;
  role: string;
  avatar_url: string | null;
}

export interface TestimonialsContent {
  heading: string;
  items: TestimonialItem[];
}

export interface FaqItem {
  question: string;
  answer: string;
}

export interface FaqsContent {
  heading: string;
  items: FaqItem[];
}

export interface LandingContent {
  hero: HeroContent;
  about: AboutContent;
  features: FeaturesContent;
  industries: IndustriesContent;
  testimonials: TestimonialsContent;
  faqs: FaqsContent;
}

export type LandingContentKey = keyof LandingContent;

export interface LandingContentSection<K extends LandingContentKey = LandingContentKey> {
  key: K;
  content: LandingContent[K];
  updated_at: string;
}

export interface CustomerMessage {
  id: number;
  customer_id: number;
  sender_user_id: number | null;
  sender_user?: User | null;
  is_from_customer: boolean;
  body: string;
  read_at: string | null;
  created_at: string;
}

export interface PortalDashboardSummary {
  active_shipments: number;
  outstanding_balance: number;
  unread_messages: number;
  unread_notifications: number;
}

export interface CustomerApiKey {
  id: number;
  name: string;
  key_prefix: string;
  last_used_at: string | null;
  revoked_at: string | null;
  created_at: string;
}

export interface AnalyticsMarginRow {
  shipment_number: string | null;
  quoted_amount: number;
  invoiced_amount: number;
  variance: number;
}

export interface AnalyticsTopCustomer {
  customer: string | null;
  revenue?: number;
  shipment_count?: number;
}

export interface AnalyticsOverview {
  range: { from: string; to: string };
  operational: {
    avg_transit_days_by_mode: Record<string, number>;
    avg_customs_clearance_days: number | null;
    on_time_delivery_rate: number | null;
    avg_container_dwell_days: number | null;
    fleet_utilization_percent: number | null;
  };
  financial: {
    revenue_by_month: Record<string, number>;
    ar_aging: Record<string, number>;
    margins: AnalyticsMarginRow[];
  };
  trends: {
    shipment_volume_by_month: Record<string, { total: number; import: number; export: number }>;
  };
  top_customers: {
    by_revenue: AnalyticsTopCustomer[];
    by_volume: AnalyticsTopCustomer[];
  };
}
