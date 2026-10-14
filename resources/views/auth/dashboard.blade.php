@extends('auth.layout')

@section('title', 'Artisan Runner')

@section('content')
    <h1>Artisan Runner</h1>
    <p>Angemeldet als {{ auth()->user()->name }}.</p>
    @can('run-artisan-runner-ui')
        @if (config('artisan-runner-ui.enabled') && app()->environment(config('artisan-runner-ui.environments')))
            <p><a href="{{ route('artisan-runner-ui.index') }}">Artisan Runner UI öffnen</a></p>
        @else
            <p>Die Artisan Runner UI ist in dieser Umgebung deaktiviert.</p>
        @endif
    @else
        <p>Für diesen Bereich fehlt dir die Zugriffsberechtigung.</p>
    @endcan
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Abmelden</button>
    </form>
@endsection
