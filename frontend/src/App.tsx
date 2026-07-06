import { Navigate, Route, Routes } from 'react-router-dom';
import { ProtectedRoute } from './components/common/ProtectedRoute';
import { LandingPage } from './routes/public/LandingPage';
import { LoginPage } from './routes/public/LoginPage';
import { ForgotPasswordPage } from './routes/public/ForgotPasswordPage';
import { ResetPasswordPage } from './routes/public/ResetPasswordPage';
import { RegistrationWizard } from './routes/registration-wizard/RegistrationWizard';
import { TrackShipmentPage } from './routes/public/TrackShipmentPage';
import { TenantLayout } from './routes/tenant/TenantLayout';
import { DashboardHomePage } from './routes/tenant/pages/DashboardHomePage';
import { UsersPage } from './routes/tenant/pages/UsersPage';
import { BranchesPage } from './routes/tenant/pages/BranchesPage';
import { AuditLogPage as TenantAuditLogPage } from './routes/tenant/pages/AuditLogPage';
import { CompanySettingsPage } from './routes/tenant/pages/CompanySettingsPage';
import { LeadsPage } from './routes/tenant/pages/crm/LeadsPage';
import { CustomersPage } from './routes/tenant/pages/crm/CustomersPage';
import { CustomerDetailPage } from './routes/tenant/pages/crm/CustomerDetailPage';
import { ClearingFilesPage } from './routes/tenant/pages/clearing/ClearingFilesPage';
import { FreightBookingsPage } from './routes/tenant/pages/freight/FreightBookingsPage';
import { ContainersPage } from './routes/tenant/pages/containers/ContainersPage';
import { DemurrageDashboardPage } from './routes/tenant/pages/demurrage/DemurrageDashboardPage';
import { DemurrageRateCardsPage } from './routes/tenant/pages/demurrage/DemurrageRateCardsPage';
import { WarehouseItemsPage } from './routes/tenant/pages/warehouse/WarehouseItemsPage';
import { VehiclesPage } from './routes/tenant/pages/fleet/VehiclesPage';
import { InvoicesPage } from './routes/tenant/pages/finance/InvoicesPage';
import { AccountsPage } from './routes/tenant/pages/accounting/AccountsPage';
import { JournalEntriesPage } from './routes/tenant/pages/accounting/JournalEntriesPage';
import { DocumentsPage } from './routes/tenant/pages/documents/DocumentsPage';
import { ReportsPage } from './routes/tenant/pages/ReportsPage';
import { AnalyticsPage } from './routes/tenant/pages/AnalyticsPage';
import { QuotationsPage } from './routes/tenant/pages/quotations/QuotationsPage';
import { ShipmentsPage } from './routes/tenant/pages/shipments/ShipmentsPage';
import { ShipmentDetailPage } from './routes/tenant/pages/shipments/ShipmentDetailPage';
import { SuperAdminLayout } from './routes/super-admin/SuperAdminLayout';
import { TenantsListPage } from './routes/super-admin/pages/TenantsListPage';
import { PlansPage } from './routes/super-admin/pages/PlansPage';
import { LandingContentPage } from './routes/super-admin/pages/LandingContentPage';
import { MetricsPage } from './routes/super-admin/pages/MetricsPage';
import { AuditLogPage as PlatformAuditLogPage } from './routes/super-admin/pages/AuditLogPage';
import { ErrorLogsPage } from './routes/super-admin/pages/ErrorLogsPage';
import { PortalLayout } from './routes/portal/PortalLayout';
import { PortalDashboardPage } from './routes/portal/pages/PortalDashboardPage';
import { PortalShipmentsPage } from './routes/portal/pages/PortalShipmentsPage';
import { PortalShipmentDetailPage } from './routes/portal/pages/PortalShipmentDetailPage';
import { PortalInvoicesPage } from './routes/portal/pages/PortalInvoicesPage';
import { PortalQuotationsPage } from './routes/portal/pages/PortalQuotationsPage';
import { PortalDocumentsPage } from './routes/portal/pages/PortalDocumentsPage';
import { PortalMessagesPage } from './routes/portal/pages/PortalMessagesPage';

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<LandingPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route path="/forgot-password" element={<ForgotPasswordPage />} />
      <Route path="/reset-password" element={<ResetPasswordPage />} />
      <Route path="/register" element={<RegistrationWizard />} />
      <Route path="/track" element={<TrackShipmentPage />} />
      <Route path="/track/:code" element={<TrackShipmentPage />} />

      <Route element={<ProtectedRoute />}>
        <Route path="/app" element={<TenantLayout />}>
          <Route index element={<Navigate to="dashboard" replace />} />
          <Route path="dashboard" element={<DashboardHomePage />} />
          <Route path="crm" element={<LeadsPage />} />
          <Route path="crm/customers" element={<CustomersPage />} />
          <Route path="crm/customers/:id" element={<CustomerDetailPage />} />
          <Route path="quotations" element={<QuotationsPage />} />
          <Route path="shipments" element={<ShipmentsPage />} />
          <Route path="shipments/:id" element={<ShipmentDetailPage />} />
          <Route path="clearing" element={<ClearingFilesPage />} />
          <Route path="freight" element={<FreightBookingsPage />} />
          <Route path="containers" element={<ContainersPage />} />
          <Route path="demurrage" element={<DemurrageDashboardPage />} />
          <Route path="demurrage/rate-cards" element={<DemurrageRateCardsPage />} />
          <Route path="warehouse" element={<WarehouseItemsPage />} />
          <Route path="fleet" element={<VehiclesPage />} />
          <Route path="finance" element={<InvoicesPage />} />
          <Route path="accounting" element={<AccountsPage />} />
          <Route path="accounting/journal-entries" element={<JournalEntriesPage />} />
          <Route path="documents" element={<DocumentsPage />} />
          <Route path="reports" element={<ReportsPage />} />
          <Route path="analytics" element={<AnalyticsPage />} />
          <Route path="users" element={<UsersPage />} />
          <Route path="branches" element={<BranchesPage />} />
          <Route path="audit-log" element={<TenantAuditLogPage />} />
          <Route path="settings" element={<CompanySettingsPage />} />
        </Route>
      </Route>

      <Route element={<ProtectedRoute requirePortalUser />}>
        <Route path="/portal" element={<PortalLayout />}>
          <Route index element={<Navigate to="dashboard" replace />} />
          <Route path="dashboard" element={<PortalDashboardPage />} />
          <Route path="shipments" element={<PortalShipmentsPage />} />
          <Route path="shipments/:id" element={<PortalShipmentDetailPage />} />
          <Route path="invoices" element={<PortalInvoicesPage />} />
          <Route path="quotations" element={<PortalQuotationsPage />} />
          <Route path="documents" element={<PortalDocumentsPage />} />
          <Route path="messages" element={<PortalMessagesPage />} />
        </Route>
      </Route>

      <Route element={<ProtectedRoute requireSuperAdmin />}>
        <Route path="/platform" element={<SuperAdminLayout />}>
          <Route index element={<Navigate to="tenants" replace />} />
          <Route path="tenants" element={<TenantsListPage />} />
          <Route path="plans" element={<PlansPage />} />
          <Route path="landing-content" element={<LandingContentPage />} />
          <Route path="metrics" element={<MetricsPage />} />
          <Route path="audit-log" element={<PlatformAuditLogPage />} />
          <Route path="error-log" element={<ErrorLogsPage />} />
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
