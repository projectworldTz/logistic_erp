<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\ClearingFile;
use App\Models\Container;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\DemurrageCharge;
use App\Models\Document;
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
use App\Observers\ClearingFileObserver;
use App\Observers\ContainerObserver;
use App\Observers\CustomerMessageObserver;
use App\Observers\CustomerObserver;
use App\Observers\DemurrageChargeObserver;
use App\Observers\DocumentObserver;
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
    }
}
