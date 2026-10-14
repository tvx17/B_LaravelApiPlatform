<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ###> api-platform/admin ###
Route::get('/admin/{path?}', fn () => view('admin'))->where('path', '.*');
// ###< api-platform/admin ###
