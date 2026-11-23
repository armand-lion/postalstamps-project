<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
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
        /** @var array<int, array<string, mixed>> $comparisonResults */
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

                'condition_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('catalog_condition_rules', 'condition_id')
                        ->where('catalog_id', $catalog->id),
                ],

                'sort' => [
                    'nullable',
                    'string',
                    Rule::in([
                        'default',
                        'absolute_increase',
                        'absolute_decrease',
                        'percentage_increase',
                        'percentage_decrease',
                    ]),
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

            // Filter de resultaten op de geselecteerde waarderingscategorie.
            if (! empty($validated['condition_id'])) {
                $selectedConditionId = (int) $validated['condition_id'];

                $filteredResults = [];

                foreach ($comparisonResults as $result) {
                    $filteredConditions = [];

                    foreach ($result['conditions'] as $conditionResult) {
                        if ((int) $conditionResult['condition_id'] === $selectedConditionId) {
                            $filteredConditions[] = $conditionResult;
                        }
                    }

                    if (! empty($filteredConditions)) {
                        $result['conditions'] = $filteredConditions;
                        $filteredResults[] = $result;
                    }
                }

                $comparisonResults = $filteredResults;
            }

            // Bereid de resultaten voor op sortering per waarderingscategorie.
            if (($validated['sort'] ?? 'default') !== 'default') {
                $sortableResults = [];

                foreach ($comparisonResults as $result) {
                    // Postzegels zonder cataloguswaarden blijven afzonderlijke regels.
                    if (empty($result['conditions'])) {
                        $sortableResults[] = $result;

                        continue;
                    }

                    // Maak voor iedere waarderingscategorie een afzonderlijk resultaat.
                    foreach ($result['conditions'] as $conditionResult) {
                        $sortableResult = $result;
                        $sortableResult['conditions'] = [$conditionResult];

                        $sortableResults[] = $sortableResult;
                    }
                }

                $comparisonResults = $sortableResults;

                // Bepaal op welk waardeverschil gesorteerd moet worden.
                $sort = $validated['sort'];

                $sortField = str_starts_with($sort, 'absolute_')
                    ? 'absolute_change'
                    : 'percentage_change';

                $descending = in_array($sort, [
                    'absolute_increase',
                    'percentage_increase',
                ], true);

                // Sorteer iedere waarderingscategorie afzonderlijk.
                usort($comparisonResults, function (array $a, array $b) use ($sortField, $descending): int {
                    $valueA = $a['conditions'][0][$sortField] ?? null;
                    $valueB = $b['conditions'][0][$sortField] ?? null;

                    // Niet-vergelijkbare waarden komen altijd onderaan.
                    if ($valueA === null && $valueB === null) {
                        return 0;
                    }

                    if ($valueA === null) {
                        return 1;
                    }

                    if ($valueB === null) {
                        return -1;
                    }

                    // Sorteer oplopend of aflopend.
                    return $descending
                        ? $valueB <=> $valueA
                        : $valueA <=> $valueB;
                });
            }
        }
        // Haal uitsluitend de edities van de geselecteerde catalogus op.
        $editions = $catalog->editions()
            ->orderByDesc('edition_year')
            ->orderBy('edition_name')
            ->get();

        // Haal uitsluitend de waarderingscategorieën van deze catalogus op.
        $conditionIds = $catalog->conditionRules()
            ->orderBy('sort_order')
            ->pluck('condition_id')
            ->unique()
            ->values();

        $conditions = Condition::query()
            ->whereIn('id', $conditionIds)
            ->where('is_active', true)
            ->with('translations')
            ->get()
            ->sortBy(fn (Condition $condition) => $conditionIds->search($condition->id))
            ->values();

        // Toon de vergelijkingspagina.
        return view('backend.catalog-editions.compare', [
            'catalog' => $catalog,
            'editions' => $editions,
            'conditions' => $conditions,
            'comparisonResults' => $comparisonResults,
        ]);
    }
}
