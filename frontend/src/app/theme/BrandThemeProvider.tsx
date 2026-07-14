import { ThemeProvider as MuiThemeProvider, createTheme, type Theme } from '@mui/material';
import { useQuery } from '@tanstack/react-query';
import type { ReactNode } from 'react';
import { fetchCompany } from '../../api/endpoints/dashboard';

/**
 * Extends the app's base theme with the current tenant's brand colors, so
 * staff and portal pages reflect the company's own palette (white-label)
 * instead of the product default. Falls back to the base theme untouched
 * when no brand colors are set yet.
 */
export function BrandThemeProvider({ children }: { children: ReactNode }) {
  const { data: company } = useQuery({ queryKey: ['tenant', 'company'], queryFn: fetchCompany, retry: false });

  return (
    <MuiThemeProvider
      theme={(outerTheme: Theme) =>
        createTheme(outerTheme, {
          palette: {
            ...(company?.primary_color ? { primary: { main: company.primary_color } } : {}),
            ...(company?.secondary_color ? { secondary: { main: company.secondary_color } } : {}),
          },
        })
      }
    >
      {children}
    </MuiThemeProvider>
  );
}
