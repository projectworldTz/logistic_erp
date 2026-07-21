<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_tax_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statutory_rule_set_id')->constrained()->cascadeOnDelete();
            $table->decimal('lower_bound', 14, 2);
            $table->decimal('upper_bound', 14, 2)->nullable();
            $table->decimal('rate', 6, 3);
            $table->unsignedSmallInteger('band_order');
            $table->timestamps();

            $table->index(['tenant_id', 'statutory_rule_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_tax_bands');
    }
};
