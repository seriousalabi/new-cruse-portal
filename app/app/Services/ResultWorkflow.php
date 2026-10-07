<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\PublicationVersion;
use App\Models\StudentEnrollment;
use App\Models\StudentResult;
use App\Models\TeacherSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ResultWorkflow
{
    public function roster(TeacherSubmission $submission): Collection
    {
        $assignment = $submission->teacherAssignment;

        return StudentEnrollment::query()
            ->with(['student.person'])
            ->where('academic_year_id', $assignment->academic_year_id)
            ->where('grade_level_id', $assignment->grade_level_id)
            ->where('status', 'active')
            ->whereHas('student', fn ($query) => $query->where('status', 'active'))
            ->orderBy('student_profile_id')
            ->get()
            ->pluck('student');
    }

    /** @return array{roster:Collection, results:Collection, complete:bool, missing:int} */
    public function completion(TeacherSubmission $submission): array
    {
        $roster = $this->roster($submission);
        $results = $submission->studentResults()->get()->keyBy('student_profile_id');
        $missing = 0;
        foreach ($roster as $student) {
            $result = $results->get($student->id);
            if (! $result || ! $result->isComplete()) {
                $missing++;
            }
        }

        return ['roster' => $roster, 'results' => $results, 'complete' => $roster->isNotEmpty() && $missing === 0, 'missing' => $missing];
    }

    public function publish(TeacherSubmission $submission, User $actor, bool $incompleteOverride, ?string $overrideReason, ?string $reviewNote): PublicationVersion
    {
        return DB::transaction(function () use ($submission, $actor, $incompleteOverride, $overrideReason, $reviewNote): PublicationVersion {
            $submission = TeacherSubmission::query()->whereKey($submission->id)->lockForUpdate()->with('teacherAssignment')->firstOrFail();
            if ($submission->status !== TeacherSubmission::STATUS_SUBMITTED) {
                throw ValidationException::withMessages(['status' => 'Only a submitted result can be approved and published.']);
            }

            $completion = $this->completion($submission);
            if ($completion['roster']->isEmpty()) {
                throw ValidationException::withMessages(['status' => 'This class has no active enrolled students for the selected academic year. A result cannot be published.']);
            }
            if (! $completion['complete']) {
                if (! $incompleteOverride || ! $actor->hasPermission('results.incomplete-override')) {
                    throw ValidationException::withMessages(['override_incomplete' => 'This result is incomplete. An authorized Admin or Super Admin must approve it with a reason.']);
                }
                if (trim((string) $overrideReason) === '') {
                    throw ValidationException::withMessages(['override_reason' => 'Enter a reason to approve an incomplete result.']);
                }
            } elseif ($incompleteOverride && trim((string) $overrideReason) === '') {
                throw ValidationException::withMessages(['override_reason' => 'Enter a reason for the publication override.']);
            }

            $rows = [];
            foreach ($completion['roster'] as $student) {
                /** @var StudentResult|null $result */
                $result = $completion['results']->get($student->id);
                $row = ['student_profile_id' => $student->id, 'student_code' => $student->student_code];
                foreach (ResultScoring::FIELDS as $field) {
                    $row[$field] = $result?->{$field};
                }
                $row['total_score'] = $result?->total_score;
                $row['teacher_comment'] = $result?->teacher_comment;
                $row['complete'] = $result?->isComplete() ?? false;
                $rows[] = $row;
            }

            $nextVersion = ((int) $submission->publicationVersions()->max('version_number')) + 1;
            $version = $submission->publicationVersions()->create([
                'version_number' => $nextVersion,
                'published_by' => $actor->id,
                'published_at' => now(),
                'incomplete_override' => ! $completion['complete'],
                'override_reason' => ! $completion['complete'] ? trim((string) $overrideReason) : null,
                'result_snapshot' => [
                    'academic_year_id' => $submission->teacherAssignment->academic_year_id,
                    'grade_level_id' => $submission->teacherAssignment->grade_level_id,
                    'term_id' => $submission->term_id,
                    'subject_id' => $submission->subject_id,
                    'rows' => $rows,
                ],
            ]);

            $submission->update([
                'status' => TeacherSubmission::STATUS_APPROVED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_note' => $reviewNote,
            ]);

            $reopen = $submission->reopenRequests()->whereNotNull('resubmitted_at')->orderByDesc('id')->first();
            if ($reopen) {
                $reopen->update(['republished_at' => now()]);
            }

            DB::table('audit_events')->insert([
                'actor_id' => $actor->id,
                'action' => 'result.published',
                'subject_type' => TeacherSubmission::class,
                'subject_id' => $submission->id,
                'reason' => ! $completion['complete'] ? trim((string) $overrideReason) : $reviewNote,
                'after_values' => json_encode([
                    'publication_version_id' => $version->id,
                    'version_number' => $version->version_number,
                    'student_count' => count($rows),
                    'missing_count' => $completion['missing'],
                    'incomplete_override' => ! $completion['complete'],
                ]),
                'ip_address' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);

            return $version;
        });
    }
}
