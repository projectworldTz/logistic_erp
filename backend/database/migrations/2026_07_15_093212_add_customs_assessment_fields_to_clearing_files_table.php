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
        Schema::table('clearing_files', function (Blueprint $table) {
            $table->string('sad_number')->nullable()->after('declaration_number');
            $table->decimal('customs_value', 14, 2)->nullable()->after('hs_code');
            $table->string('release_order_number')->nullable()->after('cleared_date');
            $table->string('assessment_status')->default('pending')->after('release_order_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clearing_files', function (Blueprint $table) {
            $table->dropColumn(['sad_number', 'customs_value', 'release_order_number', 'assessment_status']);
        });
    }
};
