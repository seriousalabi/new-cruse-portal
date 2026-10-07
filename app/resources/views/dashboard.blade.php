@extends('layouts.portal')

@php
    $roleNames = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'teacher' => 'Teacher',
        'parent' => 'Parent',
    ];
    $cards = match ($role) {
        'super_admin' => ['System overview' => 'School setup, accounts, permissions and audit history.', 'Students and families' => 'Manage student profiles, guardians and class links.', 'Staff and teaching' => 'Maintain teacher profiles and current class/subject assignments.', 'Portal accounts' => 'Create teacher and parent sign-in accounts.', 'Results' => 'Review school result activity and correction history.', 'School setup' => 'Academic years, terms, classes and official subjects.'],
        'admin' => ['Students and families' => 'Manage student profiles, guardians and class links.', 'Staff and teaching' => 'Maintain teacher profiles and current class/subject assignments.', 'Portal accounts' => 'Create teacher and parent sign-in accounts.', 'Results review' => 'Review teacher submissions before parent publication.', 'School setup' => 'Manage class offerings, teachers and school communications.'],
        'teacher' => ['My class' => 'See your assigned class and individual student records.', 'Marks' => 'Enter and submit scores for your assigned subjects.', 'School updates' => 'Read school and class announcements.'],
        'parent' => ['My children' => 'View the school information for your linked children.', 'Results and reports' => 'Read approved results and report cards.', 'Contact the school' => 'Send a question about a missing or published result.'],
        default => [],
    };
@endphp

@section('title', 'Dashboard · New Cruse Academy')

@section('content')
<div class="portal-shell">
    <header class="portal-header">
        <a class="portal-brand" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/school-logo.png') }}" alt="" class="header-logo">
            <span>New Cruse Academy</span>
        </a>
        <div class="account-actions">
            <span>{{ auth()->user()->name }} <small>{{ $roleNames[$role] }}</small></span>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="button button-light" type="submit">Sign out</button></form>
        </div>
    </header>

    <main class="dashboard-main">
        <p class="eyebrow">{{ $roleNames[$role] }} PORTAL</p>
        <h1>Welcome, {{ auth()->user()->name }}</h1>
        <p class="muted">Your account is signed in. School records and workflows will appear here as they are enabled.</p>

        <section class="dashboard-grid" aria-label="Your portal areas">
            @foreach($cards as $title => $description)
                <article class="dashboard-card">
                    <span class="card-mark" aria-hidden="true"></span>
                    <h2>{{ $title }}</h2>
                    <p>{{ $description }}</p>
                    @if(in_array($role, ['super_admin', 'admin']) && $title === 'School setup')
                        <a class="status-note action-link" href="{{ route('school.academic-years.index') }}">Open school setup <span aria-hidden="true">→</span></a>
                    @elseif(in_array($role, ['super_admin', 'admin']) && $title === 'Students and families')
                        <a class="status-note action-link" href="{{ route('students.index') }}">Open student roster <span aria-hidden="true">→</span></a>
                    @elseif(in_array($role, ['super_admin', 'admin']) && $title === 'Staff and teaching')
                        <a class="status-note action-link" href="{{ route('staff.index') }}">Open staff setup <span aria-hidden="true">→</span></a>
                    @elseif(in_array($role, ['super_admin', 'admin']) && $title === 'Results review')
                        <a class="status-note action-link" href="{{ route('admin.results.index') }}">Review submissions <span aria-hidden="true">→</span></a>
                    @elseif($role === 'super_admin' && $title === 'Results')
                        <a class="status-note action-link" href="{{ route('admin.results.index') }}">Open results workflow <span aria-hidden="true">→</span></a>
                    @elseif(in_array($role, ['super_admin', 'admin']) && $title === 'Portal accounts')
                        <a class="status-note action-link" href="{{ route('accounts.index') }}">Manage teacher and parent logins <span aria-hidden="true">→</span></a>
                    @elseif($role === 'teacher' && $title === 'Marks')
                        <a class="status-note action-link" href="{{ route('teacher.results.index') }}">Enter and submit marks <span aria-hidden="true">→</span></a>
                    @elseif($role === 'parent' && $title === 'Results and reports')
                        <a class="status-note action-link" href="{{ route('parent.results.index') }}">View children’s results <span aria-hidden="true">→</span></a>
                    @else
                        <span class="status-note">Next portal section</span>
                    @endif
                </article>
            @endforeach
        </section>

        <aside class="setup-note"><strong>Local development status</strong><span>This portal is under construction and uses no live student records.</span></aside>
    </main>
</div>
@endsection
