import { useEffect, useState } from 'react';

export const DASHBOARD_WIDGET_KEYS = [
  'daily_shipments',
  'pending_customs',
  'active_containers',
  'outstanding_invoices',
  'revenue',
  'expenses',
  'fleet_status',
  'warehouse_status',
] as const;

export type DashboardWidgetKey = (typeof DASHBOARD_WIDGET_KEYS)[number];

interface WidgetLayout {
  order: DashboardWidgetKey[];
  hidden: DashboardWidgetKey[];
}

/**
 * A first-time (no saved preference yet) widget order per role, so each
 * role's dashboard opens with the most relevant numbers up top instead of a
 * one-size-fits-all order. Purely a starting point — moveWidget/toggleWidget
 * below still let the user rearrange it, and that choice always wins once
 * saved. Roles are the same Spatie role names already on `user.roles`.
 */
const ROLE_WIDGET_PRIORITY: Partial<Record<string, DashboardWidgetKey[]>> = {
  'Clearing Officer': ['pending_customs', 'daily_shipments', 'active_containers'],
  'Forwarding Officer': ['pending_customs', 'daily_shipments', 'active_containers'],
  'Document Controller': ['pending_customs', 'daily_shipments', 'active_containers'],
  'Warehouse Manager': ['warehouse_status', 'active_containers', 'daily_shipments'],
  'Warehouse Staff': ['warehouse_status', 'active_containers', 'daily_shipments'],
  Dispatcher: ['fleet_status', 'daily_shipments', 'active_containers'],
  'Fleet Manager': ['fleet_status', 'daily_shipments'],
  Driver: ['fleet_status', 'daily_shipments'],
  'Finance Manager': ['revenue', 'outstanding_invoices', 'expenses'],
  Accountant: ['revenue', 'outstanding_invoices', 'expenses'],
  Auditor: ['outstanding_invoices', 'expenses', 'revenue'],
  'Sales Manager': ['outstanding_invoices', 'daily_shipments', 'revenue'],
  'Customer Service': ['daily_shipments', 'outstanding_invoices'],
};

function defaultOrderForRoles(roles: string[]): DashboardWidgetKey[] {
  const priority = roles.map((role) => ROLE_WIDGET_PRIORITY[role]).find((order) => order !== undefined);
  if (!priority) return [...DASHBOARD_WIDGET_KEYS];

  const rest = DASHBOARD_WIDGET_KEYS.filter((key) => !priority.includes(key));
  return [...priority, ...rest];
}

function storageKey(userId: number | undefined): string {
  return `dashboard-widget-layout:${userId ?? 'anon'}`;
}

function loadLayout(userId: number | undefined, roles: string[]): WidgetLayout {
  try {
    const raw = localStorage.getItem(storageKey(userId));
    if (!raw) return { order: defaultOrderForRoles(roles), hidden: [] };
    const parsed = JSON.parse(raw) as WidgetLayout;

    // fold in any widget key introduced after the layout was first saved
    // (e.g. a newly granted permission) so it isn't silently hidden forever
    const known = new Set(parsed.order);
    const missing = DASHBOARD_WIDGET_KEYS.filter((key) => !known.has(key));

    return { order: [...parsed.order, ...missing], hidden: parsed.hidden ?? [] };
  } catch {
    return { order: defaultOrderForRoles(roles), hidden: [] };
  }
}

/**
 * Persists each user's dashboard widget order/visibility to localStorage —
 * a personal display preference, not tenant-wide configuration, so every
 * user customizes their own view without affecting anyone else's.
 */
export function useDashboardWidgetLayout(userId: number | undefined, roles: string[] = []) {
  const [layout, setLayout] = useState<WidgetLayout>(() => loadLayout(userId, roles));

  useEffect(() => {
    setLayout(loadLayout(userId, roles));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);

  const save = (next: WidgetLayout) => {
    setLayout(next);
    localStorage.setItem(storageKey(userId), JSON.stringify(next));
  };

  const moveWidget = (key: DashboardWidgetKey, direction: -1 | 1) => {
    const index = layout.order.indexOf(key);
    const swapWith = index + direction;
    if (swapWith < 0 || swapWith >= layout.order.length) return;

    const nextOrder = [...layout.order];
    [nextOrder[index], nextOrder[swapWith]] = [nextOrder[swapWith], nextOrder[index]];
    save({ ...layout, order: nextOrder });
  };

  const toggleWidget = (key: DashboardWidgetKey) => {
    const hidden = layout.hidden.includes(key)
      ? layout.hidden.filter((k) => k !== key)
      : [...layout.hidden, key];
    save({ ...layout, hidden });
  };

  const visibleOrder = (available: DashboardWidgetKey[]): DashboardWidgetKey[] =>
    layout.order.filter((key) => available.includes(key) && !layout.hidden.includes(key));

  return { layout, moveWidget, toggleWidget, visibleOrder };
}
