<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\ContainerStatus;
use App\Enums\ContainerType;
use App\Enums\CustomerStatus;
use App\Enums\DocumentCategory;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Enums\ShipmentStatus;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Enums\WarehouseItemStatus;
use App\Models\Account;
use App\Models\ClearingFile;
use App\Models\Contact;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Document;
use App\Models\FreightBooking;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WarehouseItem;
use Illuminate\Database\Seeder;

/**
 * Populates realistic demo data across every ERP module for a single
 * existing tenant, identified by its owner's email. Not part of the
 * standard install seed list — run explicitly:
 *   php artisan db:seed --class=DemoTenantDataSeeder
 */
class DemoTenantDataSeeder extends Seeder
{
    private const OWNER_EMAIL = 'test@gmail.com';

    public function run(): void
    {
        $owner = User::where('email', self::OWNER_EMAIL)->firstOrFail();
        $tenantId = $owner->tenant_id;
        $branchId = $owner->branch_id;

        $customers = $this->seedCustomers($tenantId, $owner->id);
        $this->seedLeads($tenantId, $owner->id);
        $this->seedContacts($tenantId, $customers);
        $accounts = $this->seedAccounts($tenantId);
        $quotations = $this->seedQuotations($tenantId, $customers);
        $freightBookings = $this->seedFreightBookings($tenantId, $customers, $owner->id);
        $clearingFiles = $this->seedClearingFiles($tenantId, $customers, $owner->id);
        $this->seedShipments($tenantId, $customers, $quotations, $clearingFiles, $freightBookings);
        $this->seedContainers($tenantId, $customers, $clearingFiles, $freightBookings);
        $this->seedWarehouseItems($tenantId, $customers, $branchId);
        $this->seedVehicles($tenantId, $branchId, $owner->id);
        $this->seedInvoices($tenantId, $customers);
        $this->seedJournalEntries($tenantId, $accounts, $owner->id);
        $this->seedDocuments($tenantId, $customers, $owner->id);
    }

    private function seedCustomers(int $tenantId, int $ownerId): array
    {
        $rows = [
            ['company_name' => 'Kilimanjaro Coffee Exporters Ltd', 'industry' => 'Agriculture', 'email' => 'ops@kiliexporters.co.tz', 'phone' => '+255 27 254 1122', 'address' => 'Plot 14, Moshi Industrial Area', 'city' => 'Moshi', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Serengeti Mining Corp', 'industry' => 'Mining', 'email' => 'logistics@serengetimining.co.tz', 'phone' => '+255 28 250 3344', 'address' => 'Mwadui Road', 'city' => 'Shinyanga', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Zanzibar Spice Traders', 'industry' => 'Trading', 'email' => 'export@zanzibarspice.co.tz', 'phone' => '+255 24 223 5566', 'address' => 'Malindi Port Road', 'city' => 'Zanzibar City', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Dodoma Grain Millers', 'industry' => 'Agriculture', 'email' => 'procurement@dodomamillers.co.tz', 'phone' => '+255 26 232 7788', 'address' => 'Nala Industrial Zone', 'city' => 'Dodoma', 'country' => 'TANZANIA', 'currency' => 'TZS', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Mwanza Fisheries Co-op', 'industry' => 'Fisheries', 'email' => 'admin@mwanzafisheries.co.tz', 'phone' => '+255 28 250 9911', 'address' => 'Capri Point', 'city' => 'Mwanza', 'country' => 'TANZANIA', 'currency' => 'TZS', 'status' => CustomerStatus::Inactive->value],
            ['company_name' => 'Arusha Safari Logistics Partners', 'industry' => 'Tourism & Logistics', 'email' => 'fleet@arushasafari.co.tz', 'phone' => '+255 27 250 4433', 'address' => 'Sokoine Road', 'city' => 'Arusha', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Active->value],
        ];

        return array_map(
            fn (array $row) => Customer::create($row + ['tenant_id' => $tenantId, 'assigned_to' => $ownerId]),
            $rows,
        );
    }

    private function seedLeads(int $tenantId, int $ownerId): void
    {
        $rows = [
            ['company_name' => 'Tanga Cement Distributors', 'contact_name' => 'Elias Mrema', 'email' => 'elias@tangacement.co.tz', 'phone' => '+255 27 264 1010', 'source' => LeadSource::Website->value, 'status' => LeadStatus::New->value, 'notes' => 'Requested a quote for bulk cement export via Tanga port.'],
            ['company_name' => 'Mbeya Agro Exports', 'contact_name' => 'Grace Mwakalinga', 'email' => 'grace@mbeyaagro.co.tz', 'phone' => '+255 25 250 2020', 'source' => LeadSource::Referral->value, 'status' => LeadStatus::Contacted->value, 'notes' => 'Referred by Kilimanjaro Coffee Exporters.'],
            ['company_name' => 'Iringa Textile Mills', 'contact_name' => 'Joseph Nkya', 'email' => 'joseph@iringatextile.co.tz', 'phone' => '+255 26 270 3030', 'source' => LeadSource::ColdCall->value, 'status' => LeadStatus::Qualified->value, 'notes' => 'Interested in monthly LCL shipments to Europe.'],
            ['company_name' => 'Morogoro Sugar Co', 'contact_name' => 'Fatuma Said', 'email' => 'fatuma@morogorosugar.co.tz', 'phone' => '+255 23 261 4040', 'source' => LeadSource::Other->value, 'status' => LeadStatus::Lost->value, 'notes' => 'Went with a competitor offering lower clearing fees.'],
        ];

        foreach ($rows as $row) {
            Lead::create($row + ['tenant_id' => $tenantId, 'assigned_to' => $ownerId]);
        }
    }

    private function seedContacts(int $tenantId, array $customers): void
    {
        $titles = ['Operations Manager', 'Logistics Coordinator', 'Finance Officer', 'Managing Director'];

        foreach ($customers as $index => $customer) {
            Contact::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'name' => fake()->name(),
                'email' => fake()->companyEmail(),
                'phone' => '+255 7'.fake()->numberBetween(10, 99).' '.fake()->numberBetween(100000, 999999),
                'job_title' => $titles[$index % count($titles)],
                'is_primary' => true,
            ]);
        }
    }

    private function seedAccounts(int $tenantId): array
    {
        $rows = [
            ['code' => '1000', 'name' => 'Cash on Hand', 'type' => AccountType::Asset->value],
            ['code' => '1010', 'name' => 'Bank Account', 'type' => AccountType::Asset->value],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value],
            ['code' => '1400', 'name' => 'Prepaid Expenses', 'type' => AccountType::Asset->value],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => AccountType::Liability->value],
            ['code' => '2100', 'name' => 'VAT Payable', 'type' => AccountType::Liability->value],
            ['code' => '2200', 'name' => 'Accrued Expenses', 'type' => AccountType::Liability->value],
            ['code' => '3000', 'name' => "Owner's Equity", 'type' => AccountType::Equity->value],
            ['code' => '3100', 'name' => 'Retained Earnings', 'type' => AccountType::Equity->value],
            ['code' => '4000', 'name' => 'Freight Revenue', 'type' => AccountType::Revenue->value],
            ['code' => '4100', 'name' => 'Clearing Service Revenue', 'type' => AccountType::Revenue->value],
            ['code' => '4200', 'name' => 'Warehousing Revenue', 'type' => AccountType::Revenue->value],
            ['code' => '5000', 'name' => 'Freight Costs', 'type' => AccountType::Expense->value],
            ['code' => '5100', 'name' => 'Customs Duty Expense', 'type' => AccountType::Expense->value],
            ['code' => '5200', 'name' => 'Vehicle Maintenance', 'type' => AccountType::Expense->value],
            ['code' => '5300', 'name' => 'Office & Admin Expense', 'type' => AccountType::Expense->value],
        ];

        $accounts = [];
        foreach ($rows as $row) {
            $accounts[$row['code']] = Account::create($row + ['tenant_id' => $tenantId, 'is_active' => true]);
        }

        return $accounts;
    }

    private function seedQuotations(int $tenantId, array $customers): array
    {
        $statuses = [QuotationStatus::Draft, QuotationStatus::Sent, QuotationStatus::Accepted, QuotationStatus::Rejected, QuotationStatus::Expired];
        $quotations = [];

        foreach ($statuses as $index => $status) {
            $customer = $customers[$index % count($customers)];
            $quotations[] = Quotation::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'status' => $status->value,
                'origin_port' => 'Dar es Salaam',
                'destination_port' => fake()->randomElement(['Mombasa', 'Rotterdam', 'Dubai', 'Shanghai', 'Durban']),
                'notes' => "Quotation for {$customer->company_name}.",
            ]);
        }

        return $quotations;
    }

    private function seedFreightBookings(int $tenantId, array $customers, int $ownerId): array
    {
        $bookings = [];

        foreach (array_slice($customers, 0, 4) as $index => $customer) {
            $bookings[] = FreightBooking::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'assigned_to' => $ownerId,
                'vessel_flight_no' => 'MSC-'.fake()->numberBetween(1000, 9999),
                'booking_number' => 'BK'.now()->format('Y').str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'cargo_description' => fake()->randomElement(['General cargo', 'Coffee beans (green)', 'Mining equipment', 'Textiles', 'Processed grain']),
                'weight_kg' => fake()->randomFloat(2, 500, 20000),
                'volume_cbm' => fake()->randomFloat(2, 10, 60),
                'freight_charges' => fake()->randomFloat(2, 800, 8000),
                'etd' => now()->addDays(fake()->numberBetween(1, 10))->toDateString(),
                'eta' => now()->addDays(fake()->numberBetween(15, 30))->toDateString(),
            ]);
        }

        return $bookings;
    }

    private function seedClearingFiles(int $tenantId, array $customers, int $ownerId): array
    {
        $files = [];

        foreach (array_slice($customers, 0, 4) as $customer) {
            $files[] = ClearingFile::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'assigned_to' => $ownerId,
                'customs_office' => 'Dar es Salaam Port Customs',
                'declaration_number' => 'DCL'.fake()->numerify('########'),
                'hs_code' => fake()->numerify('####.##.##'),
                'cargo_description' => fake()->randomElement(['General cargo', 'Coffee beans (green)', 'Mining equipment', 'Textiles']),
                'duty_amount' => fake()->randomFloat(2, 200, 3000),
                'vat_amount' => fake()->randomFloat(2, 100, 1500),
                'other_charges' => fake()->randomFloat(2, 50, 400),
                'eta' => now()->addDays(fake()->numberBetween(1, 10))->toDateString(),
            ]);
        }

        return $files;
    }

    private function seedShipments(int $tenantId, array $customers, array $quotations, array $clearingFiles, array $freightBookings): void
    {
        $statuses = [ShipmentStatus::Booked, ShipmentStatus::InTransit, ShipmentStatus::Arrived, ShipmentStatus::Cleared, ShipmentStatus::Delivered];

        foreach ($statuses as $index => $status) {
            $customer = $customers[$index % count($customers)];

            Shipment::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'quotation_id' => $quotations[$index % count($quotations)]->id ?? null,
                'clearing_file_id' => $clearingFiles[$index % count($clearingFiles)]->id ?? null,
                'freight_booking_id' => $freightBookings[$index % count($freightBookings)]->id ?? null,
                'origin_port' => 'Dar es Salaam',
                'destination_port' => fake()->randomElement(['Mombasa', 'Rotterdam', 'Dubai', 'Shanghai', 'Durban']),
                'bl_awb_number' => 'BL'.fake()->numerify('########'),
                'status' => $status->value,
                'etd' => now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
                'eta' => now()->addDays(fake()->numberBetween(1, 15))->toDateString(),
                'notes' => "Shipment for {$customer->company_name}.",
            ]);
        }
    }

    private function seedContainers(int $tenantId, array $customers, array $clearingFiles, array $freightBookings): void
    {
        $statuses = [ContainerStatus::AtPort, ContainerStatus::InTransit, ContainerStatus::AtWarehouse, ContainerStatus::Delivered];

        foreach ($statuses as $index => $status) {
            $customer = $customers[$index % count($customers)];

            Container::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'clearing_file_id' => $clearingFiles[$index % count($clearingFiles)]->id ?? null,
                'freight_booking_id' => $freightBookings[$index % count($freightBookings)]->id ?? null,
                'container_type' => ContainerType::Dry40->value,
                'status' => $status->value,
                'seal_number' => 'SL'.fake()->numerify('######'),
                'gross_weight_kg' => fake()->randomFloat(2, 5000, 25000),
                'location' => fake()->randomElement(['Dar es Salaam Port Yard', 'Main Warehouse Bay 3', 'In transit to Kigali']),
                'gate_in_date' => now()->subDays(fake()->numberBetween(1, 10))->toDateString(),
            ]);
        }
    }

    private function seedWarehouseItems(int $tenantId, array $customers, ?int $branchId): void
    {
        $statuses = [WarehouseItemStatus::Received, WarehouseItemStatus::Stored, WarehouseItemStatus::Picked, WarehouseItemStatus::Dispatched, WarehouseItemStatus::Damaged];

        foreach ($statuses as $index => $status) {
            $customer = $customers[$index % count($customers)];

            WarehouseItem::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'branch_id' => $branchId,
                'description' => fake()->randomElement(['Coffee bean sacks (60kg)', 'Mining spare parts crate', 'Spice cartons', 'Grain bags (50kg)', 'Textile bales']),
                'unit' => fake()->randomElement(['sacks', 'crates', 'cartons', 'bags', 'bales']),
                'bin_location' => 'A'.fake()->numberBetween(1, 9).'-'.fake()->numberBetween(10, 40),
                'weight_kg' => fake()->randomFloat(2, 100, 3000),
                'volume_cbm' => fake()->randomFloat(2, 2, 30),
                'status' => $status->value,
                'received_date' => now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
            ]);
        }
    }

    private function seedVehicles(int $tenantId, ?int $branchId, int $ownerId): void
    {
        $rows = [
            ['vehicle_type' => VehicleType::Truck->value, 'make' => 'Isuzu', 'model' => 'FVR 34', 'year' => 2021, 'capacity_kg' => 15000, 'status' => VehicleStatus::Active->value],
            ['vehicle_type' => VehicleType::Truck->value, 'make' => 'Scania', 'model' => 'R450', 'year' => 2019, 'capacity_kg' => 30000, 'status' => VehicleStatus::InMaintenance->value],
            ['vehicle_type' => VehicleType::Van->value, 'make' => 'Toyota', 'model' => 'Hiace', 'year' => 2022, 'capacity_kg' => 1500, 'status' => VehicleStatus::Active->value],
            ['vehicle_type' => VehicleType::Forklift->value, 'make' => 'Toyota', 'model' => '8FD25', 'year' => 2020, 'capacity_kg' => 2500, 'status' => VehicleStatus::Active->value],
        ];

        foreach ($rows as $row) {
            Vehicle::factory()->create($row + [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'assigned_driver' => $ownerId,
                'last_service_date' => now()->subMonths(2)->toDateString(),
                'next_service_due' => now()->addMonths(4)->toDateString(),
            ]);
        }
    }

    private function seedInvoices(int $tenantId, array $customers): void
    {
        $statuses = [InvoiceStatus::Draft, InvoiceStatus::Sent, InvoiceStatus::Paid, InvoiceStatus::Overdue, InvoiceStatus::Paid];

        foreach ($statuses as $index => $status) {
            $customer = $customers[$index % count($customers)];

            Invoice::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'status' => $status->value,
                'currency' => $customer->currency,
                'notes' => "Invoice for freight & clearing services rendered to {$customer->company_name}.",
            ]);
        }
    }

    private function seedJournalEntries(int $tenantId, array $accounts, int $ownerId): void
    {
        $entries = [
            [
                'description' => 'Freight revenue received from Kilimanjaro Coffee Exporters',
                'lines' => [
                    ['account' => '1010', 'debit' => 4500, 'credit' => 0],
                    ['account' => '4000', 'debit' => 0, 'credit' => 4500],
                ],
            ],
            [
                'description' => 'Customs duty paid on behalf of Serengeti Mining Corp',
                'lines' => [
                    ['account' => '5100', 'debit' => 1200, 'credit' => 0],
                    ['account' => '1010', 'debit' => 0, 'credit' => 1200],
                ],
            ],
            [
                'description' => 'Vehicle maintenance expense for fleet truck servicing',
                'lines' => [
                    ['account' => '5200', 'debit' => 650, 'credit' => 0],
                    ['account' => '2000', 'debit' => 0, 'credit' => 650],
                ],
            ],
        ];

        foreach ($entries as $index => $entry) {
            $journalEntry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'entry_date' => now()->subDays(($index + 1) * 3)->toDateString(),
                'description' => $entry['description'],
                'reference' => 'DEMO-'.($index + 1),
                'status' => JournalEntryStatus::Posted->value,
                'created_by' => $ownerId,
                'posted_at' => now()->subDays(($index + 1) * 3),
            ]);

            foreach ($entry['lines'] as $line) {
                JournalEntryLine::create([
                    'tenant_id' => $tenantId,
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $accounts[$line['account']]->id,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $entry['description'],
                ]);
            }
        }
    }

    private function seedDocuments(int $tenantId, array $customers, int $ownerId): void
    {
        $categories = [DocumentCategory::BillOfLading, DocumentCategory::CustomsDeclaration, DocumentCategory::Invoice, DocumentCategory::Contract];

        foreach ($categories as $index => $category) {
            $customer = $customers[$index % count($customers)];

            Document::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'uploaded_by' => $ownerId,
                'category' => $category->value,
                'description' => "{$category->value} for {$customer->company_name}",
            ]);
        }
    }
}
