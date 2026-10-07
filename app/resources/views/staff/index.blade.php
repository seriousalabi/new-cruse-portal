@extends('layouts.portal')

@section('title', 'Staff and teaching assignments · New Cruse Academy')

@section('content')
<div class="portal-shell">
    <header class="portal-header">
        <a class="portal-brand" href="{{ route('dashboard') }}"><img src="{{ asset('images/school-logo.png') }}" alt="" class="header-logo"><span>New Cruse Academy</span></a>
        <div class="account-actions"><span>{{ auth()->user()->name }} <small>Staff and classes</small></span><a class="button button-light" href="{{ route('dashboard') }}">Dashboard</a></div>
    </header>
    <main class="dashboard-main setup-page">
        <a class="back-link" href="{{ route('students.index') }}">← Students and families</a>
        <p class="eyebrow">STAFF AND TEACHING SETUP</p>
        <h1>Teachers and assignments</h1>
        <p class="muted">
            @if($currentYear)
                Assign teachers to one class for <strong>{{ $currentYear->name }}</strong>, then choose the subjects they teach. A teacher may have several subjects in the same class.
            @else
                Activate an academic year before creating teaching assignments.
            @endif
        </p>
        @if(session('status'))<div class="flash-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->has('staff_profile_id') || $errors->has('grade_level_id') || $errors->has('subject_ids'))
            <div class="flash-error" role="alert">@foreach(['staff_profile_id','grade_level_id','subject_ids'] as $field)@if($errors->has($field))<p>{{ $errors->first($field) }}</p>@endif @endforeach</div>
        @endif

        <section class="setup-panel">
            <div class="section-heading"><div><h2>Teacher assignments</h2><p class="muted">{{ $currentYear?->name ?? 'No current year' }}</p></div><a class="button button-light" href="{{ route('students.index') }}">Student roster</a></div>
            @php($shownAssignment = false)
            @foreach($staff as $teacher)
                @foreach($teacher->assignments as $assignment)
                    @php($shownAssignment = true)
                    <article class="assignment-card">
                        <div class="assignment-heading">
                            <div><h3>{{ $teacher->person->first_name }} {{ $teacher->person->last_name }}</h3><p>{{ $teacher->staff_code ?: 'No staff ID' }} · {{ $assignment->gradeLevel->name }}</p></div>
                            <span class="year-status {{ $assignment->status === 'active' ? 'current' : '' }}">{{ ucfirst($assignment->status) }}</span>
                        </div>
                        <div class="subject-chips">@forelse($assignment->subjects as $subject)<span>{{ $subject->name }}</span>@empty<span class="muted">No subjects assigned yet</span>@endforelse</div>
                        @if($assignment->starts_on)<p class="assignment-date">Assigned from {{ $assignment->starts_on->format('M j, Y') }}</p>@endif
                        <div class="assignment-actions">
                            @if($assignment->status === 'active' && $assignment->submissions_count === 0)
                                <a class="template-link" href="{{ route('staff.assignments.edit', $assignment) }}">Edit assignment</a>
                            @elseif($assignment->submissions_count > 0)
                                <span class="muted">{{ $assignment->submissions_count }} result submission{{ $assignment->submissions_count === 1 ? '' : 's' }} · assignment edits locked</span>
                            @endif
                            @if($assignment->status === 'active')
                                <form method="post" action="{{ route('staff.assignments.end', $assignment) }}" class="end-assignment-form" onsubmit="return confirm('End this teacher assignment? Result history and the staff profile will remain.')">
                                    @csrf @method('PATCH')
                                    <label>Last day<input type="date" name="ends_on" value="{{ now()->toDateString() }}" required></label>
                                    <button class="text-button" type="submit">End assignment</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            @endforeach
            @unless($shownAssignment)
                <p class="empty-state">No teacher assignments are configured for this academic year yet.</p>
            @endunless
            <div class="pagination-wrap">{{ $staff->links() }}</div>
        </section>

        <section class="setup-panel">
            <h2>{{ $editingAssignment ? 'Edit assignment' : 'Assign a teacher to a class' }}</h2>
            @if($editingAssignment)
                <p class="muted">Editing {{ $editingAssignment->staffProfile->person->first_name }} {{ $editingAssignment->staffProfile->person->last_name }}. Assignment changes are audited. Assignments with result submissions are locked.</p>
            @else
                <p class="muted">Choose a class first to load its configured subjects. Nursery and Reception can be assigned before their subject lists are finalized.</p>
            @endif
            @if(! $currentYear)
                <p class="empty-state">No active academic year. Configure one in <a href="{{ route('school.academic-years.index') }}">School setup</a>.</p>
            @else
                @if(! $editingAssignment)
                    <form method="get" action="{{ route('staff.index') }}" class="class-subject-select">
                        <label>Class<select name="grade_id" required><option value="">Choose class to load subjects</option>@foreach($grades as $grade)<option value="{{ $grade->id }}" @selected($selectedGrade?->id === $grade->id)>{{ $grade->name }}</option>@endforeach</select></label>
                        <button class="button button-light" type="submit">Load subjects</button>
                    </form>
                @endif
                @if($selectedGrade)
                    <form method="post" action="{{ $editingAssignment ? route('staff.assignments.update', $editingAssignment) : route('staff.assignments.store') }}" class="assignment-form">
                        @csrf
                        @if($editingAssignment) @method('PUT') @endif
                        <input type="hidden" name="grade_level_id" value="{{ $selectedGrade->id }}">
                        @if($editingAssignment)<input type="hidden" name="staff_profile_id" value="{{ $editingAssignment->staff_profile_id }}">@endif
                        <div class="form-grid">
                            @unless($editingAssignment)
                                <label>Teacher<select name="staff_profile_id" required><option value="">Choose active teacher</option>@foreach($activeStaff as $teacher)<option value="{{ $teacher->id }}" @selected(old('staff_profile_id') == $teacher->id)>{{ $teacher->person->first_name }} {{ $teacher->person->last_name }}@if($teacher->staff_code) · {{ $teacher->staff_code }}@endif</option>@endforeach</select>@error('staff_profile_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                            @endunless
                            <label>Assignment starts on (optional)<input type="date" name="starts_on" value="{{ old('starts_on', $editingAssignment?->starts_on?->toDateString()) }}">@error('starts_on')<small class="field-error">{{ $message }}</small>@enderror</label>
                        </div>
                        <div class="subject-selection">
                            <h3>{{ $selectedGrade->name }} subjects</h3>
                            @if($subjects->isEmpty())
                                <p class="empty-state">No subjects are configured for this class. You can still assign the teacher to the class and add subjects later.</p>
                            @else
                                @php($selectedSubjectIds = old('subject_ids', $editingAssignment?->subjects->pluck('id')->all() ?? []))
                                <div class="subject-checkboxes">
                                    @foreach($subjects as $subject)
                                        <label class="checkbox-field"><input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}" @checked(in_array($subject->id, $selectedSubjectIds))>{{ $subject->name }}</label>
                                    @endforeach
                                </div>
                            @endif
                            @error('subject_ids')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="form-actions">
                            <p>This teacher can have only one class assignment in {{ $currentYear->name }}. Ending an assignment preserves it for history.</p>
                            <div class="button-row"><button class="button button-primary" type="submit">{{ $editingAssignment ? 'Save assignment' : 'Assign teacher' }}</button>@if($editingAssignment)<a class="button button-light" href="{{ route('staff.index') }}">Cancel</a>@endif</div>
                        </div>
                    </form>
                @endif
            @endif
        </section>

        <section class="setup-panel">
            <h2>Staff directory</h2>
            <p class="muted">Contact and identity records are retained when a teacher changes. Creating a profile does not issue sign-in access.</p>
            @forelse($staff as $teacher)
                <article class="staff-card">
                    <div><strong>{{ $teacher->person->first_name }} {{ $teacher->person->middle_name }} {{ $teacher->person->last_name }}</strong><span>{{ $teacher->staff_code ?: 'No staff ID' }} · {{ $teacher->email ?: 'No email' }} · {{ $teacher->person->phone ?: 'No phone' }}</span></div>
                    <span class="year-status {{ $teacher->status === 'active' ? 'current' : '' }}">{{ ucfirst($teacher->status) }}</span>
                    <details class="edit-contact">
                        <summary>Edit contact details</summary>
                        <form method="post" action="{{ route('staff.update', $teacher) }}" class="student-form">
                            @csrf @method('PATCH')
                            <div class="form-grid">
                                <label>Staff ID<input name="staff_code" value="{{ $teacher->staff_code }}">@error('staff_code')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>Email<input type="email" name="email" value="{{ $teacher->email }}">@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>First name<input name="first_name" value="{{ $teacher->person->first_name }}" required>@error('first_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>Middle name<input name="middle_name" value="{{ $teacher->person->middle_name }}">@error('middle_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>Last name<input name="last_name" value="{{ $teacher->person->last_name }}" required>@error('last_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>Phone<input type="tel" name="phone" value="{{ $teacher->person->phone }}">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</label>
                            </div>
                            <button class="button button-light" type="submit">Save contact details</button>
                        </form>
                    </details>
                    @if($teacher->status === 'active')
                        <form method="post" action="{{ route('staff.end', $teacher) }}" class="end-staff-form" onsubmit="return confirm('End this teacher profile and active assignments? Records will be retained and any linked login will be disabled.')">
                            @csrf @method('PATCH')
                            <label>Last employment day<input type="date" name="ended_on" value="{{ now()->toDateString() }}" required></label>
                            <button class="text-button" type="submit">End staff profile</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="empty-state">No teacher profiles have been created yet.</p>
            @endforelse
        </section>

        <section class="setup-panel">
            <h2>Add a teacher profile</h2>
            <form method="post" action="{{ route('staff.store') }}" class="student-form">
                @csrf
                <div class="form-grid">
                    <label>Staff ID<input name="staff_code" value="{{ old('staff_code') }}">@error('staff_code')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>First name<input name="first_name" value="{{ old('first_name') }}" required>@error('first_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Middle name<input name="middle_name" value="{{ old('middle_name') }}">@error('middle_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Last name<input name="last_name" value="{{ old('last_name') }}" required>@error('last_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Email<input type="email" name="email" value="{{ old('email') }}">@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Phone<input type="tel" name="phone" value="{{ old('phone') }}">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Employment start (optional)<input type="date" name="started_on" value="{{ old('started_on') }}">@error('started_on')<small class="field-error">{{ $message }}</small>@enderror</label>
                </div>
                <button class="button button-primary" type="submit">Add teacher profile</button>
            </form>
        </section>
    </main>
</div>
@endsection
