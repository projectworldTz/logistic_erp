import { AppBar, Box, Button, Stack, Toolbar, Typography } from '@mui/material';
import LocalShippingIcon from '@mui/icons-material/LocalShipping';
import { useTranslation } from 'react-i18next';
import { Link as RouterLink } from 'react-router-dom';
import { LanguageSwitcher } from './LanguageSwitcher';
import { ThemeToggle } from './ThemeToggle';

export function PublicNavbar() {
  const { t } = useTranslation('landing');

  const NAV_LINKS = [
    { label: t('nav.features'), href: '#features' },
    { label: t('nav.pricing'), href: '#pricing' },
    { label: t('nav.industries'), href: '#industries' },
    { label: t('nav.about'), href: '#about' },
    { label: t('nav.faq'), href: '#faq' },
    { label: t('nav.contact'), href: '#contact' },
  ];

  return (
    <AppBar position="sticky" color="transparent" elevation={0} sx={{ backdropFilter: 'blur(8px)', borderBottom: 1, borderColor: 'divider', bgcolor: 'background.default' }}>
      <Toolbar sx={{ maxWidth: 1200, width: '100%', mx: 'auto' }}>
        <Stack direction="row" alignItems="center" spacing={1} sx={{ flexGrow: 1 }}>
          <LocalShippingIcon color="primary" />
          <Typography variant="h6" fontWeight={700}>
            {t('brand')}
          </Typography>
        </Stack>

        <Stack direction="row" spacing={3} alignItems="center" sx={{ display: { xs: 'none', md: 'flex' }, mr: 3 }}>
          {NAV_LINKS.map((link) => (
            <Typography
              key={link.href}
              component="a"
              href={link.href}
              variant="body2"
              sx={{ color: 'text.primary', textDecoration: 'none', '&:hover': { color: 'primary.main' } }}
            >
              {link.label}
            </Typography>
          ))}
        </Stack>

        <Stack direction="row" spacing={1.5} alignItems="center">
          <LanguageSwitcher />
          <ThemeToggle />
          <Button component={RouterLink} to="/login" color="inherit">
            {t('nav.logIn')}
          </Button>
          <Button component={RouterLink} to="/register" variant="contained">
            {t('nav.startFreeTrial')}
          </Button>
        </Stack>
      </Toolbar>
      <Box />
    </AppBar>
  );
}
