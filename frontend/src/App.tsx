import { Navigate, Route, Routes } from 'react-router-dom';
import { ProtectedRoute } from './components/common/ProtectedRoute';
import { LandingPage } from './routes/public/LandingPage';
import { LoginPage } from './routes/public/LoginPage';
import { ForgotPasswordPage } from './routes/public/ForgotPasswordPage';
import { ResetPasswordPage } from './routes/public/ResetPasswordPage';
import { RegistrationWizard } from './routes/registration-wizard/RegistrationWizard';
import { TrackShipmentPage } from './routes/public/TrackShipmentPage';
import { VerifyReleaseOrderPage } from './routes/public/VerifyReleaseOrderPage';
import { VerifyDeliveryNotePage } from './routes/public/VerifyDeliveryNotePage';
import { VerifyPayslipPage } from './routes/public/VerifyPayslipPage';
import { TenantLayout } from './routes/tenant/TenantLayout';
import { DashboardHomePage } from './routes/tenant/pages/DashboardHomePage';
import { UsersPage } from './routes/tenant/pages/UsersPage';
import { BranchesPage } from './routes/tenant/pages/BranchesPage';
import { AuditLogPage as TenantAuditLogPage } from './routes/tenant/pages/AuditLogPage';
import { CompanySettingsPage } from './routes/tenant/pages/CompanySettingsPage';
import { BackupRestorePage } from './routes/tenant/pages/BackupRestorePage';
import { SubscriptionBillingPage } from './routes/tenant/pages/SubscriptionBillingPage';
import { AccountSecurityPage } from './routes/tenant/pages/AccountSecurityPage';
import { LoginHistoryPage } from './routes/tenant/pages/LoginHistoryPage';
import { LeadsPage } from './routes/tenant/pages/crm/LeadsPage';
import { CustomersPage } from './routes/tenant/pages/crm/CustomersPage';
import { CustomerDetailPage } from './routes/tenant/pages/crm/CustomerDetailPage';
import { ClearingFilesPage } from './routes/tenant/pages/clearing/ClearingFilesPage';
import { FreightBookingsPage } from './routes/tenant/pages/freight/FreightBookingsPage';
import { ContainersPage } from './routes/tenant/pages/containers/ContainersPage';
import { DemurrageDashboardPage } from './routes/tenant/pages/demurrage/DemurrageDashboardPage';
import { DemurrageRateCardsPage } from './routes/tenant/pages/demurrage/DemurrageRateCardsPage';
import { DetentionDashboardPage } from './routes/tenant/pages/detention/DetentionDashboardPage';
import { DetentionRateCardsPage } from './routes/tenant/pages/detention/DetentionRateCardsPage';
import { WarehouseItemsPage } from './routes/tenant/pages/warehouse/WarehouseItemsPage';
import { VehiclesPage } from './routes/tenant/pages/fleet/VehiclesPage';
import { InvoicesPage } from './routes/tenant/pages/finance/InvoicesPage';
import { ExpensesPage } from './routes/tenant/pages/finance/ExpensesPage';
import { ExchangeRatesPage } from './routes/tenant/pages/finance/ExchangeRatesPage';
import { WorkflowDefinitionsPage } from './routes/tenant/pages/workflow/WorkflowDefinitionsPage';
import { EmployeesPage } from './routes/tenant/pages/hr/EmployeesPage';
import { EmployeeDetailPage as HrEmployeeDetailPage } from './routes/tenant/pages/hr/EmployeeDetailPage';
import { DesignationsPage } from './routes/tenant/pages/hr/DesignationsPage';
import { EmployeeContractsPage } from './routes/tenant/pages/hr/EmployeeContractsPage';
import { DepartmentsPage } from './routes/tenant/pages/hr/DepartmentsPage';
import { AttendancePage } from './routes/tenant/pages/hr/AttendancePage';
import { ShiftsPage } from './routes/tenant/pages/hr/ShiftsPage';
import { LeaveManagementPage } from './routes/tenant/pages/hr/LeaveManagementPage';
import { TimesheetsPage } from './routes/tenant/pages/hr/TimesheetsPage';
import { PublicHolidaysPage } from './routes/tenant/pages/hr/PublicHolidaysPage';
import { PayrollComponentsPage } from './routes/tenant/pages/hr/PayrollComponentsPage';
import { StatutoryRulesPage } from './routes/tenant/pages/hr/StatutoryRulesPage';
import { PayrollSettingsPage } from './routes/tenant/pages/hr/PayrollSettingsPage';
import { PayrollPeriodsPage } from './routes/tenant/pages/hr/PayrollPeriodsPage';
import { PayrollRunDetailPage } from './routes/tenant/pages/hr/PayrollRunDetailPage';
import { LoansAdvancesPage } from './routes/tenant/pages/hr/LoansAdvancesPage';
import { OvertimeRequestsPage } from './routes/tenant/pages/hr/OvertimeRequestsPage';
import { PayslipsPage } from './routes/tenant/pages/hr/PayslipsPage';
import { PerformanceReviewsPage } from './routes/tenant/pages/hr/PerformanceReviewsPage';
import { DisciplinaryRecordsPage } from './routes/tenant/pages/hr/DisciplinaryRecordsPage';
import { EmployeeAssetsPage } from './routes/tenant/pages/hr/EmployeeAssetsPage';
import { ExitRecordsPage } from './routes/tenant/pages/hr/ExitRecordsPage';
import { JobVacanciesPage } from './routes/tenant/pages/hr/JobVacanciesPage';
import { CandidatesPage } from './routes/tenant/pages/hr/CandidatesPage';
import { JobApplicationsPage } from './routes/tenant/pages/hr/JobApplicationsPage';
import { OnboardingChecklistsPage } from './routes/tenant/pages/hr/OnboardingChecklistsPage';
import { HrDashboardPage } from './routes/tenant/pages/hr/HrDashboardPage';
import { MyHrPage } from './routes/tenant/pages/MyHrPage';
import { AccountsPage } from './routes/tenant/pages/accounting/AccountsPage';
import { JournalEntriesPage } from './routes/tenant/pages/accounting/JournalEntriesPage';
import { DocumentsPage } from './routes/tenant/pages/documents/DocumentsPage';
import { ReportsPage } from './routes/tenant/pages/ReportsPage';
import { AnalyticsPage } from './routes/tenant/pages/AnalyticsPage';
import { AiAssistantPage } from './routes/tenant/pages/ai/AiAssistantPage';
import { EmailParserPage } from './routes/tenant/pages/ai/EmailParserPage';
import { QuotationsPage } from './routes/tenant/pages/quotations/QuotationsPage';
import { ShipmentsPage } from './routes/tenant/pages/shipments/ShipmentsPage';
import { ShipmentDetailPage } from './routes/tenant/pages/shipments/ShipmentDetailPage';
import { SuperAdminLayout } from './routes/super-admin/SuperAdminLayout';
import { TenantsListPage } from './routes/super-admin/pages/TenantsListPage';
import { PlansPage } from './routes/super-admin/pages/PlansPage';
import { LandingContentPage } from './routes/super-admin/pages/LandingContentPage';
import { MetricsPage } from './routes/super-admin/pages/MetricsPage';
import { SystemHealthPage } from './routes/super-admin/pages/SystemHealthPage';
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
import { PortalApiKeysPage } from './routes/portal/pages/PortalApiKeysPage';
import { PortalAccountPage } from './routes/portal/pages/PortalAccountPage';

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
      <Route path="/verify/release-order/:token" element={<VerifyReleaseOrderPage />} />
      <Route path="/verify/delivery-note/:code" element={<VerifyDeliveryNotePage />} />
      <Route path="/verify/payslip/:code" element={<VerifyPayslipPage />} />

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
          <Route path="detention" element={<DetentionDashboardPage />} />
          <Route path="detention/rate-cards" element={<DetentionRateCardsPage />} />
          <Route path="warehouse" element={<WarehouseItemsPage />} />
          <Route path="fleet" element={<VehiclesPage />} />
          <Route path="finance" element={<InvoicesPage />} />
          <Route path="expenses" element={<ExpensesPage />} />
          <Route path="exchange-rates" element={<ExchangeRatesPage />} />
          <Route path="workflows" element={<WorkflowDefinitionsPage />} />
          <Route path="my-hr" element={<MyHrPage />} />
          <Route path="hr" element={<HrDashboardPage />} />
          <Route path="hr/employees" element={<EmployeesPage />} />
          <Route path="hr/employees/:id" element={<HrEmployeeDetailPage />} />
          <Route path="hr/departments" element={<DepartmentsPage />} />
          <Route path="hr/designations" element={<DesignationsPage />} />
          <Route path="hr/contracts" element={<EmployeeContractsPage />} />
          <Route path="hr/attendance" element={<AttendancePage />} />
          <Route path="hr/shifts" element={<ShiftsPage />} />
          <Route path="hr/leave" element={<LeaveManagementPage />} />
          <Route path="hr/timesheets" element={<TimesheetsPage />} />
          <Route path="hr/holidays" element={<PublicHolidaysPage />} />
          <Route path="hr/payroll-components" element={<PayrollComponentsPage />} />
          <Route path="hr/statutory-rules" element={<StatutoryRulesPage />} />
          <Route path="hr/payroll-settings" element={<PayrollSettingsPage />} />
          <Route path="hr/payroll-periods" element={<PayrollPeriodsPage />} />
          <Route path="hr/payroll-runs/:id" element={<PayrollRunDetailPage />} />
          <Route path="hr/loans-advances" element={<LoansAdvancesPage />} />
          <Route path="hr/overtime-requests" element={<OvertimeRequestsPage />} />
          <Route path="hr/payslips" element={<PayslipsPage />} />
          <Route path="hr/performance-reviews" element={<PerformanceReviewsPage />} />
          <Route path="hr/disciplinary-records" element={<DisciplinaryRecordsPage />} />
          <Route path="hr/employee-assets" element={<EmployeeAssetsPage />} />
          <Route path="hr/exit-records" element={<ExitRecordsPage />} />
          <Route path="hr/job-vacancies" element={<JobVacanciesPage />} />
          <Route path="hr/candidates" element={<CandidatesPage />} />
          <Route path="hr/job-applications" element={<JobApplicationsPage />} />
          <Route path="hr/onboarding" element={<OnboardingChecklistsPage />} />
          <Route path="accounting" element={<AccountsPage />} />
          <Route path="accounting/journal-entries" element={<JournalEntriesPage />} />
          <Route path="documents" element={<DocumentsPage />} />
          <Route path="reports" element={<ReportsPage />} />
          <Route path="analytics" element={<AnalyticsPage />} />
          <Route path="assistant" element={<AiAssistantPage />} />
          <Route path="email-parser" element={<EmailParserPage />} />
          <Route path="users" element={<UsersPage />} />
          <Route path="branches" element={<BranchesPage />} />
          <Route path="audit-log" element={<TenantAuditLogPage />} />
          <Route path="settings" element={<CompanySettingsPage />} />
          <Route path="backup" element={<BackupRestorePage />} />
          <Route path="subscription" element={<SubscriptionBillingPage />} />
          <Route path="security" element={<AccountSecurityPage />} />
          <Route path="login-history" element={<LoginHistoryPage />} />
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
          <Route path="api-keys" element={<PortalApiKeysPage />} />
          <Route path="account" element={<PortalAccountPage />} />
        </Route>
      </Route>

      <Route element={<ProtectedRoute requireSuperAdmin />}>
        <Route path="/platform" element={<SuperAdminLayout />}>
          <Route index element={<Navigate to="tenants" replace />} />
          <Route path="tenants" element={<TenantsListPage />} />
          <Route path="plans" element={<PlansPage />} />
          <Route path="landing-content" element={<LandingContentPage />} />
          <Route path="metrics" element={<MetricsPage />} />
          <Route path="system-health" element={<SystemHealthPage />} />
          <Route path="audit-log" element={<PlatformAuditLogPage />} />
          <Route path="error-log" element={<ErrorLogsPage />} />
        </Route>
      </Route>

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
