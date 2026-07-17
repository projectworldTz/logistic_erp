<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\ContainerStatus;
use App\Enums\ContainerType;
use App\Enums\CustomerStatus;
use App\Enums\DocumentCategory;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
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
use App\Models\Expense;
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
 * Populates realistic operational data across every ERP module for the
 * "Trace Clearing & Forwarding Ltd" tenant, identified by its owner's email.
 * Not part of the standard install seed list — run explicitly:
 *   php artisan db:seed --class=TraceClearingForwardingDataSeeder --force
 */
class TraceClearingForwardingDataSeeder extends Seeder
{
    private const OWNER_EMAIL = 'trace@gmail.com';

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
        $this->seedExpenses($tenantId, $customers, $owner->id);
        $this->seedJournalEntries($tenantId, $accounts, $owner->id);
        $this->seedDocuments($tenantId, $customers, $owner->id);
    }

    private function seedCustomers(int $tenantId, int $ownerId): array
    {
        $rows = [
            ['company_name' => 'Mtwara Cashew Processors Ltd', 'industry' => 'Agriculture (Cashew)', 'email' => 'exports@mtwaracashew.co.tz', 'phone' => '+255 23 233 4411', 'address' => 'Mikindani Industrial Area', 'city' => 'Mtwara', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Tabora Tobacco Growers Cooperative', 'industry' => 'Agriculture (Tobacco)', 'email' => 'logistics@taboratobacco.co.tz', 'phone' => '+255 26 260 2255', 'address' => 'Uyui Road', 'city' => 'Tabora', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Tanga Sisal & Fibre Exporters', 'industry' => 'Agriculture (Sisal)', 'email' => 'trade@tangasisal.co.tz', 'phone' => '+255 27 264 3366', 'address' => 'Ngamiani Industrial Zone', 'city' => 'Tanga', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Singida Livestock & Hides Co', 'industry' => 'Livestock & Hides', 'email' => 'admin@singidalivestock.co.tz', 'phone' => '+255 26 250 7788', 'address' => 'Kititimo Road', 'city' => 'Singida', 'country' => 'TANZANIA', 'currency' => 'TZS', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Njombe Horticulture Exports', 'industry' => 'Horticulture', 'email' => 'export@njombehort.co.tz', 'phone' => '+255 26 278 9911', 'address' => 'Kifanya Farms Road', 'city' => 'Njombe', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Kigoma Timber & Forest Products', 'industry' => 'Timber', 'email' => 'ops@kigomatimber.co.tz', 'phone' => '+255 28 280 4433', 'address' => 'Kibirizi Port Road', 'city' => 'Kigoma', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Inactive->value],
            ['company_name' => 'Dar es Salaam Petroleum Importers Ltd', 'industry' => 'Petroleum & Energy', 'email' => 'supply@dsmpetroleum.co.tz', 'phone' => '+255 22 212 5566', 'address' => 'Kurasini Oil Terminal Road', 'city' => 'Dar es Salaam', 'country' => 'TANZANIA', 'currency' => 'USD', 'status' => CustomerStatus::Active->value],
            ['company_name' => 'Morogoro Textile & Garments Ltd', 'industry' => 'Textiles', 'email' => 'procurement@morogorotextile.co.tz', 'phone' => '+255 23 261 6677', 'address' => 'Kihonda Industrial Estate', 'city' => 'Morogoro', 'country' => 'TANZANIA', 'currency' => 'TZS', 'status' => CustomerStatus::Active->value],
        ];

        return array_map(
            fn (array $row) => Customer::create($row + ['tenant_id' => $tenantId, 'assigned_to' => $ownerId]),
            $rows,
        );
    }

    private function seedLeads(int $tenantId, int $ownerId): void
    {
        $rows = [
            ['company_name' => 'Rukwa Rice Millers', 'contact_name' => 'Amani Sichalwe', 'email' => 'amani@rukwarice.co.tz', 'phone' => '+255 25 280 1122', 'source' => LeadSource::Website->value, 'status' => LeadStatus::New->value, 'notes' => 'Requested a quote for bulk rice export via Dar port.'],
            ['company_name' => 'Geita Gold Logistics Partners', 'contact_name' => 'Furaha Mabula', 'email' => 'furaha@geitagold.co.tz', 'phone' => '+255 28 250 3344', 'source' => LeadSource::Referral->value, 'status' => LeadStatus::Contacted->value, 'notes' => 'Referred by Singida Livestock & Hides Co.'],
            ['company_name' => 'Lindi Cashew Cooperative Union', 'contact_name' => 'Halima Chande', 'email' => 'halima@lindicashew.co.tz', 'phone' => '+255 23 220 4455', 'source' => LeadSource::ColdCall->value, 'status' => LeadStatus::Qualified->value, 'notes' => 'Interested in monthly container bookings to Asia.'],
            ['company_name' => 'Shinyanga Cotton Ginners', 'contact_name' => 'Method Kway', 'email' => 'method@shinyangacotton.co.tz', 'phone' => '+255 28 276 5566', 'source' => LeadSource::Other->value, 'status' => LeadStatus::Lost->value, 'notes' => 'Went with a competitor offering lower warehousing fees.'],
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

        foreach (array_slice($customers, 0, 5) as $index => $customer) {
            $bookings[] = FreightBooking::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'assigned_to' => $ownerId,
                'vessel_flight_no' => 'MSC-'.fake()->numberBetween(1000, 9999),
                'booking_number' => 'BK'.now()->format('Y').str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'cargo_description' => fake()->randomElement(['Raw cashew nuts', 'Cured tobacco leaf', 'Baled sisal fibre', 'Hides and skins', 'Fresh horticulture produce']),
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

        foreach (array_slice($customers, 0, 5) as $customer) {
            $files[] = ClearingFile::factory()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'assigned_to' => $ownerId,
                'customs_office' => 'Dar es Salaam Port Customs',
                'declaration_number' => 'DCL'.fake()->numerify('########'),
                'hs_code' => fake()->numerify('####.##.##'),
                'cargo_description' => fake()->randomElement(['Raw cashew nuts', 'Cured tobacco leaf', 'Baled sisal fibre', 'Hides and skins']),
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
        $statuses = [ShipmentStatus::Booked, ShipmentStatus::InTransit, ShipmentStatus::Arrived, ShipmentStatus::Cleared, ShipmentStatus::Delivered, ShipmentStatus::Delivered];

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
                'location' => fake()->randomElement(['Dar es Salaam Port Yard', 'Main Warehouse Bay 2', 'In transit to Kigali']),
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
                'description' => fake()->randomElement(['Cashew nut sacks (80kg)', 'Tobacco bales', 'Sisal fibre bales', 'Hide bundles', 'Horticulture crates']),
                'unit' => fake()->randomElement(['sacks', 'bales', 'bundles', 'crates']),
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
            ['vehicle_type' => VehicleType::Truck->value, 'make' => 'Isuzu', 'model' => 'FVR 34', 'year' => 2022, 'capacity_kg' => 15000, 'status' => VehicleStatus::Active->value],
            ['vehicle_type' => VehicleType::Truck->value, 'make' => 'Scania', 'model' => 'R450', 'year' => 2020, 'capacity_kg' => 30000, 'status' => VehicleStatus::Active->value],
            ['vehicle_type' => VehicleType::Van->value, 'make' => 'Toyota', 'model' => 'Hiace', 'year' => 2021, 'capacity_kg' => 1500, 'status' => VehicleStatus::InMaintenance->value],
            ['vehicle_type' => VehicleType::Forklift->value, 'make' => 'Toyota', 'model' => '8FD25', 'year' => 2019, 'capacity_kg' => 2500, 'status' => VehicleStatus::Active->value],
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
        $statuses = [InvoiceStatus::Draft, InvoiceStatus::Sent, InvoiceStatus::Paid, InvoiceStatus::Overdue, InvoiceStatus::Paid, InvoiceStatus::Sent];

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

    private function seedExpenses(int $tenantId, array $customers, int $ownerId): void
    {
        $rows = [
            ['category' => ExpenseCategory::CustomsDuty->value, 'description' => 'Customs duty paid on behalf of Mtwara Cashew Processors Ltd', 'amount' => 1450.00, 'status' => ExpenseStatus::Paid->value, 'is_billable' => true],
            ['category' => ExpenseCategory::Trucking->value, 'description' => 'Inland trucking from Dar port to Tabora warehouse', 'amount' => 980.50, 'status' => ExpenseStatus::Approved->value, 'is_billable' => true],
            ['category' => ExpenseCategory::PortFees->value, 'description' => 'Port handling fees for sisal fibre containers', 'amount' => 620.00, 'status' => ExpenseStatus::Submitted->value, 'is_billable' => true],
            ['category' => ExpenseCategory::Documentation->value, 'description' => 'Certificate of origin and export permit processing', 'amount' => 150.00, 'status' => ExpenseStatus::Draft->value, 'is_billable' => false],
            ['category' => ExpenseCategory::Warehousing->value, 'description' => 'Monthly warehouse rent, Dar es Salaam facility', 'amount' => 2200.00, 'status' => ExpenseStatus::Paid->value, 'is_billable' => false],
            ['category' => ExpenseCategory::OfficeSupplies->value, 'description' => 'Stationery and office supplies restock', 'amount' => 85.00, 'status' => ExpenseStatus::Rejected->value, 'is_billable' => false],
        ];

        foreach ($rows as $index => $row) {
            $customer = $row['is_billable'] ? $customers[$index % count($customers)] : null;

            Expense::create($row + [
                'tenant_id' => $tenantId,
                'customer_id' => $customer?->id,
                'currency' => $customer->currency ?? 'USD',
                'expense_date' => now()->subDays(($index + 1) * 4)->toDateString(),
                'created_by' => $ownerId,
                'approved_by' => in_array($row['status'], [ExpenseStatus::Approved->value, ExpenseStatus::Paid->value], true) ? $ownerId : null,
                'paid_at' => $row['status'] === ExpenseStatus::Paid->value ? now()->subDays($index) : null,
            ]);
        }
    }

    private function seedJournalEntries(int $tenantId, array $accounts, int $ownerId): void
    {
        $entries = [
            [
                'description' => 'Freight revenue received from Mtwara Cashew Processors Ltd',
                'lines' => [
                    ['account' => '1010', 'debit' => 5200, 'credit' => 0],
                    ['account' => '4000', 'debit' => 0, 'credit' => 5200],
                ],
            ],
            [
                'description' => 'Customs duty paid on behalf of Tabora Tobacco Growers Cooperative',
                'lines' => [
                    ['account' => '5100', 'debit' => 1450, 'credit' => 0],
                    ['account' => '1010', 'debit' => 0, 'credit' => 1450],
                ],
            ],
            [
                'description' => 'Vehicle maintenance expense for fleet truck servicing',
                'lines' => [
                    ['account' => '5200', 'debit' => 720, 'credit' => 0],
                    ['account' => '2000', 'debit' => 0, 'credit' => 720],
                ],
            ],
        ];

        foreach ($entries as $index => $entry) {
            $journalEntry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'entry_date' => now()->subDays(($index + 1) * 3)->toDateString(),
                'description' => $entry['description'],
                'reference' => 'TRACE-'.($index + 1),
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
