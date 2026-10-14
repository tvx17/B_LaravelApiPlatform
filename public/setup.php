<?php

use App\Actions\Setup\IndexAction;

// Independent of Laravel: the installer must also work before vendor/ and .env exist.
require_once __DIR__.'/../app/Support/SetupAccess.php';
require_once __DIR__.'/../app/Support/SetupRuntime.php';
require_once __DIR__.'/../app/Support/SetupProcess.php';
require_once __DIR__.'/../app/Support/SetupLocale.php';
require_once __DIR__.'/../app/Support/SetupOrigin.php';
require_once __DIR__.'/../app/Actions/Setup/RunAction.php';
require_once __DIR__.'/../app/Actions/Setup/IndexAction.php';

(new IndexAction(dirname(__DIR__)))->handle();
