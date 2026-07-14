import {
  AppBar,
  Avatar,
  Box,
  Chip,
  Divider,
  Drawer,
  IconButton,
  List,
  ListItemButton,
  ListItemText,
  Menu,
  MenuItem,
  Stack,
  Toolbar,
  Tooltip,
  Typography,
} from '@mui/material';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import LogoutIcon from '@mui/icons-material/Logout';
import MenuIcon from '@mui/icons-material/Menu';
import SecurityIcon from '@mui/icons-material/Security';
import { useMutation } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';
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
}

interface DashboardShellProps {
  title: string;
  navItems: ShellNavItem[];
  children: ReactNode;
}

export function DashboardShell({ title, navItems, children }: DashboardShellProps) {
  const { t } = useTranslation('nav');
  const location = useLocation();
  const navigate = useNavigate();
  const { user, logout } = useAuthStore();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [mobileOpen, setMobileOpen] = useState(false);

  const logoutMutation = useMutation({
    mutationFn: apiLogout,
    onSettled: () => {
      logout();
      navigate('/login');
    },
  });

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
        {navItems.map((item) => {
          const selected = location.pathname === item.path;

          if (!item.enabled) {
            return (
              <Tooltip key={item.path} title={t('comingSoon')} placement="right">
                <span>
                  <ListItemButton disabled sx={{ borderRadius: 1, mb: 0.25 }}>
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
              sx={{ borderRadius: 1, mb: 0.25 }}
            >
              <ListItemText primary={item.label} />
            </ListItemButton>
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
