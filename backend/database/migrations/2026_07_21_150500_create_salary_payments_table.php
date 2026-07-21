<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_payment_batch_id')->constrained('salary_payment_batches', indexName: 'salary_payments_batch_fk')->cascadeOnDelete();
            $table->foreignId('payroll_run_employee_id')->constrained('payroll_run_employees', indexName: 'salary_payments_run_employee_fk')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method');
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('mobile_money_provider')->nullable();
            $table->string('mobile_money_number')->nullable();
            $table->string('status')->default('pending');
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'payroll_run_employee_id'], 'salary_payments_run_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
