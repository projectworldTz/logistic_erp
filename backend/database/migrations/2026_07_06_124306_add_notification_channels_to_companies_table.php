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
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('notify_email_enabled')->default(true)->after('secondary_color');
            $table->boolean('notify_sms_enabled')->default(false)->after('notify_email_enabled');
            $table->boolean('notify_whatsapp_enabled')->default(false)->after('notify_sms_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['notify_email_enabled', 'notify_sms_enabled', 'notify_whatsapp_enabled']);
        });
    }
};
