<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->string('source')->default('manual')->after('status');
            $table->unsignedSmallInteger('late_minutes')->nullable()->after('check_out');
            $table->unsignedSmallInteger('early_departure_minutes')->nullable()->after('late_minutes');
            $table->boolean('is_weekend')->default(false)->after('early_departure_minutes');
            $table->boolean('is_holiday')->default(false)->after('is_weekend');
            $table->foreignId('approved_by')->nullable()->after('is_holiday')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['source', 'late_minutes', 'early_departure_minutes', 'is_weekend', 'is_holiday', 'approved_at']);
        });
    }
};
