<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff/admin accounts (the CEPFMS `users` table) plus Laravel's session store.
 *
 * Roles: super_admin | admin | staff. Sign-in is password + emailed 6-digit
 * code (otp_code is hashed at rest); two_factor_enabled lets non-super-admins
 * turn the code off from Profile Settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('full_name', 150);
            $table->string('email', 190)->unique();
            $table->string('password_hash', 255);
            $table->string('role', 50)->default('staff');
            $table->string('title', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->string('otp_code', 255)->nullable();
            $table->dateTime('otp_expires_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->boolean('two_factor_enabled')->default(true);
            $table->string('reset_token_hash', 255)->nullable();
            $table->dateTime('reset_token_expires_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('login_attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('identifier', 190);
            $table->enum('attempt_type', ['password', 'otp', 'submission_create', 'password_reset']);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['identifier', 'attempt_type', 'created_at'], 'idx_login_attempts_lookup');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('users');
    }
};
