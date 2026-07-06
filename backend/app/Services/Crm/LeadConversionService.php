<?php

namespace App\Services\Crm;

use App\Enums\CustomerStatus;
use App\Enums\LeadStatus;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class LeadConversionService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Convert a lead into a customer, auto-creating a primary contact from
     * the lead's contact info, in one atomic transaction.
     */
    public function convert(Lead $lead): Customer
    {
        abort_if($lead->status === LeadStatus::Converted, 409, 'Lead is already converted.');

        return DB::transaction(function () use ($lead) {
            $customer = Customer::query()->create([
                'tenant_id' => $lead->tenant_id,
                'company_name' => $lead->company_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'status' => CustomerStatus::Active,
                'assigned_to' => $lead->assigned_to,
            ]);

            Contact::query()->create([
                'tenant_id' => $lead->tenant_id,
                'customer_id' => $customer->id,
                'name' => $lead->contact_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'is_primary' => true,
            ]);

            $lead->update([
                'status' => LeadStatus::Converted,
                'converted_customer_id' => $customer->id,
            ]);

            $this->auditLogger->log(
                action: 'lead.converted',
                auditable: $lead,
                newValues: ['customer_id' => $customer->id, 'company_name' => $customer->company_name],
                tenantId: $lead->tenant_id,
            );

            return $customer;
        });
    }
}
