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
        Schema::create('employee_identity_manual_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('verification_id')->nullable()
                ->constrained('employee_identity_verifications')->nullOnDelete();

            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('pending');
            $table->string('reason', 255);
            $table->string('supporting_document_type', 100)->nullable();
            $table->string('supporting_document_number', 100)->nullable();
            $table->string('supporting_document_path')->nullable();
            $table->text('notes')->nullable();
            $table->text('reviewer_notes')->nullable();

            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'eimr_tenant_status_idx');
            $table->index(['tenant_id', 'employee_id'], 'eimr_tenant_employee_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_identity_manual_reviews');
    }
};
