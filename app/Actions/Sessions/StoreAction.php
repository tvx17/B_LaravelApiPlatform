<?php

namespace App\Actions\Sessions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StoreAction
{
    public function handle(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'E-Mail-Adresse oder Passwort ist ungültig.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('runner.dashboard'));
    }
}
