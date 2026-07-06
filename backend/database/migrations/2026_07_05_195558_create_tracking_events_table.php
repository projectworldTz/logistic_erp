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
        Schema::create('tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('trackable_type');
            $table->unsignedBigInteger('trackable_id');
            $table->string('event_type');
            $table->string('location')->nullable();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->boolean('is_customer_visible')->default(true);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trackable_type', 'trackable_id']);
            $table->index(['tenant_id', 'trackable_type', 'trackable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
    }
};
