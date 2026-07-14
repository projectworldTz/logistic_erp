import type { ReactNode } from 'react';
import DashboardIcon from '@mui/icons-material/Dashboard';
import PeopleAltIcon from '@mui/icons-material/PeopleAlt';
import PeopleIcon from '@mui/icons-material/People';
import RequestQuoteIcon from '@mui/icons-material/RequestQuote';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import FactCheckIcon from '@mui/icons-material/FactCheck';
import FlightTakeoffIcon from '@mui/icons-material/FlightTakeoff';
import Inventory2Icon from '@mui/icons-material/Inventory2';
import TimerIcon from '@mui/icons-material/Timer';
import WarehouseIcon from '@mui/icons-material/Warehouse';
import DirectionsCarFilledIcon from '@mui/icons-material/DirectionsCarFilled';
import DescriptionIcon from '@mui/icons-material/Description';
import AccountBalanceWalletIcon from '@mui/icons-material/AccountBalanceWallet';
import ReceiptLongIcon from '@mui/icons-material/ReceiptLong';
import PaymentsIcon from '@mui/icons-material/Payments';
import AccountBalanceIcon from '@mui/icons-material/AccountBalance';
import GroupsIcon from '@mui/icons-material/Groups';
import BadgeIcon from '@mui/icons-material/Badge';
import ManageAccountsIcon from '@mui/icons-material/ManageAccounts';
import InsightsIcon from '@mui/icons-material/Insights';
import SummarizeIcon from '@mui/icons-material/Summarize';
import BarChartIcon from '@mui/icons-material/BarChart';
import SettingsIcon from '@mui/icons-material/Settings';
import AccountTreeIcon from '@mui/icons-material/AccountTree';
import RuleIcon from '@mui/icons-material/Rule';
import HistoryIcon from '@mui/icons-material/History';
import VpnKeyIcon from '@mui/icons-material/VpnKey';
import BusinessIcon from '@mui/icons-material/Business';

export interface NavItem {
  labelKey: string;
  path: string;
  enabled: boolean;
  icon: ReactNode;
  /** Required permission to show this nav item. Omit if every tenant user should see it. */
  permission?: string;
}

export interface NavGroup {
  /** Omit for a top-level, ungrouped item (e.g. Dashboard) — no collapsible header. */
  labelKey?: string;
  icon?: ReactNode;
  items: NavItem[];
}

const iconProps = { fontSize: 'small' } as const;

export const TENANT_NAV_GROUPS: NavGroup[] = [
  {
    items: [{ labelKey: 'dashboard', path: '/app/dashboard', enabled: true, icon: <DashboardIcon {...iconProps} /> }],
  },
  {
    labelKey: 'groups.sales',
    icon: <PeopleAltIcon {...iconProps} />,
    items: [
      { labelKey: 'crm', path: '/app/crm', enabled: true, permission: 'crm.customers.view', icon: <PeopleIcon {...iconProps} /> },
      { labelKey: 'quotations', path: '/app/quotations', enabled: true, permission: 'quotations.items.view', icon: <RequestQuoteIcon {...iconProps} /> },
    ],
  },
  {
    labelKey: 'groups.operations',
    icon: <LocalShippingIcon {...iconProps} />,
    items: [
      { labelKey: 'shipments', path: '/app/shipments', enabled: true, permission: 'shipments.items.view', icon: <LocalShippingIcon {...iconProps} /> },
      { labelKey: 'clearing', path: '/app/clearing', enabled: true, permission: 'clearing.files.view', icon: <FactCheckIcon {...iconProps} /> },
      { labelKey: 'freight', path: '/app/freight', enabled: true, permission: 'freight.bookings.view', icon: <FlightTakeoffIcon {...iconProps} /> },
      { labelKey: 'containers', path: '/app/containers', enabled: true, permission: 'containers.items.view', icon: <Inventory2Icon {...iconProps} /> },
      { labelKey: 'demurrage', path: '/app/demurrage', enabled: true, permission: 'demurrage.charges.view', icon: <TimerIcon {...iconProps} /> },
      { labelKey: 'warehouse', path: '/app/warehouse', enabled: true, permission: 'warehouse.items.view', icon: <WarehouseIcon {...iconProps} /> },
      { labelKey: 'fleet', path: '/app/fleet', enabled: true, permission: 'fleet.vehicles.view', icon: <DirectionsCarFilledIcon {...iconProps} /> },
      { labelKey: 'documents', path: '/app/documents', enabled: true, permission: 'documents.files.view', icon: <DescriptionIcon {...iconProps} /> },
    ],
  },
  {
    labelKey: 'groups.finance',
    icon: <AccountBalanceWalletIcon {...iconProps} />,
    items: [
      { labelKey: 'finance', path: '/app/finance', enabled: true, permission: 'finance.invoices.view', icon: <ReceiptLongIcon {...iconProps} /> },
      { labelKey: 'expenses', path: '/app/expenses', enabled: true, permission: 'expenses.items.view', icon: <PaymentsIcon {...iconProps} /> },
      { labelKey: 'accounting', path: '/app/accounting', enabled: true, permission: 'accounting.accounts.view', icon: <AccountBalanceIcon {...iconProps} /> },
    ],
  },
  {
    labelKey: 'groups.people',
    icon: <GroupsIcon {...iconProps} />,
    items: [
      { labelKey: 'hr', path: '/app/hr', enabled: true, permission: 'hr.employees.view', icon: <BadgeIcon {...iconProps} /> },
      { labelKey: 'users', path: '/app/users', enabled: true, permission: 'core.users.view', icon: <ManageAccountsIcon {...iconProps} /> },
    ],
  },
  {
    labelKey: 'groups.insights',
    icon: <InsightsIcon {...iconProps} />,
    items: [
      { labelKey: 'reports', path: '/app/reports', enabled: true, permission: 'reports.view', icon: <SummarizeIcon {...iconProps} /> },
      { labelKey: 'analytics', path: '/app/analytics', enabled: true, permission: 'analytics.view', icon: <BarChartIcon {...iconProps} /> },
    ],
  },
  {
    labelKey: 'groups.administration',
    icon: <SettingsIcon {...iconProps} />,
    items: [
      { labelKey: 'branches', path: '/app/branches', enabled: true, permission: 'core.branches.view', icon: <AccountTreeIcon {...iconProps} /> },
      { labelKey: 'workflows', path: '/app/workflows', enabled: true, permission: 'workflows.definitions.view', icon: <RuleIcon {...iconProps} /> },
      { labelKey: 'auditLog', path: '/app/audit-log', enabled: true, permission: 'core.audit.view', icon: <HistoryIcon {...iconProps} /> },
      { labelKey: 'loginHistory', path: '/app/login-history', enabled: true, permission: 'core.audit.view', icon: <VpnKeyIcon {...iconProps} /> },
      { labelKey: 'companySettings', path: '/app/settings', enabled: true, permission: 'core.company.view', icon: <BusinessIcon {...iconProps} /> },
    ],
  },
];
