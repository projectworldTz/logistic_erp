import {
  Badge,
  Box,
  Button,
  CircularProgress,
  Divider,
  IconButton,
  List,
  ListItemButton,
  ListItemText,
  Popover,
  Stack,
  Tooltip,
  Typography,
} from '@mui/material';
import NotificationsIcon from '@mui/icons-material/Notifications';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  fetchNotifications,
  fetchUnreadCount,
  markAllNotificationsRead,
  markNotificationRead,
} from '../../api/endpoints/notifications';

export function NotificationBell() {
  const { t } = useTranslation('common');
  const queryClient = useQueryClient();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const open = Boolean(anchorEl);

  const { data: unreadCount } = useQuery({
    queryKey: ['notifications', 'unread-count'],
    queryFn: fetchUnreadCount,
    refetchInterval: 30000,
  });

  const { data: notifications, isLoading } = useQuery({
    queryKey: ['notifications', 'list'],
    queryFn: () => fetchNotifications(),
    enabled: open,
  });

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['notifications', 'unread-count'] });
    queryClient.invalidateQueries({ queryKey: ['notifications', 'list'] });
  };

  const readMutation = useMutation({ mutationFn: markNotificationRead, onSuccess: invalidate });
  const readAllMutation = useMutation({ mutationFn: markAllNotificationsRead, onSuccess: invalidate });

  return (
    <>
      <Tooltip title={t('notifications.tooltip')}>
        <IconButton color="inherit" onClick={(e) => setAnchorEl(e.currentTarget)} aria-label="Notifications">
          <Badge badgeContent={unreadCount ?? 0} color="error">
            <NotificationsIcon />
          </Badge>
        </IconButton>
      </Tooltip>

      <Popover
        open={open}
        anchorEl={anchorEl}
        onClose={() => setAnchorEl(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
      >
        <Box sx={{ width: 360, maxHeight: 480, overflowY: 'auto' }}>
          <Stack direction="row" justifyContent="space-between" alignItems="center" sx={{ px: 2, py: 1.5 }}>
            <Typography variant="subtitle1" fontWeight={700}>
              {t('notifications.title')}
            </Typography>
            <Button size="small" onClick={() => readAllMutation.mutate()} disabled={!unreadCount}>
              {t('notifications.markAllRead')}
            </Button>
          </Stack>
          <Divider />

          {isLoading && (
            <Stack alignItems="center" sx={{ py: 3 }}>
              <CircularProgress size={24} />
            </Stack>
          )}

          {notifications && notifications.data.length === 0 && (
            <Typography variant="body2" color="text.secondary" sx={{ px: 2, py: 3, textAlign: 'center' }}>
              {t('notifications.caughtUp')}
            </Typography>
          )}

          {notifications && notifications.data.length > 0 && (
            <List disablePadding>
              {notifications.data.map((notification) => (
                <ListItemButton
                  key={notification.id}
                  onClick={() => !notification.read_at && readMutation.mutate(notification.id)}
                  sx={{ alignItems: 'flex-start', bgcolor: notification.read_at ? 'transparent' : 'action.hover' }}
                >
                  <ListItemText
                    primary={notification.title}
                    secondary={
                      <>
                        {notification.message}
                        <Typography component="span" variant="caption" color="text.secondary" display="block">
                          {new Date(notification.created_at).toLocaleString()}
                        </Typography>
                      </>
                    }
                    slotProps={{
                      primary: { fontWeight: notification.read_at ? 400 : 700 },
                    }}
                  />
                </ListItemButton>
              ))}
            </List>
          )}
        </Box>
      </Popover>
    </>
  );
}
