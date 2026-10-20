<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use Illuminate\Http\Request;

class CatalogEditionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Catalog $catalog)
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
    public function create(Catalog $catalog)
    {
        return view('backend.catalog-editions.create', [
            'catalog' => $catalog,
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified catalog edition.
     */
    public function show(Catalog $catalog, CatalogEdition $edition)
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
    public function edit(Catalog $catalog, CatalogEdition $edition)
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
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified catalog edition.
     */
    public function destroy(Catalog $catalog, CatalogEdition $edition)
    {
        // Controleer of de editie bij deze catalogus hoort.
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        // Voorkom verwijderen wanneer er gekoppelde gegevens bestaan.
        if (
            $edition->stampEntries()->exists()
            || $edition->seriesValues()->exists()
            || $edition->userCatalogs()->exists()
        ) {
            return redirect()
                ->route('backend.catalogs.editions.show', [$catalog, $edition])
                ->with(
                    'error',
                    __('Deze cataloguseditie kan niet worden verwijderd omdat er nog catalogusvermeldingen, cataloguswaarden of gebruikers aan gekoppeld zijn.')
                );
        }

        // Verwijder de cataloguseditie.
        $edition->delete();

        // Ga terug naar het overzicht.
        return redirect()
            ->route('backend.catalogs.editions.index', $catalog)
            ->with(
                'success',
                __('De cataloguseditie is succesvol verwijderd.')
            );
    }
}
