<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->foreignId('paid_in_payroll_run_id')->nullable()->constrained('payroll_runs', indexName: 'loan_schedules_payroll_run_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_loan_id', 'installment_number'], 'loan_schedules_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_schedules');
    }
};
