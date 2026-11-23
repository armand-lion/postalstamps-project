<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Services\CatalogEditionComparisonService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogEditionComparisonController extends Controller
{
    /**
     * Toon de vergelijkingspagina voor catalogusedities.
     */
    public function index(Request $request, Catalog $catalog): View
    {

        // Standaard zijn er nog geen vergelijkingsresultaten.
        $comparisonResults = [];

        // Controleer of er catalogusedities voor een vergelijking zijn geselecteerd.
        if ($request->hasAny(['previous_edition_id', 'current_edition_id'])) {

            // Valideer beide geselecteerde catalogusedities.
            $validated = $request->validate([
                'previous_edition_id' => [
                    'required',
                    'integer',
                    Rule::exists('catalog_editions', 'id')
                        ->where('catalog_id', $catalog->id),
                ],

                'current_edition_id' => [
                    'required',
                    'integer',
                    'different:previous_edition_id',
                    Rule::exists('catalog_editions', 'id')
                        ->where('catalog_id', $catalog->id),
                ],
            ]);

            // Haal de gevalideerde vorige cataloguseditie op.
            $previousEdition = CatalogEdition::query()
                ->whereKey($validated['previous_edition_id'])
                ->firstOrFail();

            // Haal de gevalideerde huidige cataloguseditie op.
            $currentEdition = CatalogEdition::query()
                ->whereKey($validated['current_edition_id'])
                ->firstOrFail();

            // Vergelijk de cataloguswaarden van beide edities.
            $comparisonResults = app(CatalogEditionComparisonService::class)
                ->compareEditions(
                    $previousEdition,
                    $currentEdition
                );
        }
        // Haal uitsluitend de edities van de geselecteerde catalogus op.
        $editions = $catalog->editions()
            ->orderByDesc('edition_year')
            ->orderBy('edition_name')
            ->get();

        // Toon de vergelijkingspagina.
        return view('backend.catalog-editions.compare', [
            'catalog' => $catalog,
            'editions' => $editions,
            'comparisonResults' => $comparisonResults,
        ]);
    }
}
