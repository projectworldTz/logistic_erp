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
        Schema::table('containers', function (Blueprint $table) {
            $table->string('shipping_line')->nullable()->after('container_type');
            $table->string('vessel_name')->nullable()->after('shipping_line');
            $table->string('voyage_number')->nullable()->after('vessel_name');
            $table->string('port_of_loading')->nullable()->after('voyage_number');
            $table->string('port_of_discharge')->nullable()->after('port_of_loading');
            $table->date('eta')->nullable()->after('gate_in_date');
            $table->date('ata')->nullable()->after('eta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn(['shipping_line', 'vessel_name', 'voyage_number', 'port_of_loading', 'port_of_discharge', 'eta', 'ata']);
        });
    }
};
