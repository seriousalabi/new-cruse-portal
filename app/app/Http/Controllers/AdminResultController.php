<?php

namespace App\Http\Controllers;

use App\Models\TeacherSubmission;
use App\Services\ResultWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminResultController extends Controller
{
    public function index(): View
    {
        $submissions = TeacherSubmission::with(['teacherAssignment.staffProfile.person', 'teacherAssignment.gradeLevel', 'subject', 'term', 'publicationVersions'])
            ->whereIn('status', [TeacherSubmission::STATUS_SUBMITTED, TeacherSubmission::STATUS_APPROVED])
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 ELSE 1 END")
            ->orderByDesc('submitted_at')->get();

        return view('admin.results.index', compact('submissions'));
    }

    public function show(TeacherSubmission $submission, ResultWorkflow $workflow): View
    {
        $submission->load(['teacherAssignment.staffProfile.person', 'teacherAssignment.gradeLevel', 'subject', 'term', 'publicationVersions']);
        abort_unless(in_array($submission->status, [TeacherSubmission::STATUS_SUBMITTED, TeacherSubmission::STATUS_APPROVED], true), 404);
        $completion = $workflow->completion($submission);
        $latest = $submission->publicationVersions->first();
        $publishedRows = collect($latest?->result_snapshot['rows'] ?? [])->keyBy('student_profile_id');

        return view('admin.results.show', ['submission' => $submission, ...$completion, 'publishedRows' => $publishedRows]);
    }

    public function approve(Request $request, TeacherSubmission $submission, ResultWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:2000'],
            'override_incomplete' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $override = (bool) ($data['override_incomplete'] ?? false);
        abort_unless($request->user()->hasPermission('results.approve'), 403);
        $workflow->publish($submission, $request->user(), $override, $data['override_reason'] ?? null, $data['review_note'] ?? null);

        return redirect()->route('admin.results.show', $submission)->with('status', 'Result approved and published. The published snapshot is now visible to linked parents.');
    }

    public function returnToTeacher(Request $request, TeacherSubmission $submission): RedirectResponse
    {
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']]);
        abort_unless($request->user()->hasPermission('results.review'), 403);
        abort_unless($submission->status === TeacherSubmission::STATUS_SUBMITTED, 409);

        DB::transaction(function () use ($request, $submission, $data): void {
            $locked = TeacherSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === TeacherSubmission::STATUS_SUBMITTED, 409);
            $before = ['status' => $locked->status, 'review_note' => $locked->review_note];
            $locked->update(['status' => TeacherSubmission::STATUS_RETURNED, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note']]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id, 'action' => 'result.returned', 'subject_type' => TeacherSubmission::class,
                'subject_id' => $locked->id, 'reason' => $data['review_note'], 'before_values' => json_encode($before),
                'after_values' => json_encode(['status' => $locked->status, 'review_note' => $locked->review_note]),
                'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000), 'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.results.show', $submission)->with('status', 'Submission returned to the teacher with your note.');
    }

    public function reopen(Request $request, TeacherSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('results.reopen'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        DB::transaction(function () use ($request, $submission, $data): void {
            $locked = TeacherSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === TeacherSubmission::STATUS_APPROVED, 409, 'Only a published result can be reopened.');
            $before = ['status' => $locked->status];
            $reopen = $locked->reopenRequests()->create(['requested_by' => $request->user()->id, 'reason' => $data['reason'], 'opened_at' => now()]);
            $locked->update(['status' => TeacherSubmission::STATUS_REOPENED]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id, 'action' => 'result.reopened', 'subject_type' => TeacherSubmission::class,
                'subject_id' => $locked->id, 'reason' => $data['reason'], 'before_values' => json_encode($before),
                'after_values' => json_encode(['status' => $locked->status, 'reopen_request_id' => $reopen->id]),
                'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 1000), 'created_at' => now(),
            ]);
        });

        return redirect()->route('admin.results.show', $submission)->with('status', 'Submission reopened for the teacher. Parents continue to see the last approved snapshot.');
    }

    public function correctionRequests(): View
    {
        $requests = DB::table('correction_requests')->join('parent_profiles', 'parent_profiles.id', '=', 'correction_requests.parent_profile_id')
            ->join('people as parents', 'parents.id', '=', 'parent_profiles.person_id')
            ->join('student_profiles', 'student_profiles.id', '=', 'correction_requests.student_profile_id')
            ->join('people as students', 'students.id', '=', 'student_profiles.person_id')
            ->join('terms', 'terms.id', '=', 'correction_requests.term_id')
            ->leftJoin('subjects', 'subjects.id', '=', 'correction_requests.subject_id')
            ->select('correction_requests.*', 'parents.first_name as parent_first_name', 'parents.last_name as parent_last_name', 'students.first_name as student_first_name', 'students.last_name as student_last_name', 'student_profiles.student_code', 'terms.name as term_name', 'subjects.name as subject_name')
            ->orderByRaw("CASE WHEN correction_requests.status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('correction_requests.created_at')->get();

        return view('admin.results.corrections', compact('requests'));
    }

    public function respondToCorrection(Request $request, int $correctionRequest): RedirectResponse
    {
        $data = $request->validate(['school_response' => ['required', 'string', 'min:3', 'max:2000']]);
        $updated = DB::table('correction_requests')->where('id', $correctionRequest)->where('status', 'open')->update([
            'status' => 'resolved', 'responded_by' => $request->user()->id, 'school_response' => $data['school_response'], 'responded_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless($updated, 409, 'This request has already been answered. Refresh the list.');

        return redirect()->route('admin.results.corrections')->with('status', 'Your response has been saved.');
    }
}
