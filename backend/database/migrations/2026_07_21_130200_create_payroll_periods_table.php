<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            $table->string('pay_frequency')->default('monthly');
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'period_start', 'period_end'], 'payroll_periods_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
