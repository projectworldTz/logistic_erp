<?php

use App\Http\Controllers\Api\V1\Accounting;
use App\Http\Controllers\Api\V1\Ai;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ClientApi;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Clearing;
use App\Http\Controllers\Api\V1\Containers;
use App\Http\Controllers\Api\V1\Crm;
use App\Http\Controllers\Api\V1\Currency;
use App\Http\Controllers\Api\V1\Demurrage;
use App\Http\Controllers\Api\V1\Detention;
use App\Http\Controllers\Api\V1\Documents;
use App\Http\Controllers\Api\V1\Finance;
use App\Http\Controllers\Api\V1\Fleet;
use App\Http\Controllers\Api\V1\Freight;
use App\Http\Controllers\Api\V1\Hr;
use App\Http\Controllers\Api\V1\Platform;
use App\Http\Controllers\Api\V1\Portal;
use App\Http\Controllers\Api\V1\Public;
use App\Http\Controllers\Api\V1\Quotations;
use App\Http\Controllers\Api\V1\Shipments;
use App\Http\Controllers\Api\V1\Tenant;
use App\Http\Controllers\Api\V1\Warehouse;
use App\Http\Controllers\Api\V1\Workflow;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/2fa/verify', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:10,1');
    Route::post('auth/forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
    Route::post('tenants/register', [Tenant\TenantRegistrationController::class, 'store'])->middleware('throttle:5,1');
    Route::get('plans', [Public\PlanController::class, 'index']);
    Route::get('landing-content', [Public\LandingContentController::class, 'index']);
    Route::post('contact', [Public\ContactController::class, 'store'])->middleware('throttle:5,1');
    Route::post('demo-requests', [Public\DemoRequestController::class, 'store'])->middleware('throttle:5,1');
    Route::get('public/track/{trackingCode}', [Public\ShipmentTrackingController::class, 'show'])->middleware('throttle:30,1');
    Route::get('public/verify/release-order/{token}', [Public\ReleaseOrderVerificationController::class, 'show'])->middleware('throttle:30,1');
    Route::get('public/verify/delivery-note/{trackingCode}', [Public\DeliveryNoteVerificationController::class, 'show'])->middleware('throttle:30,1');
    Route::get('public/verify/payslip/{code}', [Public\PayslipVerificationController::class, 'show'])->middleware('throttle:30,1');

    // Signed, expiring download link for a private employee document — the
    // signature itself is the authorization (same model as an S3 presigned
    // URL), so it deliberately sits outside the auth:sanctum/tenant group;
    // only ever minted by EmployeeDocumentResource for a document the
    // requesting user was already tenant-scope-authorized to see.
    Route::get('employee-documents/{employeeDocument}/download', [Hr\EmployeeDocumentController::class, 'download'])
        ->name('employee-documents.download')
        ->middleware(['signed', 'throttle:30,1']);

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::put('auth/password', [AuthController::class, 'changePassword']);

        Route::post('auth/2fa/setup', [TwoFactorController::class, 'setup']);
        Route::post('auth/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('auth/2fa/disable', [TwoFactorController::class, 'disable']);

        Route::middleware('tenant')->group(function () {
            Route::get('dashboard/summary', [Tenant\DashboardController::class, 'summary']);
            Route::get('reports/overview', [Tenant\ReportsController::class, 'overview'])->middleware('permission:reports.view');
            Route::get('reports/profit', [Tenant\ReportsController::class, 'profit'])->middleware('permission:reports.view');
            Route::get('reports/customs', [Tenant\ReportsController::class, 'customs'])->middleware('permission:reports.view');
            Route::get('reports/tax', [Tenant\ReportsController::class, 'tax'])->middleware('permission:reports.view');
            Route::get('reports/export/{module}', [Tenant\ReportExportController::class, 'export']);
            Route::post('reports/import/{module}', [Tenant\DataImportController::class, 'import']);
            Route::get('reports/scheduled', [Tenant\ScheduledReportController::class, 'index'])->middleware('permission:reports.view');
            Route::post('reports/scheduled', [Tenant\ScheduledReportController::class, 'store'])->middleware('permission:reports.manage');
            Route::put('reports/scheduled/{scheduledReport}', [Tenant\ScheduledReportController::class, 'update'])->middleware('permission:reports.manage');
            Route::delete('reports/scheduled/{scheduledReport}', [Tenant\ScheduledReportController::class, 'destroy'])->middleware('permission:reports.manage');

            Route::get('backup/export', [Tenant\BackupController::class, 'export'])->middleware('permission:core.backup.manage');
            Route::post('backup/restore', [Tenant\BackupController::class, 'restore'])->middleware('permission:core.backup.manage');

            Route::get('subscription', [Tenant\SubscriptionController::class, 'show'])->middleware('permission:core.company.view');
            Route::get('subscription/invoices', [Tenant\SubscriptionController::class, 'invoices'])->middleware('permission:core.company.view');
            Route::put('subscription/plan', [Tenant\SubscriptionController::class, 'changePlan'])->middleware('permission:core.company.manage');
            Route::get('billing-profile', [Tenant\BillingProfileController::class, 'show'])->middleware('permission:core.company.view');
            Route::put('billing-profile', [Tenant\BillingProfileController::class, 'update'])->middleware('permission:core.company.manage');
            Route::get('analytics/overview', [Tenant\AnalyticsController::class, 'overview'])->middleware('permission:analytics.view');
            Route::get('company', [Tenant\CompanyController::class, 'show']);
            Route::put('company', [Tenant\CompanyController::class, 'update']);
            Route::post('company/logo', [Tenant\CompanyController::class, 'uploadLogo']);
            Route::get('branches', [Tenant\BranchController::class, 'index']);
            Route::get('branches/rollup', [Tenant\BranchRollupController::class, 'index'])->middleware('permission:core.branches.view');
            Route::get('users', [Tenant\UserController::class, 'index'])->middleware('permission:core.users.view');
            Route::get('roles', [Tenant\RoleController::class, 'index'])->middleware('permission:core.users.view');
            Route::post('users', [Tenant\UserController::class, 'store'])->middleware('permission:core.users.manage');
            Route::put('users/{user}', [Tenant\UserController::class, 'update'])->middleware('permission:core.users.manage');
            Route::post('users/{user}/suspend', [Tenant\UserController::class, 'suspend'])->middleware('permission:core.users.manage');
            Route::post('users/{user}/activate', [Tenant\UserController::class, 'activate'])->middleware('permission:core.users.manage');
            Route::get('audit-logs', [Tenant\AuditLogController::class, 'index']);
            Route::get('login-history', [Tenant\LoginHistoryController::class, 'index'])->middleware('permission:core.audit.view');
            Route::get('notifications', [Tenant\NotificationController::class, 'index']);
            Route::get('notifications/unread-count', [Tenant\NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [Tenant\NotificationController::class, 'markAllRead']);
            Route::post('notifications/{notification}/read', [Tenant\NotificationController::class, 'markRead']);
            Route::get('search', [Tenant\SearchController::class, 'index']);

            Route::prefix('ai')->group(function () {
                Route::post('assistant/chat', [Ai\AssistantController::class, 'chat'])->middleware('permission:ai.assistant.use');
                Route::post('email-parser/parse', [Ai\EmailParserController::class, 'parse'])->middleware('permission:ai.email_parser.use');
            });

            if (app()->environment('testing')) {
                Route::match(['get', 'post'], '_test/throw', function () {
                    throw new \RuntimeException('Deliberate test exception');
                });
            }

            Route::prefix('crm')->group(function () {
                Route::get('leads', [Crm\LeadController::class, 'index'])->middleware('permission:crm.leads.view');
                Route::post('leads', [Crm\LeadController::class, 'store'])->middleware('permission:crm.leads.manage');
                Route::get('leads/{lead}', [Crm\LeadController::class, 'show'])->middleware('permission:crm.leads.view');
                Route::put('leads/{lead}', [Crm\LeadController::class, 'update'])->middleware('permission:crm.leads.manage');
                Route::delete('leads/{lead}', [Crm\LeadController::class, 'destroy'])->middleware('permission:crm.leads.manage');
                Route::post('leads/{lead}/convert', [Crm\LeadController::class, 'convert'])->middleware('permission:crm.leads.manage');

                Route::get('customers', [Crm\CustomerController::class, 'index'])->middleware('permission:crm.customers.view');
                Route::post('customers', [Crm\CustomerController::class, 'store'])->middleware('permission:crm.customers.manage');
                Route::get('customers/{customer}', [Crm\CustomerController::class, 'show'])->middleware('permission:crm.customers.view');
                Route::put('customers/{customer}', [Crm\CustomerController::class, 'update'])->middleware('permission:crm.customers.manage');
                Route::delete('customers/{customer}', [Crm\CustomerController::class, 'destroy'])->middleware('permission:crm.customers.manage');

                Route::get('customers/{customer}/contacts', [Crm\ContactController::class, 'index'])->middleware('permission:crm.contacts.view');
                Route::post('customers/{customer}/contacts', [Crm\ContactController::class, 'store'])->middleware('permission:crm.contacts.manage');
                Route::put('customers/{customer}/contacts/{contact}', [Crm\ContactController::class, 'update'])->middleware('permission:crm.contacts.manage');
                Route::delete('customers/{customer}/contacts/{contact}', [Crm\ContactController::class, 'destroy'])->middleware('permission:crm.contacts.manage');

                Route::get('customers/{customer}/messages', [Crm\CustomerMessageController::class, 'index'])->middleware('permission:crm.customers.manage');
                Route::post('customers/{customer}/messages', [Crm\CustomerMessageController::class, 'store'])->middleware('permission:crm.customers.manage');

                Route::get('customers/{customer}/compliance-documents', [Crm\ComplianceDocumentController::class, 'index'])->middleware('permission:crm.compliance.view');
                Route::post('customers/{customer}/compliance-documents', [Crm\ComplianceDocumentController::class, 'store'])->middleware('permission:crm.compliance.manage');
                Route::delete('customers/{customer}/compliance-documents/{complianceDocument}', [Crm\ComplianceDocumentController::class, 'destroy'])->middleware('permission:crm.compliance.manage');
            });

            Route::prefix('clearing')->group(function () {
                Route::get('files', [Clearing\ClearingFileController::class, 'index'])->middleware('permission:clearing.files.view');
                Route::post('files', [Clearing\ClearingFileController::class, 'store'])->middleware('permission:clearing.files.manage');
                Route::get('files/{clearingFile}', [Clearing\ClearingFileController::class, 'show'])->middleware('permission:clearing.files.view');
                Route::put('files/{clearingFile}', [Clearing\ClearingFileController::class, 'update'])->middleware('permission:clearing.files.manage');
                Route::delete('files/{clearingFile}', [Clearing\ClearingFileController::class, 'destroy'])->middleware('permission:clearing.files.manage');
                Route::get('files/{clearingFile}/release-order-qr', [Clearing\ClearingFileController::class, 'releaseOrderQr'])->middleware('permission:clearing.files.view');
            });

            Route::prefix('freight')->group(function () {
                Route::get('bookings', [Freight\FreightBookingController::class, 'index'])->middleware('permission:freight.bookings.view');
                Route::post('bookings', [Freight\FreightBookingController::class, 'store'])->middleware('permission:freight.bookings.manage');
                Route::get('bookings/{freightBooking}', [Freight\FreightBookingController::class, 'show'])->middleware('permission:freight.bookings.view');
                Route::put('bookings/{freightBooking}', [Freight\FreightBookingController::class, 'update'])->middleware('permission:freight.bookings.manage');
                Route::delete('bookings/{freightBooking}', [Freight\FreightBookingController::class, 'destroy'])->middleware('permission:freight.bookings.manage');
            });

            Route::prefix('containers')->group(function () {
                Route::get('items', [Containers\ContainerController::class, 'index'])->middleware('permission:containers.items.view');
                Route::post('items', [Containers\ContainerController::class, 'store'])->middleware('permission:containers.items.manage');
                Route::get('items/{container}', [Containers\ContainerController::class, 'show'])->middleware('permission:containers.items.view');
                Route::put('items/{container}', [Containers\ContainerController::class, 'update'])->middleware('permission:containers.items.manage');
                Route::delete('items/{container}', [Containers\ContainerController::class, 'destroy'])->middleware('permission:containers.items.manage');
                Route::post('items/{container}/demurrage/calculate', [Demurrage\DemurrageChargeController::class, 'calculate'])->middleware('permission:demurrage.charges.manage');
                Route::post('items/{container}/detention/calculate', [Detention\DetentionChargeController::class, 'calculate'])->middleware('permission:detention.charges.manage');
            });

            Route::prefix('demurrage')->group(function () {
                Route::get('dashboard', [Demurrage\DemurrageDashboardController::class, 'index'])->middleware('permission:demurrage.charges.view');
                Route::get('rate-cards', [Demurrage\DemurrageRateCardController::class, 'index'])->middleware('permission:demurrage.rate_cards.view');
                Route::post('rate-cards', [Demurrage\DemurrageRateCardController::class, 'store'])->middleware('permission:demurrage.rate_cards.manage');
                Route::get('rate-cards/{rateCard}', [Demurrage\DemurrageRateCardController::class, 'show'])->middleware('permission:demurrage.rate_cards.view');
                Route::put('rate-cards/{rateCard}', [Demurrage\DemurrageRateCardController::class, 'update'])->middleware('permission:demurrage.rate_cards.manage');
                Route::delete('rate-cards/{rateCard}', [Demurrage\DemurrageRateCardController::class, 'destroy'])->middleware('permission:demurrage.rate_cards.manage');
                Route::get('charges', [Demurrage\DemurrageChargeController::class, 'index'])->middleware('permission:demurrage.charges.view');
                Route::get('charges/{charge}', [Demurrage\DemurrageChargeController::class, 'show'])->middleware('permission:demurrage.charges.view');
                Route::post('charges/{charge}/waive', [Demurrage\DemurrageChargeController::class, 'waive'])->middleware('permission:demurrage.charges.manage');
                Route::post('charges/{charge}/generate-invoice', [Demurrage\DemurrageChargeController::class, 'generateInvoice'])->middleware('permission:demurrage.charges.manage');
            });

            Route::prefix('detention')->group(function () {
                Route::get('dashboard', [Detention\DetentionDashboardController::class, 'index'])->middleware('permission:detention.charges.view');
                Route::get('rate-cards', [Detention\DetentionRateCardController::class, 'index'])->middleware('permission:detention.rate_cards.view');
                Route::post('rate-cards', [Detention\DetentionRateCardController::class, 'store'])->middleware('permission:detention.rate_cards.manage');
                Route::get('rate-cards/{rateCard}', [Detention\DetentionRateCardController::class, 'show'])->middleware('permission:detention.rate_cards.view');
                Route::put('rate-cards/{rateCard}', [Detention\DetentionRateCardController::class, 'update'])->middleware('permission:detention.rate_cards.manage');
                Route::delete('rate-cards/{rateCard}', [Detention\DetentionRateCardController::class, 'destroy'])->middleware('permission:detention.rate_cards.manage');
                Route::get('charges', [Detention\DetentionChargeController::class, 'index'])->middleware('permission:detention.charges.view');
                Route::get('charges/{charge}', [Detention\DetentionChargeController::class, 'show'])->middleware('permission:detention.charges.view');
                Route::post('charges/{charge}/waive', [Detention\DetentionChargeController::class, 'waive'])->middleware('permission:detention.charges.manage');
                Route::post('charges/{charge}/generate-invoice', [Detention\DetentionChargeController::class, 'generateInvoice'])->middleware('permission:detention.charges.manage');
            });

            Route::prefix('warehouse')->group(function () {
                Route::get('items', [Warehouse\WarehouseItemController::class, 'index'])->middleware('permission:warehouse.items.view');
                Route::post('items', [Warehouse\WarehouseItemController::class, 'store'])->middleware('permission:warehouse.items.manage');
                Route::get('items/{warehouseItem}', [Warehouse\WarehouseItemController::class, 'show'])->middleware('permission:warehouse.items.view');
                Route::put('items/{warehouseItem}', [Warehouse\WarehouseItemController::class, 'update'])->middleware('permission:warehouse.items.manage');
                Route::delete('items/{warehouseItem}', [Warehouse\WarehouseItemController::class, 'destroy'])->middleware('permission:warehouse.items.manage');
            });

            Route::prefix('fleet')->group(function () {
                Route::get('vehicles', [Fleet\VehicleController::class, 'index'])->middleware('permission:fleet.vehicles.view');
                Route::post('vehicles', [Fleet\VehicleController::class, 'store'])->middleware('permission:fleet.vehicles.manage');
                Route::get('vehicles/{vehicle}', [Fleet\VehicleController::class, 'show'])->middleware('permission:fleet.vehicles.view');
                Route::put('vehicles/{vehicle}', [Fleet\VehicleController::class, 'update'])->middleware('permission:fleet.vehicles.manage');
                Route::delete('vehicles/{vehicle}', [Fleet\VehicleController::class, 'destroy'])->middleware('permission:fleet.vehicles.manage');
                Route::get('vehicles/{vehicle}/logs', [Fleet\VehicleLogController::class, 'index'])->middleware('permission:fleet.vehicles.view');
                Route::post('vehicles/{vehicle}/logs', [Fleet\VehicleLogController::class, 'store'])->middleware('permission:fleet.vehicles.manage');
                Route::delete('vehicles/{vehicle}/logs/{log}', [Fleet\VehicleLogController::class, 'destroy'])->middleware('permission:fleet.vehicles.manage');
            });

            Route::prefix('finance')->group(function () {
                Route::get('invoices', [Finance\InvoiceController::class, 'index'])->middleware('permission:finance.invoices.view');
                Route::post('invoices', [Finance\InvoiceController::class, 'store'])->middleware('permission:finance.invoices.manage');
                Route::get('invoices/{invoice}', [Finance\InvoiceController::class, 'show'])->middleware('permission:finance.invoices.view');
                Route::get('invoices/{invoice}/pdf', [Finance\InvoiceController::class, 'pdf'])->middleware('permission:finance.invoices.view');
                Route::put('invoices/{invoice}', [Finance\InvoiceController::class, 'update'])->middleware('permission:finance.invoices.manage');
                Route::delete('invoices/{invoice}', [Finance\InvoiceController::class, 'destroy'])->middleware('permission:finance.invoices.manage');

                Route::get('expenses', [Finance\ExpenseController::class, 'index'])->middleware('permission:expenses.items.view');
                Route::post('expenses', [Finance\ExpenseController::class, 'store'])->middleware('permission:expenses.items.manage');
                Route::get('expenses/{expense}', [Finance\ExpenseController::class, 'show'])->middleware('permission:expenses.items.view');
                Route::put('expenses/{expense}', [Finance\ExpenseController::class, 'update'])->middleware('permission:expenses.items.manage');
                Route::delete('expenses/{expense}', [Finance\ExpenseController::class, 'destroy'])->middleware('permission:expenses.items.manage');
                Route::post('expenses/{expense}/submit', [Finance\ExpenseController::class, 'submit'])->middleware('permission:expenses.items.manage');
                Route::post('expenses/{expense}/approve', [Finance\ExpenseController::class, 'approve'])->middleware('permission:expenses.items.view');
                Route::post('expenses/{expense}/reject', [Finance\ExpenseController::class, 'reject'])->middleware('permission:expenses.items.view');
                Route::post('expenses/{expense}/mark-paid', [Finance\ExpenseController::class, 'markPaid'])->middleware('permission:expenses.items.manage');

                Route::get('exchange-rates', [Currency\ExchangeRateController::class, 'index'])->middleware('permission:finance.exchange_rates.view');
                Route::post('exchange-rates', [Currency\ExchangeRateController::class, 'store'])->middleware('permission:finance.exchange_rates.manage');
                Route::delete('exchange-rates/{exchangeRate}', [Currency\ExchangeRateController::class, 'destroy'])->middleware('permission:finance.exchange_rates.manage');
                Route::post('exchange-rates/convert', [Currency\ExchangeRateController::class, 'convert'])->middleware('permission:finance.exchange_rates.view');
            });

            Route::prefix('workflows')->group(function () {
                Route::get('definitions', [Workflow\ApprovalWorkflowController::class, 'index'])->middleware('permission:workflows.definitions.view');
                Route::post('definitions', [Workflow\ApprovalWorkflowController::class, 'store'])->middleware('permission:workflows.definitions.manage');
                Route::get('definitions/{workflow}', [Workflow\ApprovalWorkflowController::class, 'show'])->middleware('permission:workflows.definitions.view');
                Route::put('definitions/{workflow}', [Workflow\ApprovalWorkflowController::class, 'update'])->middleware('permission:workflows.definitions.manage');
                Route::delete('definitions/{workflow}', [Workflow\ApprovalWorkflowController::class, 'destroy'])->middleware('permission:workflows.definitions.manage');
            });

            Route::prefix('hr')->group(function () {
                Route::get('dashboard', [Hr\HrDashboardController::class, 'index'])->middleware('permission:hr.employees.view');
                Route::get('departments', [Hr\DepartmentController::class, 'index'])->middleware('permission:hr.departments.view');
                Route::post('departments', [Hr\DepartmentController::class, 'store'])->middleware('permission:hr.departments.manage');
                Route::get('departments/{department}', [Hr\DepartmentController::class, 'show'])->middleware('permission:hr.departments.view');
                Route::put('departments/{department}', [Hr\DepartmentController::class, 'update'])->middleware('permission:hr.departments.manage');
                Route::delete('departments/{department}', [Hr\DepartmentController::class, 'destroy'])->middleware('permission:hr.departments.manage');

                Route::get('employees', [Hr\EmployeeController::class, 'index'])->middleware('permission:hr.employees.view');
                Route::post('employees', [Hr\EmployeeController::class, 'store'])->middleware('permission:hr.employees.manage');
                Route::get('employees/{employee}', [Hr\EmployeeController::class, 'show'])->middleware('permission:hr.employees.view');
                Route::put('employees/{employee}', [Hr\EmployeeController::class, 'update'])->middleware('permission:hr.employees.manage');
                Route::delete('employees/{employee}', [Hr\EmployeeController::class, 'destroy'])->middleware('permission:hr.employees.manage');

                // Separate from hr.employees.view — a manager can see the employee
                // record without seeing salary/bank/national-ID.
                Route::get('employees/{employee}/salary', [Hr\EmployeeSalaryController::class, 'show'])->middleware('permission:hr.employees.salary.view');

                Route::get('employees/{employee}/documents', [Hr\EmployeeDocumentController::class, 'index'])->middleware('permission:hr.employees.documents.view');
                Route::post('employees/{employee}/documents', [Hr\EmployeeDocumentController::class, 'store'])->middleware('permission:hr.employees.documents.manage');
                Route::get('employee-documents/{employeeDocument}', [Hr\EmployeeDocumentController::class, 'show'])->middleware('permission:hr.employees.documents.view');
                Route::post('employee-documents/{employeeDocument}/verify', [Hr\EmployeeDocumentController::class, 'verify'])->middleware('permission:hr.employees.documents.manage');
                Route::post('employee-documents/{employeeDocument}/reject', [Hr\EmployeeDocumentController::class, 'reject'])->middleware('permission:hr.employees.documents.manage');
                Route::delete('employee-documents/{employeeDocument}', [Hr\EmployeeDocumentController::class, 'destroy'])->middleware('permission:hr.employees.documents.manage');

                Route::get('designations', [Hr\DesignationController::class, 'index'])->middleware('permission:hr.designations.view');
                Route::post('designations', [Hr\DesignationController::class, 'store'])->middleware('permission:hr.designations.manage');
                Route::get('designations/{designation}', [Hr\DesignationController::class, 'show'])->middleware('permission:hr.designations.view');
                Route::put('designations/{designation}', [Hr\DesignationController::class, 'update'])->middleware('permission:hr.designations.manage');
                Route::delete('designations/{designation}', [Hr\DesignationController::class, 'destroy'])->middleware('permission:hr.designations.manage');

                Route::get('contracts', [Hr\EmployeeContractController::class, 'index'])->middleware('permission:hr.contracts.view');
                Route::post('contracts', [Hr\EmployeeContractController::class, 'store'])->middleware('permission:hr.contracts.manage');
                Route::get('contracts/{employeeContract}', [Hr\EmployeeContractController::class, 'show'])->middleware('permission:hr.contracts.view');
                Route::put('contracts/{employeeContract}', [Hr\EmployeeContractController::class, 'update'])->middleware('permission:hr.contracts.manage');
                Route::delete('contracts/{employeeContract}', [Hr\EmployeeContractController::class, 'destroy'])->middleware('permission:hr.contracts.manage');
                Route::post('contracts/{employeeContract}/submit', [Hr\EmployeeContractController::class, 'submit'])->middleware('permission:hr.contracts.manage');
                Route::post('contracts/{employeeContract}/approve', [Hr\EmployeeContractController::class, 'approve'])->middleware('permission:hr.contracts.view');
                Route::post('contracts/{employeeContract}/reject', [Hr\EmployeeContractController::class, 'reject'])->middleware('permission:hr.contracts.view');

                Route::get('attendance', [Hr\AttendanceRecordController::class, 'index'])->middleware('permission:hr.attendance.view');
                Route::post('attendance', [Hr\AttendanceRecordController::class, 'store'])->middleware('permission:hr.attendance.manage');
                Route::get('attendance/{attendanceRecord}', [Hr\AttendanceRecordController::class, 'show'])->middleware('permission:hr.attendance.view');
                Route::put('attendance/{attendanceRecord}', [Hr\AttendanceRecordController::class, 'update'])->middleware('permission:hr.attendance.manage');
                Route::delete('attendance/{attendanceRecord}', [Hr\AttendanceRecordController::class, 'destroy'])->middleware('permission:hr.attendance.manage');

                Route::get('shifts', [Hr\ShiftController::class, 'index'])->middleware('permission:hr.shifts.view');
                Route::post('shifts', [Hr\ShiftController::class, 'store'])->middleware('permission:hr.shifts.manage');
                Route::get('shifts/{shift}', [Hr\ShiftController::class, 'show'])->middleware('permission:hr.shifts.view');
                Route::put('shifts/{shift}', [Hr\ShiftController::class, 'update'])->middleware('permission:hr.shifts.manage');
                Route::delete('shifts/{shift}', [Hr\ShiftController::class, 'destroy'])->middleware('permission:hr.shifts.manage');

                Route::get('employee-shifts', [Hr\EmployeeShiftController::class, 'index'])->middleware('permission:hr.shifts.view');
                Route::post('employee-shifts', [Hr\EmployeeShiftController::class, 'store'])->middleware('permission:hr.shifts.manage');
                Route::delete('employee-shifts/{employeeShift}', [Hr\EmployeeShiftController::class, 'destroy'])->middleware('permission:hr.shifts.manage');

                Route::get('timesheets', [Hr\TimesheetController::class, 'index'])->middleware('permission:hr.timesheets.view');
                Route::post('timesheets', [Hr\TimesheetController::class, 'store'])->middleware('permission:hr.timesheets.manage');
                Route::get('timesheets/{timesheet}', [Hr\TimesheetController::class, 'show'])->middleware('permission:hr.timesheets.view');
                Route::put('timesheets/{timesheet}', [Hr\TimesheetController::class, 'update'])->middleware('permission:hr.timesheets.manage');
                Route::delete('timesheets/{timesheet}', [Hr\TimesheetController::class, 'destroy'])->middleware('permission:hr.timesheets.manage');
                Route::post('timesheets/{timesheet}/approve', [Hr\TimesheetController::class, 'approve'])->middleware('permission:hr.timesheets.approve');
                Route::post('timesheets/{timesheet}/reject', [Hr\TimesheetController::class, 'reject'])->middleware('permission:hr.timesheets.approve');

                Route::get('leave-types', [Hr\LeaveTypeController::class, 'index'])->middleware('permission:hr.leave.view');
                Route::post('leave-types', [Hr\LeaveTypeController::class, 'store'])->middleware('permission:hr.leave.manage');
                Route::put('leave-types/{leaveType}', [Hr\LeaveTypeController::class, 'update'])->middleware('permission:hr.leave.manage');
                Route::delete('leave-types/{leaveType}', [Hr\LeaveTypeController::class, 'destroy'])->middleware('permission:hr.leave.manage');

                Route::get('leave-balances', [Hr\LeaveBalanceController::class, 'index'])->middleware('permission:hr.leave.view');

                Route::get('leave-requests', [Hr\LeaveRequestController::class, 'index'])->middleware('permission:hr.leave.view');
                Route::post('leave-requests', [Hr\LeaveRequestController::class, 'store'])->middleware('permission:hr.leave.manage');
                Route::get('leave-requests/{leaveRequest}', [Hr\LeaveRequestController::class, 'show'])->middleware('permission:hr.leave.view');
                Route::post('leave-requests/{leaveRequest}/approve', [Hr\LeaveRequestController::class, 'approve'])->middleware('permission:hr.leave.view');
                Route::post('leave-requests/{leaveRequest}/reject', [Hr\LeaveRequestController::class, 'reject'])->middleware('permission:hr.leave.view');
                Route::post('leave-requests/{leaveRequest}/cancel', [Hr\LeaveRequestController::class, 'cancel'])->middleware('permission:hr.leave.manage');

                // Employee self-service: no permission middleware, same model as
                // payslips/performance-reviews — every method hard-scopes to the
                // caller's own Employee record inside the controller.
                Route::get('my/profile', [Hr\MyHrController::class, 'profile']);
                Route::get('my/attendance', [Hr\MyHrController::class, 'attendance']);
                Route::get('my/leave-types', [Hr\MyHrController::class, 'leaveTypes']);
                Route::get('my/leave-balances', [Hr\MyHrController::class, 'leaveBalances']);
                Route::get('my/leave-requests', [Hr\MyHrController::class, 'leaveRequests']);
                Route::post('my/leave-requests', [Hr\MyHrController::class, 'storeLeaveRequest']);
                Route::post('my/leave-requests/{leaveRequest}/cancel', [Hr\MyHrController::class, 'cancelLeaveRequest']);
                Route::get('my/assets', [Hr\MyHrController::class, 'assets']);

                Route::get('public-holidays', [Hr\PublicHolidayController::class, 'index'])->middleware('permission:hr.attendance.view');
                Route::post('public-holidays', [Hr\PublicHolidayController::class, 'store'])->middleware('permission:hr.attendance.manage');
                Route::delete('public-holidays/{publicHoliday}', [Hr\PublicHolidayController::class, 'destroy'])->middleware('permission:hr.attendance.manage');

                Route::get('payroll-components', [Hr\PayrollComponentController::class, 'index'])->middleware('permission:hr.payroll_components.view');
                Route::post('payroll-components', [Hr\PayrollComponentController::class, 'store'])->middleware('permission:hr.payroll_components.manage');
                Route::get('payroll-components/{payrollComponent}', [Hr\PayrollComponentController::class, 'show'])->middleware('permission:hr.payroll_components.view');
                Route::put('payroll-components/{payrollComponent}', [Hr\PayrollComponentController::class, 'update'])->middleware('permission:hr.payroll_components.manage');
                Route::delete('payroll-components/{payrollComponent}', [Hr\PayrollComponentController::class, 'destroy'])->middleware('permission:hr.payroll_components.manage');

                Route::get('employee-payroll-components', [Hr\EmployeePayrollComponentController::class, 'index'])->middleware('permission:hr.payroll_components.view');
                Route::post('employee-payroll-components', [Hr\EmployeePayrollComponentController::class, 'store'])->middleware('permission:hr.payroll_components.manage');
                Route::delete('employee-payroll-components/{employeePayrollComponent}', [Hr\EmployeePayrollComponentController::class, 'destroy'])->middleware('permission:hr.payroll_components.manage');

                Route::get('statutory-rule-sets', [Hr\StatutoryRuleSetController::class, 'index'])->middleware('permission:hr.statutory_rules.view');
                Route::post('statutory-rule-sets', [Hr\StatutoryRuleSetController::class, 'store'])->middleware('permission:hr.statutory_rules.manage');
                Route::get('statutory-rule-sets/{statutoryRuleSet}', [Hr\StatutoryRuleSetController::class, 'show'])->middleware('permission:hr.statutory_rules.view');
                Route::put('statutory-rule-sets/{statutoryRuleSet}', [Hr\StatutoryRuleSetController::class, 'update'])->middleware('permission:hr.statutory_rules.manage');
                Route::delete('statutory-rule-sets/{statutoryRuleSet}', [Hr\StatutoryRuleSetController::class, 'destroy'])->middleware('permission:hr.statutory_rules.manage');

                Route::post('statutory-rule-sets/{statutoryRuleSet}/tax-bands', [Hr\StatutoryTaxBandController::class, 'store'])->middleware('permission:hr.statutory_rules.manage');
                Route::put('statutory-rule-sets/{statutoryRuleSet}/tax-bands/{taxBand}', [Hr\StatutoryTaxBandController::class, 'update'])->middleware('permission:hr.statutory_rules.manage');
                Route::delete('statutory-rule-sets/{statutoryRuleSet}/tax-bands/{taxBand}', [Hr\StatutoryTaxBandController::class, 'destroy'])->middleware('permission:hr.statutory_rules.manage');

                Route::post('statutory-rule-sets/{statutoryRuleSet}/contribution-rules', [Hr\StatutoryContributionRuleController::class, 'store'])->middleware('permission:hr.statutory_rules.manage');
                Route::put('statutory-rule-sets/{statutoryRuleSet}/contribution-rules/{contributionRule}', [Hr\StatutoryContributionRuleController::class, 'update'])->middleware('permission:hr.statutory_rules.manage');
                Route::delete('statutory-rule-sets/{statutoryRuleSet}/contribution-rules/{contributionRule}', [Hr\StatutoryContributionRuleController::class, 'destroy'])->middleware('permission:hr.statutory_rules.manage');

                Route::get('payroll-settings', [Hr\PayrollSettingsController::class, 'show'])->middleware('permission:hr.payroll_settings.view');
                Route::put('payroll-settings', [Hr\PayrollSettingsController::class, 'update'])->middleware('permission:hr.payroll_settings.manage');

                Route::get('payroll-periods', [Hr\PayrollPeriodController::class, 'index'])->middleware('permission:hr.payroll_periods.view');
                Route::post('payroll-periods', [Hr\PayrollPeriodController::class, 'store'])->middleware('permission:hr.payroll_periods.manage');
                Route::get('payroll-periods/{payrollPeriod}', [Hr\PayrollPeriodController::class, 'show'])->middleware('permission:hr.payroll_periods.view');
                Route::delete('payroll-periods/{payrollPeriod}', [Hr\PayrollPeriodController::class, 'destroy'])->middleware('permission:hr.payroll_periods.manage');
                Route::post('payroll-periods/{payrollPeriod}/runs', [Hr\PayrollRunController::class, 'store'])->middleware('permission:hr.payroll_runs.manage');

                Route::get('payroll-runs', [Hr\PayrollRunController::class, 'index'])->middleware('permission:hr.payroll_runs.view');
                Route::get('payroll-runs/{payrollRun}', [Hr\PayrollRunController::class, 'show'])->middleware('permission:hr.payroll_runs.view');
                Route::post('payroll-runs/{payrollRun}/calculate', [Hr\PayrollRunController::class, 'calculate'])->middleware('permission:hr.payroll_runs.manage');
                Route::post('payroll-runs/{payrollRun}/submit', [Hr\PayrollRunController::class, 'submit'])->middleware('permission:hr.payroll_runs.manage');
                Route::post('payroll-runs/{payrollRun}/approve', [Hr\PayrollRunController::class, 'approve'])->middleware('permission:hr.payroll_runs.view');
                Route::post('payroll-runs/{payrollRun}/reject', [Hr\PayrollRunController::class, 'reject'])->middleware('permission:hr.payroll_runs.view');
                Route::post('payroll-runs/{payrollRun}/finalize', [Hr\PayrollRunController::class, 'finalize'])->middleware('permission:hr.payroll_runs.approve');

                Route::put('payroll-run-employees/{payrollRunEmployee}', [Hr\PayrollRunEmployeeController::class, 'update'])->middleware('permission:hr.payroll_runs.manage');

                Route::get('loans', [Hr\EmployeeLoanController::class, 'index'])->middleware('permission:hr.loans.view');
                Route::post('loans', [Hr\EmployeeLoanController::class, 'store'])->middleware('permission:hr.loans.manage');
                Route::get('loans/{employeeLoan}', [Hr\EmployeeLoanController::class, 'show'])->middleware('permission:hr.loans.view');
                Route::delete('loans/{employeeLoan}', [Hr\EmployeeLoanController::class, 'destroy'])->middleware('permission:hr.loans.manage');
                Route::post('loans/{employeeLoan}/submit', [Hr\EmployeeLoanController::class, 'submit'])->middleware('permission:hr.loans.manage');
                Route::post('loans/{employeeLoan}/approve', [Hr\EmployeeLoanController::class, 'approve'])->middleware('permission:hr.loans.view');
                Route::post('loans/{employeeLoan}/reject', [Hr\EmployeeLoanController::class, 'reject'])->middleware('permission:hr.loans.view');

                Route::get('salary-advances', [Hr\SalaryAdvanceController::class, 'index'])->middleware('permission:hr.advances.view');
                Route::post('salary-advances', [Hr\SalaryAdvanceController::class, 'store'])->middleware('permission:hr.advances.manage');
                Route::get('salary-advances/{salaryAdvance}', [Hr\SalaryAdvanceController::class, 'show'])->middleware('permission:hr.advances.view');
                Route::delete('salary-advances/{salaryAdvance}', [Hr\SalaryAdvanceController::class, 'destroy'])->middleware('permission:hr.advances.manage');
                Route::post('salary-advances/{salaryAdvance}/submit', [Hr\SalaryAdvanceController::class, 'submit'])->middleware('permission:hr.advances.manage');
                Route::post('salary-advances/{salaryAdvance}/approve', [Hr\SalaryAdvanceController::class, 'approve'])->middleware('permission:hr.advances.view');
                Route::post('salary-advances/{salaryAdvance}/reject', [Hr\SalaryAdvanceController::class, 'reject'])->middleware('permission:hr.advances.view');

                Route::get('overtime-requests', [Hr\OvertimeRequestController::class, 'index'])->middleware('permission:hr.overtime.view');
                Route::post('overtime-requests', [Hr\OvertimeRequestController::class, 'store'])->middleware('permission:hr.overtime.manage');
                Route::delete('overtime-requests/{overtimeRequest}', [Hr\OvertimeRequestController::class, 'destroy'])->middleware('permission:hr.overtime.manage');
                Route::post('overtime-requests/{overtimeRequest}/approve', [Hr\OvertimeRequestController::class, 'approve'])->middleware('permission:hr.overtime.approve');
                Route::post('overtime-requests/{overtimeRequest}/reject', [Hr\OvertimeRequestController::class, 'reject'])->middleware('permission:hr.overtime.approve');

                Route::post('payroll-runs/{payrollRun}/post-to-accounting', [Hr\PayrollRunController::class, 'postToAccounting'])->middleware('permission:hr.payroll_runs.approve');

                // No permission middleware: authorization is per-record inside the
                // controller (own payslip, or hr.payslips.view.all for staff) so
                // employee self-service access isn't blocked by an HR permission.
                Route::get('payslips', [Hr\PayslipController::class, 'index']);
                Route::get('payslips/{payslip}', [Hr\PayslipController::class, 'show']);
                Route::get('payslips/{payslip}/pdf', [Hr\PayslipController::class, 'pdf']);

                Route::post('payroll-runs/{payrollRun}/salary-payments', [Hr\SalaryPaymentBatchController::class, 'store'])->middleware('permission:hr.payroll_runs.manage');
                Route::get('salary-payment-batches/{salaryPaymentBatch}', [Hr\SalaryPaymentBatchController::class, 'show'])->middleware('permission:hr.payroll_runs.view');
                Route::get('salary-payment-batches/{salaryPaymentBatch}/export', [Hr\SalaryPaymentBatchController::class, 'exportCsv'])->middleware('permission:hr.payroll_runs.view');
                Route::put('salary-payments/{salaryPayment}', [Hr\SalaryPaymentBatchController::class, 'updatePayment'])->middleware('permission:hr.payroll_runs.manage');

                // No permission middleware on index/show: PerformanceReviewController enforces
                // per-record access internally (own reviews, or hr.performance.view.all for staff).
                Route::get('performance-reviews', [Hr\PerformanceReviewController::class, 'index']);
                Route::post('performance-reviews', [Hr\PerformanceReviewController::class, 'store'])->middleware('permission:hr.performance.manage');
                Route::get('performance-reviews/{performanceReview}', [Hr\PerformanceReviewController::class, 'show']);
                Route::delete('performance-reviews/{performanceReview}', [Hr\PerformanceReviewController::class, 'destroy'])->middleware('permission:hr.performance.manage');
                Route::post('performance-reviews/{performanceReview}/submit', [Hr\PerformanceReviewController::class, 'submit'])->middleware('permission:hr.performance.manage');
                Route::post('performance-reviews/{performanceReview}/acknowledge', [Hr\PerformanceReviewController::class, 'acknowledge']);

                Route::get('disciplinary-records', [Hr\DisciplinaryRecordController::class, 'index'])->middleware('permission:hr.disciplinary.view');
                Route::post('disciplinary-records', [Hr\DisciplinaryRecordController::class, 'store'])->middleware('permission:hr.disciplinary.manage');
                Route::get('disciplinary-records/{disciplinaryRecord}', [Hr\DisciplinaryRecordController::class, 'show'])->middleware('permission:hr.disciplinary.view');
                Route::delete('disciplinary-records/{disciplinaryRecord}', [Hr\DisciplinaryRecordController::class, 'destroy'])->middleware('permission:hr.disciplinary.manage');
                Route::post('disciplinary-records/{disciplinaryRecord}/acknowledge', [Hr\DisciplinaryRecordController::class, 'acknowledge'])->middleware('permission:hr.disciplinary.manage');
                Route::post('disciplinary-records/{disciplinaryRecord}/resolve', [Hr\DisciplinaryRecordController::class, 'resolve'])->middleware('permission:hr.disciplinary.manage');

                Route::get('employee-assets', [Hr\EmployeeAssetController::class, 'index'])->middleware('permission:hr.assets.view');
                Route::post('employee-assets', [Hr\EmployeeAssetController::class, 'store'])->middleware('permission:hr.assets.manage');
                Route::get('employee-assets/{employeeAsset}', [Hr\EmployeeAssetController::class, 'show'])->middleware('permission:hr.assets.view');
                Route::delete('employee-assets/{employeeAsset}', [Hr\EmployeeAssetController::class, 'destroy'])->middleware('permission:hr.assets.manage');
                Route::post('employee-assets/{employeeAsset}/return', [Hr\EmployeeAssetController::class, 'returnAsset'])->middleware('permission:hr.assets.manage');

                Route::get('exit-records', [Hr\ExitRecordController::class, 'index'])->middleware('permission:hr.exits.view');
                Route::post('exit-records', [Hr\ExitRecordController::class, 'store'])->middleware('permission:hr.exits.manage');
                Route::get('exit-records/{exitRecord}', [Hr\ExitRecordController::class, 'show'])->middleware('permission:hr.exits.view');
                Route::put('exit-records/{exitRecord}', [Hr\ExitRecordController::class, 'update'])->middleware('permission:hr.exits.manage');
                Route::post('exit-records/{exitRecord}/complete', [Hr\ExitRecordController::class, 'complete'])->middleware('permission:hr.exits.manage');

                Route::get('job-vacancies', [Hr\JobVacancyController::class, 'index'])->middleware('permission:hr.recruitment.view');
                Route::post('job-vacancies', [Hr\JobVacancyController::class, 'store'])->middleware('permission:hr.recruitment.manage');
                Route::get('job-vacancies/{jobVacancy}', [Hr\JobVacancyController::class, 'show'])->middleware('permission:hr.recruitment.view');
                Route::put('job-vacancies/{jobVacancy}', [Hr\JobVacancyController::class, 'update'])->middleware('permission:hr.recruitment.manage');
                Route::delete('job-vacancies/{jobVacancy}', [Hr\JobVacancyController::class, 'destroy'])->middleware('permission:hr.recruitment.manage');
                Route::post('job-vacancies/{jobVacancy}/close', [Hr\JobVacancyController::class, 'close'])->middleware('permission:hr.recruitment.manage');

                Route::get('candidates', [Hr\CandidateController::class, 'index'])->middleware('permission:hr.recruitment.view');
                Route::post('candidates', [Hr\CandidateController::class, 'store'])->middleware('permission:hr.recruitment.manage');
                Route::get('candidates/{candidate}', [Hr\CandidateController::class, 'show'])->middleware('permission:hr.recruitment.view');
                Route::put('candidates/{candidate}', [Hr\CandidateController::class, 'update'])->middleware('permission:hr.recruitment.manage');
                Route::delete('candidates/{candidate}', [Hr\CandidateController::class, 'destroy'])->middleware('permission:hr.recruitment.manage');

                Route::get('job-applications', [Hr\JobApplicationController::class, 'index'])->middleware('permission:hr.recruitment.view');
                Route::post('job-applications', [Hr\JobApplicationController::class, 'store'])->middleware('permission:hr.recruitment.manage');
                Route::get('job-applications/{jobApplication}', [Hr\JobApplicationController::class, 'show'])->middleware('permission:hr.recruitment.view');
                Route::put('job-applications/{jobApplication}/status', [Hr\JobApplicationController::class, 'updateStatus'])->middleware('permission:hr.recruitment.manage');
                Route::post('job-applications/{jobApplication}/hire', [Hr\JobApplicationController::class, 'hire'])->middleware('permission:hr.recruitment.manage');
                Route::delete('job-applications/{jobApplication}', [Hr\JobApplicationController::class, 'destroy'])->middleware('permission:hr.recruitment.manage');

                Route::post('interviews', [Hr\InterviewController::class, 'store'])->middleware('permission:hr.recruitment.manage');
                Route::get('interviews/{interview}', [Hr\InterviewController::class, 'show'])->middleware('permission:hr.recruitment.view');
                Route::post('interviews/{interview}/complete', [Hr\InterviewController::class, 'complete'])->middleware('permission:hr.recruitment.manage');
                Route::delete('interviews/{interview}', [Hr\InterviewController::class, 'destroy'])->middleware('permission:hr.recruitment.manage');

                Route::get('onboarding-checklists', [Hr\OnboardingChecklistController::class, 'index'])->middleware('permission:hr.onboarding.view');
                Route::get('onboarding-checklists/{onboardingChecklist}', [Hr\OnboardingChecklistController::class, 'show'])->middleware('permission:hr.onboarding.view');
                Route::post('onboarding-checklists/{onboardingChecklist}/tasks', [Hr\OnboardingChecklistController::class, 'storeTask'])->middleware('permission:hr.onboarding.manage');
                Route::post('onboarding-tasks/{onboardingTask}/toggle', [Hr\OnboardingChecklistController::class, 'toggleTask'])->middleware('permission:hr.onboarding.manage');
                Route::delete('onboarding-tasks/{onboardingTask}', [Hr\OnboardingChecklistController::class, 'destroyTask'])->middleware('permission:hr.onboarding.manage');
            });

            Route::prefix('accounting')->group(function () {
                Route::get('accounts', [Accounting\AccountController::class, 'index'])->middleware('permission:accounting.accounts.view');
                Route::post('accounts', [Accounting\AccountController::class, 'store'])->middleware('permission:accounting.accounts.manage');
                Route::get('accounts/{account}', [Accounting\AccountController::class, 'show'])->middleware('permission:accounting.accounts.view');
                Route::put('accounts/{account}', [Accounting\AccountController::class, 'update'])->middleware('permission:accounting.accounts.manage');
                Route::delete('accounts/{account}', [Accounting\AccountController::class, 'destroy'])->middleware('permission:accounting.accounts.manage');

                Route::get('journal-entries', [Accounting\JournalEntryController::class, 'index'])->middleware('permission:accounting.journal.view');
                Route::post('journal-entries', [Accounting\JournalEntryController::class, 'store'])->middleware('permission:accounting.journal.manage');
                Route::get('journal-entries/{journalEntry}', [Accounting\JournalEntryController::class, 'show'])->middleware('permission:accounting.journal.view');
                Route::put('journal-entries/{journalEntry}', [Accounting\JournalEntryController::class, 'update'])->middleware('permission:accounting.journal.manage');
                Route::delete('journal-entries/{journalEntry}', [Accounting\JournalEntryController::class, 'destroy'])->middleware('permission:accounting.journal.manage');
                Route::post('journal-entries/{journalEntry}/post', [Accounting\JournalEntryController::class, 'post'])->middleware('permission:accounting.journal.post');
                Route::post('journal-entries/{journalEntry}/void', [Accounting\JournalEntryController::class, 'void'])->middleware('permission:accounting.journal.post');
            });

            Route::prefix('documents')->group(function () {
                Route::get('files', [Documents\DocumentController::class, 'index'])->middleware('permission:documents.files.view');
                Route::post('files', [Documents\DocumentController::class, 'store'])->middleware('permission:documents.files.manage');
                Route::get('files/{document}', [Documents\DocumentController::class, 'show'])->middleware('permission:documents.files.view');
                Route::get('files/{document}/versions', [Documents\DocumentController::class, 'versions'])->middleware('permission:documents.files.view');
                Route::delete('files/{document}', [Documents\DocumentController::class, 'destroy'])->middleware('permission:documents.files.manage');
            });

            Route::prefix('quotations')->group(function () {
                Route::get('items', [Quotations\QuotationController::class, 'index'])->middleware('permission:quotations.items.view');
                Route::post('items', [Quotations\QuotationController::class, 'store'])->middleware('permission:quotations.items.manage');
                Route::get('items/{quotation}', [Quotations\QuotationController::class, 'show'])->middleware('permission:quotations.items.view');
                Route::put('items/{quotation}', [Quotations\QuotationController::class, 'update'])->middleware('permission:quotations.items.manage');
                Route::delete('items/{quotation}', [Quotations\QuotationController::class, 'destroy'])->middleware('permission:quotations.items.manage');
                Route::post('items/{quotation}/convert-to-shipment', [Quotations\QuotationController::class, 'convertToShipment'])->middleware('permission:quotations.items.manage');
                Route::post('items/{quotation}/submit', [Quotations\QuotationController::class, 'submit'])->middleware('permission:quotations.items.manage');
                Route::post('items/{quotation}/approve', [Quotations\QuotationController::class, 'approve'])->middleware('permission:quotations.items.approve');
                Route::post('items/{quotation}/reject', [Quotations\QuotationController::class, 'reject'])->middleware('permission:quotations.items.approve');
            });

            Route::prefix('shipments')->group(function () {
                Route::get('items', [Shipments\ShipmentController::class, 'index'])->middleware('permission:shipments.items.view');
                Route::post('items', [Shipments\ShipmentController::class, 'store'])->middleware('permission:shipments.items.manage');
                Route::get('items/{shipment}', [Shipments\ShipmentController::class, 'show'])->middleware('permission:shipments.items.view');
                Route::put('items/{shipment}', [Shipments\ShipmentController::class, 'update'])->middleware('permission:shipments.items.manage');
                Route::delete('items/{shipment}', [Shipments\ShipmentController::class, 'destroy'])->middleware('permission:shipments.items.manage');
                Route::get('items/{shipment}/tracking-qr', [Shipments\ShipmentController::class, 'trackingQr'])->middleware('permission:shipments.items.view');
                Route::get('items/{shipment}/delivery-note-qr', [Shipments\ShipmentController::class, 'deliveryNoteQr'])->middleware('permission:shipments.items.view');
                Route::get('items/{shipment}/cost-summary', [Shipments\ShipmentController::class, 'costSummary'])->middleware('permission:shipments.costs.view');
                Route::post('sla-check', [Shipments\ShipmentController::class, 'checkSla'])->middleware('permission:shipments.items.manage');
                Route::post('items/{shipment}/milestones', [Shipments\ShipmentMilestoneController::class, 'store'])->middleware('permission:shipments.items.manage');
                Route::get('items/{shipment}/proof-of-delivery', [Shipments\ProofOfDeliveryController::class, 'show'])->middleware('permission:shipments.items.view');
                Route::post('items/{shipment}/proof-of-delivery', [Shipments\ProofOfDeliveryController::class, 'store'])->middleware('permission:shipments.items.manage');
                Route::get('items/{shipment}/delay-risk', [Shipments\ShipmentController::class, 'delayRisk'])->middleware('permission:shipments.items.view');
            });

            Route::prefix('portal')->middleware('portal')->group(function () {
                Route::get('dashboard/summary', [Portal\PortalDashboardController::class, 'summary'])->middleware('permission:portal.access');

                Route::get('shipments', [Portal\PortalShipmentController::class, 'index'])->middleware('permission:portal.access');
                Route::get('shipments/{shipment}', [Portal\PortalShipmentController::class, 'show'])->middleware('permission:portal.access');
                Route::get('shipments/{shipment}/tracking-qr', [Portal\PortalShipmentController::class, 'trackingQr'])->middleware('permission:portal.access');
                Route::get('shipments/{shipment}/proof-of-delivery', [Portal\PortalShipmentController::class, 'proofOfDelivery'])->middleware('permission:portal.access');

                Route::get('invoices', [Portal\PortalInvoiceController::class, 'index'])->middleware('permission:portal.access');
                Route::get('invoices/{invoice}', [Portal\PortalInvoiceController::class, 'show'])->middleware('permission:portal.access');
                Route::get('invoices/{invoice}/pdf', [Portal\PortalInvoiceController::class, 'pdf'])->middleware('permission:portal.access');

                Route::get('quotations', [Portal\PortalQuotationController::class, 'index'])->middleware('permission:portal.access');
                Route::get('quotations/{quotation}', [Portal\PortalQuotationController::class, 'show'])->middleware('permission:portal.access');
                Route::post('quotations/{quotation}/approve', [Portal\PortalQuotationController::class, 'approve'])->middleware('permission:portal.quotations.approve');
                Route::post('quotations/{quotation}/reject', [Portal\PortalQuotationController::class, 'reject'])->middleware('permission:portal.quotations.approve');

                Route::get('documents', [Portal\PortalDocumentController::class, 'index'])->middleware('permission:portal.access');
                Route::post('documents', [Portal\PortalDocumentController::class, 'store'])->middleware('permission:portal.documents.upload');

                Route::get('messages', [Portal\PortalMessageController::class, 'index'])->middleware('permission:portal.access');
                Route::post('messages', [Portal\PortalMessageController::class, 'store'])->middleware('permission:portal.messages.send');

                Route::get('api-keys', [Portal\PortalApiKeyController::class, 'index'])->middleware('permission:portal.api_keys.manage');
                Route::post('api-keys', [Portal\PortalApiKeyController::class, 'store'])->middleware('permission:portal.api_keys.manage');
                Route::delete('api-keys/{apiKey}', [Portal\PortalApiKeyController::class, 'destroy'])->middleware('permission:portal.api_keys.manage');
            });
        });

        Route::prefix('platform')->middleware('platform.admin')->group(function () {
            Route::get('tenants', [Platform\TenantManagementController::class, 'index']);
            Route::get('tenants/{tenant}', [Platform\TenantManagementController::class, 'show']);
            Route::post('tenants/{tenant}/suspend', [Platform\TenantManagementController::class, 'suspend']);
            Route::post('tenants/{tenant}/activate', [Platform\TenantManagementController::class, 'activate']);
            Route::apiResource('plans', Platform\PlanController::class);
            Route::get('landing-content', [Platform\LandingContentController::class, 'index']);
            Route::put('landing-content/{key}', [Platform\LandingContentController::class, 'update']);
            Route::post('landing-content/upload-image', [Platform\LandingContentController::class, 'uploadImage']);
            Route::get('subscriptions', [Platform\SubscriptionController::class, 'index']);
            Route::get('metrics', [Platform\MetricsController::class, 'index']);
            Route::get('system-health', [Platform\SystemHealthController::class, 'index']);
            Route::get('audit-logs', [Platform\AuditLogController::class, 'index']);
            Route::get('error-logs', [Platform\ErrorLogController::class, 'index']);
            Route::get('error-logs/{errorLog}', [Platform\ErrorLogController::class, 'show']);
            Route::post('error-logs/{errorLog}/resolve', [Platform\ErrorLogController::class, 'resolve']);
        });
    });

    // Client API: external integration surface for customers, authenticated by a
    // plaintext API key (not a Sanctum session token) generated from the portal's
    // "API Keys" page. Deliberately outside the auth:sanctum group above.
    Route::prefix('client-api')->middleware(['client-api-key', 'throttle:60,1'])->group(function () {
        Route::get('shipments', [ClientApi\ShipmentController::class, 'index']);
        Route::get('shipments/{shipment}', [ClientApi\ShipmentController::class, 'show']);
        Route::get('invoices', [ClientApi\InvoiceController::class, 'index']);
        Route::get('invoices/{invoice}', [ClientApi\InvoiceController::class, 'show']);
        Route::get('quotations', [ClientApi\QuotationController::class, 'index']);
        Route::get('quotations/{quotation}', [ClientApi\QuotationController::class, 'show']);
    });
});
