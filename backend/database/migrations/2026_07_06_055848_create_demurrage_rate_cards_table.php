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
        Schema::create('demurrage_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('container_type')->nullable();
            $table->unsignedInteger('free_days');
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'container_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demurrage_rate_cards');
    }
};
