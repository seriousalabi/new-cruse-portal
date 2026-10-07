@extends('layouts.portal')
@section('title', 'My marks · New Cruse Academy')
@section('content')
<div class="portal-shell"><header class="portal-header"><a class="portal-brand" href="{{ route('dashboard') }}"><img src="{{ asset('images/school-logo.png') }}" alt="" class="header-logo"><span>New Cruse Academy</span></a><div class="account-actions"><span>{{ auth()->user()->name }} <small>Teacher</small></span><form method="post" action="{{ route('logout') }}">@csrf<button class="button button-light">Sign out</button></form></div></header>
<main class="dashboard-main"><a class="action-link" href="{{ route('dashboard') }}">← Dashboard</a><p class="eyebrow">TEACHER PORTAL</p><h1>My marks</h1><p class="muted">Enter four assignment marks (10 each) and an exam mark (60). Submitting locks the marks until Admin review.</p>
@if(session('status'))<div class="status-note">{{ session('status') }}</div>@endif
@if(!$currentYear)<div class="setup-note"><strong>No active academic year</strong><span>Ask an administrator to set up the current school year.</span></div>@elseif(count($items) === 0)<div class="setup-note"><strong>No active teaching assignments</strong><span>Your current year class and subject assignments will appear here.</span></div>@else
<div class="table-wrap"><table class="data-table"><thead><tr><th>Class</th><th>Subject</th><th>Term</th><th>Status</th><th>Action</th></tr></thead><tbody>
@foreach($items as $item)<tr><td>{{ $item['assignment']->gradeLevel->name }}</td><td>{{ $item['subject']->name }}</td><td>{{ $item['term']->name }}</td><td>{{ ucfirst($item['submission']?->status ?? 'Not started') }}</td><td><a class="button button-primary" href="{{ route('teacher.results.edit', [$item['assignment']->id, $item['term']->id, $item['subject']->id]) }}">{{ $item['submission']?->status === 'approved' ? 'View' : 'Open marks' }}</a></td></tr>@endforeach
</tbody></table></div>@endif</main></div>
@endsection
