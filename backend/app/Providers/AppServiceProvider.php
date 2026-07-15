<?php

namespace App\Providers;

use App\Contracts\Notifications\SmsChannel;
use App\Contracts\Notifications\WhatsAppChannel;
use App\Models\Account;
use App\Models\ClearingFile;
use App\Models\Container;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\DemurrageCharge;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\FreightBooking;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarehouseItem;
use App\Observers\AccountObserver;
use App\Observers\AttendanceRecordObserver;
use App\Observers\ClearingFileObserver;
use App\Observers\ContainerObserver;
use App\Observers\CustomerMessageObserver;
use App\Observers\CustomerObserver;
use App\Observers\DemurrageChargeObserver;
use App\Observers\DepartmentObserver;
use App\Observers\DocumentObserver;
use App\Observers\EmployeeObserver;
use App\Observers\ExpenseObserver;
use App\Observers\FreightBookingObserver;
use App\Observers\InvoiceObserver;
use App\Observers\JournalEntryObserver;
use App\Observers\LeadObserver;
use App\Observers\QuotationObserver;
use App\Observers\ShipmentObserver;
use App\Observers\TrackingEventObserver;
use App\Observers\UserObserver;
use App\Observers\VehicleObserver;
use App\Observers\WarehouseItemObserver;
use App\Services\Notifications\Channels\BeemSmsChannel;
use App\Services\Notifications\Channels\LogSmsChannel;
use App\Services\Notifications\Channels\LogWhatsAppChannel;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(
            SmsChannel::class,
            fn () => config('services.beem.api_key') ? new BeemSmsChannel() : new LogSmsChannel(),
        );
        $this->app->bind(WhatsAppChannel::class, LogWhatsAppChannel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn ($request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        User::observe(UserObserver::class);
        Lead::observe(LeadObserver::class);
        Customer::observe(CustomerObserver::class);
        ClearingFile::observe(ClearingFileObserver::class);
        FreightBooking::observe(FreightBookingObserver::class);
        Container::observe(ContainerObserver::class);
        WarehouseItem::observe(WarehouseItemObserver::class);
        Vehicle::observe(VehicleObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Account::observe(AccountObserver::class);
        JournalEntry::observe(JournalEntryObserver::class);
        Document::observe(DocumentObserver::class);
        Quotation::observe(QuotationObserver::class);
        Shipment::observe(ShipmentObserver::class);
        TrackingEvent::observe(TrackingEventObserver::class);
        CustomerMessage::observe(CustomerMessageObserver::class);
        DemurrageCharge::observe(DemurrageChargeObserver::class);
        Expense::observe(ExpenseObserver::class);
        Department::observe(DepartmentObserver::class);
        Employee::observe(EmployeeObserver::class);
        AttendanceRecord::observe(AttendanceRecordObserver::class);
    }
}
