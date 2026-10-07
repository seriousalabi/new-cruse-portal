@extends('layouts.portal')

@section('title', 'Academic years · New Cruse Academy')

@section('content')
<div class="portal-shell">
    <header class="portal-header">
        <a class="portal-brand" href="{{ route('dashboard') }}"><img src="{{ asset('images/school-logo.png') }}" alt="" class="header-logo"><span>New Cruse Academy</span></a>
        <div class="account-actions"><span>{{ auth()->user()->name }} <small>School setup</small></span><a class="button button-light" href="{{ route('dashboard') }}">Dashboard</a></div>
    </header>

    <main class="dashboard-main setup-page">
        <a class="back-link" href="{{ route('dashboard') }}">← Dashboard</a>
        <p class="eyebrow">SCHOOL SETUP</p>
        <h1>Academic years and terms</h1>
        <p class="muted">Set the school-year dates and the three term windows before adding this year's student enrollments. New years are saved as drafts until you activate one.</p>

        @if(session('status'))<div class="flash-success" role="status">{{ session('status') }}</div>@endif

        <section class="setup-panel">
            <h2>Configured academic years</h2>
            @forelse($academicYears as $year)
                <article class="year-card">
                    <div class="year-heading"><div><h3>{{ $year->name }}</h3><p>{{ $year->starts_on->format('M j, Y') }} – {{ $year->ends_on->format('M j, Y') }}</p></div><span class="year-status {{ $year->is_current ? 'current' : '' }}">{{ $year->is_current ? 'Current' : ucfirst($year->status) }}</span></div>
                    <ol class="term-list">
                        @foreach($year->terms as $term)
                            <li><strong>{{ $term->name }}</strong><span>{{ $term->starts_on?->format('M j') }} – {{ $term->ends_on?->format('M j, Y') }}</span></li>
                        @endforeach
                    </ol>
                    @unless($year->is_current)
                        <form method="post" action="{{ route('school.academic-years.activate', $year) }}" onsubmit="return confirm('Make {{ $year->name }} the current academic year?')">@csrf<button class="button button-light" type="submit">Set as current year</button></form>
                    @endunless
                </article>
            @empty
                <p class="empty-state">No academic year has been set up yet.</p>
            @endforelse
        </section>

        <section class="setup-panel">
            <h2>Add an academic year</h2>
            <p class="muted">Enter all three terms. You can adjust the assumed dates here before making the year current.</p>
            <form method="post" action="{{ route('school.academic-years.store') }}" class="year-form">
                @csrf
                <div class="form-grid year-fields">
                    <label>Academic-year label<input name="name" value="{{ old('name', '2026-2027') }}" placeholder="2026-2027" required>@error('name')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Year starts<input type="date" name="starts_on" value="{{ old('starts_on', '2026-09-01') }}" required>@error('starts_on')<small class="field-error">{{ $message }}</small>@enderror</label>
                    <label>Year ends<input type="date" name="ends_on" value="{{ old('ends_on', '2027-07-31') }}" required>@error('ends_on')<small class="field-error">{{ $message }}</small>@enderror</label>
                </div>
                <div class="term-form-grid">
                    @php($termDefaults = [
                        'first' => ['First Term', '2026-09-01', '2026-12-18'],
                        'second' => ['Second Term', '2027-01-11', '2027-03-26'],
                        'third' => ['Third Term', '2027-05-03', '2027-07-31'],
                    ])
                    @foreach($termDefaults as $key => [$defaultName, $defaultStart, $defaultEnd])
                        <fieldset class="term-fieldset">
                            <legend>{{ ucfirst($key) }} term</legend>
                            <label>Term name<input name="terms[{{ $key }}][name]" value="{{ old("terms.$key.name", $defaultName) }}" required>@error("terms.$key.name")<small class="field-error">{{ $message }}</small>@enderror</label>
                            <div class="form-grid">
                                <label>Begins<input type="date" name="terms[{{ $key }}][starts_on]" value="{{ old("terms.$key.starts_on", $defaultStart) }}" required>@error("terms.$key.starts_on")<small class="field-error">{{ $message }}</small>@enderror</label>
                                <label>Ends<input type="date" name="terms[{{ $key }}][ends_on]" value="{{ old("terms.$key.ends_on", $defaultEnd) }}" required>@error("terms.$key.ends_on")<small class="field-error">{{ $message }}</small>@enderror</label>
                            </div>
                        </fieldset>
                    @endforeach
                </div>
                @if($errors->has('terms'))<p class="field-error">{{ $errors->first('terms') }}</p>@endif
                <div class="form-actions"><p>Saving creates a draft only. The audit log records setup and activation.</p><button class="button button-primary" type="submit">Save academic year</button></div>
            </form>
        </section>
    </main>
</div>
@endsection
