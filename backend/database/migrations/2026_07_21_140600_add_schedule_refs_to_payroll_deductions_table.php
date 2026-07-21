<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->foreignId('loan_schedule_id')->nullable()->after('statutory_contribution_rule_id')
                ->constrained(indexName: 'payroll_deductions_loan_schedule_fk')->nullOnDelete();
            $table->foreignId('salary_advance_schedule_id')->nullable()->after('loan_schedule_id')
                ->constrained(indexName: 'payroll_deductions_advance_schedule_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_deductions', function (Blueprint $table) {
            $table->dropForeign('payroll_deductions_loan_schedule_fk');
            $table->dropForeign('payroll_deductions_advance_schedule_fk');
            $table->dropColumn(['loan_schedule_id', 'salary_advance_schedule_id']);
        });
    }
};
