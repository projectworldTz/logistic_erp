import { IconButton, ListItemText, Menu, MenuItem, Tooltip } from '@mui/material';
import TranslateIcon from '@mui/icons-material/Translate';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

const LANGUAGES = [
  { code: 'en', labelKey: 'english' },
  { code: 'sw', labelKey: 'kiswahili' },
] as const;

export function LanguageSwitcher() {
  const { t, i18n } = useTranslation('nav');
  const [anchorEl, setAnchorEl] = useState<null | HTMLElement>(null);

  const handleSelect = (code: string) => {
    void i18n.changeLanguage(code);
    setAnchorEl(null);
  };

  return (
    <>
      <Tooltip title={t('language')}>
        <IconButton
          color="inherit"
          onClick={(e) => setAnchorEl(e.currentTarget)}
          aria-label="Change language"
        >
          <TranslateIcon />
        </IconButton>
      </Tooltip>
      <Menu anchorEl={anchorEl} open={!!anchorEl} onClose={() => setAnchorEl(null)}>
        {LANGUAGES.map((lang) => (
          <MenuItem
            key={lang.code}
            selected={i18n.language === lang.code}
            onClick={() => handleSelect(lang.code)}
          >
            <ListItemText>{t(lang.labelKey)}</ListItemText>
          </MenuItem>
        ))}
      </Menu>
    </>
  );
}
