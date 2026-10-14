<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Sessions\CreateAction;
use App\Actions\Sessions\DestroyAction;
use App\Actions\Sessions\StoreAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function create(CreateAction $action): View
    {
        return $action->handle();
    }

    public function store(Request $request, StoreAction $action): RedirectResponse
    {
        return $action->handle($request);
    }

    public function destroy(Request $request, DestroyAction $action): RedirectResponse
    {
        return $action->handle($request);
    }
}
