<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained()->restrictOnDelete();
            $table->string('student_code')->unique();
            $table->date('admitted_on')->nullable();
            $table->string('status')->default('active')->index();
            $table->date('left_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->timestamps();
        });

        Schema::create('parent_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('staff_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained()->restrictOnDelete();
            $table->string('staff_code')->nullable()->unique();
            $table->string('status')->default('active')->index();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('person_id')->nullable()->unique()->constrained()->nullOnDelete();
        });

        Schema::create('parent_student_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->string('relationship');
            $table->boolean('is_primary_contact')->default(false);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->index(['student_profile_id', 'valid_until']);
            $table->index(['parent_profile_id', 'valid_until']);
        });

        Schema::create('student_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->unique(['student_profile_id', 'academic_year_id']);
        });

        Schema::create('teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_level_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('active')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->unique(['staff_profile_id', 'academic_year_id']);
            $table->index(['grade_level_id', 'academic_year_id']);
        });

        Schema::create('teacher_assignment_subjects', function (Blueprint $table): void {
            $table->foreignId('teacher_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['teacher_assignment_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignment_subjects');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('student_enrollments');
        Schema::dropIfExists('parent_student_links');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['person_id']);
            $table->dropUnique(['person_id']);
            $table->dropColumn('person_id');
        });
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('parent_profiles');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('people');
    }
};
