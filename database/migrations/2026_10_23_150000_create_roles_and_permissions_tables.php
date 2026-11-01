<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique()->comment('Machine name: admin, officer, public');
            $table->string('display_name', 128);
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('level')->default(0)->comment('Higher level = broader access');
            $table->boolean('is_system')->default(false)->comment('Cannot be deleted');
            $table->timestamps();

            $table->index('level');
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->unique()->comment('Ability string, e.g. applications.assign');
            $table->string('display_name', 160);
            $table->string('group', 64)->index();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->unique(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
