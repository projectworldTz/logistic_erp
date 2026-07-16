<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('plan_name'); // snapshot, in case the plan is later renamed
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_date');
            $table->string('status', 20)->default('pending'); // pending | paid | overdue | void
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
