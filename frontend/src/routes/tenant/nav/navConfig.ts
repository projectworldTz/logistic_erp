export interface NavItem {
  labelKey: string;
  path: string;
  enabled: boolean;
  /** Required permission to show this nav item. Omit if every tenant user should see it. */
  permission?: string;
}

export const TENANT_NAV_ITEMS: NavItem[] = [
  { labelKey: 'dashboard', path: '/app/dashboard', enabled: true },
  { labelKey: 'crm', path: '/app/crm', enabled: true, permission: 'crm.customers.view' },
  { labelKey: 'quotations', path: '/app/quotations', enabled: true, permission: 'quotations.items.view' },
  { labelKey: 'shipments', path: '/app/shipments', enabled: true, permission: 'shipments.items.view' },
  { labelKey: 'clearing', path: '/app/clearing', enabled: true, permission: 'clearing.files.view' },
  { labelKey: 'freight', path: '/app/freight', enabled: true, permission: 'freight.bookings.view' },
  { labelKey: 'containers', path: '/app/containers', enabled: true, permission: 'containers.items.view' },
  { labelKey: 'demurrage', path: '/app/demurrage', enabled: true, permission: 'demurrage.charges.view' },
  { labelKey: 'warehouse', path: '/app/warehouse', enabled: true, permission: 'warehouse.items.view' },
  { labelKey: 'fleet', path: '/app/fleet', enabled: true, permission: 'fleet.vehicles.view' },
  { labelKey: 'finance', path: '/app/finance', enabled: true, permission: 'finance.invoices.view' },
  { labelKey: 'accounting', path: '/app/accounting', enabled: true, permission: 'accounting.accounts.view' },
  { labelKey: 'documents', path: '/app/documents', enabled: true, permission: 'documents.files.view' },
  { labelKey: 'reports', path: '/app/reports', enabled: true, permission: 'reports.view' },
  { labelKey: 'analytics', path: '/app/analytics', enabled: true, permission: 'analytics.view' },
  { labelKey: 'users', path: '/app/users', enabled: true, permission: 'core.users.view' },
  { labelKey: 'branches', path: '/app/branches', enabled: true, permission: 'core.branches.view' },
  { labelKey: 'auditLog', path: '/app/audit-log', enabled: true, permission: 'core.audit.view' },
  { labelKey: 'companySettings', path: '/app/settings', enabled: true, permission: 'core.company.view' },
];
