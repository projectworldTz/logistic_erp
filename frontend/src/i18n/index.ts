import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import commonEn from './locales/en/common.json';
import navEn from './locales/en/nav.json';
import authEn from './locales/en/auth.json';
import landingEn from './locales/en/landing.json';
import registrationEn from './locales/en/registration.json';
import dashboardEn from './locales/en/dashboard.json';
import crmEn from './locales/en/crm.json';
import quotationsEn from './locales/en/quotations.json';
import shipmentsEn from './locales/en/shipments.json';
import clearingEn from './locales/en/clearing.json';
import freightEn from './locales/en/freight.json';
import containersEn from './locales/en/containers.json';
import demurrageEn from './locales/en/demurrage.json';
import detentionEn from './locales/en/detention.json';
import warehouseEn from './locales/en/warehouse.json';
import fleetEn from './locales/en/fleet.json';
import financeEn from './locales/en/finance.json';
import expensesEn from './locales/en/expenses.json';
import exchangeRatesEn from './locales/en/exchangeRates.json';
import workflowsEn from './locales/en/workflows.json';
import hrEn from './locales/en/hr.json';
import accountingEn from './locales/en/accounting.json';
import documentsEn from './locales/en/documents.json';
import reportsEn from './locales/en/reports.json';
import usersEn from './locales/en/users.json';
import branchesEn from './locales/en/branches.json';
import settingsEn from './locales/en/settings.json';
import auditLogEn from './locales/en/auditLog.json';
import securityEn from './locales/en/security.json';
import superAdminEn from './locales/en/superAdmin.json';
import trackingEn from './locales/en/tracking.json';
import analyticsEn from './locales/en/analytics.json';
import portalEn from './locales/en/portal.json';

import commonSw from './locales/sw/common.json';
import navSw from './locales/sw/nav.json';
import authSw from './locales/sw/auth.json';
import landingSw from './locales/sw/landing.json';
import registrationSw from './locales/sw/registration.json';
import dashboardSw from './locales/sw/dashboard.json';
import crmSw from './locales/sw/crm.json';
import quotationsSw from './locales/sw/quotations.json';
import shipmentsSw from './locales/sw/shipments.json';
import clearingSw from './locales/sw/clearing.json';
import freightSw from './locales/sw/freight.json';
import containersSw from './locales/sw/containers.json';
import demurrageSw from './locales/sw/demurrage.json';
import detentionSw from './locales/sw/detention.json';
import warehouseSw from './locales/sw/warehouse.json';
import fleetSw from './locales/sw/fleet.json';
import financeSw from './locales/sw/finance.json';
import expensesSw from './locales/sw/expenses.json';
import exchangeRatesSw from './locales/sw/exchangeRates.json';
import workflowsSw from './locales/sw/workflows.json';
import hrSw from './locales/sw/hr.json';
import accountingSw from './locales/sw/accounting.json';
import documentsSw from './locales/sw/documents.json';
import reportsSw from './locales/sw/reports.json';
import usersSw from './locales/sw/users.json';
import branchesSw from './locales/sw/branches.json';
import settingsSw from './locales/sw/settings.json';
import auditLogSw from './locales/sw/auditLog.json';
import securitySw from './locales/sw/security.json';
import superAdminSw from './locales/sw/superAdmin.json';
import trackingSw from './locales/sw/tracking.json';
import analyticsSw from './locales/sw/analytics.json';
import portalSw from './locales/sw/portal.json';

export const STORAGE_KEY = 'app-locale';

function getInitialLanguage(): 'en' | 'sw' {
  const stored = localStorage.getItem(STORAGE_KEY);
  if (stored === 'en' || stored === 'sw') return stored;
  return 'en';
}

void i18n.use(initReactI18next).init({
  lng: getInitialLanguage(),
  fallbackLng: 'en',
  defaultNS: 'common',
  ns: [
    'common',
    'nav',
    'auth',
    'landing',
    'registration',
    'dashboard',
    'crm',
    'quotations',
    'shipments',
    'clearing',
    'freight',
    'containers',
    'demurrage',
    'detention',
    'warehouse',
    'fleet',
    'finance',
    'expenses',
    'exchangeRates',
    'workflows',
    'hr',
    'accounting',
    'documents',
    'reports',
    'users',
    'branches',
    'settings',
    'auditLog',
    'security',
    'superAdmin',
    'tracking',
    'analytics',
    'portal',
  ],
  resources: {
    en: {
      common: commonEn,
      nav: navEn,
      auth: authEn,
      landing: landingEn,
      registration: registrationEn,
      dashboard: dashboardEn,
      crm: crmEn,
      quotations: quotationsEn,
      shipments: shipmentsEn,
      clearing: clearingEn,
      freight: freightEn,
      containers: containersEn,
      demurrage: demurrageEn,
      detention: detentionEn,
      warehouse: warehouseEn,
      fleet: fleetEn,
      finance: financeEn,
      expenses: expensesEn,
      exchangeRates: exchangeRatesEn,
      workflows: workflowsEn,
      hr: hrEn,
      accounting: accountingEn,
      documents: documentsEn,
      reports: reportsEn,
      users: usersEn,
      branches: branchesEn,
      settings: settingsEn,
      auditLog: auditLogEn,
      security: securityEn,
      superAdmin: superAdminEn,
      tracking: trackingEn,
      analytics: analyticsEn,
      portal: portalEn,
    },
    sw: {
      common: commonSw,
      nav: navSw,
      auth: authSw,
      landing: landingSw,
      registration: registrationSw,
      dashboard: dashboardSw,
      crm: crmSw,
      quotations: quotationsSw,
      shipments: shipmentsSw,
      clearing: clearingSw,
      freight: freightSw,
      containers: containersSw,
      demurrage: demurrageSw,
      detention: detentionSw,
      warehouse: warehouseSw,
      fleet: fleetSw,
      finance: financeSw,
      expenses: expensesSw,
      exchangeRates: exchangeRatesSw,
      workflows: workflowsSw,
      hr: hrSw,
      accounting: accountingSw,
      documents: documentsSw,
      reports: reportsSw,
      users: usersSw,
      branches: branchesSw,
      settings: settingsSw,
      auditLog: auditLogSw,
      security: securitySw,
      superAdmin: superAdminSw,
      tracking: trackingSw,
      analytics: analyticsSw,
      portal: portalSw,
    },
  },
  interpolation: { escapeValue: false },
});

i18n.on('languageChanged', (lng) => {
  localStorage.setItem(STORAGE_KEY, lng);
});

export default i18n;
