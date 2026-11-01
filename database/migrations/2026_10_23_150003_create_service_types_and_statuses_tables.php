<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('code', 48)->unique();
            $table->text('description')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->unsignedSmallInteger('sla_days')->default(15);
            $table->json('required_documents')->nullable()->comment('List of document labels a citizen must upload');
            $table->json('eligibility_notes')->nullable();
            $table->boolean('is_public')->default(true)->comment('Visible in the citizen-facing catalog');
            $table->boolean('requires_payment')->default(false);
            $table->timestamps();

            $table->index(['department_id', 'is_public']);
        });

        Schema::create('application_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->string('color', 24)->default('gray');
            $table->boolean('is_terminal')->default(false)->comment('No further transitions allowed');
            $table->boolean('is_citizen_visible')->default(true);
            $table->boolean('counts_as_open')->default(true);
            $table->timestamps();

            $table->index(['is_terminal', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_statuses');
        Schema::dropIfExists('service_types');
    }
};
