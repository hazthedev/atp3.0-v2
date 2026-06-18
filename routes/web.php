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

// Administration (Phase 6 — reference data)
Route::get('/admin/aircraft-types', fn () => view('admin.aircraft-types'))->name('admin.aircraft-types');

// Technical Data (Phase 6 — built)
Route::get('/technical-data/maintenance-programs', fn () => view('technical-data.maintenance-programs'))->name('technical-data.maintenance-programs');

// Flight Recording (Phase 6 — built)
Route::get('/flight/record', fn () => view('flight.flight-entry'))->name('flight.record');

// MRO (Phase 6 — built)
Route::get('/mro/work-packages', fn () => view('mro.work-packages'))->name('mro.work-packages');

// Inventory (Phase 6 — built)
Route::get('/inventory/items', fn () => view('inventory.item-master-data'))->name('inventory.items');

// Reports (Phase 6 — built)
Route::get('/reports/fleet-status', fn () => view('reports.fleet-status'))->name('reports.fleet-status');

// Modules not yet built — honest stub so the nav never dead-ends
Route::get('/m/{module}', fn (string $module) => view('stub', ['module' => $module]))->name('stub');

// Phase 6 design-system preview (review artifact)
Route::get('/design', fn () => view('design'))->name('design');
