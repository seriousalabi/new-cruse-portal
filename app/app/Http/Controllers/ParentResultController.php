<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ParentProfile;
use App\Models\PublicationVersion;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParentResultController extends Controller
{
    public function index(Request $request): View
    {
        $parent = ParentProfile::with(['students.person', 'students.enrollments.gradeLevel'])
            ->where('person_id', $request->user()->person_id)->where('status', 'active')->firstOrFail();
        $students = $parent->activeStudents()->with(['person', 'enrollments' => fn ($q) => $q->where('status', 'active')->with(['academicYear', 'gradeLevel'])])->get();
        $enrolledYearIds = $students->flatMap(fn ($student) => $student->enrollments->pluck('academic_year_id'))->unique()->values();
        $years = AcademicYear::whereIn('id', $enrolledYearIds)->orderByDesc('starts_on')->get();
        $year = $years->firstWhere('id', (int) $request->query('year')) ?? AcademicYear::where('is_current', true)->first() ?? $years->first();
        $terms = $year?->terms()->orderBy('sort_order')->get() ?? collect();
        $term = $terms->firstWhere('id', (int) $request->query('term')) ?? $terms->first();
        $reports = [];

        if ($year && $term) {
            foreach ($students as $student) {
                $enrollment = $student->enrollments->firstWhere('academic_year_id', $year->id);
                if (! $enrollment) {
                    continue;
                }
                $offerings = \Illuminate\Support\Facades\DB::table('class_subjects')->join('subjects', 'subjects.id', '=', 'class_subjects.subject_id')
                    ->where('class_subjects.grade_level_id', $enrollment->grade_level_id)
                    ->where(fn ($q) => $q->whereNull('class_subjects.academic_year_id')->orWhere('class_subjects.academic_year_id', $year->id))
                    ->where('class_subjects.is_required', true)->select('subjects.id', 'subjects.name')->orderBy('subjects.name')->get();
                $subjectRows = [];
                foreach ($offerings as $offering) {
                    $version = PublicationVersion::query()->whereHas('teacherSubmission', function ($q) use ($enrollment, $term, $offering) {
                        $q->where('term_id', $term->id)->where('subject_id', $offering->id)
                            ->whereHas('teacherAssignment', fn ($assignment) => $assignment->where('academic_year_id', $enrollment->academic_year_id)->where('grade_level_id', $enrollment->grade_level_id));
                    })->orderByDesc('published_at')->first();
                    $row = collect($version?->result_snapshot['rows'] ?? [])->firstWhere('student_profile_id', $student->id);
                    $latestRequest = DB::table('correction_requests')->where('parent_profile_id', $parent->id)->where('student_profile_id', $student->id)->where('term_id', $term->id)->where('subject_id', $offering->id)->orderByDesc('id')->first();
                    $subjectRows[] = ['id' => $offering->id, 'subject' => $offering->name, 'score' => $row['total_score'] ?? null, 'status' => ($row && $row['total_score'] !== null) ? 'Available' : 'N/A', 'request' => $latestRequest];
                }
                $reports[] = ['student' => $student, 'grade' => $enrollment->gradeLevel, 'subjects' => $subjectRows];
            }
        }

        return view('parent.results.index', compact('students', 'years', 'year', 'terms', 'term', 'reports'));
    }

    public function requestCorrection(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_profile_id' => ['required', 'integer'],
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $parent = ParentProfile::where('person_id', $request->user()->person_id)->where('status', 'active')->firstOrFail();
        abort_unless($parent->activeStudents()->where('student_profiles.id', $data['student_profile_id'])->exists(), 403);
        $term = Term::findOrFail($data['term_id']);
        abort_unless($parent->activeStudents()->where('student_profiles.id', $data['student_profile_id'])->whereHas('enrollments', fn ($q) => $q->where('academic_year_id', $term->academic_year_id))->exists(), 403);
        if ($data['subject_id'] ?? null) {
            $gradeId = \App\Models\StudentEnrollment::where('student_profile_id', $data['student_profile_id'])->where('academic_year_id', $term->academic_year_id)->value('grade_level_id');
            abort_unless(DB::table('class_subjects')->where('grade_level_id', $gradeId)->where('subject_id', $data['subject_id'])
                ->where(fn ($q) => $q->whereNull('academic_year_id')->orWhere('academic_year_id', $term->academic_year_id))->exists(), 403);
        }

        DB::table('correction_requests')->insert([
            'parent_profile_id' => $parent->id,
            'student_profile_id' => $data['student_profile_id'],
            'term_id' => $term->id,
            'subject_id' => $data['subject_id'] ?? null,
            'message' => $data['message'],
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('parent.results.index', ['year' => $term->academic_year_id, 'term' => $term->id])->with('status', 'Your message has been sent to the school.');
    }
}
