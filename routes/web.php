<?php

use App\Http\Controllers\Backend\StampController;
use App\Http\Controllers\Backend\StampSeriesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Language switcher route
Route::post('/language', function (Request $request) {
    $locale = $request->validate([
        'locale' => ['required', 'in:nl,en,de,fr,es'],
    ])['locale'];

    session(['locale' => $locale]);

    return redirect()->back();
})->name('language.switch');

// Dashboard and backend routes

// Route::middleware(['auth', 'verified'])->group(function () {   => tijdelijk verwijderd, omdat ik geen email verificatie wil hebben
Route::middleware(['auth'])->group(function () { //
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('backend')
        ->name('backend.')
        ->group(function () {
            Route::resource('stamps', StampController::class);
            Route::resource('stamp-series', StampSeriesController::class);
        });
});

require __DIR__.'/settings.php';
