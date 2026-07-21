<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_employee_id')->constrained('payroll_run_employees', indexName: 'payroll_earnings_run_employee_fk')->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('component');
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->boolean('is_taxable')->default(true);
            $table->boolean('is_pensionable')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_earnings');
    }
};
