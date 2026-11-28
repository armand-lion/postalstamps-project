<?php

use App\Http\Controllers\Backend\CatalogController;
use App\Http\Controllers\Backend\CatalogEditionBulkEditController;
use App\Http\Controllers\Backend\CatalogEditionComparisonController;
use App\Http\Controllers\Backend\CatalogEditionCompletenessController;
use App\Http\Controllers\Backend\CatalogEditionController;
use App\Http\Controllers\Backend\CatalogEditionExportController;
use App\Http\Controllers\Backend\CatalogEditionHistoryController;
use App\Http\Controllers\Backend\CatalogEditionImportController;
use App\Http\Controllers\Backend\CatalogEditionReviewController;
use App\Http\Controllers\Backend\CollectibleController;
use App\Http\Controllers\Backend\CollectibleCopyController;
use App\Http\Controllers\Backend\CollectibleTypeController;
use App\Http\Controllers\Backend\CountryController;
use App\Http\Controllers\Backend\StampBulkEditController;
use App\Http\Controllers\Backend\StampCatalogValueReviewController;
use App\Http\Controllers\Backend\StampChangeHistoryController;
use App\Http\Controllers\Backend\StampController;
use App\Http\Controllers\Backend\StampCopyController;
use App\Http\Controllers\Backend\StampCopyImageController;
use App\Http\Controllers\Backend\StampCopyValuationController;
use App\Http\Controllers\Backend\StampImageController;
use App\Http\Controllers\Backend\StampSeriesController;
use App\Http\Controllers\Backend\ThemeController;
use App\Http\Controllers\Backend\WatermarkController;
use App\Http\Middleware\EnsureUserIsAdmin;
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
            Route::resource('watermarks', WatermarkController::class);

            // Bulkbewerking van algemene postzegelgegevens.
            Route::middleware(EnsureUserIsAdmin::class)->group(function () {
                Route::get(
                    'stamps/bulk-edit',
                    [StampBulkEditController::class, 'index']
                )->name('stamps.bulk-edit.index');

                Route::get(
                    'stamps/bulk-edit/selected',
                    [StampBulkEditController::class, 'selected']
                )->name('stamps.bulk-edit.selected');

                Route::get(
                    'stamps/bulk-edit/edit',
                    [StampBulkEditController::class, 'edit']
                )->name('stamps.bulk-edit.edit');

                Route::post(
                    'stamps/bulk-edit/preview',
                    [StampBulkEditController::class, 'preview']
                )->name('stamps.bulk-edit.preview');

                Route::get(
                    'stamps/bulk-edit/preview',
                    [StampBulkEditController::class, 'showPreview']
                )->name('stamps.bulk-edit.preview.show');

                Route::post(
                    'stamps/bulk-edit/preview/exclusion',
                    [StampBulkEditController::class, 'updateExclusion']
                )->name('stamps.bulk-edit.preview.exclusion');

                Route::post(
                    'stamps/bulk-edit/apply',
                    [StampBulkEditController::class, 'apply']
                )->name('stamps.bulk-edit.apply');

                Route::post(
                    'stamps/bulk-edit/selection',
                    [StampBulkEditController::class, 'updateSelection']
                )->name('stamps.bulk-edit.selection.update');

                Route::post(
                    'stamps/bulk-edit/selection/all-filtered',
                    [StampBulkEditController::class, 'selectAllFiltered']
                )->name('stamps.bulk-edit.selection.all-filtered');

                Route::delete(
                    'stamps/bulk-edit/selection',
                    [StampBulkEditController::class, 'clearSelection']
                )->name('stamps.bulk-edit.selection.clear');
            });

            // Algemene wijzigingsgeschiedenis van postzegels.
            Route::get(
                'stamps/history',
                [StampChangeHistoryController::class, 'index']
            )->name('stamps.history.index');

            Route::get(
                'stamps/history/{batch}',
                [StampChangeHistoryController::class, 'show']
            )->whereNumber('batch')->name('stamps.history.show');

            Route::resource('stamps', StampController::class);

            Route::patch(
                'stamp-catalog-values/{stampCatalogValue}/review',
                [StampCatalogValueReviewController::class, 'update']
            )
                ->middleware('admin')
                ->name('stamp-catalog-values.review');

            Route::resource('stamp-copies', StampCopyController::class)
                ->parameters(['stamp-copies' => 'stampCopy']);

            Route::resource('stamp-copies.images', StampCopyImageController::class)
                ->only(['store', 'update', 'destroy'])
                ->parameters(['stamp-copies' => 'stampCopy']);

            Route::resource('stamp-copies.valuations', StampCopyValuationController::class)
                ->only(['store', 'update', 'destroy'])
                ->parameters([
                    'stamp-copies' => 'stampCopy',
                    'valuations' => 'valuation',
                ]);

            Route::resource('collectibles', CollectibleController::class);

            Route::resource('collectible-copies', CollectibleCopyController::class)
                ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
                ->parameters(['collectible-copies' => 'collectibleCopy']);

            // Overzicht van verzamelobjecttypen.
            Route::resource('collectible-types', CollectibleTypeController::class)
                ->except(['show'])
                ->parameters(['collectible-types' => 'collectibleType']);

            // Postzegelseries dynamisch ophalen op basis van land en uitgiftejaar.
            Route::get('stamp-series/lookup', [StampSeriesController::class, 'lookup'])
                ->name('stamp-series.lookup');

            Route::resource('stamp-series', StampSeriesController::class);
            Route::resource('catalogs', CatalogController::class);

            // Algemeen overzicht van catalogusedities
            Route::get('catalog-editions', [CatalogEditionController::class, 'all'])
                ->name('catalog-editions.index');

            Route::resource('stamps.images', StampImageController::class)
                ->only(['store', 'update', 'destroy']);

            // Catalogusedities

            // Dashboard voor de volledigheid van catalogusgegevens.
            Route::get(
                'catalogs/{catalog}/editions/{edition}/completeness',
                [CatalogEditionCompletenessController::class, 'index']
            )->name('catalogs.editions.completeness');

            // Overzicht van cataloguswaarden die nog gecontroleerd moeten worden.
            Route::get(
                'catalogs/{catalog}/editions/{edition}/review',
                [CatalogEditionReviewController::class, 'index']
            )
                ->middleware('admin')
                ->name('catalogs.editions.review');

            // Bulkbewerking van catalogusnummers en cataloguswaarden.
            Route::get(
                'catalogs/{catalog}/editions/{edition}/bulk-edit',
                [CatalogEditionBulkEditController::class, 'edit']
            )->name('catalogs.editions.bulk-edit');

            Route::patch(
                'catalogs/{catalog}/editions/{edition}/bulk-edit',
                [CatalogEditionBulkEditController::class, 'update']
            )->name('catalogs.editions.bulk-update');

            // Excel-export van een cataloguseditie.
            Route::get(
                'catalogs/{catalog}/editions/{edition}/export/excel',
                [CatalogEditionExportController::class, 'exportExcel']
            )->name('catalogs.editions.export.excel');

            // Excel-import van een cataloguseditie.
            // Alleen beheerders mogen de importpagina openen en bestanden uploaden.
            Route::middleware(EnsureUserIsAdmin::class)->group(function () {
                Route::get(
                    'catalogs/{catalog}/editions/import',
                    [CatalogEditionImportController::class, 'create']
                )->name('catalogs.editions.import.create');

                Route::post(
                    'catalogs/{catalog}/editions/import',
                    [CatalogEditionImportController::class, 'store']
                )->name('catalogs.editions.import.store');

                // Voorbeeldweergave van een gevalideerd Excel-importbestand.
                Route::get(
                    'catalogs/{catalog}/editions/import/preview',
                    [CatalogEditionImportController::class, 'preview']
                )->name('catalogs.editions.import.preview');

                // Bevestig en start de definitieve Excel-import.
                Route::post(
                    'catalogs/{catalog}/editions/import/confirm',
                    [CatalogEditionImportController::class, 'confirm']
                )->name('catalogs.editions.import.confirm');
            });

            // Vergelijking van twee catalogusedities.
            Route::get(
                'catalogs/{catalog}/editions/compare',
                [CatalogEditionComparisonController::class, 'index']
            )->name('catalogs.editions.compare');

            // Overzicht van de wijzigingsgeschiedenis van een cataloguseditie.
            Route::get(
                'catalogs/{catalog}/editions/{edition}/history',
                [CatalogEditionHistoryController::class, 'index']
            )->name('catalogs.editions.history.index');

            // Details van een geschiedenisbatch.
            Route::get(
                'catalogs/{catalog}/editions/{edition}/history/{batch}',
                [CatalogEditionHistoryController::class, 'show']
            )->name('catalogs.editions.history.show');

            Route::resource('catalogs.editions', CatalogEditionController::class);
        });
});

require __DIR__.'/settings.php';
