<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number')->nullable();
            $table->string('employment_type');
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->decimal('basic_salary', 12, 2);
            $table->string('pay_frequency')->default('monthly');
            $table->unsignedSmallInteger('working_hours_per_week')->nullable();
            $table->json('workdays')->nullable();
            $table->unsignedSmallInteger('probation_period_days')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->text('benefits')->nullable();
            $table->boolean('overtime_eligible')->default(false);
            $table->boolean('commission_eligible')->default(false);
            $table->unsignedSmallInteger('leave_entitlement_days')->nullable();
            $table->string('document_path')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('renewed_from_contract_id')->nullable()->constrained('employee_contracts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'contract_number']);
            $table->index(['tenant_id', 'employee_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_contracts');
    }
};
