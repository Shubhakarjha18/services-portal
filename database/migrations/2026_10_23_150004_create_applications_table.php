<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 32)->unique()->comment('Human readable tracking id, e.g. PSP-2026-000123');
            $table->foreignId('service_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('citizen_id')->constrained('users')->restrictOnDelete()->comment('Applicant who owns the request');
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('application_status_id')->constrained()->restrictOnDelete();

            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('applicant_name', 160);
            $table->string('applicant_phone', 32)->nullable();
            $table->string('applicant_address')->nullable();
            $table->string('priority', 16)->default('normal');
            $table->unsignedSmallInteger('stage')->default(1)->comment('Monotonic workflow stage counter');

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('due_at')->nullable()->comment('SLA deadline derived from service sla_days');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['citizen_id', 'created_at']);
            $table->index(['assigned_officer_id', 'application_status_id']);
            $table->index(['department_id', 'application_status_id']);
            $table->index(['due_at', 'application_status_id']);
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
