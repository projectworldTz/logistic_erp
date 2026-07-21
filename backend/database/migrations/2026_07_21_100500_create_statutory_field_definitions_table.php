<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defines which statutory identifier fields (TIN, pension number, etc.)
     * a tenant collects per country, so the core payroll engine never
     * hard-codes one country's statutory system. Values themselves live in
     * employees.statutory_details (JSON), keyed by this table's `key`.
     */
    public function up(): void
    {
        Schema::create('statutory_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('country', 2)->default('TZ');
            $table->string('key');
            $table->string('label');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'country', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_field_definitions');
    }
};
