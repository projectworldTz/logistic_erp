<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinary_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('incident_date');
            $table->string('category');
            $table->string('severity');
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users', indexName: 'disciplinary_records_issued_by_fk')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->text('employee_response')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'disciplinary_records_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_records');
    }
};
