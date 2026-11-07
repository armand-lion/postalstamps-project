<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CatalogEditionController extends Controller
{
    /**
     * Display a listing of all catalog editions.
     */
    public function all(Request $request): View
    {
        // Haal alle catalogussen op voor het filter.
        $catalogs = Catalog::query()
            ->orderBy('name')
            ->get();

        // Bouw de query voor alle catalogusedities op.
        $query = CatalogEdition::query()
            ->with('catalog');

        // Filter op catalogus wanneer er een catalogus is geselecteerd.
        if ($request->filled('catalog_id')) {
            $query->where('catalog_id', $request->integer('catalog_id'));
        }

        // Haal de gefilterde catalogusedities op.
        $editions = $query
            ->orderByDesc('edition_year')
            ->orderBy('edition_name')
            ->get();

        return view('backend.catalog-editions.all', [
            'catalogs' => $catalogs,
            'editions' => $editions,
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Catalog $catalog): View
    {
        // Haal uitsluitend de edities van deze catalogus op.
        $editions = $catalog->editions()
            ->orderByDesc('edition_year')
            ->orderBy('edition_name')
            ->get();

        return view('backend.catalog-editions.index', [
            'catalog' => $catalog,
            'editions' => $editions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Catalog $catalog): View
    {
        return view('backend.catalog-editions.create', [
            'catalog' => $catalog,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): void
    {
        //
    }

    /**
     * Display the specified catalog edition.
     */
    public function show(Catalog $catalog, CatalogEdition $edition): View
    {
        // Controleer of de editie bij deze catalogus hoort.
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        // Toon de detailpagina van de cataloguseditie.
        return view('backend.catalog-editions.show', [
            'catalog' => $catalog,
            'edition' => $edition,
        ]);
    }

    /**
     * Show the form for editing the specified catalog edition.
     */
    public function edit(Catalog $catalog, CatalogEdition $edition): View
    {
        // Controleer of de editie bij deze catalogus hoort.
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        // Toon het bewerkformulier.
        return view('backend.catalog-editions.edit', [
            'catalog' => $catalog,
            'edition' => $edition,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): void
    {
        //
    }

    /**
     * Remove the specified catalog edition.
     */
    public function destroy(Catalog $catalog, CatalogEdition $edition): RedirectResponse
    {
        // Controleer of de editie bij deze catalogus hoort.
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        // Voorkom verwijderen wanneer er gekoppelde gegevens bestaan.
        if (
            $edition->stampEntries()->exists()
            || $edition->collectibleEntries()->exists()
            || $edition->seriesValues()->exists()
            || $edition->userCatalogs()->exists()
        ) {
            return redirect()
                ->back()
                ->with(
                    'error',
                    __('Deze cataloguseditie kan niet worden verwijderd omdat er nog postzegels, verzamelobjecten, cataloguswaarden of gebruikers aan gekoppeld zijn.')
                );
        }

        // Verwijder de cataloguseditie.
        $edition->delete();

        // Ga terug naar het overzicht.
        return redirect()
            ->back()
            ->with(
                'success',
                __('De cataloguseditie is succesvol verwijderd.')
            );
    }
}
