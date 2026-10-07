<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\StudentResult;
use App\Models\StaffProfile;
use App\Models\TeacherAssignment;
use App\Models\TeacherSubmission;
use App\Models\Term;
use App\Services\ResultScoring;
use App\Services\ResultWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeacherResultController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $this->staffFor($request);
        $year = AcademicYear::where('is_current', true)->first();
        $assignments = $year
            ? $staff->assignments()->where('academic_year_id', $year->id)->where('status', 'active')->with(['gradeLevel', 'subjects'])->get()
            : collect();
        $terms = $year?->terms ?? collect();
        $items = [];
        foreach ($assignments as $assignment) {
            foreach ($assignment->subjects as $subject) {
                foreach ($terms as $term) {
                    $submission = TeacherSubmission::where('teacher_assignment_id', $assignment->id)
                        ->where('term_id', $term->id)
                        ->where('subject_id', $subject->id)
                        ->first();
                    $items[] = compact('assignment', 'subject', 'term', 'submission');
                }
            }
        }

        return view('teacher.results.index', ['items' => $items, 'currentYear' => $year]);
    }

    public function edit(Request $request, int $assignment, int $term, int $subject, ResultWorkflow $workflow): View
    {
        $staff = $this->staffFor($request);
        $year = AcademicYear::where('is_current', true)->firstOrFail();
        $assignmentRecord = TeacherAssignment::with(['gradeLevel', 'subjects'])
            ->whereKey($assignment)
            ->where('staff_profile_id', $staff->id)
            ->where('academic_year_id', $year->id)
            ->firstOrFail();
        abort_unless($assignmentRecord->subjects->contains('id', $subject), 403);
        $termRecord = Term::whereKey($term)->where('academic_year_id', $year->id)->firstOrFail();

        $submission = TeacherSubmission::firstOrCreate([
            'teacher_assignment_id' => $assignmentRecord->id,
            'term_id' => $termRecord->id,
            'subject_id' => $subject,
        ], ['status' => TeacherSubmission::STATUS_DRAFT]);
        $submission->load(['subject', 'term']);
        $completion = $workflow->completion($submission);
        $locked = in_array($submission->status, [TeacherSubmission::STATUS_SUBMITTED, TeacherSubmission::STATUS_APPROVED], true);
        $latestReopen = $submission->reopenRequests()->orderByDesc('id')->first();

        return view('teacher.results.edit', [
            'submission' => $submission,
            'assignment' => $assignmentRecord,
            'roster' => $completion['roster'],
            'results' => $completion['results'],
            'locked' => $locked,
            'latestReopen' => $latestReopen,
        ]);
    }

    public function save(Request $request, int $assignment, int $term, int $subject, ResultScoring $scoring, ResultWorkflow $workflow): RedirectResponse
    {
        $staff = $this->staffFor($request);
        $year = AcademicYear::where('is_current', true)->firstOrFail();
        $assignmentRecord = TeacherAssignment::whereKey($assignment)
            ->where('staff_profile_id', $staff->id)
            ->where('academic_year_id', $year->id)
            ->firstOrFail();
        abort_unless($assignmentRecord->subjects()->where('subjects.id', $subject)->exists(), 403);
        $termRecord = Term::whereKey($term)->where('academic_year_id', $year->id)->firstOrFail();
        $submission = TeacherSubmission::where('teacher_assignment_id', $assignmentRecord->id)
            ->where('term_id', $termRecord->id)
            ->where('subject_id', $subject)
            ->firstOrFail();
        abort_unless(in_array($submission->status, [TeacherSubmission::STATUS_DRAFT, TeacherSubmission::STATUS_RETURNED, TeacherSubmission::STATUS_REOPENED], true), 409, 'Submitted or approved marks are locked.');

        $roster = $workflow->roster($submission);
        $allowedIds = $roster->pluck('id')->map(fn ($id) => (string) $id)->all();
        $providedMarks = $request->input('marks', []);
        $providedComments = $request->input('comments', []);
        if (array_diff(array_map('strval', array_keys($providedMarks)), $allowedIds)
            || array_diff(array_map('strval', array_keys($providedComments)), $allowedIds)) {
            throw ValidationException::withMessages(['marks' => 'The form included a student outside your assigned class. Refresh the page and try again.']);
        }

        $rules = ['marks' => ['nullable', 'array'], 'comments' => ['nullable', 'array']];
        foreach ($allowedIds as $id) {
            foreach (ResultScoring::FIELDS as $field) {
                $maximum = $field === 'exam_score' ? 60 : 10;
                $rules["marks.$id.$field"] = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$maximum];
            }
            $rules["comments.$id"] = ['nullable', 'string', 'max:1000'];
        }
        $data = $request->validate($rules);
        $submit = $request->input('action') === 'submit';
        $reopened = $submission->status === TeacherSubmission::STATUS_REOPENED;
        $reopen = $reopened ? $submission->reopenRequests()->orderByDesc('id')->first() : null;

        DB::transaction(function () use ($request, $submission, $roster, $data, $scoring, $submit, $reopened, $reopen): void {
            $submission = TeacherSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($submission->status, [TeacherSubmission::STATUS_DRAFT, TeacherSubmission::STATUS_RETURNED, TeacherSubmission::STATUS_REOPENED], true), 409, 'This result was locked while you were editing it. Refresh and check its status.');

            foreach ($roster as $student) {
                $id = (string) $student->id;
                $marks = $data['marks'][$id] ?? [];
                $comment = $data['comments'][$id] ?? null;
                $hasInput = collect(ResultScoring::FIELDS)->contains(fn ($field) => array_key_exists($field, $marks) && $marks[$field] !== null && $marks[$field] !== '')
                    || ($comment !== null && trim($comment) !== '');
                $existing = StudentResult::where('teacher_submission_id', $submission->id)->where('student_profile_id', $student->id)->first();
                if (! $existing && ! $hasInput && ! $submit) {
                    continue;
                }
                $result = $existing ?? new StudentResult(['teacher_submission_id' => $submission->id, 'student_profile_id' => $student->id]);
                $before = [];
                foreach (ResultScoring::FIELDS as $field) {
                    $before[$field] = $result->{$field};
                }
                $before['total_score'] = $result->total_score;
                $before['teacher_comment'] = $result->teacher_comment;
                $result->applyMarks($marks, $scoring);
                $result->teacher_comment = $comment;
                $result->save();
                if ($reopened) {
                    $after = [];
                    foreach (ResultScoring::FIELDS as $field) {
                        $after[$field] = $result->{$field};
                    }
                    $after['total_score'] = $result->total_score;
                    $after['teacher_comment'] = $result->teacher_comment;
                    if ($before !== $after) {
                        DB::table('result_revisions')->insert([
                            'reopen_request_id' => $reopen?->id,
                            'teacher_submission_id' => $submission->id,
                            'student_result_id' => $result->id,
                            'actor_id' => $request->user()->id,
                            'action' => 'score.updated',
                            'reason' => $reopen?->reason,
                            'before_values' => json_encode($before),
                            'after_values' => json_encode($after),
                            'created_at' => now(),
                        ]);
                    }
                }
            }

            if ($submit) {
                $submission->update([
                    'status' => TeacherSubmission::STATUS_SUBMITTED,
                    'submitted_by' => $request->user()->id,
                    'submitted_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                ]);
                if ($reopen) {
                    $reopen->update(['resubmitted_at' => now()]);
                }
                DB::table('audit_events')->insert([
                    'actor_id' => $request->user()->id,
                    'action' => 'result.submitted',
                    'subject_type' => TeacherSubmission::class,
                    'subject_id' => $submission->id,
                    'after_values' => json_encode(['status' => TeacherSubmission::STATUS_SUBMITTED, 'reopened_correction' => $reopened]),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                    'created_at' => now(),
                ]);
            }
        });

        return redirect()->route('teacher.results.edit', [$assignmentRecord->id, $termRecord->id, $subject])
            ->with('status', $submit ? 'Marks submitted to Admin for verification. They are locked while under review.' : 'Draft marks saved.');
    }

    private function staffFor(Request $request): StaffProfile
    {
        $personId = $request->user()->person_id;
        abort_unless($personId, 403, 'This teacher login is not linked to a staff profile.');

        return StaffProfile::where('person_id', $personId)->firstOrFail();
    }
}
