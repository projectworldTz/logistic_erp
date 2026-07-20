import {
  Box,
  Dialog,
  Divider,
  IconButton,
  InputBase,
  List,
  ListItemButton,
  ListItemIcon,
  ListItemText,
  ListSubheader,
  Stack,
  Tooltip,
  Typography,
} from '@mui/material';
import SearchRoundedIcon from '@mui/icons-material/SearchRounded';
import SubdirectoryArrowRightRoundedIcon from '@mui/icons-material/SubdirectoryArrowRightRounded';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useMemo, useRef, useState, type KeyboardEvent as ReactKeyboardEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { searchAll, type SearchResultItem } from '../../api/endpoints/search';
import { useAuthStore } from '../../hooks/useAuth';
import { TENANT_NAV_GROUPS } from '../../routes/tenant/nav/navConfig';

interface PaletteEntry {
  key: string;
  label: string;
  path: string;
  group: string;
  icon?: ReactNode;
}

export function CommandPalette() {
  const { t } = useTranslation('common');
  const { t: tNav } = useTranslation('nav');
  const navigate = useNavigate();
  const permissions = useAuthStore((s) => s.user?.permissions) ?? [];

  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [selectedIndex, setSelectedIndex] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(query), 250);
    return () => clearTimeout(timer);
  }, [query]);

  useEffect(() => {
    function handleKeyDown(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setOpen((prev) => !prev);
      }
    }
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  useEffect(() => {
    if (open) {
      setQuery('');
      setDebouncedQuery('');
      setSelectedIndex(0);
      setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [open]);

  const { data: searchResults, isFetching } = useQuery({
    queryKey: ['search', debouncedQuery],
    queryFn: () => searchAll(debouncedQuery),
    enabled: open && debouncedQuery.trim().length >= 2,
  });

  const navEntries: PaletteEntry[] = useMemo(
    () =>
      TENANT_NAV_GROUPS.flatMap((group) =>
        group.items
          .filter((item) => item.enabled && (!item.permission || permissions.includes(item.permission)))
          .map((item) => ({
            key: item.path,
            label: tNav(item.labelKey),
            path: item.path,
            group: t('commandPalette.goTo'),
            icon: item.icon,
          })),
      ),
    [permissions, t, tNav],
  );

  const filteredNavEntries = useMemo(() => {
    const q = debouncedQuery.trim().toLowerCase();
    if (!q) return navEntries;
    return navEntries.filter((entry) => entry.label.toLowerCase().includes(q));
  }, [navEntries, debouncedQuery]);

  const searchEntries: PaletteEntry[] = useMemo(() => {
    if (!searchResults) return [];
    return Object.entries(searchResults)
      .filter(([, items]) => items.length > 0)
      .flatMap(([groupKey, items]) =>
        items.map((item: SearchResultItem) => ({
          key: `${groupKey}-${item.id}`,
          label: item.label,
          path: item.path,
          group: t(`search.groups.${groupKey}`, { defaultValue: groupKey }),
        })),
      );
  }, [searchResults, t]);

  const flatEntries = useMemo(() => [...filteredNavEntries, ...searchEntries], [filteredNavEntries, searchEntries]);

  useEffect(() => {
    setSelectedIndex(0);
  }, [flatEntries.length]);

  const handleClose = () => setOpen(false);

  const handleSelect = (entry: PaletteEntry) => {
    navigate(entry.path);
    handleClose();
  };

  const handleKeyDown = (e: ReactKeyboardEvent) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setSelectedIndex((prev) => (flatEntries.length ? (prev + 1) % flatEntries.length : 0));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setSelectedIndex((prev) => (flatEntries.length ? (prev - 1 + flatEntries.length) % flatEntries.length : 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const entry = flatEntries[selectedIndex];
      if (entry) handleSelect(entry);
    }
  };

  // Group consecutive entries under their section header for display.
  const sections = useMemo(() => {
    const map = new Map<string, PaletteEntry[]>();
    flatEntries.forEach((entry) => {
      const list = map.get(entry.group) ?? [];
      list.push(entry);
      map.set(entry.group, list);
    });
    return Array.from(map.entries());
  }, [flatEntries]);

  let runningIndex = -1;

  return (
    <>
      <Tooltip title={t('commandPalette.tooltip')}>
        <IconButton color="inherit" onClick={() => setOpen(true)} aria-label="Search">
          <SearchRoundedIcon />
        </IconButton>
      </Tooltip>

      <Dialog
        open={open}
        onClose={handleClose}
        fullWidth
        maxWidth="sm"
        sx={{ '& .MuiDialog-container': { alignItems: 'flex-start' }, '& .MuiPaper-root': { mt: '10vh' } }}
      >
        <Box sx={{ p: 1.5, display: 'flex', alignItems: 'center', gap: 1, borderBottom: 1, borderColor: 'divider' }}>
          <SearchRoundedIcon sx={{ color: 'text.secondary' }} />
          <InputBase
            inputRef={inputRef}
            fullWidth
            placeholder={t('commandPalette.placeholder')}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            onKeyDown={handleKeyDown}
            sx={{ fontSize: '1rem' }}
          />
        </Box>

        <Box sx={{ maxHeight: 420, overflowY: 'auto' }}>
          {debouncedQuery.trim().length === 0 && (
            <Typography variant="caption" color="text.secondary" sx={{ px: 2, py: 1, display: 'block' }}>
              {t('commandPalette.startTyping')}
            </Typography>
          )}

          {isFetching && (
            <Typography variant="body2" color="text.secondary" sx={{ px: 2, py: 2 }}>
              {t('labels.loading')}
            </Typography>
          )}

          {!isFetching && debouncedQuery.trim().length >= 2 && flatEntries.length === 0 && (
            <Typography variant="body2" color="text.secondary" sx={{ px: 2, py: 2 }}>
              {t('commandPalette.noResults', { query: debouncedQuery })}
            </Typography>
          )}

          {sections.map(([groupLabel, entries]) => (
            <List key={groupLabel} dense disablePadding subheader={<ListSubheader>{groupLabel}</ListSubheader>}>
              {entries.map((entry) => {
                runningIndex += 1;
                const isSelected = runningIndex === selectedIndex;
                return (
                  <ListItemButton
                    key={entry.key}
                    selected={isSelected}
                    onClick={() => handleSelect(entry)}
                    onMouseEnter={() => setSelectedIndex(runningIndex)}
                  >
                    <ListItemIcon sx={{ minWidth: 34 }}>
                      {entry.icon ?? <SubdirectoryArrowRightRoundedIcon fontSize="small" />}
                    </ListItemIcon>
                    <ListItemText primary={entry.label} />
                  </ListItemButton>
                );
              })}
            </List>
          ))}
        </Box>

        <Divider />
        <Stack direction="row" justifyContent="flex-end" sx={{ px: 2, py: 1 }}>
          <Typography variant="caption" color="text.secondary">
            {t('commandPalette.hint')}
          </Typography>
        </Stack>
      </Dialog>
    </>
  );
}
