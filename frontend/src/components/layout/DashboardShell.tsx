import {
  AppBar,
  Avatar,
  Box,
  Chip,
  Collapse,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  Menu,
  MenuItem,
  Stack,
  Toolbar,
  Tooltip,
  Typography,
} from '@mui/material';
import ExpandLessIcon from '@mui/icons-material/ExpandLess';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import LogoutIcon from '@mui/icons-material/Logout';
import MenuIcon from '@mui/icons-material/Menu';
import SecurityIcon from '@mui/icons-material/Security';
import { useMutation } from '@tanstack/react-query';
import { useEffect, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink, useLocation, useNavigate } from 'react-router-dom';
import { logout as apiLogout } from '../../api/endpoints/auth';
import { useAuthStore } from '../../hooks/useAuth';
import { GlobalSearch } from './GlobalSearch';
import { LanguageSwitcher } from './LanguageSwitcher';
import { NotificationBell } from './NotificationBell';
import { ThemeToggle } from './ThemeToggle';

const DRAWER_WIDTH = 260;

export interface ShellNavItem {
  label: string;
  path: string;
  enabled: boolean;
  icon?: ReactNode;
}

export interface ShellNavGroup {
  /** Omit for a top-level, ungrouped item list (no collapsible header). */
  label?: string;
  icon?: ReactNode;
  items: ShellNavItem[];
}

interface DashboardShellProps {
  title: string;
  navItems: ShellNavGroup[];
  children: ReactNode;
}

export function DashboardShell({ title, navItems, children }: DashboardShellProps) {
  const { t } = useTranslation('nav');
  const location = useLocation();
  const navigate = useNavigate();
  const { user, logout } = useAuthStore();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [mobileOpen, setMobileOpen] = useState(false);
  // Every group starts collapsed; the group containing the active page is
  // opened automatically (below) so the current location is never hidden.
  const [openGroups, setOpenGroups] = useState<Set<string>>(new Set());

  // Whenever the route changes, make sure the group containing it is expanded
  // — this only ever adds to the open set, so a manual collapse elsewhere isn't fought.
  useEffect(() => {
    const activeGroup = navItems.find((g) => g.label && g.items.some((item) => item.path === location.pathname));
    if (activeGroup?.label) {
      setOpenGroups((prev) => (prev.has(activeGroup.label!) ? prev : new Set(prev).add(activeGroup.label!)));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [location.pathname]);

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

  const logoutMutation = useMutation({
    mutationFn: apiLogout,
    onSettled: () => {
      logout();
      navigate('/login');
    },
  });

  const renderItem = (item: ShellNavItem, nested: boolean) => {
    const selected = location.pathname === item.path;

    if (!item.enabled) {
      return (
        <Tooltip key={item.path} title={t('comingSoon')} placement="right">
          <span>
            <ListItemButton disabled sx={{ borderRadius: 1.5, mb: 0.25, pl: nested ? 3.5 : 2 }}>
              {item.icon && <ListItemIcon sx={{ minWidth: 34 }}>{item.icon}</ListItemIcon>}
              <ListItemText primary={item.label} />
              <Chip label={t('soon')} size="small" variant="outlined" />
            </ListItemButton>
          </span>
        </Tooltip>
      );
    }

    return (
      <ListItemButton
        key={item.path}
        component={RouterLink}
        to={item.path}
        selected={selected}
        onClick={() => setMobileOpen(false)}
        sx={{
          borderRadius: 1.5,
          mb: 0.25,
          pl: nested ? 3.5 : 2,
          transition: 'background-color 0.15s ease, transform 0.15s ease, color 0.15s ease',
          '&:hover': { transform: 'translateX(3px)' },
          ...(selected && {
            bgcolor: (theme) =>
              theme.palette.mode === 'dark' ? 'rgba(59, 130, 246, 0.16)' : 'rgba(26, 86, 219, 0.1)',
            borderLeft: 3,
            borderColor: 'primary.main',
            pl: nested ? 2.75 : 1.75,
            '& .MuiListItemIcon-root': { color: 'primary.main' },
            '& .MuiListItemText-primary': { color: 'primary.main', fontWeight: 700 },
            '&:hover': {
              bgcolor: (theme) =>
                theme.palette.mode === 'dark' ? 'rgba(59, 130, 246, 0.22)' : 'rgba(26, 86, 219, 0.16)',
            },
          }),
        }}
      >
        {item.icon && <ListItemIcon sx={{ minWidth: 34 }}>{item.icon}</ListItemIcon>}
        <ListItemText primary={item.label} />
      </ListItemButton>
    );
  };

  const drawerContent = (
    <>
      <Toolbar>
        <Stack direction="row" spacing={1} alignItems="center">
          <LocalShippingIcon color="primary" />
          <Typography variant="subtitle1" fontWeight={700} noWrap>
            {title}
          </Typography>
        </Stack>
      </Toolbar>
      <Divider />
      <List sx={{ px: 1, py: 1 }}>
        {navItems.map((group, groupIndex) => {
          if (!group.label) {
            return <Box key={`group-${groupIndex}`}>{group.items.map((item) => renderItem(item, false))}</Box>;
          }

          const isOpen = openGroups.has(group.label);
          const hasActiveChild = group.items.some((item) => item.path === location.pathname);

          return (
            <Box key={group.label} sx={{ mt: 0.5 }}>
              <ListItemButton
                onClick={() => toggleGroup(group.label!)}
                sx={{
                  borderRadius: 1.5,
                  mb: 0.25,
                  transition: 'background-color 0.15s ease',
                  ...(hasActiveChild && {
                    '& .MuiListItemIcon-root': { color: 'primary.main' },
                  }),
                }}
              >
                {group.icon && <ListItemIcon sx={{ minWidth: 34 }}>{group.icon}</ListItemIcon>}
                <ListItemText
                  primary={group.label}
                  slotProps={{
                    primary: {
                      sx: {
                        fontSize: '0.72rem',
                        fontWeight: 700,
                        letterSpacing: 0.6,
                        textTransform: 'uppercase',
                        color: 'text.secondary',
                      },
                    },
                  }}
                />
                <Box
                  sx={{
                    display: 'flex',
                    transition: 'transform 0.2s ease',
                    transform: isOpen ? 'rotate(0deg)' : 'rotate(-90deg)',
                  }}
                >
                  {isOpen ? (
                    <ExpandLessIcon fontSize="small" sx={{ color: 'text.secondary' }} />
                  ) : (
                    <ExpandMoreIcon fontSize="small" sx={{ color: 'text.secondary' }} />
                  )}
                </Box>
              </ListItemButton>
              <Collapse in={isOpen} timeout={200} unmountOnExit>
                <Box
                  sx={{
                    bgcolor: (theme) =>
                      theme.palette.mode === 'dark' ? 'rgba(255, 255, 255, 0.035)' : 'rgba(15, 23, 42, 0.035)',
                    borderRadius: 1.5,
                    py: 0.5,
                    mb: 0.5,
                  }}
                >
                  <List component="div" disablePadding>
                    {group.items.map((item) => renderItem(item, true))}
                  </List>
                </Box>
              </Collapse>
            </Box>
          );
        })}
      </List>
    </>
  );

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh', overflowX: 'hidden' }}>
      <Drawer
        variant="temporary"
        open={mobileOpen}
        onClose={() => setMobileOpen(false)}
        ModalProps={{ keepMounted: true }}
        className="no-print"
        sx={{
          display: { xs: 'block', md: 'none' },
          [`& .MuiDrawer-paper`]: { width: DRAWER_WIDTH, boxSizing: 'border-box' },
        }}
      >
        {drawerContent}
      </Drawer>

      <Drawer
        variant="permanent"
        className="no-print"
        sx={{
          display: { xs: 'none', md: 'block' },
          width: DRAWER_WIDTH,
          flexShrink: 0,
          [`& .MuiDrawer-paper`]: { width: DRAWER_WIDTH, boxSizing: 'border-box' },
        }}
      >
        {drawerContent}
      </Drawer>

      <Box sx={{ flexGrow: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <AppBar
          position="sticky"
          color="inherit"
          elevation={0}
          className="no-print"
          sx={{ borderBottom: 1, borderColor: 'divider' }}
        >
          <Toolbar sx={{ justifyContent: 'space-between' }}>
            <Stack direction="row" spacing={1} alignItems="center" sx={{ display: { xs: 'flex', md: 'none' } }}>
              <IconButton onClick={() => setMobileOpen(true)} aria-label={t('openMenu')}>
                <MenuIcon />
              </IconButton>
              <Typography variant="subtitle1" fontWeight={700} noWrap sx={{ display: { xs: 'none', sm: 'block' } }}>
                {title}
              </Typography>
            </Stack>

            <Stack direction="row" spacing={{ xs: 0, sm: 1 }} alignItems="center" sx={{ ml: 'auto' }}>
              {!user?.is_super_admin && !user?.customer_id && <GlobalSearch />}
              <LanguageSwitcher />
              <ThemeToggle />
              {!user?.is_super_admin && <NotificationBell />}
              <IconButton onClick={(e) => setAnchorEl(e.currentTarget)}>
                <Avatar sx={{ width: 32, height: 32 }}>{user?.name.charAt(0)}</Avatar>
              </IconButton>
              <Menu anchorEl={anchorEl} open={!!anchorEl} onClose={() => setAnchorEl(null)}>
                <MenuItem disabled>{user?.email}</MenuItem>
                <Divider />
                {!user?.is_super_admin && !user?.customer_id && (
                  <MenuItem
                    component={RouterLink}
                    to="/app/security"
                    onClick={() => setAnchorEl(null)}
                  >
                    <SecurityIcon fontSize="small" sx={{ mr: 1 }} />
                    {t('accountSecurity')}
                  </MenuItem>
                )}
                <MenuItem onClick={() => logoutMutation.mutate()}>
                  <LogoutIcon fontSize="small" sx={{ mr: 1 }} />
                  {t('logOut')}
                </MenuItem>
              </Menu>
            </Stack>
          </Toolbar>
        </AppBar>

        <Box component="main" sx={{ flexGrow: 1, p: { xs: 2, md: 4 }, minWidth: 0 }}>
          {children}
        </Box>
      </Box>
    </Box>
  );
}
