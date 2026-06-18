<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Phase 6 design-system preview (review artifact; remove before shipping screens)
Route::get('/design', function () {
    return view('design');
});
