<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_vacancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->date('applied_date');
            $table->string('status')->default('applied');
            $table->text('notes')->nullable();
            $table->foreignId('converted_employee_id')->nullable()->constrained('employees', indexName: 'job_applications_employee_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'job_applications_created_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'job_vacancy_id', 'candidate_id'], 'job_applications_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
