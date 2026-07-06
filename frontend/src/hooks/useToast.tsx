import { Alert, Snackbar } from '@mui/material';
import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';

type ToastSeverity = 'success' | 'error' | 'info' | 'warning';

interface ToastState {
  message: string;
  severity: ToastSeverity;
  key: number;
}

interface ToastContextValue {
  showToast: (message: string, severity?: ToastSeverity) => void;
}

const ToastContext = createContext<ToastContextValue | undefined>(undefined);

// Plain module-level bridge so code outside the React tree (e.g. the axios
// response interceptor) can surface a toast without needing a hook.
let globalShowToast: ((message: string, severity?: ToastSeverity) => void) | null = null;

export function showGlobalToast(message: string, severity: ToastSeverity = 'error'): void {
  globalShowToast?.(message, severity);
}

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toast, setToast] = useState<ToastState | null>(null);

  const showToast = useCallback((message: string, severity: ToastSeverity = 'success') => {
    setToast({ message, severity, key: Date.now() });
  }, []);

  globalShowToast = showToast;

  const value = useMemo(() => ({ showToast }), [showToast]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <Snackbar
        key={toast?.key}
        open={!!toast}
        autoHideDuration={4000}
        onClose={() => setToast(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      >
        {toast ? (
          <Alert onClose={() => setToast(null)} severity={toast.severity} variant="filled" sx={{ width: '100%' }}>
            {toast.message}
          </Alert>
        ) : undefined}
      </Snackbar>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastContextValue {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error('useToast must be used within ToastProvider');
  return ctx;
}
