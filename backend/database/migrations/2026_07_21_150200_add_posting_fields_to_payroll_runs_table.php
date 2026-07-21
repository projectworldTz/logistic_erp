<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')->nullable()->after('created_by')
                ->constrained(indexName: 'payroll_runs_journal_entry_fk')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign('payroll_runs_journal_entry_fk');
            $table->dropColumn(['journal_entry_id', 'posted_at']);
        });
    }
};
