<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Populates one staff user per remaining tenant role (Company Owner already
 * exists from registration; Customer Portal User is skipped as a dormant,
 * zero-permission role with no associated portal). Not part of the standard
 * install seed list — run explicitly:
 *   php artisan db:seed --class=DemoTenantStaffSeeder
 */
class DemoTenantStaffSeeder extends Seeder
{
    private const OWNER_EMAIL = 'test@gmail.com';

    private const DEMO_PASSWORD = 'StaffDemo123!';

    private const STAFF = [
        ['role' => 'Company Admin', 'name' => 'Neema Kessy', 'email' => 'neema.kessy@projectwoold.co.tz'],
        ['role' => 'Branch Manager', 'name' => 'Hassan Juma', 'email' => 'hassan.juma@projectwoold.co.tz'],
        ['role' => 'Operations Manager', 'name' => 'Consolata Mushi', 'email' => 'consolata.mushi@projectwoold.co.tz'],
        ['role' => 'Clearing Officer', 'name' => 'Rashid Kombo', 'email' => 'rashid.kombo@projectwoold.co.tz'],
        ['role' => 'Forwarding Officer', 'name' => 'Agnes Temba', 'email' => 'agnes.temba@projectwoold.co.tz'],
        ['role' => 'Warehouse Manager', 'name' => 'Emmanuel Shirima', 'email' => 'emmanuel.shirima@projectwoold.co.tz'],
        ['role' => 'Warehouse Staff', 'name' => 'Zainab Ally', 'email' => 'zainab.ally@projectwoold.co.tz'],
        ['role' => 'Dispatcher', 'name' => 'Godfrey Mollel', 'email' => 'godfrey.mollel@projectwoold.co.tz'],
        ['role' => 'Fleet Manager', 'name' => 'Salma Rajabu', 'email' => 'salma.rajabu@projectwoold.co.tz'],
        ['role' => 'Driver', 'name' => 'Peter Massawe', 'email' => 'peter.massawe@projectwoold.co.tz'],
        ['role' => 'Finance Manager', 'name' => 'Irene Komba', 'email' => 'irene.komba@projectwoold.co.tz'],
        ['role' => 'Accountant', 'name' => 'David Mwanga', 'email' => 'david.mwanga@projectwoold.co.tz'],
        ['role' => 'Sales Manager', 'name' => 'Fatma Rashid', 'email' => 'fatma.rashid@projectwoold.co.tz'],
        ['role' => 'Sales Executive', 'name' => 'John Kilewa', 'email' => 'john.kilewa@projectwoold.co.tz'],
        ['role' => 'Customer Service', 'name' => 'Mariam Hamisi', 'email' => 'mariam.hamisi@projectwoold.co.tz'],
        ['role' => 'Document Controller', 'name' => 'Baraka Ngowi', 'email' => 'baraka.ngowi@projectwoold.co.tz'],
        ['role' => 'Auditor', 'name' => 'Lucy Mbwana', 'email' => 'lucy.mbwana@projectwoold.co.tz'],
    ];

    public function run(): void
    {
        $owner = User::where('email', self::OWNER_EMAIL)->firstOrFail();
        $tenant = Tenant::findOrFail($owner->tenant_id);
        $branch = Branch::where('tenant_id', $tenant->id)->where('is_default', true)->firstOrFail();

        // 18 staff (17 here + the existing owner) exceeds the Starter plan's
        // 5-user cap. That cap isn't enforced in code today, but for a
        // realistic demo roster we bump the tenant to Professional (25 users).
        $professional = Plan::where('code', 'professional')->firstOrFail();
        $tenant->subscription->update(['plan_id' => $professional->id]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        foreach (self::STAFF as $index => $staff) {
            $user = User::firstOrCreate(
                ['email' => $staff['email']],
                [
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                    'name' => $staff['name'],
                    'phone' => '+255 7'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT).' '.fake()->numberBetween(100000, 999999),
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'status' => UserStatus::Active->value,
                    'is_super_admin' => false,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$staff['role']]);
        }
    }
}
