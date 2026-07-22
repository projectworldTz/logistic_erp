<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MySQL's legacy "first TIMESTAMP column with no explicit default gets
     * an implicit ON UPDATE CURRENT_TIMESTAMP" behavior was silently
     * rewriting employee_identity_verifications.requested_at to the
     * current time on every UPDATE to the row (e.g. when responded_at was
     * set moments later) — turning an audit trail's own "when was this
     * requested" column into "when was this row last touched". DATETIME
     * columns have no such implicit behavior, so every custom point-in-time
     * column added for Identity Verification is converted here. Doctrine
     * DBAL isn't installed, so this uses raw MODIFY statements rather than
     * Blueprint::change().
     */
    public function up(): void
    {
        // The implicit-auto-update TIMESTAMP quirk this migration works
        // around is MySQL-specific (SQLite, used in tests, has no such
        // behavior and no MODIFY COLUMN syntax at all).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('employee_identity_verifications')) {
            DB::statement('ALTER TABLE employee_identity_verifications MODIFY requested_at DATETIME NOT NULL');
            DB::statement('ALTER TABLE employee_identity_verifications MODIFY responded_at DATETIME NULL');
            DB::statement('ALTER TABLE employee_identity_verifications MODIFY confirmed_at DATETIME NULL');
            DB::statement('ALTER TABLE employee_identity_verifications MODIFY rejected_at DATETIME NULL');
        }

        if (Schema::hasTable('employee_identity_manual_reviews')) {
            DB::statement('ALTER TABLE employee_identity_manual_reviews MODIFY submitted_at DATETIME NOT NULL');
            DB::statement('ALTER TABLE employee_identity_manual_reviews MODIFY reviewed_at DATETIME NULL');
        }

        if (Schema::hasColumn('employees', 'identity_verified_at')) {
            DB::statement('ALTER TABLE employees MODIFY identity_verified_at DATETIME NULL');
            DB::statement('ALTER TABLE employees MODIFY identity_last_synced_at DATETIME NULL');
            DB::statement('ALTER TABLE employees MODIFY identity_overridden_at DATETIME NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('employee_identity_verifications')) {
            DB::statement('ALTER TABLE employee_identity_verifications MODIFY requested_at TIMESTAMP NOT NULL');
            DB::statement('ALTER TABLE employee_identity_verifications MODIFY responded_at TIMESTAMP NULL');
            DB::statement('ALTER TABLE employee_identity_verifications MODIFY confirmed_at TIMESTAMP NULL');
            DB::statement('ALTER TABLE employee_identity_verifications MODIFY rejected_at TIMESTAMP NULL');
        }

        if (Schema::hasTable('employee_identity_manual_reviews')) {
            DB::statement('ALTER TABLE employee_identity_manual_reviews MODIFY submitted_at TIMESTAMP NOT NULL');
            DB::statement('ALTER TABLE employee_identity_manual_reviews MODIFY reviewed_at TIMESTAMP NULL');
        }

        if (Schema::hasColumn('employees', 'identity_verified_at')) {
            DB::statement('ALTER TABLE employees MODIFY identity_verified_at TIMESTAMP NULL');
            DB::statement('ALTER TABLE employees MODIFY identity_last_synced_at TIMESTAMP NULL');
            DB::statement('ALTER TABLE employees MODIFY identity_overridden_at TIMESTAMP NULL');
        }
    }
};
