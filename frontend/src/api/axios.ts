import axios from 'axios';
import i18n from '../i18n';
import { useAuthStore } from '../hooks/useAuth';
import { showGlobalToast } from '../hooks/useToast';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8001/api/v1',
  headers: { Accept: 'application/json' },
});

api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      useAuthStore.getState().logout();
    }

    const reference = error.response?.data?.reference;
    if (error.response?.status >= 500 && reference) {
      showGlobalToast(i18n.t('common:errors.serverError', { reference }));
    }

    return Promise.reject(error);
  },
);
