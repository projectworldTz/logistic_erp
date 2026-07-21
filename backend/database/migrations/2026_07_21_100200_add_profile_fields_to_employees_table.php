<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Purely additive — extends the existing employees table for the HR &
     * Payroll module without touching any column already in use.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Personal information
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('gender')->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('nationality')->nullable()->after('date_of_birth');
            $table->string('marital_status')->nullable()->after('nationality');
            $table->string('photo_path')->nullable()->after('marital_status');
            $table->string('alternative_phone')->nullable()->after('phone');
            $table->text('residential_address')->nullable()->after('alternative_phone');
            $table->string('emergency_contact_name')->nullable()->after('residential_address');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->text('national_id_number')->nullable()->after('emergency_contact_phone');

            // Employment information
            $table->foreignId('designation_id')->nullable()->after('job_title')->constrained()->nullOnDelete();
            $table->string('employee_category')->nullable()->after('designation_id');
            $table->date('confirmation_date')->nullable()->after('hire_date');
            $table->date('probation_end_date')->nullable()->after('confirmation_date');
            $table->string('work_location')->nullable()->after('probation_end_date');
            $table->foreignId('reporting_manager_id')->nullable()->after('work_location')
                ->constrained('employees')->nullOnDelete();
            $table->boolean('payroll_eligible')->default(true)->after('reporting_manager_id');
            $table->unsignedSmallInteger('notice_period_days')->nullable()->after('payroll_eligible');

            // Bank and payment information
            $table->string('bank_name')->nullable()->after('salary');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->text('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_branch_name')->nullable()->after('bank_account_number');
            $table->string('mobile_money_provider')->nullable()->after('bank_branch_name');
            $table->text('mobile_money_number')->nullable()->after('mobile_money_provider');
            $table->string('preferred_payment_method')->default('bank_transfer')->after('mobile_money_number');
            $table->string('pay_currency', 3)->nullable()->after('preferred_payment_method');

            // Configurable statutory fields (no single country hard-coded)
            $table->json('statutory_details')->nullable()->after('pay_currency');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reporting_manager_id');
            $table->dropConstrainedForeignId('designation_id');

            $table->dropColumn([
                'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
                'nationality', 'marital_status', 'photo_path', 'alternative_phone',
                'residential_address', 'emergency_contact_name', 'emergency_contact_phone',
                'national_id_number', 'employee_category', 'confirmation_date',
                'probation_end_date', 'work_location', 'payroll_eligible', 'notice_period_days',
                'bank_name', 'bank_account_name', 'bank_account_number', 'bank_branch_name',
                'mobile_money_provider', 'mobile_money_number', 'preferred_payment_method',
                'pay_currency', 'statutory_details',
            ]);
        });
    }
};
