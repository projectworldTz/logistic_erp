<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_employee_id')->constrained('payroll_run_employees', indexName: 'payslips_run_employee_fk')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->string('payslip_number')->nullable();
            $table->decimal('gross_pay', 12, 2);
            $table->decimal('total_deductions', 12, 2);
            $table->decimal('net_pay', 12, 2);
            $table->decimal('total_employer_contributions', 12, 2);
            $table->decimal('ytd_gross', 14, 2)->default(0);
            $table->decimal('ytd_deductions', 14, 2)->default(0);
            $table->decimal('ytd_net', 14, 2)->default(0);
            $table->string('verification_code', 40)->unique();
            $table->timestamps();

            $table->unique(['tenant_id', 'payroll_run_employee_id'], 'payslips_run_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
