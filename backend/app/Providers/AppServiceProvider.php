<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Contracts\IdentityVerificationProvider;
use App\Contracts\Notifications\SmsChannel;
use App\Contracts\Notifications\WhatsAppChannel;
use App\Models\Account;
use App\Models\Candidate;
use App\Models\ClearingFile;
use App\Models\Container;
use App\Models\Customer;
use App\Models\CustomerMessage;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\DemurrageCharge;
use App\Models\Designation;
use App\Models\DisciplinaryRecord;
use App\Models\Document;
use App\Models\Employee;
use App\Models\EmployeeAsset;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\EmployeeShift;
use App\Models\ExitRecord;
use App\Models\Expense;
use App\Models\FreightBooking;
use App\Models\Invoice;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\JournalEntry;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollComponent;
use App\Models\EmployeeLoan;
use App\Models\EmployeePayrollComponent;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSettings;
use App\Models\PerformanceReview;
use App\Models\Payslip;
use App\Models\PublicHoliday;
use App\Models\Quotation;
use App\Models\Shift;
use App\Models\SalaryAdvance;
use App\Models\SalaryPayment;
use App\Models\SalaryPaymentBatch;
use App\Models\Shipment;
use App\Models\StatutoryContributionRule;
use App\Models\StatutoryRuleSet;
use App\Models\StatutoryTaxBand;
use App\Models\Timesheet;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarehouseItem;
use App\Observers\AccountObserver;
use App\Observers\AttendanceRecordObserver;
use App\Observers\CandidateObserver;
use App\Observers\ClearingFileObserver;
use App\Observers\ContainerObserver;
use App\Observers\CustomerMessageObserver;
use App\Observers\CustomerObserver;
use App\Observers\DemurrageChargeObserver;
use App\Observers\DepartmentObserver;
use App\Observers\DesignationObserver;
use App\Observers\DisciplinaryRecordObserver;
use App\Observers\DocumentObserver;
use App\Observers\EmployeeAssetObserver;
use App\Observers\EmployeeContractObserver;
use App\Observers\EmployeeDocumentObserver;
use App\Observers\EmployeeObserver;
use App\Observers\EmployeeShiftObserver;
use App\Observers\ExitRecordObserver;
use App\Observers\ExpenseObserver;
use App\Observers\FreightBookingObserver;
use App\Observers\InvoiceObserver;
use App\Observers\JobApplicationObserver;
use App\Observers\JobVacancyObserver;
use App\Observers\JournalEntryObserver;
use App\Observers\LeadObserver;
use App\Observers\LeaveRequestObserver;
use App\Observers\LeaveTypeObserver;
use App\Observers\PayrollComponentObserver;
use App\Observers\EmployeePayrollComponentObserver;
use App\Observers\EmployeeLoanObserver;
use App\Observers\OvertimeRequestObserver;
use App\Observers\PayrollPeriodObserver;
use App\Observers\PayrollRunObserver;
use App\Observers\PayrollSettingsObserver;
use App\Observers\PayslipObserver;
use App\Observers\PerformanceReviewObserver;
use App\Observers\SalaryAdvanceObserver;
use App\Observers\SalaryPaymentBatchObserver;
use App\Observers\SalaryPaymentObserver;
use App\Observers\PublicHolidayObserver;
use App\Observers\QuotationObserver;
use App\Observers\ShiftObserver;
use App\Observers\ShipmentObserver;
use App\Observers\StatutoryContributionRuleObserver;
use App\Observers\StatutoryRuleSetObserver;
use App\Observers\StatutoryTaxBandObserver;
use App\Observers\TimesheetObserver;
use App\Observers\TrackingEventObserver;
use App\Observers\UserObserver;
use App\Observers\VehicleObserver;
use App\Observers\WarehouseItemObserver;
use App\Services\Identity\IdentityProviderFactory;
use App\Services\Notifications\Channels\BeemSmsChannel;
use App\Services\Notifications\Channels\LogSmsChannel;
use App\Services\Notifications\Channels\LogWhatsAppChannel;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(
            AnthropicClient::class,
            fn () => new AnthropicClient(apiKey: config('services.anthropic.api_key') ?: 'not-configured'),
        );
        $this->app->bind(IdentityVerificationProvider::class, fn () => IdentityProviderFactory::make());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn ($request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        Event::subscribe(\App\Listeners\Identity\IdentityAuditSubscriber::class);
        Event::subscribe(\App\Listeners\Identity\IdentityNotificationSubscriber::class);

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
        Designation::observe(DesignationObserver::class);
        EmployeeDocument::observe(EmployeeDocumentObserver::class);
        EmployeeContract::observe(EmployeeContractObserver::class);
        Shift::observe(ShiftObserver::class);
        EmployeeShift::observe(EmployeeShiftObserver::class);
        Timesheet::observe(TimesheetObserver::class);
        LeaveType::observe(LeaveTypeObserver::class);
        LeaveRequest::observe(LeaveRequestObserver::class);
        PublicHoliday::observe(PublicHolidayObserver::class);
        PayrollComponent::observe(PayrollComponentObserver::class);
        EmployeePayrollComponent::observe(EmployeePayrollComponentObserver::class);
        StatutoryRuleSet::observe(StatutoryRuleSetObserver::class);
        StatutoryTaxBand::observe(StatutoryTaxBandObserver::class);
        StatutoryContributionRule::observe(StatutoryContributionRuleObserver::class);
        PayrollSettings::observe(PayrollSettingsObserver::class);
        PayrollPeriod::observe(PayrollPeriodObserver::class);
        PayrollRun::observe(PayrollRunObserver::class);
        EmployeeLoan::observe(EmployeeLoanObserver::class);
        SalaryAdvance::observe(SalaryAdvanceObserver::class);
        OvertimeRequest::observe(OvertimeRequestObserver::class);
        Payslip::observe(PayslipObserver::class);
        SalaryPaymentBatch::observe(SalaryPaymentBatchObserver::class);
        SalaryPayment::observe(SalaryPaymentObserver::class);
        PerformanceReview::observe(PerformanceReviewObserver::class);
        DisciplinaryRecord::observe(DisciplinaryRecordObserver::class);
        EmployeeAsset::observe(EmployeeAssetObserver::class);
        ExitRecord::observe(ExitRecordObserver::class);
        JobVacancy::observe(JobVacancyObserver::class);
        Candidate::observe(CandidateObserver::class);
        JobApplication::observe(JobApplicationObserver::class);
    }
}
