<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

class SetupAdmin extends Command
{
    protected $signature = 'setup:admin';

    protected $description = 'Legt nach Bestätigung ein Admin-Konto an oder ergänzt dessen Zugangsrechte';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Interaktive Einrichtung erforderlich.');

            return self::FAILURE;
        }
        $email = trim((string) $this->ask('E-Mail des Administrators'));
        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('Ungültige E-Mail.');

            return self::FAILURE;
        }
        $user = User::where('email', $email)->first();
        $identity = null;
        if (! $user) {
            if (! $this->confirm('Neues Konto anlegen?', false)) {
                return self::SUCCESS;
            }
            $name = $this->ask('Anzeigename');
            $password = $this->secret('Passwort (mindestens 12 Zeichen)', false);
            $confirmation = $this->secret('Passwort bestätigen', false);
            $identity = ['name' => $name, 'email' => $email, 'password' => $password];
            if (Validator::make($identity + ['password_confirmation' => $confirmation], ['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:12', 'confirmed']])->fails()) {
                $this->error('Name oder Passwort ungültig; kein Konto angelegt.');

                return self::FAILURE;
            }
        } else {
            $this->info('Konto vorhanden; Name und Passwort bleiben unverändert.');
        }
        if (! $this->confirm('Filament- und Runner-Rechte für dieses Konto vergeben?', false)) {
            return self::SUCCESS;
        }
        DB::transaction(function () use ($user, $identity): void {
            $user ??= User::create($identity);
            foreach (['access-admin-panel', 'run-artisan-runner-ui'] as $name) {
                $user->givePermissionTo(Permission::findOrCreate($name, 'web'));
            }
        });
        $this->info('Zugang eingerichtet: /filament/login und /artisan/login.');

        return self::SUCCESS;
    }
}
