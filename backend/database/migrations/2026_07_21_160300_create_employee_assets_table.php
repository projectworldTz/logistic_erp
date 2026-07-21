<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type');
            $table->string('asset_name');
            $table->string('serial_number')->nullable();
            $table->date('assigned_date');
            $table->date('return_date')->nullable();
            $table->string('condition_at_assignment')->nullable();
            $table->string('condition_at_return')->nullable();
            $table->string('status')->default('assigned');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'employee_assets_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_assets');
    }
};
