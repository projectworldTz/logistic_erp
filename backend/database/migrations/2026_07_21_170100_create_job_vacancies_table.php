<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->string('employment_type')->nullable();
            $table->unsignedSmallInteger('number_of_openings')->default(1);
            $table->string('status')->default('open');
            $table->date('posted_date')->nullable();
            $table->date('closing_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'job_vacancies_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_vacancies');
    }
};
