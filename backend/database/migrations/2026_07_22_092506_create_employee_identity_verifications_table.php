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
        Schema::create('employee_identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();

            $table->string('identity_document_type', 30);
            $table->string('identity_number_hash', 64);
            $table->string('identity_number_masked', 100);
            $table->string('identity_country_code', 3)->nullable();

            $table->string('provider', 50);
            $table->string('provider_reference', 100)->nullable();
            $table->string('verification_status', 30)->default('pending');
            $table->string('result_code', 50)->nullable();
            $table->string('result_message', 255)->nullable();
            $table->string('failure_reason', 255)->nullable();

            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('requested_at');
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->json('request_metadata')->nullable();
            $table->json('response_metadata')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'employee_id'], 'eiv_tenant_employee_idx');
            $table->index(['tenant_id', 'identity_number_hash'], 'eiv_tenant_hash_idx');
            $table->index(['tenant_id', 'verification_status'], 'eiv_tenant_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_identity_verifications');
    }
};
