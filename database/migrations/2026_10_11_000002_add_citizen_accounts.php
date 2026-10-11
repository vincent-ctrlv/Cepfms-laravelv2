<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Citizen accounts: citizens sign in with Google and verify their identity
 * with a government ID (address in San Jose del Monte) before they can submit.
 *
 * citizens gains the Google account link; citizen_verifications holds each ID
 * check. The ID number itself is never stored: only its last 4 characters (for
 * the reviewer) and a keyed hash (to spot one ID used on several accounts).
 * The photos live outside public/ (storage/app/private/verifications/...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizens', function (Blueprint $table) {
            $table->string('google_sub', 64)->nullable()->unique('uq_citizens_google_sub')->after('phone');
            $table->dateTime('last_login_at')->nullable()->after('google_sub');
            $table->boolean('is_active')->default(true)->after('last_login_at');
        });

        Schema::create('citizen_verifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('citizen_id');
            $table->string('full_name', 150);
            $table->string('id_type', 60);
            $table->char('id_number_hash', 64);
            $table->string('id_number_last4', 4);
            $table->string('barangay', 120);
            $table->string('id_photo', 255);
            $table->string('selfie_photo', 255);
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->string('reject_reason', 255)->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('citizen_id', 'fk_cv_citizen')->references('id')->on('citizens')->cascadeOnDelete();
            $table->foreign('reviewed_by', 'fk_cv_reviewer')->references('id')->on('users')->nullOnDelete();
            $table->index(['citizen_id', 'id'], 'idx_cv_citizen');
            $table->index(['status', 'created_at'], 'idx_cv_status');
            $table->index('id_number_hash', 'idx_cv_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citizen_verifications');
        Schema::table('citizens', function (Blueprint $table) {
            $table->dropUnique('uq_citizens_google_sub');
            $table->dropColumn(['google_sub', 'last_login_at', 'is_active']);
        });
    }
};
