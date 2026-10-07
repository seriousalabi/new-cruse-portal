@extends('layouts.portal')

@section('title', 'Families · New Cruse Academy')

@section('content')
<div class="portal-shell">
    <header class="portal-header">
        <a class="portal-brand" href="{{ route('dashboard') }}"><img src="{{ asset('images/school-logo.png') }}" alt="" class="header-logo"><span>New Cruse Academy</span></a>
        <div class="account-actions"><span>{{ auth()->user()->name }} <small>Parent/guardian records</small></span><a class="button button-light" href="{{ route('dashboard') }}">Dashboard</a></div>
    </header>
    <main class="dashboard-main setup-page">
        <a class="back-link" href="{{ route('students.index') }}">← Student roster</a>
        <p class="eyebrow">FAMILY RECORDS</p>
        <h1>Parents and guardians</h1>
        <p class="muted">Create parent/guardian contact records and connect them to enrolled students. The portal does not create parent login accounts here.</p>
        @if($currentYear)<p class="year-context">Current year: <strong>{{ $currentYear->name }}</strong></p>@else<p class="empty-state">A current academic year is required before guardians can be linked to students.</p>@endif
        @if(session('status'))<div class="flash-success" role="status">{{ session('status') }}</div>@endif

        <section class="setup-panel">
            <div class="section-heading"><div><h2>Family directory</h2><p class="muted">{{ $parents->total() }} parent/guardian profiles</p></div><a class="button button-light" href="{{ route('students.index') }}">Student roster</a></div>
            @forelse($parents as $parent)
                <article class="parent-card">
                    <div class="parent-heading">
                        <div>
                            <h3>{{ trim($parent->person->first_name.' '.$parent->person->middle_name.' '.$parent->person->last_name) }}</h3>
                            <p>{{ $parent->email ?: 'No email recorded' }} · {{ $parent->person->phone ?: 'No phone recorded' }}</p>
                            @if($parent->person->external_id)<span class="parent-code">School ID: {{ $parent->person->external_id }}</span>@endif
                        </div>
                        <span class="year-status current">{{ ucfirst($parent->status) }}</span>
                    </div>
                    @if($parent->activeStudents->isEmpty())
                        <p class="empty-state compact-empty">No active student links.</p>
                    @else
                        <div class="linked-students">
                            @foreach($parent->activeStudents as $student)
                                @php($enrollment = $student->enrollments->first())
                                <div class="linked-student">
                                    <div><strong>{{ $student->student_code }} · {{ trim($student->person->first_name.' '.$student->person->last_name) }}</strong><span>{{ $enrollment?->gradeLevel?->name ?? 'No current-year class' }} · {{ $student->pivot->relationship }}@if($student->pivot->is_primary_contact) · Primary contact @endif</span></div>
                                    <form method="post" action="{{ route('families.links.close', [$parent, $student]) }}" onsubmit="return confirm('End this guardian link? The parent and student records will remain in the system.')">
                                        @csrf @method('DELETE')<button class="text-button" type="submit">End link</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <details class="edit-contact">
                        <summary>Edit contact details</summary>
                        <form method="post" action="{{ route('families.update', $parent) }}" class="student-form">
                            @csrf @method('PATCH')
                            <div class="form-grid">
                                <label>Parent external ID<input name="parent_external_id" value="{{ $parent->person->external_id }}">@error('parent_external_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>Email<input type="email" name="email" value="{{ $parent->email }}">@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>First name<input name="first_name" value="{{ $parent->person->first_name }}" required>@error('first_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>Middle name<input name="middle_name" value="{{ $parent->person->middle_name }}">@error('middle_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>Last name<input name="last_name" value="{{ $parent->person->last_name }}" required>@error('last_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>Phone<input type="tel" name="phone" value="{{ $parent->person->phone }}">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</label>
                            </div>
                            <button class="button button-light" type="submit">Save contact details</button>
                        </form>
                    </details>
                </article>
            @empty
                <p class="empty-state">No parent or guardian profiles have been added.</p>
            @endforelse
            <div class="pagination-wrap">{{ $parents->links() }}</div>
        </section>

        <div class="family-forms">
            <section class="setup-panel">
                <h2>Add a parent/guardian and link a student</h2>
                <p class="muted">Use this for a new parent contact. To connect a sibling, use the existing-guardian form beside it.</p>
                <form method="post" action="{{ route('families.store') }}" class="student-form">
                    @csrf
                    <div class="form-grid">
                        <label>Parent external ID<input name="parent_external_id" value="{{ old('parent_external_id') }}">@error('parent_external_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Student<select name="student_profile_id" required><option value="">Choose enrolled student</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_profile_id') == $student->id)>{{ $student->student_code }} · {{ $student->person->first_name }} {{ $student->person->last_name }} · {{ $student->enrollments->first()?->gradeLevel?->name }}</option>@endforeach</select>@error('student_profile_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>First name<input name="first_name" value="{{ old('first_name') }}" required>@error('first_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Middle name<input name="middle_name" value="{{ old('middle_name') }}">@error('middle_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Last name<input name="last_name" value="{{ old('last_name') }}" required>@error('last_name')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Relationship to student<input name="relationship" value="{{ old('relationship') }}" placeholder="Mother, father, guardian…" required>@error('relationship')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Email<input type="email" name="email" value="{{ old('email') }}">@error('email')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Phone<input type="tel" name="phone" value="{{ old('phone') }}">@error('phone')<small class="field-error">{{ $message }}</small>@enderror</label>
                        <label>Link started on<input type="date" name="valid_from" value="{{ old('valid_from') }}">@error('valid_from')<small class="field-error">{{ $message }}</small>@enderror</label>
                    </div>
                    <label class="checkbox-field"><input type="checkbox" name="is_primary_contact" value="1" @checked(old('is_primary_contact'))> Set as the student's primary contact</label>
                    <button class="button button-primary" type="submit" @disabled(! $currentYear || $students->isEmpty())>Create profile and link</button>
                </form>
            </section>
            <section class="setup-panel">
                <h2>Link an existing guardian</h2>
                <p class="muted">Connect a sibling to an existing family contact without duplicating the parent profile.</p>
                <form method="post" action="{{ route('families.link-existing') }}" class="student-form">
                    @csrf
                    <label>Parent/guardian<select name="parent_profile_id" required><option value="">Choose existing guardian</option>@foreach($parentOptions as $parent)<option value="{{ $parent->id }}" @selected(old('parent_profile_id') == $parent->id)>{{ $parent->person->first_name }} {{ $parent->person->last_name }}@if($parent->email) · {{ $parent->email }}@endif</option>@endforeach</select>@error('parent_profile_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Student<select name="student_profile_id" required><option value="">Choose enrolled student</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected(old('student_profile_id') == $student->id)>{{ $student->student_code }} · {{ $student->person->first_name }} {{ $student->person->last_name }} · {{ $student->enrollments->first()?->gradeLevel?->name }}</option>@endforeach</select>@error('student_profile_id')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Relationship to student<input name="relationship" value="{{ old('relationship') }}" placeholder="Mother, father, guardian…" required>@error('relationship')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Link started on<input type="date" name="valid_from" value="{{ old('valid_from') }}">@error('valid_from')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label class="checkbox-field"><input type="checkbox" name="is_primary_contact" value="1" @checked(old('is_primary_contact'))> Set as the student's primary contact</label>
                    <button class="button button-primary" type="submit" @disabled(! $currentYear || $students->isEmpty() || $parentOptions->isEmpty())>Link guardian</button>
                </form>
            </section>
        </div>
    </main>
</div>
@endsection
