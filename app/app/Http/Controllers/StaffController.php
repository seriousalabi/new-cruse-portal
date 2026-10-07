<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Person;
use App\Models\StaffProfile;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $year = AcademicYear::where('is_current', true)->first();
        $staff = StaffProfile::with([
            'person',
            'assignments' => fn ($query) => $query->when($year, fn ($assignments) => $assignments->where('academic_year_id', $year->id))
                ->with(['gradeLevel', 'subjects'])
                ->withCount('submissions'),
        ])->orderBy('id')->paginate(25)->withQueryString();

        $grades = GradeLevel::where('is_active', true)->orderBy('sort_order')->get();
        $selectedGrade = $grades->firstWhere('id', $request->integer('grade_id'));
        $subjects = $selectedGrade && $year
            ? Subject::whereIn('id', DB::table('class_subjects')
                ->where('grade_level_id', $selectedGrade->id)
                ->where(fn ($query) => $query->whereNull('academic_year_id')->orWhere('academic_year_id', $year->id))
                ->pluck('subject_id'))
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();

        return view('staff.index', [
            'staff' => $staff,
            'activeStaff' => StaffProfile::with('person')->where('status', 'active')->orderBy('id')->get(),
            'grades' => $grades,
            'subjects' => $subjects,
            'selectedGrade' => $selectedGrade,
            'currentYear' => $year,
            'editingAssignment' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'staff_code' => ['nullable', 'string', 'max:80', 'unique:staff_profiles,staff_code'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'started_on' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $person = Person::create([
                'first_name' => trim($data['first_name']),
                'middle_name' => isset($data['middle_name']) ? trim($data['middle_name']) : null,
                'last_name' => trim($data['last_name']),
                'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            ]);
            $profile = StaffProfile::create([
                'person_id' => $person->id,
                'staff_code' => $data['staff_code'] ? trim($data['staff_code']) : null,
                'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
                'status' => 'active',
                'started_on' => $data['started_on'] ?? null,
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'staff.profile.created',
                'subject_type' => StaffProfile::class,
                'subject_id' => $profile->id,
                'after_values' => json_encode(['staff_code' => $profile->staff_code, 'staff_profile_id' => $profile->id]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('staff.index')->with('status', 'Teacher profile created. Sign-in access has not been issued.');
    }

    public function update(Request $request, StaffProfile $staffProfile): RedirectResponse
    {
        $person = $staffProfile->person;
        $data = $request->validate([
            'staff_code' => ['nullable', 'string', 'max:80', Rule::unique('staff_profiles', 'staff_code')->ignore($staffProfile->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $before = [
            'staff_code' => $staffProfile->staff_code,
            'first_name' => $person->first_name,
            'middle_name' => $person->middle_name,
            'last_name' => $person->last_name,
            'email' => $staffProfile->email,
            'phone' => $person->phone,
        ];

        DB::transaction(function () use ($request, $staffProfile, $person, $data, $before): void {
            $person->update([
                'first_name' => trim($data['first_name']),
                'middle_name' => isset($data['middle_name']) ? trim($data['middle_name']) : null,
                'last_name' => trim($data['last_name']),
                'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            ]);
            $staffProfile->update([
                'staff_code' => $data['staff_code'] ? trim($data['staff_code']) : null,
                'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'staff.contact.updated',
                'subject_type' => StaffProfile::class,
                'subject_id' => $staffProfile->id,
                'before_values' => json_encode($before),
                'after_values' => json_encode([
                    'staff_code' => $staffProfile->staff_code,
                    'first_name' => $person->first_name,
                    'middle_name' => $person->middle_name,
                    'last_name' => $person->last_name,
                    'email' => $staffProfile->email,
                    'phone' => $person->phone,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('staff.index')->with('status', 'Teacher contact details updated. Existing assignments and result history are retained.');
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $year = AcademicYear::where('is_current', true)->first();
        abort_unless($year, 409, 'Set a current academic year before assigning teachers.');
        $data = $this->validateAssignment($request, $year);
        $staffProfile = StaffProfile::whereKey($data['staff_profile_id'])->where('status', 'active')->firstOrFail();
        abort_if(
            TeacherAssignment::where('staff_profile_id', $staffProfile->id)->where('academic_year_id', $year->id)->exists(),
            422,
            'This teacher already has an assignment in the current academic year. End or edit that assignment instead.'
        );

        DB::transaction(function () use ($request, $data, $year, $staffProfile): void {
            $assignment = TeacherAssignment::create([
                'staff_profile_id' => $staffProfile->id,
                'grade_level_id' => $data['grade_level_id'],
                'academic_year_id' => $year->id,
                'status' => 'active',
                'starts_on' => $data['starts_on'] ?? null,
            ]);
            $assignment->subjects()->sync($data['subject_ids']);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.assignment.created',
                'subject_type' => TeacherAssignment::class,
                'subject_id' => $assignment->id,
                'after_values' => json_encode([
                    'staff_profile_id' => $staffProfile->id,
                    'grade_level_id' => $assignment->grade_level_id,
                    'academic_year_id' => $year->id,
                    'subject_ids' => $data['subject_ids'],
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('staff.index', ['grade_id' => $data['grade_level_id']])->with('status', 'Teacher assigned to the selected class and subjects.');
    }

    public function editAssignment(Request $request, TeacherAssignment $assignment): View
    {
        $year = AcademicYear::where('is_current', true)->first();
        abort_unless($year && (int) $assignment->academic_year_id === (int) $year->id && $assignment->status === 'active', 404);
        abort_if($assignment->submissions()->exists(), 409, 'An assignment with result submissions cannot be edited. End it and create a replacement assignment to preserve its history.');

        $selectedGrade = GradeLevel::whereKey($assignment->grade_level_id)->first();
        $subjects = $this->subjectsForGrade($selectedGrade?->id, $year->id);
        $staff = StaffProfile::with([
            'person',
            'assignments' => fn ($query) => $query->where('academic_year_id', $year->id)->with(['gradeLevel', 'subjects'])->withCount('submissions'),
        ])->orderBy('id')->paginate(25)->withQueryString();

        return view('staff.index', [
            'staff' => $staff,
            'activeStaff' => StaffProfile::with('person')->where('status', 'active')->orderBy('id')->get(),
            'grades' => GradeLevel::where('is_active', true)->orderBy('sort_order')->get(),
            'subjects' => $subjects,
            'selectedGrade' => $selectedGrade,
            'currentYear' => $year,
            'editingAssignment' => $assignment->load(['staffProfile.person', 'subjects']),
        ]);
    }

    public function updateAssignment(Request $request, TeacherAssignment $assignment): RedirectResponse
    {
        $year = AcademicYear::where('is_current', true)->first();
        abort_unless($year && (int) $assignment->academic_year_id === (int) $year->id && $assignment->status === 'active', 404);
        abort_if($assignment->submissions()->exists(), 409, 'An assignment with result submissions cannot be edited. End it and create a replacement assignment to preserve its history.');
        $data = $this->validateAssignment($request, $year);
        $before = ['grade_level_id' => $assignment->grade_level_id, 'subject_ids' => $assignment->subjects()->pluck('subjects.id')->all()];

        DB::transaction(function () use ($request, $assignment, $data, $before): void {
            $assignment->update([
                'grade_level_id' => $data['grade_level_id'],
                'starts_on' => $data['starts_on'] ?? null,
            ]);
            $assignment->subjects()->sync($data['subject_ids']);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.assignment.updated',
                'subject_type' => TeacherAssignment::class,
                'subject_id' => $assignment->id,
                'before_values' => json_encode($before),
                'after_values' => json_encode(['grade_level_id' => $assignment->grade_level_id, 'subject_ids' => $data['subject_ids']]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('staff.index')->with('status', 'Teacher assignment updated and recorded in the audit history.');
    }

    public function endAssignment(Request $request, TeacherAssignment $assignment): RedirectResponse
    {
        abort_unless($assignment->status === 'active', 404);
        $data = $request->validate(['ends_on' => ['required', 'date', 'before_or_equal:today']]);
        abort_if($assignment->starts_on && $data['ends_on'] < $assignment->starts_on->toDateString(), 422, 'The end date cannot be earlier than the assignment start date.');

        DB::transaction(function () use ($request, $assignment, $data): void {
            $before = ['status' => $assignment->status, 'ends_on' => $assignment->ends_on?->toDateString()];
            $assignment->update(['status' => 'ended', 'ends_on' => $data['ends_on']]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'teacher.assignment.ended',
                'subject_type' => TeacherAssignment::class,
                'subject_id' => $assignment->id,
                'before_values' => json_encode($before),
                'after_values' => json_encode(['status' => $assignment->status, 'ends_on' => $assignment->ends_on?->toDateString()]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('staff.index')->with('status', 'Teacher assignment ended. The staff profile and result history were retained.');
    }

    public function endStaffProfile(Request $request, StaffProfile $staffProfile): RedirectResponse
    {
        abort_unless($staffProfile->status === 'active', 404);
        $data = $request->validate(['ended_on' => ['required', 'date', 'before_or_equal:today']]);
        abort_if($staffProfile->started_on && $data['ended_on'] < $staffProfile->started_on->toDateString(), 422, 'The end date cannot be earlier than the employment start date.');

        DB::transaction(function () use ($request, $staffProfile, $data): void {
            $activeAssignments = $staffProfile->assignments()->where('status', 'active')->get();
            foreach ($activeAssignments as $assignment) {
                abort_if($assignment->starts_on && $data['ended_on'] < $assignment->starts_on->toDateString(), 422, 'The staff end date cannot be earlier than an active assignment start date.');
            }
            $assignmentIds = $activeAssignments->pluck('id')->all();
            $staffProfile->update(['status' => 'ended', 'ended_on' => $data['ended_on']]);
            $staffProfile->assignments()->whereIn('id', $assignmentIds)->update(['status' => 'ended', 'ends_on' => $data['ended_on']]);
            $login = $staffProfile->person->user;
            $login?->update(['account_status' => 'inactive']);

            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'staff.profile.ended',
                'subject_type' => StaffProfile::class,
                'subject_id' => $staffProfile->id,
                'after_values' => json_encode(['status' => 'ended', 'ended_on' => $data['ended_on'], 'assignment_ids' => $assignmentIds, 'linked_login_disabled' => (bool) $login]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('staff.index')->with('status', 'Teacher profile and active assignments ended. The staff identity and result records were retained.');
    }

    /** @return array{staff_profile_id:int, grade_level_id:int, starts_on:?string, subject_ids:list<int>} */
    private function validateAssignment(Request $request, AcademicYear $year): array
    {
        $validated = $request->validate([
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'starts_on' => ['nullable', 'date', 'after_or_equal:'.$year->starts_on->toDateString(), 'before_or_equal:today', 'before_or_equal:'.$year->ends_on->toDateString()],
            'subject_ids' => ['sometimes', 'array'],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
        ]);
        $grade = GradeLevel::whereKey($validated['grade_level_id'])->where('is_active', true)->firstOrFail();
        $subjectIds = array_map('intval', $validated['subject_ids'] ?? []);
        $offeredIds = $this->subjectsForGrade($grade->id, $year->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (array_diff($subjectIds, $offeredIds)) {
            throw ValidationException::withMessages(['subject_ids' => 'Select only subjects configured for the chosen class and academic year.']);
        }

        return [
            'staff_profile_id' => (int) $validated['staff_profile_id'],
            'grade_level_id' => $grade->id,
            'starts_on' => $validated['starts_on'] ?? null,
            'subject_ids' => $subjectIds,
        ];
    }

    private function subjectsForGrade(?int $gradeId, int $yearId)
    {
        if (! $gradeId) {
            return collect();
        }

        $ids = DB::table('class_subjects')
            ->where('grade_level_id', $gradeId)
            ->where(fn ($query) => $query->whereNull('academic_year_id')->orWhere('academic_year_id', $yearId))
            ->pluck('subject_id');

        return Subject::whereIn('id', $ids)->where('is_active', true)->orderBy('name')->get();
    }
}
