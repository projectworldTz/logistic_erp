<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('standard_working_days_per_month')->default(26)->after('overtime_multiplier');
            $table->unsignedTinyInteger('standard_hours_per_day')->default(8)->after('standard_working_days_per_month');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['standard_working_days_per_month', 'standard_hours_per_day']);
        });
    }
};
