<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

class CreateRunnerUser extends Command
{
    protected $signature = 'runner:create-user {email : E-Mail-Adresse des neuen Benutzers} {--name= : Anzeigename}';

    protected $description = 'Erstellt einen Benutzer mit Zugriff auf die Artisan Runner UI';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Dieser Befehl benötigt eine interaktive, verdeckte Passworteingabe.');

            return self::FAILURE;
        }

        $email = trim((string) $this->argument('email'));
        $name = trim((string) ($this->option('name') ?: $email));
        $identity = Validator::make(compact('email', 'name'), [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($identity->fails()) {
            $this->error($identity->errors()->first());

            return self::FAILURE;
        }

        $password = $this->secret('Passwort (mindestens 12 Zeichen)', false);
        $passwordConfirmation = $this->secret('Passwort bestätigen', false);
        $validation = Validator::make([
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], ['password' => ['required', 'string', 'min:12', 'confirmed']]);

        if ($validation->fails()) {
            $this->error($validation->errors()->first());

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $name, $password): void {
            $permission = Permission::findOrCreate('run-artisan-runner-ui', 'web');
            $user = User::create(compact('email', 'name', 'password'));
            $user->givePermissionTo($permission);
        });

        $this->info('Benutzer mit Runner-Zugriff wurde erstellt. Anmeldung unter /artisan/login.');

        return self::SUCCESS;
    }
}
