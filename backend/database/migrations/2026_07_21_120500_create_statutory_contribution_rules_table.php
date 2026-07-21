<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statutory_contribution_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('statutory_rule_set_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->decimal('employee_rate', 6, 3)->nullable();
            $table->decimal('employer_rate', 6, 3)->nullable();
            $table->decimal('min_base', 14, 2)->nullable();
            $table->decimal('max_base', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'statutory_rule_set_id', 'code'], 'statutory_contribution_rules_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statutory_contribution_rules');
    }
};
