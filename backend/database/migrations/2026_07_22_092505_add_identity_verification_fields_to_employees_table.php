<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('identity_document_type', 30)->nullable()->after('national_id_number');
            $table->text('identity_number')->nullable()->after('identity_document_type');
            $table->string('identity_number_hash', 64)->nullable()->after('identity_number');
            $table->string('identity_country_code', 3)->nullable()->after('identity_number_hash');
            $table->string('identity_provider', 50)->nullable()->after('identity_country_code');
            $table->string('identity_reference', 100)->nullable()->after('identity_provider');
            $table->string('identity_verification_status', 30)->default('not_verified')->after('identity_reference');
            $table->boolean('identity_verified')->default(false)->after('identity_verification_status');
            $table->timestamp('identity_verified_at')->nullable()->after('identity_verified');
            $table->foreignId('identity_verified_by')->nullable()->after('identity_verified_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('identity_last_synced_at')->nullable()->after('identity_verified_by');
            $table->text('identity_override_reason')->nullable()->after('identity_last_synced_at');
            $table->foreignId('identity_overridden_by')->nullable()->after('identity_override_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('identity_overridden_at')->nullable()->after('identity_overridden_by');

            $table->unique(
                ['tenant_id', 'identity_document_type', 'identity_number_hash'],
                'employees_identity_hash_unique'
            );
            $table->index('identity_verification_status', 'employees_identity_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_identity_hash_unique');
            $table->dropIndex('employees_identity_status_idx');
            $table->dropConstrainedForeignId('identity_verified_by');
            $table->dropConstrainedForeignId('identity_overridden_by');
            $table->dropColumn([
                'identity_document_type',
                'identity_number',
                'identity_number_hash',
                'identity_country_code',
                'identity_provider',
                'identity_reference',
                'identity_verification_status',
                'identity_verified',
                'identity_verified_at',
                'identity_last_synced_at',
                'identity_override_reason',
                'identity_overridden_at',
            ]);
        });
    }
};
