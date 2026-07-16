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

const DEFAULT_LAYOUT: WidgetLayout = {
  order: [...DASHBOARD_WIDGET_KEYS],
  hidden: [],
};

function storageKey(userId: number | undefined): string {
  return `dashboard-widget-layout:${userId ?? 'anon'}`;
}

function loadLayout(userId: number | undefined): WidgetLayout {
  try {
    const raw = localStorage.getItem(storageKey(userId));
    if (!raw) return DEFAULT_LAYOUT;
    const parsed = JSON.parse(raw) as WidgetLayout;

    // fold in any widget key introduced after the layout was first saved
    // (e.g. a newly granted permission) so it isn't silently hidden forever
    const known = new Set(parsed.order);
    const missing = DASHBOARD_WIDGET_KEYS.filter((key) => !known.has(key));

    return { order: [...parsed.order, ...missing], hidden: parsed.hidden ?? [] };
  } catch {
    return DEFAULT_LAYOUT;
  }
}

/**
 * Persists each user's dashboard widget order/visibility to localStorage —
 * a personal display preference, not tenant-wide configuration, so every
 * user customizes their own view without affecting anyone else's.
 */
export function useDashboardWidgetLayout(userId: number | undefined) {
  const [layout, setLayout] = useState<WidgetLayout>(() => loadLayout(userId));

  useEffect(() => {
    setLayout(loadLayout(userId));
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
