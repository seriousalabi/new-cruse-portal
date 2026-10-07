<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use App\Models\Person;
use App\Models\StudentEnrollment;
use App\Models\StudentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        DB::table('student_import_batches')
            ->where('status', 'previewed')
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $year = AcademicYear::where('is_current', true)->first();
        $students = StudentProfile::query()
            ->with(['person', 'enrollments' => fn ($query) => $query->where('academic_year_id', $year?->id)->with('gradeLevel')])
            ->withCount('activeParentProfiles')
            ->when($year, fn ($query) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('academic_year_id', $year->id)))
            ->when($request->filled('class'), fn ($query) => $query->whereHas('enrollments', fn ($enrollments) => $enrollments->where('academic_year_id', $year?->id)->where('grade_level_id', $request->integer('class'))))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->query('q'))).'%';
                $query->where(fn ($people) => $people->whereHas('person', fn ($person) => $person
                    ->where('first_name', 'like', $search)
                    ->orWhere('middle_name', 'like', $search)
                    ->orWhere('last_name', 'like', $search))
                    ->orWhere('student_code', 'like', $search));
            })
            ->orderBy('student_code')
            ->paginate(25)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'grades' => GradeLevel::where('is_active', true)->orderBy('sort_order')->get(),
            'currentYear' => $year,
            'preview' => session('import_preview'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $year = AcademicYear::where('is_current', true)->first();
        abort_unless($year, 409, 'Set a current academic year before enrolling students.');

        $data = $request->validate([
            'student_code' => ['required', 'string', 'max:80', 'unique:student_profiles,student_code', 'unique:people,external_id'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'admitted_on' => ['nullable', 'date'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
        ]);

        DB::transaction(function () use ($data, $year, $request): void {
            $person = Person::create([
                'external_id' => $data['student_code'],
                'first_name' => trim($data['first_name']),
                'middle_name' => $data['middle_name'] ? trim($data['middle_name']) : null,
                'last_name' => trim($data['last_name']),
                'date_of_birth' => $data['date_of_birth'] ?? null,
            ]);
            $student = StudentProfile::create([
                'person_id' => $person->id,
                'student_code' => trim($data['student_code']),
                'admitted_on' => $data['admitted_on'] ?? null,
                'status' => 'active',
            ]);
            $enrollment = StudentEnrollment::create([
                'student_profile_id' => $student->id,
                'academic_year_id' => $year->id,
                'grade_level_id' => $data['grade_level_id'],
                'status' => 'active',
                'starts_on' => $year->starts_on,
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'student.created',
                'subject_type' => StudentProfile::class,
                'subject_id' => $student->id,
                'after_values' => json_encode(['student_code' => $student->student_code, 'grade_level_id' => $enrollment->grade_level_id, 'academic_year_id' => $year->id]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('students.index')->with('status', 'Student record and current-year class enrollment created.');
    }

    public function previewImport(Request $request): RedirectResponse
    {
        $request->validate([
            'student_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $year = AcademicYear::where('is_current', true)->first();
        abort_unless($year, 409, 'Set a current academic year before importing student enrollments.');

        $file = $request->file('student_file');
        $checksum = hash_file('sha256', $file->getRealPath());
        $alreadyImported = DB::table('student_import_batches')
            ->where('file_sha256', $checksum)
            ->where('status', 'committed')
            ->exists();
        if ($alreadyImported) {
            return back()->withErrors(['student_file' => 'This exact file has already been imported. Check the student list or upload a corrected file.']);
        }

        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            return back()->withErrors(['student_file' => 'The uploaded CSV could not be read.']);
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '');
            if (! is_array($headers)) {
                return back()->withErrors(['student_file' => 'The uploaded file is empty.']);
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
            if (count(array_unique($headers)) !== count($headers)) {
                return back()->withErrors(['student_file' => 'The CSV contains duplicate column headers. Download a fresh copy of the template.']);
            }
            $required = ['school_student_id', 'first_name', 'middle_name', 'last_name', 'date_of_birth', 'admission_date', 'current_class_code', 'student_status'];
            if (array_diff($required, $headers)) {
                return back()->withErrors(['student_file' => 'CSV headers must match the provided student template.']);
            }

            $grades = GradeLevel::where('is_active', true)->get();
            $gradeMap = [];
            foreach ($grades as $grade) {
                $gradeMap[strtolower($grade->key)] = $grade;
                $gradeMap[strtolower($grade->name)] = $grade;
            }
            foreach (['pn' => 'pre_nursery', 'n1' => 'nursery_1', 'n2' => 'nursery_2', 'r1' => 'reception', 'p1' => 'primary_1', 'p2' => 'primary_2', 'p3' => 'primary_3', 'p4' => 'primary_4', 'p5' => 'primary_5'] as $alias => $gradeKey) {
                if ($grade = $grades->firstWhere('key', $gradeKey)) {
                    $gradeMap[$alias] = $grade;
                }
            }

            $rows = [];
            $seenIds = [];
            $line = 1;
            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null] || (count($values) === 1 && trim((string) $values[0]) === '')) {
                    continue;
                }
                if (count($rows) >= 2000) {
                    return back()->withErrors(['student_file' => 'The importer accepts at most 2,000 student rows at a time. Split this file into smaller class-level files.']);
                }
                if (count($values) !== count($headers)) {
                    $rows[] = ['row_number' => $line, 'errors' => ['This row has a different number of columns from the template.']];
                    continue;
                }
                $row = array_combine($headers, $values);
                $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
                $validator = Validator::make($row, [
                    'school_student_id' => ['required', 'string', 'max:80'],
                    'first_name' => ['required', 'string', 'max:100'],
                    'middle_name' => ['nullable', 'string', 'max:100'],
                    'last_name' => ['required', 'string', 'max:100'],
                    'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                    'admission_date' => ['nullable', 'date_format:Y-m-d'],
                    'current_class_code' => ['required', 'string', 'max:80'],
                    'student_status' => ['required', 'in:active,left,completed'],
                ]);
                $errors = array_merge(...array_values($validator->errors()->toArray() ?: [[]]));
                $id = (string) ($row['school_student_id'] ?? '');
                if ($id !== '' && isset($seenIds[strtolower($id)])) {
                    $errors[] = 'Student ID is duplicated in this file.';
                }
                if ($id !== '') {
                    $seenIds[strtolower($id)] = true;
                }
                if ($id !== '' && (StudentProfile::whereRaw('LOWER(student_code) = ?', [strtolower($id)])->exists()
                    || Person::whereRaw('LOWER(external_id) = ?', [strtolower($id)])->exists())) {
                    $errors[] = 'Student ID already exists in the portal.';
                }
                $grade = $gradeMap[strtolower((string) ($row['current_class_code'] ?? ''))] ?? null;
                if (! $grade) {
                    $errors[] = 'Class code does not match a configured class.';
                }

                $rows[] = [
                    'row_number' => $line,
                    'student_code' => $id,
                    'first_name' => $row['first_name'] ?? '',
                    'middle_name' => $row['middle_name'] ?? '',
                    'last_name' => $row['last_name'] ?? '',
                    'date_of_birth' => $row['date_of_birth'] ?: null,
                    'admitted_on' => $row['admission_date'] ?: null,
                    'grade_level_id' => $grade?->id,
                    'grade_name' => $grade?->name,
                    'status' => strtolower((string) ($row['student_status'] ?? 'active')),
                    'errors' => array_values($errors),
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($rows === []) {
            return back()->withErrors(['student_file' => 'The CSV contains no student rows.']);
        }

        $validCount = count(array_filter($rows, fn ($row) => $row['errors'] === []));
        $batchId = DB::table('student_import_batches')->insertGetId([
            'uploaded_by' => $request->user()->id,
            'original_filename' => substr($file->getClientOriginalName(), 0, 255),
            'file_sha256' => $checksum,
            'status' => 'previewed',
            'row_count' => count($rows),
            'valid_count' => $validCount,
            'rejected_count' => count($rows) - $validCount,
            'preview_rows' => json_encode($rows),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('students.index')->with('import_preview', [
            'id' => $batchId,
            'filename' => $file->getClientOriginalName(),
            'rows' => $rows,
            'valid_count' => $validCount,
            'rejected_count' => count($rows) - $validCount,
        ]);
    }

    public function commitImport(Request $request, int $batch): RedirectResponse
    {
        $importBatch = DB::table('student_import_batches')->where('id', $batch)->first();
        abort_unless($importBatch && (int) $importBatch->uploaded_by === (int) $request->user()->id, 404);
        abort_unless($importBatch->status === 'previewed', 409, 'This import batch has already been committed or is no longer available.');
        abort_if((int) $importBatch->rejected_count > 0, 422, 'Correct all rejected rows and upload a corrected CSV before importing.');

        $year = AcademicYear::where('is_current', true)->first();
        abort_unless($year, 409, 'Set a current academic year before importing student enrollments.');
        $rows = json_decode($importBatch->preview_rows, true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($request, $importBatch, $rows, $year): void {
            foreach ($rows as $row) {
                $person = Person::create([
                    'external_id' => $row['student_code'],
                    'first_name' => $row['first_name'],
                    'middle_name' => $row['middle_name'] ?: null,
                    'last_name' => $row['last_name'],
                    'date_of_birth' => $row['date_of_birth'],
                ]);
                $status = $row['status'];
                $student = StudentProfile::create([
                    'person_id' => $person->id,
                    'student_code' => $row['student_code'],
                    'admitted_on' => $row['admitted_on'],
                    'status' => $status,
                    'left_on' => null,
                    'completed_on' => null,
                ]);
                StudentEnrollment::create([
                    'student_profile_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'grade_level_id' => $row['grade_level_id'],
                    'status' => $status,
                    'starts_on' => $year->starts_on,
                    'ends_on' => null,
                ]);
            }

            DB::table('student_import_batches')->where('id', $importBatch->id)->update([
                'status' => 'committed',
                'imported_count' => count($rows),
                'preview_rows' => json_encode([]),
                'committed_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'students.import.committed',
                'subject_type' => 'student_import_batch',
                'subject_id' => $importBatch->id,
                'after_values' => json_encode(['rows' => count($rows), 'academic_year' => $year->name, 'sha256' => $importBatch->file_sha256]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('students.index')->with('status', count($rows).' student records were imported into '.$year->name.'.');
    }

    public function discardImport(Request $request, int $batch): RedirectResponse
    {
        DB::table('student_import_batches')
            ->where('id', $batch)
            ->where('uploaded_by', $request->user()->id)
            ->where('status', 'previewed')
            ->delete();

        return redirect()->route('students.index')->with('status', 'Uncommitted preview removed. No student records were created.');
    }

    public function downloadImportErrors(Request $request, int $batch): StreamedResponse
    {
        $importBatch = DB::table('student_import_batches')
            ->where('id', $batch)
            ->where('uploaded_by', $request->user()->id)
            ->where('status', 'previewed')
            ->first();
        abort_unless($importBatch, 404);

        $rows = json_decode($importBatch->preview_rows, true, flags: JSON_THROW_ON_ERROR);
        $filename = 'student-import-errors-'.$batch.'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['source_csv_row', 'school_student_id', 'student_name', 'errors']);
            foreach ($rows as $row) {
                if ($row['errors'] === []) {
                    continue;
                }
                $values = [
                    $row['row_number'] ?? '',
                    $row['student_code'] ?? '',
                    trim(($row['first_name'] ?? '').' '.($row['middle_name'] ?? '').' '.($row['last_name'] ?? '')),
                    implode('; ', $row['errors']),
                ];
                $values = array_map(fn ($value) => preg_match('/^[=+\\-@\\t\\r]/', (string) $value) ? "'".$value : $value, $values);
                fputcsv($output, $values);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
