<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable workflow timeline: one row per status change. This is the
        // table the citizen-facing tracker reads from.
        Schema::create('application_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('application_statuses')->nullOnDelete();
            $table->foreignId('to_status_id')->constrained('application_statuses')->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('performed_by_name', 160)->nullable()->comment('Denormalised for audit even if the user is deleted');
            $table->string('note', 500)->nullable();
            $table->unsignedSmallInteger('stage')->default(1);
            $table->boolean('visible_to_citizen')->default(true);
            $table->unsignedInteger('duration_seconds')->nullable()->comment('Time spent in the previous status');
            $table->timestamps();

            $table->index(['application_id', 'created_at']);
            $table->index('to_status_id');
        });

        // Compliance audit trail for every model mutation, not only applications.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 48)->index()->comment('created | updated | status_changed | deleted | login | logout | file_uploaded');
            // Nullable: security events (login_failed, tracking_miss) have no subject row.
            $table->string('auditable_type', 160)->nullable()->index();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 160)->nullable();
            $table->string('role_name', 64)->nullable();
            $table->string('url', 512)->nullable();
            $table->string('method', 16)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('reference_number', 32)->nullable()->comment('Denormalised application reference for fast search');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['auditable_type', 'auditable_id', 'created_at'], 'audit_logs_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('application_transitions');
    }
};
