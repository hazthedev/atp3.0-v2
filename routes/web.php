<?php

use App\Models\FunctionalLocation;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

// Fleet (Phase 6 — built)
Route::get('/fleet', fn () => view('fleet.index', [
    'aircraft' => FunctionalLocation::query()->with('aircraftType')->orderBy('registration')->get(),
]))->name('fleet.index');

Route::get('/fleet/aircraft/{registration}/counters', fn (string $registration) => view('fleet.aircraft-counters', [
    'registration' => $registration,
]))->name('fleet.aircraft.counters');

Route::get('/fleet/aircraft/{registration}/airworthiness', fn (string $registration) => view('fleet.airworthiness', [
    'registration' => $registration,
]))->name('fleet.aircraft.airworthiness');

// Modules not yet built — honest stub so the nav never dead-ends
Route::get('/m/{module}', fn (string $module) => view('stub', ['module' => $module]))->name('stub');

// Phase 6 design-system preview (review artifact)
Route::get('/design', fn () => view('design'))->name('design');
