<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('draft')->index();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['teacher_assignment_id', 'term_id', 'subject_id'], 'teacher_submission_scope_unique');
        });

        Schema::create('student_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->decimal('assignment_1', 5, 2)->nullable();
            $table->decimal('assignment_2', 5, 2)->nullable();
            $table->decimal('assignment_3', 5, 2)->nullable();
            $table->decimal('assignment_4', 5, 2)->nullable();
            $table->decimal('exam_score', 5, 2)->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->text('teacher_comment')->nullable();
            $table->timestamps();
            $table->unique(['teacher_submission_id', 'student_profile_id'], 'submission_student_result_unique');
            $table->index(['student_profile_id', 'teacher_submission_id']);
        });

        Schema::create('publication_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_submission_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->boolean('incomplete_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->json('result_snapshot');
            $table->timestamps();
            $table->unique(['teacher_submission_id', 'version_number']);
        });

        Schema::create('reopen_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->timestamp('opened_at');
            $table->timestamp('resubmitted_at')->nullable();
            $table->timestamp('republished_at')->nullable();
            $table->timestamps();
            $table->index(['teacher_submission_id', 'opened_at']);
        });

        Schema::create('result_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reopen_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_submission_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_result_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['teacher_submission_id', 'created_at']);
        });

        Schema::create('correction_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('term_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->string('status')->default('open')->index();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('school_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('run_number');
            $table->string('status')->default('draft')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['academic_year_id', 'run_number']);
        });

        Schema::create('promotion_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promotion_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('current_grade_level_id')->constrained('grade_levels')->restrictOnDelete();
            $table->foreignId('proposed_grade_level_id')->nullable()->constrained('grade_levels')->nullOnDelete();
            $table->unsignedSmallInteger('required_subject_count')->default(0);
            $table->unsignedSmallInteger('failed_subject_count')->default(0);
            $table->string('outcome')->index();
            $table->text('decision_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['promotion_run_id', 'student_profile_id']);
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('promotion_decisions');
        Schema::dropIfExists('promotion_runs');
        Schema::dropIfExists('correction_requests');
        Schema::dropIfExists('result_revisions');
        Schema::dropIfExists('reopen_requests');
        Schema::dropIfExists('publication_versions');
        Schema::dropIfExists('student_results');
        Schema::dropIfExists('teacher_submissions');
    }
};
