<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('loan_number')->nullable();
            $table->decimal('principal_amount', 12, 2);
            $table->decimal('interest_rate', 6, 3)->default(0);
            $table->unsignedSmallInteger('number_of_installments');
            $table->decimal('installment_amount', 12, 2);
            $table->date('start_date');
            $table->string('status')->default('draft');
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'employee_loans_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'loan_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_loans');
    }
};
