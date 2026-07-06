<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demurrage_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('demurrage_rate_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('calculated_at');
            $table->unsignedInteger('dwell_days');
            $table->unsignedInteger('free_days');
            $table->unsignedInteger('chargeable_days');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->json('breakdown')->nullable();
            $table->string('status')->default('pending');
            $table->text('waived_reason')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demurrage_charges');
    }
};
