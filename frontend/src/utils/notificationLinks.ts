import type { UserNotification } from '../types';

/**
 * Maps a notification's `type` (a stable "module.event" string set at the
 * point of creation, e.g. "quotation.created") to where clicking it should
 * take the user. Deep-links to a record's own detail page only for the
 * handful of modules that actually have one (Customer, Shipment, Employee,
 * PayrollRun) — everything else lands on the relevant module's list page,
 * since that's genuinely where the record lives (most modules here are
 * list+dialog, not list+detail-route).
 */
/**
 * @param basePath '/app' for staff, '/portal' for customer portal users —
 * portal users only ever receive `shipment.*` notifications today, but the
 * prefix is threaded through generically rather than special-cased.
 */
export function resolveNotificationLink(
  notification: Pick<UserNotification, 'type' | 'notifiable_id'>,
  basePath: '/app' | '/portal' = '/app',
): string | null {
  const module = notification.type.split('.')[0];
  const id = notification.notifiable_id;

  if (basePath === '/portal') {
    switch (module) {
      case 'shipment':
      case 'tracking_event':
        return id ? `/portal/shipments/${id}` : '/portal/shipments';
      case 'invoice':
        return '/portal/invoices';
      case 'quotation':
        return '/portal/quotations';
      case 'document':
        return '/portal/documents';
      case 'customer_message':
        return '/portal/messages';
      default:
        return null;
    }
  }

  switch (module) {
    case 'customer':
      return id ? `/app/crm/customers/${id}` : '/app/crm';
    case 'lead':
    case 'customer_message':
      return '/app/crm';
    case 'quotation':
      return '/app/quotations';
    case 'shipment':
    case 'tracking_event':
      return id ? `/app/shipments/${id}` : '/app/shipments';
    case 'clearing_file':
      return '/app/clearing';
    case 'freight_booking':
      return '/app/freight';
    case 'container':
      return '/app/containers';
    case 'demurrage_charge':
      return '/app/demurrage';
    case 'warehouse_item':
      return '/app/warehouse';
    case 'vehicle':
      return '/app/fleet';
    case 'invoice':
      return '/app/finance';
    case 'expense':
      return '/app/expenses';
    case 'journal_entry':
      return '/app/accounting';
    case 'document':
      return '/app/documents';
    case 'employee':
      return id ? `/app/hr/employees/${id}` : '/app/hr/employees';
    case 'employee_contract':
      return '/app/hr/contracts';
    case 'employee_document':
      return '/app/hr/employees';
    case 'attendance':
      return '/app/hr/attendance';
    case 'leave_request':
      return '/app/hr/leave';
    case 'employee_loan':
      return '/app/hr/loans-advances';
    case 'salary_advance':
      return '/app/hr/loans-advances';
    case 'overtime_request':
      return '/app/hr/overtime-requests';
    case 'payroll_period':
      return '/app/hr/payroll-periods';
    case 'exit_record':
      return '/app/hr/exit-records';
    default:
      return null;
  }
}
