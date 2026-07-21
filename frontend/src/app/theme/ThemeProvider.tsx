import { createContext, useContext, useMemo, useState, type ReactNode } from 'react';
import { CssBaseline, ThemeProvider as MuiThemeProvider, createTheme } from '@mui/material';

type ThemeMode = 'light' | 'dark';

interface ThemeModeContextValue {
  mode: ThemeMode;
  toggleMode: () => void;
}

const ThemeModeContext = createContext<ThemeModeContextValue | undefined>(undefined);

const STORAGE_KEY = 'theme-mode';

function getInitialMode(): ThemeMode {
  const stored = localStorage.getItem(STORAGE_KEY);
  if (stored === 'light' || stored === 'dark') return stored;

  // Dark is the product default regardless of OS preference — a user who
  // hasn't explicitly chosen light yet should still land on dark.
  return 'dark';
}

export function AppThemeProvider({ children }: { children: ReactNode }) {
  const [mode, setMode] = useState<ThemeMode>(getInitialMode);

  const toggleMode = () => {
    setMode((prev) => {
      const next = prev === 'light' ? 'dark' : 'light';
      localStorage.setItem(STORAGE_KEY, next);
      return next;
    });
  };

  const theme = useMemo(
    () =>
      createTheme({
        palette: {
          mode,
          primary: { main: '#1a56db' },
          secondary: { main: '#0f766e' },
          // A flat near-black (dark) or stark-white (light) neutral reads as
          // dull/unfinished — a subtle blue-slate tint in both modes keeps
          // the canvas feeling intentional and ties it to the brand blue.
          // In light mode the canvas is a touch darker than card surfaces so
          // cards read as elevated, mirroring dark mode's own relationship.
          background:
            mode === 'dark'
              ? { default: '#0f172a', paper: '#182338' }
              : { default: '#eef1f7', paper: '#ffffff' },
        },
        shape: { borderRadius: 12 },
        typography: {
          fontFamily:
            '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        },
        components: {
          MuiButton: {
            styleOverrides: {
              root: { textTransform: 'none', fontWeight: 600, borderRadius: 10 },
            },
          },
          MuiCard: {
            defaultProps: { elevation: 0 },
            styleOverrides: {
              root: ({ theme: t }) => ({
                borderRadius: 16,
                border: `1px solid ${t.palette.divider}`,
                boxShadow:
                  t.palette.mode === 'dark'
                    ? '0 1px 2px rgba(0,0,0,0.24), 0 8px 24px rgba(0,0,0,0.28)'
                    : '0 1px 2px rgba(15,23,42,0.04), 0 8px 24px rgba(15,23,42,0.06)',
              }),
            },
          },
          MuiPaper: {
            styleOverrides: {
              rounded: { borderRadius: 16 },
            },
          },
          MuiChip: {
            styleOverrides: {
              root: { borderRadius: 8, fontWeight: 600 },
            },
          },
        },
      }),
    [mode],
  );

  return (
    <ThemeModeContext.Provider value={{ mode, toggleMode }}>
      <MuiThemeProvider theme={theme}>
        <CssBaseline />
        {children}
      </MuiThemeProvider>
    </ThemeModeContext.Provider>
  );
}

export function useThemeMode(): ThemeModeContextValue {
  const ctx = useContext(ThemeModeContext);
  if (!ctx) throw new Error('useThemeMode must be used within AppThemeProvider');
  return ctx;
}
