<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('email_footer_text')->nullable()->after('secondary_color');
            $table->string('email_reply_to')->nullable()->after('email_footer_text');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['email_footer_text', 'email_reply_to']);
        });
    }
};
