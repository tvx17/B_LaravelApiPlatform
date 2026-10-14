<?php

use App\Support\SetupLocale;
use App\Support\SetupRuntime;

// Standalone template: no Laravel dependencies required.
$escape = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$h = fn (string $text) => $escape($t($text));
$csrf = $escape($_SESSION['setup_csrf'] ?? '');
$status = $_SESSION['setup_steps'] ?? [];
$defaults = [
    'path' => ($environment['DB_CONNECTION'] ?? 'sqlite') === 'sqlite' ? ($environment['DB_DATABASE'] ?? 'database/database.sqlite') : 'database/database.sqlite',
    'host' => $environment['DB_HOST'] ?? '127.0.0.1',
    'port' => $environment['DB_PORT'] ?? '3306',
    'database' => ($environment['DB_CONNECTION'] ?? 'sqlite') === 'sqlite' ? 'laravel_api_platform' : ($environment['DB_DATABASE'] ?? 'laravel_api_platform'),
    'username' => $environment['DB_USERNAME'] ?? 'root',
    'api' => $base,
    'frontend' => $environment['CORS_ALLOWED_ORIGINS'] ?? '',
];
$field = function (string $name, string $label, string $type = 'text') use ($escape, $h, $defaults): void {
    $value = $type === 'password' ? '' : ($defaults[$name] ?? '');
    echo '<label>'.$h($label).'<input type="'.$escape($type).'" name="'.$escape($name).'" value="'.$escape($value).'" autocomplete="'.($type === 'password' ? 'new-password' : 'off').'"></label>';
};
?>
<!doctype html>
<html lang="<?= $escape($locale->language) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="setup-progress.js?v=<?= filemtime($this->root.'/public/setup-progress.js') ?>" defer></script>
    <title>Laravel API Platform · <?= $h('Einrichtung') ?></title>
    <style>
        :root{color-scheme:light;font-family:system-ui,sans-serif;color:#17283b;background:#eef2f7}*{box-sizing:border-box}body{margin:0}main{max-width:1050px;margin:40px auto;padding:0 20px}header{margin-bottom:24px}h1{margin:8px 0;font-size:2rem}h2{margin-top:0}p{line-height:1.6}.eyebrow{color:#405975;font-weight:650;font-size:.85rem;letter-spacing:.06em}.layout{display:grid;grid-template-columns:260px 1fr;gap:24px}nav,.panel{background:white;border:1px solid #d8e1ec;border-radius:12px;padding:24px}nav{padding:12px}nav a{display:block;padding:10px;color:#304c6b;text-decoration:none;border-radius:6px}nav a[aria-current=step]{background:#e6efff;color:#123f83;font-weight:650}.status{display:block;color:#64758a;font-size:.75rem}.notice{padding:14px 18px;border-radius:8px;margin:16px 0;background:#e0f2e8}.error{background:#fce8e6;color:#802d26}label{display:block;margin:16px 0;font-weight:550}input,select{display:block;width:100%;padding:11px;border:1px solid #a6b6c9;border-radius:6px;margin-top:6px;font:inherit}input[type=checkbox]{display:inline;width:auto;margin-right:8px}.confirmation{background:#f2f5f9;padding:12px;border-radius:6px;font-weight:400}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:24px}button{border:0;border-radius:6px;padding:12px 18px;font:inherit;font-weight:600;cursor:pointer;background:#174d96;color:white}button.secondary{background:#e9eef4;color:#274360}button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid #74a8ee;outline-offset:3px}small{color:#52677f}code{overflow-wrap:anywhere}a{color:#174d96}ul{padding-left:20px;line-height:1.8}dl{display:grid;grid-template-columns:1fr 1fr;gap:8px}dt,dd{margin:0}footer{color:#52677f;font-size:.85rem;margin-top:20px}@media(max-width:760px){main{margin:24px auto}.layout{grid-template-columns:1fr}nav{display:flex;overflow:auto}nav a{flex:0 0 190px}.panel{padding:20px}}
     .language-picker{display:flex;align-items:end;gap:12px;flex-wrap:wrap}.language-picker label{margin:0;min-width:180px}</style>
</head>
<body><main>
    <header><span class="eyebrow">LARAVEL API PLATFORM</span><h1><?= $h('Einrichtung im Browser') ?></h1><p><?= $h('Ein Schritt nach dem anderen. Zum Ausführen genügt ein Klick.') ?></p>
        <form method="get" action="setup.php" class="language-picker">
            <input type="hidden" name="step" value="<?= $escape($step) ?>">
            <label><?= $h('Sprache') ?><select name="lang"><?php foreach (SetupLocale::LANGUAGES as $id => $name) { ?><option value="<?= $id ?>" <?= $locale->language === $id ? 'selected' : '' ?>><?= $escape($name) ?></option><?php } ?></select></label>
            <button type="submit" class="secondary"><?= $h('Übernehmen') ?></button>
        </form>
    </header>
    <section id="setup-progress" class="panel" hidden aria-live="polite" aria-busy="false" data-translations="<?= $escape(json_encode($locale->client(), JSON_THROW_ON_ERROR)) ?>">
        <h2><?= $h('Schritt wird ausgeführt') ?></h2><p data-message></p><p data-time><?= $h('Laufzeit: 0 s') ?></p><p data-detail></p>
        <progress aria-label="<?= $h('Schritt läuft') ?>"></progress>
    </section>
    <?php if ($message) { ?><div class="notice" role="status"><?= $h($message) ?></div><?php } ?>
    <?php if ($error) { ?><div class="notice error" role="alert"><?= $h($error) ?></div><?php } ?>
    <?php if ($locked) { ?>
        <section class="panel"><h2><?= $h('Einrichtung gesperrt') ?></h2><p><?= $h('Nur public/ öffentlich ausliefern. Die Freigabedatei gehört in das darüberliegende Projektverzeichnis.') ?></p><p><?= $h('Nach Abschluss bleibt der Assistent gesperrt; die Anwendung läuft weiter.') ?></p></section>
    <?php } elseif (! $authenticated) { ?>
        <section class="panel"><h2><?= $h('Anmelden') ?></h2><p><?= $h('Den Schlüssel aus .setup-access eingeben. Er ist unabhängig vom Administratorkonto.') ?></p>
            <form method="post" action="setup.php"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="operation" value="login">
                <?php $field('access_key', 'Zugangsschlüssel', 'password') ?>
                <button type="submit"><?= $h('Einrichtung öffnen') ?></button>
            </form>
        </section>
    <?php } else { ?>
        <div class="layout">
            <nav aria-label="<?= $h('Einrichtungsschritte') ?>">
                <?php foreach ($steps as $id => $title) { ?><a href="setup.php?step=<?= $escape($id) ?>&amp;lang=<?= $escape($locale->language) ?>" <?= $step === $id ? 'aria-current="step"' : '' ?>><?= $h($title) ?><span class="status"><?= $h($status[$id] ?? 'offen') ?></span></a><?php } ?>
            </nav>
            <section class="panel"><h2><?= $h($steps[$step]) ?></h2>
                <?php if (! $finished) { ?><form method="post" action="setup.php?step=<?= $escape($step) ?>">
                    <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="step" value="<?= $escape($step) ?>">
                    <?php if ($step === 'install') { ?>
                        <p><?= $h('Composer installiert Pakete aus dem Lockfile einschließlich Entwicklungspaketen. Laravel-Hooks folgen bei den Erweiterungen.') ?></p>
                        <dl><dt>PHP</dt><dd><?= $escape(PHP_VERSION) ?></dd><dt><?= $h('Prozessausführung') ?></dt><dd><?= $h(function_exists('proc_open') ? 'Verfügbar' : 'Gesperrt') ?></dd><dt><?= $h('SQLite-Treiber') ?></dt><dd><?= $h(extension_loaded('pdo_sqlite') ? 'Verfügbar' : 'Fehlt') ?></dd><dt><?= $h('MariaDB/MySQL-Treiber') ?></dt><dd><?= $h(extension_loaded('pdo_mysql') ? 'Verfügbar' : 'Fehlt') ?></dd><dt><?= $h('vendor/ vorhanden') ?></dt><dd><?= $h(is_file($this->root.'/vendor/autoload.php') ? 'Ja' : 'Nein') ?></dd><dt><?= $h('.env vorhanden') ?></dt><dd><?= $h(is_file($this->root.'/.env') ? 'Ja' : 'Nein') ?></dd></dl>
                        <p><?= $h('PHP-CLI, Composer und proc_open müssen verfügbar sein. Bei hochgeladenem vendor/ überspringen. Installation kann mehrere Minuten dauern.') ?></p>
                        <?php try {
                            $cliPath = SetupRuntime::php();
                            $composerPath = SetupRuntime::composer()[1];
                            echo '<p>'.$h('Ermittelte PHP-CLI').': <code>'.$escape($cliPath).'</code><br>Composer: <code>'.$escape($composerPath).'</code></p>';
                        } catch (RuntimeException $runtimeError) {
                            echo '<p class="notice error">'.$h($runtimeError->getMessage()).'</p>';
                        } ?>
                    <?php } elseif ($step === 'environment') { ?><p><?= $h('Eine fehlende .env wird aus der sicheren Vorlage erstellt. Bestehende Werte bleiben erhalten.') ?></p>
                    <?php } elseif ($step === 'cache') { ?><p><?= $h('Caches leeren, damit die aktuelle .env verwendet wird.') ?></p>
                    <?php } elseif ($step === 'key') { ?><p><?= $h('Fehlt der Anwendungsschlüssel, wird er erzeugt. Ein bestehender Schlüssel bleibt erhalten.') ?></p>
                    <?php } elseif ($step === 'database') { ?>
                        <p><?= $h('Verbindung prüfen und speichern. Neue Datenbanken anlegen; vorhandene Daten behalten. MariaDB/MySQL benötigt einen laufenden Server; Neuanlage braucht CREATE-Rechte.') ?></p>
                        <label><?= $h('Datenbanktyp') ?><select name="driver"><?php foreach (['sqlite' => 'SQLite', 'mariadb' => 'MariaDB', 'mysql' => 'MySQL'] as $id => $label) { ?><option value="<?= $id ?>" <?= ($environment['DB_CONNECTION'] ?? 'sqlite') === $id ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></label>
                        <label><?= $h('Vorhandene oder neue Datenbank?') ?><select name="mode"><option value="existing"><?= $h('Vorhandene Datenbank') ?></option><option value="new"><?= $h('Neue Datenbank anlegen') ?></option></select></label>
                        <?php $field('path', 'SQLite: Datei innerhalb von database/ (.sqlite)') ?>
                        <details><summary><?= $h('MariaDB / MySQL: Verbindungsdaten') ?></summary>
                            <?php $field('host', 'Host');
                        $field('port', 'Port', 'number');
                        $field('database', 'Datenbankname');
                        $field('username', 'Benutzer');
                        $field('password', 'Passwort (bei Änderung erneut eingeben)', 'password') ?>
                        </details><p><small><?= $h('Neue Datenbanken bleiben bei späteren Fehlern erhalten. Passwörter werden nicht ins Formular übernommen.') ?></small></p>
                    <?php } elseif ($step === 'cors') { ?>
                        <p><?= $h('API und Frontend benötigen denselben Host und dasselbe Schema. Verschiedene Ports sind möglich. Frontend muss Cookies, CSRF und Credentials mitsenden.') ?></p>
                        <?php $field('api', 'API-URL', 'url');
                        $field('frontend', 'Frontend-Origin', 'url') ?>
                    <?php } elseif ($step === 'verification') { ?>
                        <p><?= $h('Mailzustellung und öffentliche API-Adresse prüfen; keine Testmail versandt. Verifizierungsendpunkte sind noch nicht eingerichtet.') ?></p>
                        <p><?= $h('Für echte Bestätigungsmails SMTP oder einen anderen Maildienst in .env konfigurieren. APP_URL muss die öffentliche API-Adresse sein; AUTH_VERIFICATION_EXPIRE bestimmt die Linkdauer in Minuten.') ?></p>
                        <dl><dt>MAIL_MAILER</dt><dd><?= $escape($environment['MAIL_MAILER'] ?? 'log') ?></dd><dt>APP_URL</dt><dd><?= $escape($environment['APP_URL'] ?? '') ?></dd><dt>AUTH_VERIFICATION_EXPIRE</dt><dd><?= $escape((int) ($environment['AUTH_VERIFICATION_EXPIRE'] ?? 60)) ?></dd></dl>
                        <p><?= $h('MAIL_MAILER=log schreibt Bestätigungsmails nur in das Laravel-Log. Hier wird keine Testmail gesendet und kein Benutzer automatisch verifiziert.') ?></p>
                        <p><?= $h('Der Bestätigungslink benötigt eine Anmeldung desselben Benutzers. API-Login sowie Filament-/Runner-Zugriff werden nicht pauschal auf verifizierte Benutzer beschränkt.') ?></p>
                    <?php } elseif ($step === 'migrate') { ?>
                        <p><?= $h('Migrationen auf diesem Ziel ausführen, ohne die Datenbank zurückzusetzen.') ?></p><p><strong><?= $escape($environment['DB_CONNECTION'] ?? 'sqlite') ?></strong> · <code><?= $escape($environment['DB_DATABASE'] ?? 'database/database.sqlite') ?></code></p>
                    <?php } elseif ($step === 'packages') { ?><p><?= $h('Pakete registrieren, Filament-Assets veröffentlichen und Permission-Cache leeren. Zuerst Migrationen ausführen.') ?></p>
                    <?php } elseif ($step === 'admin') { ?>
                        <p><?= $h('Filament- und Runner-Zugang vergeben. Bei vorhandener E-Mail bleiben Name und Passwort erhalten.') ?></p>
                        <?php $field('email', 'E-Mail', 'email');
                        $field('name', 'Name (nur für neues Konto)');
                        $field('password', 'Passwort für neues Konto (mindestens 12 Zeichen)', 'password');
                        $field('password_confirmation', 'Passwort bestätigen', 'password') ?>
                        <label><?= $h('Kontoanlage') ?><select name="create_account"><option value="no"><?= $h('Nur bestehendes Konto verwenden') ?></option><option value="yes"><?= $h('Falls nötig, neues Konto anlegen') ?></option></select></label>
                    <?php } elseif ($step === 'runner') { ?><p><?= $h('Runner aktivieren. Anmeldung und Runner-Recht bleiben erforderlich.') ?></p>
                    <?php } elseif ($step === 'storage') { ?><p><?= $h('Öffentlichen Link für zukünftige Media-Dateien erstellen. Windows kann zusätzliche Symlink-Rechte benötigen.') ?></p>
                    <?php } elseif ($step === 'assets') { ?><p><?= $h('Node.js und npm erforderlich. Abhängigkeiten werden aus package-lock.json installiert und die API-Platform-Admin-Assets gebaut. Alternativ public/build separat bereitstellen.') ?></p>
                    <?php } elseif ($step === 'brain') { ?><p><?= $h('Architekturgraph erzeugen. Nur mit Entwicklungspaketen und APP_ENV=local verfügbar; andernfalls überspringen.') ?></p>
                    <?php } elseif ($step === 'finish') { ?><p><?= $h('Abschluss sperrt den Assistenten und entfernt den Zugangsschlüssel. Übersprungene Schritte bleiben offen. Server, Queue und Backup werden nicht gestartet.') ?></p>
                    <?php } ?>
                    <div class="actions"><button type="submit" name="operation" value="run"><?= $h($step === 'finish' ? 'Abschließen und sperren' : 'Schritt ausführen') ?></button><?php if ($step !== 'finish') { ?><button class="secondary" type="submit" name="operation" value="skip"><?= $h('Überspringen') ?></button><?php } ?></div>
                </form><?php } ?>
                <?php if ($step === 'finish') { ?>
                    <h3><?= $h('Adressübersicht') ?></h3><p><?= $h('Adressen verwenden Host und Port dieses Aufrufs. Verfügbarkeit hängt von Einrichtung und Webserver ab.') ?></p>
                    <ul><?php foreach (['/api' => 'API Platform', '/api/docs' => 'OpenAPI', '/admin' => 'API Platform Admin', '/up' => 'Health', '/sanctum/csrf-cookie' => 'CSRF-Cookie', '/filament/login' => 'Filament', '/artisan/login' => 'Artisan-Anmeldung', '/artisan' => 'Artisan Runner'] as $path => $label) { ?><li><?= $h($label) ?>: <a href="<?= $escape($base.$path) ?>"><?= $escape($base.$path) ?></a></li><?php } ?></ul>
                    <?php if (($environment['APP_ENV'] ?? 'local') === 'local') { ?><p>Telescope: <a href="<?= $escape($base.'/telescope') ?>">/telescope</a> · Brain: <a href="<?= $escape($base.'/_laravel-brain') ?>">/_laravel-brain</a> (<?= $h('Entwicklungspakete erforderlich') ?>).</p><?php } ?>
                    <p><?= $h('Frontend-Origin') ?>: <code><?= $escape($environment['CORS_ALLOWED_ORIGINS'] ?? $t('Nicht konfiguriert')) ?></code>.</p>
                <?php } ?>
                <?php if (! $finished) { ?><form method="post" action="setup.php" class="actions"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="operation" value="logout"><button class="secondary" type="submit"><?= $h('Abmelden') ?></button></form><?php } ?>
            </section>
        </div>
    <?php } ?>
    <footer><?= $h('Ausgeführte Änderungen bleiben bei Abbruch bestehen. Anleitung: docs/SETUP.md. Kopia und externe Dienste separat einrichten.') ?></footer>
</main></body></html>
