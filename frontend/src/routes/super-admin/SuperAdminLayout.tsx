import ApartmentIcon from '@mui/icons-material/Apartment';
import BugReportIcon from '@mui/icons-material/BugReport';
import CardMembershipIcon from '@mui/icons-material/CardMembership';
import DomainIcon from '@mui/icons-material/Domain';
import HistoryIcon from '@mui/icons-material/History';
import MonitorHeartIcon from '@mui/icons-material/MonitorHeart';
import QueryStatsIcon from '@mui/icons-material/QueryStats';
import WebIcon from '@mui/icons-material/Web';
import { Outlet } from 'react-router-dom';
import { DashboardShell, type ShellNavGroup } from '../../components/layout/DashboardShell';

const iconProps = { fontSize: 'small' } as const;

const PLATFORM_NAV_GROUPS: ShellNavGroup[] = [
  {
    label: 'Platform',
    icon: <DomainIcon {...iconProps} />,
    items: [
      { label: 'Tenants', path: '/platform/tenants', enabled: true, icon: <ApartmentIcon {...iconProps} /> },
      { label: 'Plans', path: '/platform/plans', enabled: true, icon: <CardMembershipIcon {...iconProps} /> },
      { label: 'Landing Content', path: '/platform/landing-content', enabled: true, icon: <WebIcon {...iconProps} /> },
    ],
  },
  {
    label: 'Monitoring',
    icon: <QueryStatsIcon {...iconProps} />,
    items: [
      { label: 'Metrics', path: '/platform/metrics', enabled: true, icon: <QueryStatsIcon {...iconProps} /> },
      { label: 'System Health', path: '/platform/system-health', enabled: true, icon: <MonitorHeartIcon {...iconProps} /> },
      { label: 'Audit Log', path: '/platform/audit-log', enabled: true, icon: <HistoryIcon {...iconProps} /> },
      { label: 'Error Log', path: '/platform/error-log', enabled: true, icon: <BugReportIcon {...iconProps} /> },
    ],
  },
];

export function SuperAdminLayout() {
  return (
    <DashboardShell title="Platform Admin" navItems={PLATFORM_NAV_GROUPS}>
      <Outlet />
    </DashboardShell>
  );
}
