import { Breadcrumbs as MuiBreadcrumbs, Link, Typography } from '@mui/material';
import HomeRoundedIcon from '@mui/icons-material/HomeRounded';
import NavigateNextRoundedIcon from '@mui/icons-material/NavigateNextRounded';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useLocation } from 'react-router-dom';
import type { AppShellNavGroup } from './AppShell';

interface Crumb {
  label: string;
  path?: string;
}

interface BreadcrumbsProps {
  navGroups: AppShellNavGroup[];
  /** Label for the first crumb, e.g. the tenant/company name or a static shell title. */
  homeLabel: string;
  /** Path the first crumb links to, e.g. "/app/dashboard". */
  homePath: string;
}

function humanizeSegment(segment: string): string {
  return segment
    .split('-')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

/**
 * Derives a breadcrumb trail purely from the current path and the shell's
 * own nav groups — no extra data fetching, so record-specific segments
 * (ids) fall back to a generic "Details" label rather than the entity's name.
 */
export function Breadcrumbs({ navGroups, homeLabel, homePath }: BreadcrumbsProps) {
  const { t } = useTranslation('nav');
  const { pathname } = useLocation();

  const flatItems = navGroups.flatMap((group) => group.items);
  const matched = flatItems
    .filter((item) => pathname === item.path || pathname.startsWith(`${item.path}/`))
    .sort((a, b) => b.path.length - a.path.length)[0];

  const crumbs: Crumb[] = [];

  if (!matched || matched.path === homePath) {
    crumbs.push({ label: homeLabel });
  } else {
    crumbs.push({ label: homeLabel, path: homePath });
    crumbs.push({ label: matched.label, path: pathname === matched.path ? undefined : matched.path });

    const rest = pathname.slice(matched.path.length).split('/').filter(Boolean);
    rest.forEach((segment, index) => {
      const isLast = index === rest.length - 1;
      crumbs.push({
        label: /^\d+$/.test(segment) ? t('breadcrumbs.details') : humanizeSegment(segment),
        path: isLast ? undefined : `${matched.path}/${rest.slice(0, index + 1).join('/')}`,
      });
    });
  }

  return (
    <MuiBreadcrumbs
      separator={<NavigateNextRoundedIcon fontSize="small" sx={{ color: 'text.disabled' }} />}
      sx={{ mb: { xs: 2, md: 3 } }}
    >
      {crumbs.map((crumb, index) =>
        crumb.path ? (
          <Link
            key={`${crumb.path}-${index}`}
            component={RouterLink}
            to={crumb.path}
            underline="hover"
            color="text.secondary"
            variant="body2"
            sx={{ display: 'inline-flex', alignItems: 'center', gap: 0.5 }}
          >
            {index === 0 && <HomeRoundedIcon fontSize="inherit" />}
            {crumb.label}
          </Link>
        ) : (
          <Typography key={`current-${index}`} variant="body2" color="text.primary" fontWeight={600}>
            {crumb.label}
          </Typography>
        ),
      )}
    </MuiBreadcrumbs>
  );
}
