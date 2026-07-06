import {
  Box,
  CircularProgress,
  IconButton,
  List,
  ListItemButton,
  ListItemText,
  ListSubheader,
  Popover,
  TextField,
  Tooltip,
  Typography,
} from '@mui/material';
import SearchIcon from '@mui/icons-material/Search';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { searchAll } from '../../api/endpoints/search';

export function GlobalSearch() {
  const { t } = useTranslation('common');
  const navigate = useNavigate();
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const open = Boolean(anchorEl);

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(query), 300);
    return () => clearTimeout(timer);
  }, [query]);

  const { data: results, isFetching } = useQuery({
    queryKey: ['search', debouncedQuery],
    queryFn: () => searchAll(debouncedQuery),
    enabled: open && debouncedQuery.trim().length >= 2,
  });

  const handleClose = () => {
    setAnchorEl(null);
    setQuery('');
    setDebouncedQuery('');
  };

  const handleSelect = (path: string) => {
    navigate(path);
    handleClose();
  };

  const groups = results ? Object.entries(results).filter(([, items]) => items.length > 0) : [];

  return (
    <>
      <Tooltip title={t('search.tooltip')}>
        <IconButton color="inherit" onClick={(e) => setAnchorEl(e.currentTarget)} aria-label="Search">
          <SearchIcon />
        </IconButton>
      </Tooltip>

      <Popover
        open={open}
        anchorEl={anchorEl}
        onClose={handleClose}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
      >
        <Box sx={{ width: 380, maxHeight: 480, overflowY: 'auto' }}>
          <Box sx={{ p: 1.5 }}>
            <TextField
              autoFocus
              fullWidth
              size="small"
              placeholder={t('search.placeholder')}
              value={query}
              onChange={(e) => setQuery(e.target.value)}
            />
          </Box>

          {isFetching && (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 2 }}>
              <CircularProgress size={22} />
            </Box>
          )}

          {!isFetching && debouncedQuery.trim().length >= 2 && groups.length === 0 && (
            <Typography variant="body2" color="text.secondary" sx={{ px: 2, py: 2, textAlign: 'center' }}>
              {t('search.noResultsFor', { query: debouncedQuery })}
            </Typography>
          )}

          {!isFetching && debouncedQuery.trim().length > 0 && debouncedQuery.trim().length < 2 && (
            <Typography variant="body2" color="text.secondary" sx={{ px: 2, py: 2, textAlign: 'center' }}>
              {t('search.keepTyping')}
            </Typography>
          )}

          {groups.length > 0 && (
            <List dense disablePadding>
              {groups.map(([groupKey, items]) => (
                <li key={groupKey}>
                  <ul style={{ padding: 0 }}>
                    <ListSubheader>{t(`search.groups.${groupKey}`, { defaultValue: groupKey })}</ListSubheader>
                    {items.map((item) => (
                      <ListItemButton key={item.id} onClick={() => handleSelect(item.path)}>
                        <ListItemText primary={item.label} />
                      </ListItemButton>
                    ))}
                  </ul>
                </li>
              ))}
            </List>
          )}
        </Box>
      </Popover>
    </>
  );
}
