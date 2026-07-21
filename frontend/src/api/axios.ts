import axios, { type AxiosError } from 'axios';
import i18n from '../i18n';
import { useAuthStore } from '../hooks/useAuth';
import { showGlobalToast } from '../hooks/useToast';

// Lets specific call sites opt out of the blanket error toast below — for
// background/silent polling (notification bell, system health) where a
// transient failure shouldn't interrupt the user, or for requests whose
// page already renders its own inline error UI (login, registration).
declare module 'axios' {
  export interface AxiosRequestConfig {
    skipErrorToast?: boolean;
  }
}

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

interface ApiErrorBody {
  message?: string;
  errors?: Record<string, string[]>;
  reference?: string;
}

function extractErrorMessage(error: AxiosError<ApiErrorBody>): string {
  const data = error.response?.data;

  if (data?.message) {
    return data.message;
  }

  const firstFieldErrors = data?.errors ? Object.values(data.errors)[0] : undefined;
  if (Array.isArray(firstFieldErrors) && typeof firstFieldErrors[0] === 'string') {
    return firstFieldErrors[0];
  }

  return i18n.t('common:errors.genericFailure');
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => {
    if (error.response?.status === 401) {
      const wasLoggedIn = !!useAuthStore.getState().token;
      useAuthStore.getState().logout();
      if (wasLoggedIn && !error.config?.skipErrorToast) {
        showGlobalToast(i18n.t('common:errors.sessionExpired'));
      }
      return Promise.reject(error);
    }

    if (error.config?.skipErrorToast) {
      return Promise.reject(error);
    }

    if (!error.response) {
      showGlobalToast(i18n.t('common:errors.networkError'));
    } else if (error.response.status >= 500) {
      const reference = error.response.data?.reference;
      showGlobalToast(reference ? i18n.t('common:errors.serverError', { reference }) : i18n.t('common:errors.genericFailure'));
    } else {
      showGlobalToast(extractErrorMessage(error));
    }

    return Promise.reject(error);
  },
);
