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
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clearing_file_id')->nullable()->constrained('clearing_files')->nullOnDelete();
            $table->foreignId('freight_booking_id')->nullable()->constrained('freight_bookings')->nullOnDelete();
            $table->string('container_number');
            $table->string('container_type')->default('dry_20');
            $table->string('seal_number')->nullable();
            $table->string('status')->default('at_port');
            $table->decimal('gross_weight_kg', 12, 2)->nullable();
            $table->string('location')->nullable();
            $table->date('gate_in_date')->nullable();
            $table->date('gate_out_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'container_number']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
