<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interviewer_id')->nullable()->constrained('users', indexName: 'interviews_interviewer_fk')->nullOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('mode')->default('in_person');
            $table->string('location')->nullable();
            $table->string('status')->default('scheduled');
            $table->text('feedback')->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'interviews_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
