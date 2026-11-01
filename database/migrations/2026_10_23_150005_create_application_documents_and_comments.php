<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('label', 160)->comment('Which required document this satisfies');
            $table->string('original_name', 255);
            $table->string('path');
            $table->string('disk', 32)->default('documents');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 64)->comment('sha256 of stored bytes, proves the file was not altered');
            $table->string('status', 24)->default('pending')->comment('pending | accepted | rejected');
            $table->text('review_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['application_id', 'status']);
            $table->index('checksum');
        });

        Schema::create('application_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false)->comment('Hidden from the public applicant');
            $table->timestamps();

            $table->index(['application_id', 'is_internal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_comments');
        Schema::dropIfExists('application_documents');
    }
};
