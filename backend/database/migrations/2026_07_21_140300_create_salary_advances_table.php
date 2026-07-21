<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('advance_number')->nullable();
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('number_of_installments')->default(1);
            $table->decimal('installment_amount', 12, 2);
            $table->date('request_date');
            $table->string('status')->default('draft');
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users', indexName: 'salary_advances_approved_by_fk')->nullOnDelete();
            $table->timestamp('disbursed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'salary_advances_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'advance_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
    }
};
