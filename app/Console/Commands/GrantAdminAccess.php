<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class GrantAdminAccess extends Command
{
    protected $signature = 'admin:grant-access {email : E-Mail-Adresse eines bestehenden Benutzers}';

    protected $description = 'Erteilt einem bestehenden Benutzer Zugriff auf das Filament-Admin-Panel';

    public function handle(): int
    {
        $user = User::where('email', trim((string) $this->argument('email')))->first();

        if (! $user) {
            $this->error('Benutzer nicht gefunden. Es wurde kein Konto angelegt.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user): void {
            $user->givePermissionTo(Permission::findOrCreate('access-admin-panel', 'web'));
        });

        $this->info('Admin-Zugriff erteilt. Anmeldung unter /filament/login.');

        return self::SUCCESS;
    }
}
