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
        Schema::create('demurrage_rate_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demurrage_rate_card_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->unsignedInteger('from_day');
            $table->unsignedInteger('to_day')->nullable();
            $table->decimal('daily_rate', 10, 2);
            $table->timestamps();

            $table->index(['demurrage_rate_card_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demurrage_rate_tiers');
    }
};
