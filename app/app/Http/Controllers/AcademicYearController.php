<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function index(): View
    {
        return view('school.academic-years.index', [
            'academicYears' => AcademicYear::with('terms')->orderByDesc('starts_on')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', 'unique:academic_years,name'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'terms' => ['required', 'array', 'size:3'],
            'terms.first.name' => ['required', 'string', 'max:40'],
            'terms.first.starts_on' => ['required', 'date'],
            'terms.first.ends_on' => ['required', 'date', 'after_or_equal:terms.first.starts_on'],
            'terms.second.name' => ['required', 'string', 'max:40'],
            'terms.second.starts_on' => ['required', 'date'],
            'terms.second.ends_on' => ['required', 'date', 'after_or_equal:terms.second.starts_on'],
            'terms.third.name' => ['required', 'string', 'max:40'],
            'terms.third.starts_on' => ['required', 'date'],
            'terms.third.ends_on' => ['required', 'date', 'after_or_equal:terms.third.starts_on'],
        ]);

        $yearStart = Carbon::parse($data['starts_on'])->startOfDay();
        $yearEnd = Carbon::parse($data['ends_on'])->startOfDay();
        $termDates = [];
        foreach (['first', 'second', 'third'] as $key) {
            $termStart = Carbon::parse($data['terms'][$key]['starts_on'])->startOfDay();
            $termEnd = Carbon::parse($data['terms'][$key]['ends_on'])->startOfDay();
            if ($termStart->lt($yearStart) || $termEnd->gt($yearEnd)) {
                throw ValidationException::withMessages([
                    "terms.$key.starts_on" => 'Each term must fall inside the academic-year dates.',
                ]);
            }
            $termDates[] = [$termStart, $termEnd];
        }

        for ($index = 1; $index < count($termDates); $index++) {
            if ($termDates[$index][0]->lte($termDates[$index - 1][1])) {
                $termName = $index === 1 ? 'second' : 'third';
                throw ValidationException::withMessages([
                    "terms.$termName.starts_on" => 'Terms must be in order and must not overlap.',
                ]);
            }
        }

        DB::transaction(function () use ($data, $request): void {
            $year = AcademicYear::create([
                'name' => $data['name'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'status' => 'draft',
                'is_current' => false,
            ]);

            foreach (['first', 'second', 'third'] as $index => $key) {
                $year->terms()->create([
                    'key' => $key,
                    'name' => $data['terms'][$key]['name'],
                    'sort_order' => $index + 1,
                    'starts_on' => $data['terms'][$key]['starts_on'],
                    'ends_on' => $data['terms'][$key]['ends_on'],
                    'status' => 'draft',
                ]);
            }

            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'academic_year.created',
                'subject_type' => AcademicYear::class,
                'subject_id' => $year->id,
                'after_values' => json_encode(['name' => $year->name, 'starts_on' => $year->starts_on, 'ends_on' => $year->ends_on]),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('school.academic-years.index')->with('status', 'Academic year and its three terms were saved as a draft.');
    }

    public function activate(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        DB::transaction(function () use ($request, $academicYear): void {
            AcademicYear::query()->update(['is_current' => false]);
            AcademicYear::whereKey($academicYear->id)->update(['is_current' => true, 'status' => 'active']);
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->id,
                'action' => 'academic_year.activated',
                'subject_type' => AcademicYear::class,
                'subject_id' => $academicYear->id,
                'after_values' => json_encode(['name' => $academicYear->name, 'is_current' => true, 'status' => 'active']),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'created_at' => now(),
            ]);
        });

        return redirect()->route('school.academic-years.index')->with('status', $academicYear->name.' is now the current academic year.');
    }
}
