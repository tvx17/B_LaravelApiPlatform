@extends('auth.layout')

@section('title', 'Anmeldung')

@section('content')
    <h1>Anmeldung</h1>
    <p>Melde dich für die Artisan Runner UI an.</p>
    @if ($errors->any())
        <p class="error" role="alert">{{ $errors->first() }}</p>
    @endif
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">E-Mail-Adresse</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required autofocus>
        <label for="password">Passwort</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Anmelden</button>
    </form>
@endsection
