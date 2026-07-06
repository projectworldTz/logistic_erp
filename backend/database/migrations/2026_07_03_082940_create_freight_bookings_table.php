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
        Schema::create('freight_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no')->nullable();
            $table->string('direction')->default('import');
            $table->string('mode')->default('sea');
            $table->string('carrier')->nullable();
            $table->string('vessel_flight_no')->nullable();
            $table->string('booking_number')->nullable();
            $table->string('origin_port')->nullable();
            $table->string('destination_port')->nullable();
            $table->text('cargo_description')->nullable();
            $table->decimal('weight_kg', 12, 2)->nullable();
            $table->decimal('volume_cbm', 12, 2)->nullable();
            $table->decimal('freight_charges', 12, 2)->nullable();
            $table->string('status')->default('booked');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
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
        Schema::dropIfExists('freight_bookings');
    }
};
