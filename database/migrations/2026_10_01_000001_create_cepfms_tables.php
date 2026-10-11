<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Core CEPFMS schema: citizens, submissions (+ complaint/proposal extensions),
 * attachments, the moderation queue, responses, the activity log, settings,
 * and the citizen-submission IP rate limit table.
 *
 * Mirrors database/production.sql of the original CEPFMS app column for
 * column, so the business rules (moderation-first workflow, approved-only
 * analytics, SLA tracking) carry over unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('citizens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('full_name', 150);
            $table->string('email', 190)->index('idx_citizens_email');
            $table->string('phone', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('ALTER TABLE citizens AUTO_INCREMENT = 100000');
        }

        Schema::create('submissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('reference_number', 20)->unique();
            $table->unsignedInteger('citizen_id');
            $table->enum('submission_type', ['feedback', 'complaint', 'proposal']);
            $table->string('subject', 200);
            $table->text('description');
            $table->string('content_hash', 64)->nullable();
            $table->string('category', 60)->nullable()->default('Other');
            $table->enum('priority', ['Critical', 'High', 'Medium', 'Low'])->default('Medium');
            $table->string('summary', 500)->nullable();
            $table->decimal('ai_confidence', 4, 3)->nullable();
            $table->string('ai_reasoning', 500)->nullable();
            $table->enum('sentiment', ['Positive', 'Neutral', 'Negative'])->default('Neutral');
            $table->decimal('sentiment_score', 4, 3)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('barangay', 120)->nullable();
            $table->string('street', 190)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', ['Pending', 'Received', 'In Review', 'Assigned', 'Escalated', 'Resolved'])->default('Pending');
            $table->enum('moderation_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->unsignedInteger('support_count')->default(0);
            $table->dateTime('sla_due_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('citizen_id', 'fk_sub_citizen')->references('id')->on('citizens')->cascadeOnDelete();
            $table->index('status', 'idx_sub_status');
            $table->index('moderation_status', 'idx_sub_moderation_status');
            $table->index('category', 'idx_sub_category');
            $table->index('priority', 'idx_sub_priority');
            $table->index('sentiment', 'idx_sub_sentiment');
            $table->index('created_at', 'idx_sub_created');
            $table->index('submission_type', 'idx_sub_type');
            $table->index(['moderation_status', 'status'], 'idx_sub_modstatus_status');
            $table->index(['content_hash', 'created_at'], 'idx_sub_content_hash');
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('submission_id');
            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('file_path', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('submission_id', 'fk_att_submission')->references('id')->on('submissions')->cascadeOnDelete();
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('submission_id')->unique();
            $table->enum('sla_status', ['On Track', 'At Risk', 'Breached', 'Met'])->default('On Track');
            $table->boolean('escalated')->default(false);
            $table->dateTime('closed_at')->nullable();
            $table->foreign('submission_id', 'fk_complaint_submission')->references('id')->on('submissions')->cascadeOnDelete();
            $table->index('sla_status', 'idx_complaint_sla');
        });

        Schema::create('proposals', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('submission_id')->unique();
            $table->enum('proposal_status', ['Under Review', 'Approved', 'Rejected', 'Implemented'])->default('Under Review');
            $table->foreign('submission_id', 'fk_proposal_submission')->references('id')->on('submissions')->cascadeOnDelete();
        });

        Schema::create('moderation_queue', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('content_type', ['Feedback', 'Complaint', 'Proposal']);
            $table->unsignedInteger('submission_id')->nullable();
            $table->unsignedInteger('citizen_id')->nullable();
            $table->text('content_snapshot');
            $table->string('flag', 60)->nullable();
            $table->unsignedInteger('spam_score')->default(0);
            $table->string('spam_flags', 255)->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Flagged'])->default('Pending');
            $table->string('reject_reason', 40)->nullable();
            $table->string('reject_note', 255)->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('submission_id', 'fk_mod_submission')->references('id')->on('submissions')->cascadeOnDelete();
            $table->foreign('reviewed_by', 'fk_mod_reviewer')->references('id')->on('users')->nullOnDelete();
            $table->index('status', 'idx_mod_status');
        });

        Schema::create('submission_rate_limits', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ip_address', 45);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ip_address', 'created_at'], 'idx_rate_ip_created');
        });

        Schema::create('responses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('submission_id');
            $table->unsignedInteger('staff_id')->nullable();
            $table->string('subject', 200);
            $table->text('body');
            $table->text('internal_notes')->nullable();
            $table->enum('status', ['Draft', 'Pending Approval', 'Sent', 'Overdue'])->default('Draft');
            $table->dateTime('due_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->foreign('submission_id', 'fk_resp_submission')->references('id')->on('submissions')->cascadeOnDelete();
            $table->foreign('staff_id', 'fk_resp_staff')->references('id')->on('users')->nullOnDelete();
            $table->index('status', 'idx_resp_status');
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('staff_id')->nullable();
            $table->string('action', 150);
            $table->string('entity', 60);
            $table->unsignedInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('staff_id', 'fk_log_staff')->references('id')->on('users')->nullOnDelete();
            $table->index(['entity', 'entity_id'], 'idx_log_entity');
            $table->index('created_at', 'idx_log_created');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('setting_key', 100)->primary();
            $table->string('setting_value', 500)->nullable();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'activity_log', 'responses', 'submission_rate_limits', 'moderation_queue',
                  'proposals', 'complaints', 'attachments', 'submissions', 'citizens'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
