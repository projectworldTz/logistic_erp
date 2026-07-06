import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, type RenderOptions } from '@testing-library/react';
import type { ReactElement, ReactNode } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { AppThemeProvider } from '../app/theme/ThemeProvider';
import { ToastProvider } from '../hooks/useToast';

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false, refetchOnWindowFocus: false },
    },
  });
}

interface WrapperProps {
  children: ReactNode;
  initialEntries?: string[];
}

function AllProviders({ children, initialEntries = ['/'] }: WrapperProps) {
  const queryClient = createTestQueryClient();

  return (
    <QueryClientProvider client={queryClient}>
      <AppThemeProvider>
        <ToastProvider>
          <MemoryRouter initialEntries={initialEntries}>{children}</MemoryRouter>
        </ToastProvider>
      </AppThemeProvider>
    </QueryClientProvider>
  );
}

function customRender(
  ui: ReactElement,
  options?: Omit<RenderOptions, 'wrapper'> & { initialEntries?: string[] },
) {
  const { initialEntries, ...renderOptions } = options ?? {};

  return render(ui, {
    wrapper: ({ children }) => <AllProviders initialEntries={initialEntries}>{children}</AllProviders>,
    ...renderOptions,
  });
}

export * from '@testing-library/react';
export { customRender as render };
