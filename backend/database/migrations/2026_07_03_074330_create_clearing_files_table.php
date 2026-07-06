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
        Schema::create('clearing_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no')->nullable();
            $table->string('direction')->default('import');
            $table->string('mode')->default('sea');
            $table->string('port_of_loading')->nullable();
            $table->string('port_of_discharge')->nullable();
            $table->string('bl_awb_number')->nullable();
            $table->string('customs_office')->nullable();
            $table->string('declaration_number')->nullable();
            $table->string('hs_code')->nullable();
            $table->text('cargo_description')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('duty_amount', 12, 2)->nullable();
            $table->decimal('vat_amount', 12, 2)->nullable();
            $table->decimal('other_charges', 12, 2)->nullable();
            $table->date('eta')->nullable();
            $table->date('cleared_date')->nullable();
            $table->date('delivered_date')->nullable();
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
        Schema::dropIfExists('clearing_files');
    }
};
