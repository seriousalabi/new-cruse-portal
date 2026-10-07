<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ParentProfile;
use App\Models\Person;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FamilyController extends Controller
{
    public function index(): View
    {
        $year = AcademicYear::where('is_current', true)->first();
        $parents = ParentProfile::with([
            'person',
            'activeStudents' => fn ($query) => $query->with([
                'person',
                'enrollments' => fn ($enrollments) => $enrollments->where('academic_year_id', $year?->id)->with('gradeLevel'),
            ]),
        ])->orderBy('id')->paginate(20);

        $students = $year
            ? StudentProfile::with(['person', 'enrollments' => fn ($query) => $query->where('academic_year_id', $year->id)->with('gradeLevel')])
                ->where('status', 'active')
                ->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $year->id)->where('status', 'active'))
                ->orderBy('student_code')
                ->get()
            : collect();

        return view('families.index', [
            'parents' => $parents,
            'students' => $students,
            'currentYear' => $year,
            'parentOptions' => ParentProfile::with('person')->where('status', 'active')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $year = AcademicYear::where('is_current', true)->first();
        abort_unless($year, 409, 'Set a current academic year before linking a guardian to an enrolled student.');

        $data = $request->validate([
            'parent_external_id' => ['nullable', 'string', 'max:80', 'unique:people,external_id'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'relationship' => ['required', 'string', 'max:40'],
            'valid_from' => ['nullable', 'date', 'before_or_equal:today'],
            'is_primary_contact' => ['sometimes', 'boolean'],
        ]);

        $student = StudentProfile::whereKey($data['student_profile_id'])
            ->where('status', 'active')
            ->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $year->id)->where('status', 'active'))
            ->firstOrFail();
        $isPrimary = $request->boolean('is_primary_contact');

        DB::transaction(function () use ($data, $student, $isPrimary, $request): void {
            $person = Person::create([
                'external_id' => $data['parent_external_id'] ?: null,
                'first_name' => trim($data['first_name']),
                'middle_name' => isset($data['middle_name']) ? trim($data['middle_name']) : null,
                'last_name' => trim($data['last_name']),
                'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            ]);
            $parent = ParentProfile::create([
                'person_id' => $person->id,
                'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
                'status' => 'active',
            ]);

            if ($isPrimary) {
                DB::table('parent_student_links')
                    ->where('student_profile_id', $student->id)
                    ->whereNull('valid_until')
                    ->update(['is_primary_contact' => false, 'updated_at' => now()]);
            }

            $parent->students()->attach($student->id, [
                'relationship' => trim($data['relationship']),
                'is_primary_contact' => $isPrimary,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_until' => null,
            ]);

            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'parent.created_and_linked',
                'subject_type' => ParentProfile::class,
                'subject_id' => $parent->id,
                'after_values' => json_encode([
                    'parent_external_id' => $person->external_id,
                    'student_profile_id' => $student->id,
                    'relationship' => trim($data['relationship']),
                    'is_primary_contact' => $isPrimary,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('families.index')->with('status', 'Parent/guardian profile created and linked to the student. No login account was created.');
    }

    public function linkExisting(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'parent_profile_id' => ['required', 'integer', 'exists:parent_profiles,id'],
            'student_profile_id' => ['required', 'integer', 'exists:student_profiles,id'],
            'relationship' => ['required', 'string', 'max:40'],
            'valid_from' => ['nullable', 'date', 'before_or_equal:today'],
            'is_primary_contact' => ['sometimes', 'boolean'],
        ]);
        $year = AcademicYear::where('is_current', true)->first();
        abort_unless($year, 409, 'Set a current academic year before linking a guardian to an enrolled student.');
        $parent = ParentProfile::whereKey($data['parent_profile_id'])->where('status', 'active')->firstOrFail();
        $student = StudentProfile::whereKey($data['student_profile_id'])
            ->where('status', 'active')
            ->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $year->id)->where('status', 'active'))
            ->firstOrFail();
        abort_if($parent->activeStudents()->whereKey($student->id)->exists(), 422, 'This guardian is already linked to that student.');
        $isPrimary = $request->boolean('is_primary_contact');

        DB::transaction(function () use ($data, $parent, $student, $isPrimary, $request): void {
            if ($isPrimary) {
                DB::table('parent_student_links')
                    ->where('student_profile_id', $student->id)
                    ->whereNull('valid_until')
                    ->update(['is_primary_contact' => false, 'updated_at' => now()]);
            }
            $parent->students()->attach($student->id, [
                'relationship' => trim($data['relationship']),
                'is_primary_contact' => $isPrimary,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_until' => null,
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'parent.student_link.created',
                'subject_type' => ParentProfile::class,
                'subject_id' => $parent->id,
                'after_values' => json_encode([
                    'student_profile_id' => $student->id,
                    'relationship' => trim($data['relationship']),
                    'is_primary_contact' => $isPrimary,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('families.index')->with('status', 'Existing guardian linked to the student.');
    }

    public function update(Request $request, ParentProfile $parentProfile): RedirectResponse
    {
        $person = $parentProfile->person;
        $data = $request->validate([
            'parent_external_id' => ['nullable', 'string', 'max:80', Rule::unique('people', 'external_id')->ignore($person->id)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $before = [
            'external_id' => $person->external_id,
            'first_name' => $person->first_name,
            'middle_name' => $person->middle_name,
            'last_name' => $person->last_name,
            'email' => $parentProfile->email,
            'phone' => $person->phone,
        ];

        DB::transaction(function () use ($request, $parentProfile, $person, $data, $before): void {
            $person->update([
                'external_id' => $data['parent_external_id'] ?: null,
                'first_name' => trim($data['first_name']),
                'middle_name' => isset($data['middle_name']) ? trim($data['middle_name']) : null,
                'last_name' => trim($data['last_name']),
                'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            ]);
            $parentProfile->update(['email' => isset($data['email']) ? strtolower(trim($data['email'])) : null]);

            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'parent.contact.updated',
                'subject_type' => ParentProfile::class,
                'subject_id' => $parentProfile->id,
                'before_values' => json_encode($before),
                'after_values' => json_encode([
                    'external_id' => $person->external_id,
                    'first_name' => $person->first_name,
                    'middle_name' => $person->middle_name,
                    'last_name' => $person->last_name,
                    'email' => $parentProfile->email,
                    'phone' => $person->phone,
                ]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('families.index')->with('status', 'Parent/guardian contact details updated.');
    }

    public function closeLink(Request $request, ParentProfile $parentProfile, StudentProfile $studentProfile): RedirectResponse
    {
        $link = DB::table('parent_student_links')
            ->where('parent_profile_id', $parentProfile->id)
            ->where('student_profile_id', $studentProfile->id)
            ->whereNull('valid_until')
            ->first();
        abort_unless($link, 404);

        DB::transaction(function () use ($request, $parentProfile, $studentProfile, $link): void {
            DB::table('parent_student_links')->where('id', $link->id)->update([
                'valid_until' => now()->toDateString(),
                'is_primary_contact' => false,
                'updated_at' => now(),
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'parent.student_link.closed',
                'subject_type' => ParentProfile::class,
                'subject_id' => $parentProfile->id,
                'before_values' => json_encode(['student_profile_id' => $studentProfile->id, 'relationship' => $link->relationship, 'is_primary_contact' => (bool) $link->is_primary_contact]),
                'after_values' => json_encode(['valid_until' => now()->toDateString(), 'is_primary_contact' => false]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('families.index')->with('status', 'Guardian link ended. The parent and student records were retained.');
    }
}
