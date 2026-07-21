<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('exit_type');
            $table->date('notice_date');
            $table->date('last_working_date');
            $table->text('reason')->nullable();
            $table->text('exit_interview_notes')->nullable();
            $table->string('status')->default('initiated');
            $table->boolean('assets_cleared')->default(false);
            $table->boolean('handover_completed')->default(false);
            $table->decimal('unused_leave_days', 6, 2)->nullable();
            $table->decimal('leave_payout_amount', 12, 2)->nullable();
            $table->decimal('outstanding_loan_balance', 12, 2)->nullable();
            $table->decimal('outstanding_advance_balance', 12, 2)->nullable();
            $table->decimal('final_settlement_amount', 12, 2)->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users', indexName: 'exit_records_initiated_by_fk')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'exit_records_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_id'], 'exit_records_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_records');
    }
};
