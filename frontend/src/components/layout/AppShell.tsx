import {
  AppBar,
  Avatar,
  Box,
  Button,
  Chip,
  ClickAwayListener,
  Collapse,
  Divider,
  Drawer,
  Grow,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Menu,
  MenuItem,
  Paper,
  Popper,
  Stack,
  Toolbar,
  Typography,
} from '@mui/material';
import CloseRoundedIcon from '@mui/icons-material/CloseRounded';
import ExpandMoreRoundedIcon from '@mui/icons-material/ExpandMoreRounded';
import ExpandLessRoundedIcon from '@mui/icons-material/ExpandLessRounded';
import LocalShippingRoundedIcon from '@mui/icons-material/LocalShippingRounded';
import LogoutRoundedIcon from '@mui/icons-material/LogoutRounded';
import MenuRoundedIcon from '@mui/icons-material/MenuRounded';
import SecurityRoundedIcon from '@mui/icons-material/SecurityRounded';
import { useQuery, useMutation } from '@tanstack/react-query';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useLocation, useNavigate } from 'react-router-dom';
import { logout as apiLogout } from '../../api/endpoints/auth';
import { fetchCompany } from '../../api/endpoints/dashboard';
import { useAuthStore } from '../../hooks/useAuth';
import { Breadcrumbs } from './Breadcrumbs';
import { CommandPalette } from './CommandPalette';
import { LanguageSwitcher } from './LanguageSwitcher';
import { NotificationBell } from './NotificationBell';
import { ThemeToggle } from './ThemeToggle';

export interface AppShellNavItem {
  label: string;
  path: string;
  enabled: boolean;
  icon?: ReactNode;
  description?: string;
}

export interface AppShellNavGroup {
  /** Omit for a top-level, ungrouped item (e.g. Dashboard) — rendered as a plain link, no mega-menu. */
  label?: string;
  icon?: ReactNode;
  items: AppShellNavItem[];
}

interface AppShellProps {
  title: string;
  navGroups: AppShellNavGroup[];
  /** Route the logo/title and the first breadcrumb link to, e.g. "/app/dashboard". */
  homePath: string;
  /** Ctrl+K search — tenant-only (its nav list is tenant-specific), off by default for other shells. */
  showCommandPalette?: boolean;
  /** Notification bell — shown for tenant staff and portal customers, hidden for super admins. */
  showNotifications?: boolean;
  /** Fetch and display the tenant's own logo/name — off for the tenant-less platform shell. */
  showCompanyBranding?: boolean;
  children: ReactNode;
}

function isPathActive(pathname: string, itemPath: string): boolean {
  return pathname === itemPath || pathname.startsWith(`${itemPath}/`);
}

function NavMegaMenu({ group }: { group: AppShellNavGroup }) {
  const { t } = useTranslation('nav');
  const { pathname } = useLocation();
  const anchorRef = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const closeTimer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  const isGroupActive = group.items.some((item) => isPathActive(pathname, item.path));

  const cancelClose = () => {
    if (closeTimer.current) clearTimeout(closeTimer.current);
  };
  const scheduleClose = () => {
    cancelClose();
    closeTimer.current = setTimeout(() => setOpen(false), 200);
  };

  return (
    <Box onMouseLeave={scheduleClose}>
      <Button
        ref={anchorRef}
        onClick={() => setOpen((prev) => !prev)}
        onMouseEnter={() => {
          cancelClose();
          setOpen(true);
        }}
        endIcon={
          <ExpandMoreRoundedIcon
            fontSize="small"
            sx={{ transition: 'transform 0.15s ease', transform: open ? 'rotate(180deg)' : 'none' }}
          />
        }
        sx={{
          color: isGroupActive ? 'primary.main' : 'text.primary',
          bgcolor: isGroupActive ? 'action.selected' : 'transparent',
          borderRadius: 2.5,
          px: 1.5,
          '&:hover': { bgcolor: 'action.hover' },
        }}
      >
        {group.label}
      </Button>

      <Popper
        open={open}
        anchorEl={anchorRef.current}
        placement="bottom-start"
        transition
        sx={{ zIndex: (theme) => theme.zIndex.appBar + 10 }}
        modifiers={[{ name: 'offset', options: { offset: [0, 10] } }]}
      >
        {({ TransitionProps }) => (
          <ClickAwayListener onClickAway={() => setOpen(false)}>
            <Grow {...TransitionProps} timeout={150} style={{ transformOrigin: '0 0 0' }}>
              <Paper
                onMouseEnter={cancelClose}
                onMouseLeave={scheduleClose}
                sx={{
                  width: 560,
                  maxWidth: '92vw',
                  p: 1.5,
                  display: 'grid',
                  gridTemplateColumns: '1fr 1fr',
                  gap: 0.5,
                  boxShadow: (theme) =>
                    theme.palette.mode === 'dark'
                      ? '0 16px 40px rgba(0,0,0,0.45)'
                      : '0 16px 40px rgba(15,23,42,0.16)',
                }}
              >
                {group.items.map((item) => {
                  const active = isPathActive(pathname, item.path);
                  const inner = (
                    <Stack
                      direction="row"
                      spacing={1.5}
                      sx={{
                        p: 1.25,
                        borderRadius: 2,
                        height: '100%',
                        bgcolor: active ? 'action.selected' : 'transparent',
                        '&:hover': item.enabled ? { bgcolor: 'action.hover' } : undefined,
                      }}
                    >
                      <Box sx={{ color: active ? 'primary.main' : 'text.secondary', mt: 0.25, display: 'flex' }}>
                        {item.icon}
                      </Box>
                      <Stack spacing={0.25} sx={{ minWidth: 0 }}>
                        <Stack direction="row" spacing={0.75} alignItems="center">
                          <Typography
                            variant="body2"
                            fontWeight={600}
                            color={active ? 'primary.main' : 'text.primary'}
                            noWrap
                          >
                            {item.label}
                          </Typography>
                          {!item.enabled && <Chip label={t('soon')} size="small" variant="outlined" />}
                        </Stack>
                        {item.description && (
                          <Typography variant="caption" color="text.secondary" sx={{ lineHeight: 1.35 }}>
                            {item.description}
                          </Typography>
                        )}
                      </Stack>
                    </Stack>
                  );

                  return item.enabled ? (
                    <Box
                      key={item.path}
                      component={RouterLink}
                      to={item.path}
                      aria-label={item.label}
                      onClick={() => setOpen(false)}
                      sx={{ textDecoration: 'none', display: 'block' }}
                    >
                      {inner}
                    </Box>
                  ) : (
                    <Box key={item.path} sx={{ opacity: 0.55, cursor: 'not-allowed' }}>
                      {inner}
                    </Box>
                  );
                })}
              </Paper>
            </Grow>
          </ClickAwayListener>
        )}
      </Popper>
    </Box>
  );
}

function MobileNavDrawer({
  open,
  onClose,
  navGroups,
}: {
  open: boolean;
  onClose: () => void;
  navGroups: AppShellNavGroup[];
}) {
  const { t } = useTranslation('nav');
  const { pathname } = useLocation();
  const [openGroups, setOpenGroups] = useState<Set<string>>(new Set());

  useEffect(() => {
    const active = navGroups.find((g) => g.label && g.items.some((item) => isPathActive(pathname, item.path)));
    if (active?.label) setOpenGroups((prev) => new Set(prev).add(active.label!));
  }, [pathname, navGroups]);

  const toggleGroup = (label: string) => {
    setOpenGroups((prev) => {
      const next = new Set(prev);
      if (next.has(label)) {
        next.delete(label);
      } else {
        next.add(label);
      }
      return next;
    });
  };

  return (
    <Drawer anchor="top" open={open} onClose={onClose} ModalProps={{ keepMounted: true }} className="no-print">
      <Box sx={{ pt: 1, pb: 2, maxHeight: '85vh', overflowY: 'auto' }}>
        <Stack direction="row" justifyContent="flex-end" sx={{ px: 1 }}>
          <IconButton onClick={onClose} aria-label={t('closeMenu')}>
            <CloseRoundedIcon />
          </IconButton>
        </Stack>
        <List sx={{ px: 1 }}>
          {navGroups.map((group, groupIndex) => {
            if (!group.label) {
              return (
                <Box key={`ungrouped-${groupIndex}`}>
                  {group.items.map((item) => (
                    <ListItemButton
                      key={item.path}
                      component={RouterLink}
                      to={item.path}
                      onClick={onClose}
                      selected={isPathActive(pathname, item.path)}
                      sx={{ borderRadius: 1.5, mb: 0.25 }}
                    >
                      {item.icon && <ListItemIcon sx={{ minWidth: 34 }}>{item.icon}</ListItemIcon>}
                      <ListItemText primary={item.label} />
                    </ListItemButton>
                  ))}
                </Box>
              );
            }

            const isOpen = openGroups.has(group.label);
            return (
              <Box key={group.label} sx={{ mt: 0.5 }}>
                <ListItemButton onClick={() => toggleGroup(group.label!)} sx={{ borderRadius: 1.5 }}>
                  {group.icon && <ListItemIcon sx={{ minWidth: 34 }}>{group.icon}</ListItemIcon>}
                  <ListItemText
                    primary={group.label}
                    slotProps={{
                      primary: { sx: { fontSize: '0.72rem', fontWeight: 700, letterSpacing: 0.6, textTransform: 'uppercase', color: 'text.secondary' } },
                    }}
                  />
                  {isOpen ? <ExpandLessRoundedIcon fontSize="small" /> : <ExpandMoreRoundedIcon fontSize="small" />}
                </ListItemButton>
                <Collapse in={isOpen} timeout={150} unmountOnExit>
                  {group.items.map((item) => (
                    <ListItemButton
                      key={item.path}
                      component={RouterLink}
                      to={item.path}
                      onClick={onClose}
                      selected={isPathActive(pathname, item.path)}
                      sx={{ borderRadius: 1.5, mb: 0.25, pl: 3.5 }}
                    >
                      {item.icon && <ListItemIcon sx={{ minWidth: 34 }}>{item.icon}</ListItemIcon>}
                      <ListItemText primary={item.label} />
                    </ListItemButton>
                  ))}
                </Collapse>
              </Box>
            );
          })}
        </List>
      </Box>
    </Drawer>
  );
}

export function AppShell({
  title,
  navGroups,
  homePath,
  showCommandPalette = true,
  showNotifications = true,
  showCompanyBranding = true,
  children,
}: AppShellProps) {
  const { t } = useTranslation('nav');
  const { pathname } = useLocation();
  const navigate = useNavigate();
  const { user, logout } = useAuthStore();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [mobileOpen, setMobileOpen] = useState(false);
  const canManageOwnSecurity = !user?.is_super_admin && !user?.customer_id;

  const { data: company } = useQuery({
    queryKey: ['tenant', 'company'],
    queryFn: fetchCompany,
    enabled: showCompanyBranding,
    retry: false,
  });

  const logoutMutation = useMutation({
    mutationFn: apiLogout,
    onSettled: () => {
      logout();
      navigate('/login');
    },
  });

  return (
    <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
      <AppBar
        position="sticky"
        color="inherit"
        elevation={0}
        className="no-print"
        sx={{
          borderBottom: 1,
          borderColor: 'divider',
          backdropFilter: 'blur(10px)',
          bgcolor: (theme) => (theme.palette.mode === 'dark' ? 'rgba(15,23,42,0.85)' : 'rgba(255,255,255,0.85)'),
        }}
      >
        <Toolbar sx={{ gap: 1 }}>
          <IconButton
            onClick={() => setMobileOpen(true)}
            aria-label={t('openMenu')}
            sx={{ display: { xs: 'inline-flex', md: 'none' } }}
          >
            <MenuRoundedIcon />
          </IconButton>

          <Stack direction="row" spacing={1} alignItems="center" sx={{ mr: 2, flexShrink: 0 }}>
            {company?.logo_url ? (
              <Box
                component="img"
                src={company.logo_url}
                alt={title}
                sx={{ height: 28, width: 28, objectFit: 'contain', borderRadius: 1 }}
              />
            ) : (
              <LocalShippingRoundedIcon color="primary" />
            )}
            <Typography variant="subtitle1" fontWeight={800} noWrap sx={{ display: { xs: 'none', sm: 'block' } }}>
              {company?.name || title}
            </Typography>
          </Stack>

          <Stack direction="row" spacing={0.25} sx={{ display: { xs: 'none', md: 'flex' }, flexGrow: 1 }}>
            {navGroups.map((group, index) =>
              group.label ? (
                <NavMegaMenu key={group.label} group={group} />
              ) : (
                <Box key={`ungrouped-${index}`}>
                  {group.items.map((item) => (
                    <Button
                      key={item.path}
                      component={RouterLink}
                      to={item.path}
                      sx={{
                        color: isPathActive(pathname, item.path) ? 'primary.main' : 'text.primary',
                        bgcolor: isPathActive(pathname, item.path) ? 'action.selected' : 'transparent',
                        borderRadius: 2.5,
                        px: 1.5,
                        '&:hover': { bgcolor: 'action.hover' },
                      }}
                    >
                      {item.label}
                    </Button>
                  ))}
                </Box>
              ),
            )}
          </Stack>

          <Box sx={{ flexGrow: { xs: 1, md: 0 } }} />

          <Stack direction="row" spacing={{ xs: 0, sm: 0.5 }} alignItems="center">
            {showCommandPalette && <CommandPalette />}
            <LanguageSwitcher />
            <ThemeToggle />
            {showNotifications && <NotificationBell />}
            <IconButton onClick={(e) => setAnchorEl(e.currentTarget)} sx={{ ml: 0.5 }}>
              <Avatar sx={{ width: 32, height: 32 }}>{user?.name.charAt(0)}</Avatar>
            </IconButton>
            <Menu anchorEl={anchorEl} open={!!anchorEl} onClose={() => setAnchorEl(null)}>
              <MenuItem disabled>{user?.email}</MenuItem>
              <Divider />
              {canManageOwnSecurity && (
                <MenuItem component={RouterLink} to="/app/security" onClick={() => setAnchorEl(null)}>
                  <SecurityRoundedIcon fontSize="small" sx={{ mr: 1 }} />
                  {t('accountSecurity')}
                </MenuItem>
              )}
              <MenuItem onClick={() => logoutMutation.mutate()}>
                <LogoutRoundedIcon fontSize="small" sx={{ mr: 1 }} />
                {t('logOut')}
              </MenuItem>
            </Menu>
          </Stack>
        </Toolbar>
      </AppBar>

      <MobileNavDrawer open={mobileOpen} onClose={() => setMobileOpen(false)} navGroups={navGroups} />

      <Box component="main" sx={{ flexGrow: 1, p: { xs: 2, md: 4 }, minWidth: 0 }}>
        <Breadcrumbs navGroups={navGroups} homeLabel={company?.name || title} homePath={homePath} />
        {children}
      </Box>
    </Box>
  );
}
