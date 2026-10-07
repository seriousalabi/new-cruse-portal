@extends('layouts.portal')

@section('title', 'Sign in · New Cruse Academy')

@section('content')
<main class="signin-page">
    <section class="signin-card" aria-labelledby="signin-title">
        <div class="brand-block">
            <img src="{{ asset('images/school-logo.png') }}" alt="New Cruse Academy crest" class="school-logo">
            <p class="eyebrow">NEW CRUSE ACADEMY</p>
            <h1 id="signin-title">School Portal</h1>
            <p class="muted">Sign in to continue to your school account.</p>
        </div>

        <form method="post" action="{{ route('login.store') }}" class="signin-form">
            @csrf
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            @error('email')<p class="field-error">{{ $message }}</p>@enderror

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password')<p class="field-error">{{ $message }}</p>@enderror

            <label class="remember-row"><input type="checkbox" name="remember" value="1"> <span>Keep me signed in</span></label>
            <button class="button button-primary" type="submit">Sign in</button>
        </form>

        <p class="signin-footnote">Need help accessing your account? Contact the school administrator.</p>
    </section>
</main>
@endsection
