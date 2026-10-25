<?php

use App\Http\Controllers\Backend\CatalogController;
use App\Http\Controllers\Backend\CatalogEditionController;
use App\Http\Controllers\Backend\CollectibleController;
use App\Http\Controllers\Backend\CountryController;
use App\Http\Controllers\Backend\StampController;
use App\Http\Controllers\Backend\StampImageController;
use App\Http\Controllers\Backend\StampSeriesController;
use App\Http\Controllers\Backend\ThemeController;
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
            Route::resource('countries', CountryController::class);
            Route::resource('themes', ThemeController::class);
            Route::resource('stamps', StampController::class);
            Route::resource('collectibles', CollectibleController::class);
            Route::resource('stamp-series', StampSeriesController::class);
            Route::resource('catalogs', CatalogController::class);
            Route::resource('stamps.images', StampImageController::class)
                ->only(['store', 'update', 'destroy']);

            // Catalogusedities
            Route::resource('catalogs.editions', CatalogEditionController::class);
        });
});

require __DIR__.'/settings.php';
