import { api } from '../axios';
import type { Paginated, UserNotification } from '../../types';

export async function fetchNotifications(page = 1): Promise<Paginated<UserNotification>> {
  const { data } = await api.get<Paginated<UserNotification>>('/notifications', { params: { page } });
  return data;
}

export async function fetchUnreadCount(): Promise<number> {
  // Polled every 30s in the background — a transient failure shouldn't
  // interrupt the user with a toast.
  const { data } = await api.get<{ count: number }>('/notifications/unread-count', { skipErrorToast: true });
  return data.count;
}

export async function markNotificationRead(id: number): Promise<void> {
  await api.post(`/notifications/${id}/read`);
}

export async function markAllNotificationsRead(): Promise<void> {
  await api.post('/notifications/read-all');
}
