<?php

use App\Http\Controllers\SepioInspectorController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sepio-inspector', [SepioInspectorController::class, 'index']);
