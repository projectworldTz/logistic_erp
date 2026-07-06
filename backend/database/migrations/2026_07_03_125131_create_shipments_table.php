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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('clearing_file_id')->nullable()->constrained('clearing_files')->nullOnDelete();
            $table->foreignId('freight_booking_id')->nullable()->constrained('freight_bookings')->nullOnDelete();
            $table->string('shipment_number')->nullable();
            $table->string('direction')->default('import');
            $table->string('mode')->default('sea');
            $table->string('origin_port')->nullable();
            $table->string('destination_port')->nullable();
            $table->string('bl_awb_number')->nullable();
            $table->string('status')->default('booked');
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'shipment_number']);
            $table->index(['tenant_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
