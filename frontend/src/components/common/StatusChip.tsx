import { Chip, type ChipProps } from '@mui/material';
import type { ReactElement } from 'react';
import CheckCircleRoundedIcon from '@mui/icons-material/CheckCircleRounded';
import ErrorRoundedIcon from '@mui/icons-material/ErrorRounded';
import ScheduleRoundedIcon from '@mui/icons-material/ScheduleRounded';
import WarningAmberRoundedIcon from '@mui/icons-material/WarningAmberRounded';
import { useTranslation } from 'react-i18next';

type StatusCategory = 'neutral' | 'warning' | 'success' | 'error';

/**
 * Every status value used across the ERP is bucketed into one of four
 * categories so color/icon/wording stay consistent everywhere a status
 * appears, instead of each page inventing its own scheme.
 */
const STATUS_CATEGORY: Record<string, StatusCategory> = {
  new: 'neutral',
  active: 'neutral',
  draft: 'neutral',
  open: 'neutral',
  booked: 'neutral',
  in_transit: 'neutral',
  submitted_to_customs: 'neutral',
  under_assessment: 'neutral',
  pending: 'warning',
  in_progress: 'warning',
  awaiting_documents: 'warning',
  awaiting_payment: 'warning',
  awaiting_approval: 'warning',
  expiring_soon: 'warning',
  partially_paid: 'warning',
  released: 'success',
  delivered: 'success',
  completed: 'success',
  valid: 'success',
  paid: 'success',
  cleared: 'success',
  arrived: 'success',
  approved: 'success',
  closed: 'success',
  cancelled: 'error',
  inactive: 'error',
  expired: 'error',
  overdue: 'error',
  rejected: 'error',
  // Domain-specific vocab from individual modules (shipments/containers/
  // freight/quotations/expenses/attendance) mapped onto the same 4 buckets.
  sent: 'neutral',
  cargo_received: 'neutral',
  at_port: 'neutral',
  at_warehouse: 'neutral',
  on_leave: 'neutral',
  submitted: 'warning',
  half_day: 'warning',
  late: 'warning',
  documents_received: 'warning',
  under_clearance: 'warning',
  assessed: 'neutral',
  customs_hold: 'error',
  objected: 'error',
  accepted: 'success',
  returned: 'success',
  empty_return: 'success',
  present: 'success',
  absent: 'error',
  // HR & Payroll vocab (employees/contracts/documents)
  pending_verification: 'warning',
  pending_approval: 'warning',
  probation: 'warning',
  terminated: 'error',
  suspended: 'error',
  renewed: 'success',
  // Demurrage/detention charge lifecycle
  invoiced: 'success',
  waived: 'neutral',
};

const CATEGORY_STYLE: Record<StatusCategory, { color: ChipProps['color']; icon: ReactElement }> = {
  neutral: { color: 'info', icon: <ScheduleRoundedIcon /> },
  warning: { color: 'warning', icon: <WarningAmberRoundedIcon /> },
  success: { color: 'success', icon: <CheckCircleRoundedIcon /> },
  error: { color: 'error', icon: <ErrorRoundedIcon /> },
};

function toCamelCase(status: string): string {
  const parts = status.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').split('_').filter(Boolean);
  return parts.map((word, i) => (i === 0 ? word : word.charAt(0).toUpperCase() + word.slice(1))).join('');
}

function humanize(status: string): string {
  return status
    .trim()
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .replace(/^./, (c) => c.toUpperCase());
}

export interface StatusChipProps {
  /** Raw status value as stored/returned by the API, e.g. "in_transit", "Awaiting Payment". Drives color/icon. */
  status: string;
  /**
   * Pre-translated label to display instead of StatusChip's own wording —
   * pass the calling page's existing `t('statuses.xxx')` result so its
   * module-specific i18n (including languages beyond what StatusChip covers
   * itself) keeps being used verbatim. `status` still decides color/icon.
   */
  label?: string;
  size?: ChipProps['size'];
}

/** Renders any ERP status value with a consistent color, icon and label. */
export function StatusChip({ status, label, size = 'small' }: StatusChipProps) {
  const { t } = useTranslation('common');
  const key = status.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
  const category = STATUS_CATEGORY[key] ?? 'neutral';
  const { color, icon } = CATEGORY_STYLE[category];
  const resolvedLabel = label ?? t(`statuses.${toCamelCase(status)}`, { defaultValue: humanize(status) });

  return <Chip size={size} color={color} icon={icon} label={resolvedLabel} variant="filled" />;
}
