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
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('version')->default(1)->after('mime_type');
            $table->foreignId('parent_document_id')->nullable()->after('version')->constrained('documents')->nullOnDelete();
            $table->foreignId('root_document_id')->nullable()->after('parent_document_id')->constrained('documents')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipment_id');
            $table->dropConstrainedForeignId('parent_document_id');
            $table->dropConstrainedForeignId('root_document_id');
            $table->dropColumn('version');
        });
    }
};
