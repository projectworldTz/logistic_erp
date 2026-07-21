<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('statutory_rule_set_id')->nullable()->constrained()->nullOnDelete();
            $table->string('default_pay_frequency')->default('monthly');
            $table->decimal('overtime_multiplier', 4, 2)->default(1.5);

            $table->unsignedBigInteger('salary_expense_account_id')->nullable();
            $table->unsignedBigInteger('allowance_expense_account_id')->nullable();
            $table->unsignedBigInteger('overtime_expense_account_id')->nullable();
            $table->unsignedBigInteger('bonus_expense_account_id')->nullable();
            $table->unsignedBigInteger('employer_contribution_expense_account_id')->nullable();
            $table->unsignedBigInteger('payroll_payable_account_id')->nullable();
            $table->unsignedBigInteger('tax_payable_account_id')->nullable();
            $table->unsignedBigInteger('statutory_contributions_payable_account_id')->nullable();
            $table->unsignedBigInteger('loan_receivable_account_id')->nullable();
            $table->unsignedBigInteger('advance_receivable_account_id')->nullable();
            $table->unsignedBigInteger('bank_cash_account_id')->nullable();

            $table->timestamps();

            $table->foreign('salary_expense_account_id', 'ps_salary_expense_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('allowance_expense_account_id', 'ps_allowance_expense_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('overtime_expense_account_id', 'ps_overtime_expense_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('bonus_expense_account_id', 'ps_bonus_expense_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('employer_contribution_expense_account_id', 'ps_employer_contrib_expense_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('payroll_payable_account_id', 'ps_payroll_payable_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('tax_payable_account_id', 'ps_tax_payable_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('statutory_contributions_payable_account_id', 'ps_statutory_payable_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('loan_receivable_account_id', 'ps_loan_receivable_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('advance_receivable_account_id', 'ps_advance_receivable_fk')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('bank_cash_account_id', 'ps_bank_cash_fk')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settings');
    }
};
