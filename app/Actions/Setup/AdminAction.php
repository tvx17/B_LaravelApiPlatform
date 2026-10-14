<?php

namespace App\Actions\Setup;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Spatie\Permission\Models\Permission;

final class AdminAction
{
    public function handle(array $data): void
    {
        if (($data['confirm'] ?? '') !== 'yes') {
            throw new RuntimeException('Rechtevergabe nicht bestätigt.');
        }
        $email = trim($data['email'] ?? '');
        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            throw new RuntimeException('Ungültige E-Mail.');
        }
        $user = User::where('email', $email)->first();
        $identity = null;
        if (! $user) {
            $identity = ['name' => $data['name'] ?? '', 'email' => $email, 'password' => $data['password'] ?? ''];
            if (($data['create_account'] ?? '') !== 'yes' || Validator::make($identity + ['password_confirmation' => $data['password_confirmation'] ?? ''], ['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:12', 'confirmed']])->fails()) {
                throw new RuntimeException('Neues Konto benötigt Anlagebestätigung, Namen und übereinstimmende Passwörter mit mindestens zwölf Zeichen.');
            }
        }
        DB::transaction(function () use ($user, $identity): void {
            $user ??= User::create($identity);
            foreach (['access-admin-panel', 'run-artisan-runner-ui'] as $name) {
                $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
            }
        });
    }
}
