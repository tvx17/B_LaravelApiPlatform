<?php

namespace App\Actions\Sessions;

use Illuminate\View\View;

class CreateAction
{
    public function handle(): View
    {
        return view('auth.login');
    }
}
