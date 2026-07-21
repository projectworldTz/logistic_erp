<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_payment_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('payment_date');
            $table->string('status')->default('draft');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'salary_payment_batches_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'payroll_run_id'], 'salary_payment_batches_run_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payment_batches');
    }
};
