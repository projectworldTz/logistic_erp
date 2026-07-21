<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->foreignId('other_deductions_payable_account_id')->nullable()->after('advance_receivable_account_id')
                ->constrained('accounts', indexName: 'payroll_settings_other_deductions_fk')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropForeign('payroll_settings_other_deductions_fk');
            $table->dropColumn('other_deductions_payable_account_id');
        });
    }
};
