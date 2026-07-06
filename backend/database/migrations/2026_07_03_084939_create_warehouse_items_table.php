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
        Schema::create('warehouse_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('reference_no')->nullable();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->string('unit')->default('pcs');
            $table->string('bin_location')->nullable();
            $table->decimal('weight_kg', 12, 2)->nullable();
            $table->decimal('volume_cbm', 12, 2)->nullable();
            $table->string('status')->default('received');
            $table->date('received_date')->nullable();
            $table->date('dispatched_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'reference_no']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_items');
    }
};
