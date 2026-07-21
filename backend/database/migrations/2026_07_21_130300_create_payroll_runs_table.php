<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('run_number')->default(1);
            $table->string('status')->default('draft');
            $table->foreignId('statutory_rule_set_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_employer_contributions', 14, 2)->default(0);
            $table->decimal('total_employer_cost', 14, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'payroll_period_id', 'run_number'], 'payroll_runs_period_run_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_runs');
    }
};
