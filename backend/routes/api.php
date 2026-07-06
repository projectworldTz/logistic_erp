<?php

use App\Http\Controllers\Api\V1\Accounting;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Clearing;
use App\Http\Controllers\Api\V1\Containers;
use App\Http\Controllers\Api\V1\Crm;
use App\Http\Controllers\Api\V1\Demurrage;
use App\Http\Controllers\Api\V1\Documents;
use App\Http\Controllers\Api\V1\Finance;
use App\Http\Controllers\Api\V1\Fleet;
use App\Http\Controllers\Api\V1\Freight;
use App\Http\Controllers\Api\V1\Platform;
use App\Http\Controllers\Api\V1\Portal;
use App\Http\Controllers\Api\V1\Public;
use App\Http\Controllers\Api\V1\Quotations;
use App\Http\Controllers\Api\V1\Shipments;
use App\Http\Controllers\Api\V1\Tenant;
use App\Http\Controllers\Api\V1\Warehouse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('auth/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
    Route::post('tenants/register', [Tenant\TenantRegistrationController::class, 'store'])->middleware('throttle:5,1');
    Route::get('plans', [Public\PlanController::class, 'index']);
    Route::get('landing-content', [Public\LandingContentController::class, 'index']);
    Route::post('contact', [Public\ContactController::class, 'store'])->middleware('throttle:5,1');
    Route::post('demo-requests', [Public\DemoRequestController::class, 'store'])->middleware('throttle:5,1');
    Route::get('public/track/{trackingCode}', [Public\ShipmentTrackingController::class, 'show'])->middleware('throttle:30,1');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::middleware('tenant')->group(function () {
            Route::get('dashboard/summary', [Tenant\DashboardController::class, 'summary']);
            Route::get('reports/overview', [Tenant\ReportsController::class, 'overview'])->middleware('permission:reports.view');
            Route::get('analytics/overview', [Tenant\AnalyticsController::class, 'overview'])->middleware('permission:analytics.view');
            Route::get('company', [Tenant\CompanyController::class, 'show']);
            Route::put('company', [Tenant\CompanyController::class, 'update']);
            Route::get('branches', [Tenant\BranchController::class, 'index']);
            Route::get('users', [Tenant\UserController::class, 'index'])->middleware('permission:core.users.view');
            Route::get('roles', [Tenant\RoleController::class, 'index'])->middleware('permission:core.users.view');
            Route::post('users', [Tenant\UserController::class, 'store'])->middleware('permission:core.users.manage');
            Route::put('users/{user}', [Tenant\UserController::class, 'update'])->middleware('permission:core.users.manage');
            Route::post('users/{user}/suspend', [Tenant\UserController::class, 'suspend'])->middleware('permission:core.users.manage');
            Route::post('users/{user}/activate', [Tenant\UserController::class, 'activate'])->middleware('permission:core.users.manage');
            Route::get('audit-logs', [Tenant\AuditLogController::class, 'index']);
            Route::get('notifications', [Tenant\NotificationController::class, 'index']);
            Route::get('notifications/unread-count', [Tenant\NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [Tenant\NotificationController::class, 'markAllRead']);
            Route::post('notifications/{notification}/read', [Tenant\NotificationController::class, 'markRead']);
            Route::get('search', [Tenant\SearchController::class, 'index']);

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
            });

            Route::prefix('clearing')->group(function () {
                Route::get('files', [Clearing\ClearingFileController::class, 'index'])->middleware('permission:clearing.files.view');
                Route::post('files', [Clearing\ClearingFileController::class, 'store'])->middleware('permission:clearing.files.manage');
                Route::get('files/{clearingFile}', [Clearing\ClearingFileController::class, 'show'])->middleware('permission:clearing.files.view');
                Route::put('files/{clearingFile}', [Clearing\ClearingFileController::class, 'update'])->middleware('permission:clearing.files.manage');
                Route::delete('files/{clearingFile}', [Clearing\ClearingFileController::class, 'destroy'])->middleware('permission:clearing.files.manage');
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
            });

            Route::prefix('finance')->group(function () {
                Route::get('invoices', [Finance\InvoiceController::class, 'index'])->middleware('permission:finance.invoices.view');
                Route::post('invoices', [Finance\InvoiceController::class, 'store'])->middleware('permission:finance.invoices.manage');
                Route::get('invoices/{invoice}', [Finance\InvoiceController::class, 'show'])->middleware('permission:finance.invoices.view');
                Route::get('invoices/{invoice}/pdf', [Finance\InvoiceController::class, 'pdf'])->middleware('permission:finance.invoices.view');
                Route::put('invoices/{invoice}', [Finance\InvoiceController::class, 'update'])->middleware('permission:finance.invoices.manage');
                Route::delete('invoices/{invoice}', [Finance\InvoiceController::class, 'destroy'])->middleware('permission:finance.invoices.manage');
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
                Route::delete('files/{document}', [Documents\DocumentController::class, 'destroy'])->middleware('permission:documents.files.manage');
            });

            Route::prefix('quotations')->group(function () {
                Route::get('items', [Quotations\QuotationController::class, 'index'])->middleware('permission:quotations.items.view');
                Route::post('items', [Quotations\QuotationController::class, 'store'])->middleware('permission:quotations.items.manage');
                Route::get('items/{quotation}', [Quotations\QuotationController::class, 'show'])->middleware('permission:quotations.items.view');
                Route::put('items/{quotation}', [Quotations\QuotationController::class, 'update'])->middleware('permission:quotations.items.manage');
                Route::delete('items/{quotation}', [Quotations\QuotationController::class, 'destroy'])->middleware('permission:quotations.items.manage');
            });

            Route::prefix('shipments')->group(function () {
                Route::get('items', [Shipments\ShipmentController::class, 'index'])->middleware('permission:shipments.items.view');
                Route::post('items', [Shipments\ShipmentController::class, 'store'])->middleware('permission:shipments.items.manage');
                Route::get('items/{shipment}', [Shipments\ShipmentController::class, 'show'])->middleware('permission:shipments.items.view');
                Route::put('items/{shipment}', [Shipments\ShipmentController::class, 'update'])->middleware('permission:shipments.items.manage');
                Route::delete('items/{shipment}', [Shipments\ShipmentController::class, 'destroy'])->middleware('permission:shipments.items.manage');
                Route::post('items/{shipment}/milestones', [Shipments\ShipmentMilestoneController::class, 'store'])->middleware('permission:shipments.items.manage');
            });

            Route::prefix('portal')->middleware('portal')->group(function () {
                Route::get('dashboard/summary', [Portal\PortalDashboardController::class, 'summary'])->middleware('permission:portal.access');

                Route::get('shipments', [Portal\PortalShipmentController::class, 'index'])->middleware('permission:portal.access');
                Route::get('shipments/{shipment}', [Portal\PortalShipmentController::class, 'show'])->middleware('permission:portal.access');

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
            Route::get('audit-logs', [Platform\AuditLogController::class, 'index']);
            Route::get('error-logs', [Platform\ErrorLogController::class, 'index']);
            Route::get('error-logs/{errorLog}', [Platform\ErrorLogController::class, 'show']);
            Route::post('error-logs/{errorLog}/resolve', [Platform\ErrorLogController::class, 'resolve']);
        });
    });
});
