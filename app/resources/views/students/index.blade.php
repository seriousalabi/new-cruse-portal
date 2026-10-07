@extends('layouts.portal')

@section('title', 'Students · New Cruse Academy')

@section('content')
<div class="portal-shell">
    <header class="portal-header">
        <a class="portal-brand" href="{{ route('dashboard') }}"><img src="{{ asset('images/school-logo.png') }}" alt="" class="header-logo"><span>New Cruse Academy</span></a>
        <div class="account-actions"><span>{{ auth()->user()->name }} <small>Student records</small></span><a class="button button-light" href="{{ route('dashboard') }}">Dashboard</a></div>
    </header>
    <main class="dashboard-main setup-page">
        <a class="back-link" href="{{ route('dashboard') }}">← Dashboard</a>
        <p class="eyebrow">STUDENT RECORDS</p>
        <h1>Students and class enrollment</h1>
        <p class="muted">
            @if($currentYear)
                Current academic year: <strong>{{ $currentYear->name }}</strong>. Students added or imported here are assigned to one class for this year.
            @else
                Create and activate an academic year in School setup before adding or importing students.
            @endif
        </p>
        @if(session('status'))<div class="flash-success" role="status">{{ session('status') }}</div>@endif

        @if($preview)
            <section class="setup-panel import-preview">
                <div class="section-heading"><div><p class="eyebrow">IMPORT PREVIEW</p><h2>{{ $preview['filename'] }}</h2></div><span class="year-status {{ $preview['rejected_count'] === 0 ? 'current' : '' }}">{{ $preview['valid_count'] }} ready · {{ $preview['rejected_count'] }} rejected</span></div>
                <p class="muted">Review all rows before committing. A rejected row blocks the whole batch; correct the file and upload it again.</p>
                <div class="table-scroll">
                    <table class="data-table">
                        <thead><tr><th>CSV row</th><th>Student ID</th><th>Student name</th><th>Class</th><th>Status</th><th>Review</th></tr></thead>
                        <tbody>
                        @foreach($preview['rows'] as $row)
                            <tr>
                                <td>{{ $row['row_number'] }}</td>
                                <td>{{ $row['student_code'] ?? '—' }}</td>
                                <td>{{ trim(($row['first_name'] ?? '').' '.($row['middle_name'] ?? '').' '.($row['last_name'] ?? '')) ?: '—' }}</td>
                                <td>{{ $row['grade_name'] ?? '—' }}</td>
                                <td>{{ ucfirst($row['status'] ?? '—') }}</td>
                                <td>@if($row['errors'])<ul class="row-errors">@foreach($row['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>@else<span class="row-ready">Ready</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($preview['rejected_count'] === 0)
                    <form method="post" action="{{ route('students.import.commit', $preview['id']) }}" class="form-actions" onsubmit="return confirm('Import all {{ $preview['valid_count'] }} student rows into {{ $currentYear?->name }}? This creates student and enrollment records.')">
                        @csrf
                        <p>This will create {{ $preview['valid_count'] }} student records in {{ $currentYear?->name }}. Confirm the file and class assignments first.</p>
                        <button class="button button-primary" type="submit">Commit student import</button>
                    </form>
                @else
                    <p class="import-blocked">Import is blocked until all rejected rows are corrected.</p>
                    <a class="template-link" href="{{ route('students.import.errors', $preview['id']) }}">Download rejected-row report</a>
                @endif
                <form method="post" action="{{ route('students.import.discard', $preview['id']) }}" class="discard-form" onsubmit="return confirm('Discard this preview? No student records will be created.')">
                    @csrf @method('DELETE')
                    <button class="text-button" type="submit">Discard this preview</button>
                </form>
            </section>
        @endif

        <section class="setup-panel">
            <div class="section-heading"><div><h2>Current-year roster</h2><p class="muted">{{ $students->total() }} enrolled student{{ $students->total() === 1 ? '' : 's' }}</p></div></div>
            <form method="get" action="{{ route('students.index') }}" class="roster-filters">
                <label>Search<input type="search" name="q" value="{{ request('q') }}" placeholder="Name or student ID"></label>
                <label>Class<select name="class"><option value="">All classes</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected((string) request('class') === (string) $grade->id)>{{ $grade->name }}</option>@endforeach</select></label>
                <button class="button button-light" type="submit">Filter</button>
            </form>
            @if(! $currentYear)
                <p class="empty-state">No current academic year is active yet. Set one up before enrolling students.</p>
            @elseif($students->isEmpty())
                <p class="empty-state">No students are enrolled for {{ $currentYear->name }} yet. Add a student or upload a reviewed CSV.</p>
            @else
                <div class="table-scroll">
                    <table class="data-table">
                        <thead><tr><th>Student ID</th><th>Name</th><th>Class</th><th>Guardians</th><th>Record status</th><th>Admitted</th></tr></thead>
                        <tbody>
                        @foreach($students as $student)
                            @php($enrollment = $student->enrollments->first())
                            <tr>
                                <td>{{ $student->student_code }}</td>
                                <td>{{ trim($student->person->first_name.' '.$student->person->middle_name.' '.$student->person->last_name) }}</td>
                                <td>{{ $enrollment?->gradeLevel?->name ?? '—' }}</td>
                                <td>{{ $student->active_parent_profiles_count }}</td>
                                <td><span class="record-status {{ $student->status }}">{{ ucfirst($student->status) }}</span></td>
                                <td>{{ $student->admitted_on?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="pagination-wrap">{{ $students->links() }}</div>
            @endif
        </section>

        <div class="student-tools">
            <section class="setup-panel">
                <h2>Add one student</h2>
                <p class="muted">Creates a student profile and current-year enrollment. It does not create a student login.</p>
                <form method="post" action="{{ route('students.store') }}" class="student-form">
                    @csrf
                    <div class="form-grid">
                        <label>School student ID<input name="student_code" value="{{ old('student_code') }}" required>@error('student_code')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Class<select name="grade_level_id" required><option value="">Choose class</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected(old('grade_level_id') == $grade->id)>{{ $grade->name }}</option>@endforeach</select>@error('grade_level_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>First name<input name="first_name" value="{{ old('first_name') }}" required>@error('first_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Middle name<input name="middle_name" value="{{ old('middle_name') }}">@error('middle_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Last name<input name="last_name" value="{{ old('last_name') }}" required>@error('last_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}">@error('date_of_birth')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Admission date<input type="date" name="admitted_on" value="{{ old('admitted_on') }}">@error('admitted_on')<small class="field-error">{{ $message }}</small>@enderror</label>
                    </div>
                    <button class="button button-primary" type="submit" @disabled(! $currentYear)>Add student</button>
                </form>
            </section>
            <section class="setup-panel">
                <h2>Import a class roster</h2>
                <p class="muted">Upload UTF-8 CSV using the template. The portal previews names, class matches, duplicate IDs and date errors before allowing a batch import.</p>
                <a class="template-link" href="{{ asset('templates/students.csv') }}" download>Download student CSV template</a>
                <form method="post" action="{{ route('students.import.preview') }}" enctype="multipart/form-data" class="upload-form">
                    @csrf
                    <label>Student CSV<input type="file" name="student_file" accept=".csv,text/csv" required>@error('student_file')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <button class="button button-primary" type="submit" @disabled(! $currentYear)>Preview CSV</button>
                </form>
                <p class="privacy-note">Use synthetic data during development. Do not upload the school's real roster until the school approves the import mapping.</p>
            </section>
        </div>
        <p class="family-link-shortcut"><a class="template-link" href="{{ route('families.index') }}">Manage parent/guardian profiles and student links →</a></p>
    </main>
</div>
@endsection
