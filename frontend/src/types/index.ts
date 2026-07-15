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
  timezone: string;
  industry: string;
  logo_url: string | null;
  primary_color: string | null;
  secondary_color: string | null;
  notify_email_enabled: boolean;
  notify_sms_enabled: boolean;
  notify_whatsapp_enabled: boolean;
  phone: string | null;
  email: string | null;
  website: string | null;
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

export interface Employee {
  id: number;
  employee_number: string | null;
  department_id: number | null;
  department?: Department;
  branch_id: number | null;
  branch?: Branch;
  user_id: number | null;
  user?: User;
  name: string;
  email: string | null;
  phone: string | null;
  job_title: string | null;
  employment_type: 'full_time' | 'part_time' | 'contract' | 'intern';
  status: 'active' | 'on_leave' | 'terminated';
  hire_date: string;
  termination_date: string | null;
  salary: string | null;
  notes: string | null;
  created_at: string;
}

export interface AttendanceRecord {
  id: number;
  employee_id: number;
  employee?: Employee;
  date: string;
  status: 'present' | 'absent' | 'late' | 'on_leave' | 'half_day';
  check_in: string | null;
  check_out: string | null;
  notes: string | null;
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
