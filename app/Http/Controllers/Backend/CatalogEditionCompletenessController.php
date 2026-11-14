<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogConditionRule;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CatalogEditionCompletenessController extends Controller
{
    public function index(
        Request $request,
        Catalog $catalog,
        CatalogEdition $edition
    ): View {
        // Controleer of de cataloguseditie bij deze catalogus hoort.
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        // Haal de landen en gebieden op die bij deze catalogus horen.
        $countries = $catalog->countries()
            ->with('translations')
            ->get();

        $countryIds = $countries->pluck('id');

        // Haal het geselecteerde land op uit de URL.
        $selectedCountryId = $request->integer('country_id');

        // Controleer of het geselecteerde land bij deze catalogus hoort.
        if ($selectedCountryId > 0 && ! $countryIds->contains($selectedCountryId)) {
            abort(404);
        }

        // Beperk de selectie tot het gekozen land.
        if ($selectedCountryId > 0) {
            $countryIds = collect([$selectedCountryId]);
        }

        // Tel alle postzegels die bij deze landen en gebieden horen.
        $totalStamps = Stamp::query()
            ->whereIn('country_id', $countryIds)
            ->count();

        // Tel postzegels met een ingevuld catalogusnummer in deze editie.
        $stampsWithCatalogNumber = Stamp::query()
            ->whereIn('country_id', $countryIds)
            ->whereHas('catalogEntries', function ($query) use ($edition) {
                $query->where('catalog_edition_id', $edition->id)
                    ->whereNotNull('catalog_number')
                    ->where('catalog_number', '!=', '');
            })
            ->count();

        // Bereken het aantal postzegels zonder catalogusnummer.
        $stampsWithoutCatalogNumber = $totalStamps - $stampsWithCatalogNumber;

        // Haal postzegels zonder catalogusnummer in deze editie op.
        $stampsMissingCatalogNumber = Stamp::query()
            ->whereIn('country_id', $countryIds)
            ->whereDoesntHave('catalogEntries', function ($query) use ($edition) {
                $query->where('catalog_edition_id', $edition->id)
                    ->whereNotNull('catalog_number')
                    ->where('catalog_number', '!=', '');
            })
            ->with('country')
            ->orderBy('issue_year')
            ->orderBy('name')
            ->get();

        $catalogNumberCompleteness = $totalStamps > 0
            ? round(($stampsWithCatalogNumber / $totalStamps) * 100, 1)
            : 0;

        // Haal de waarderingsregels van deze catalogus op.
        $conditionRules = CatalogConditionRule::query()
            ->where('catalog_id', $catalog->id)
            ->orderBy('sort_order')
            ->get();

        // Haal de waarderingscategorieën op die in deze catalogus worden gebruikt.
        $conditionIds = $conditionRules
            ->pluck('condition_id')
            ->unique()
            ->values();

        $conditions = Condition::query()
            ->whereIn('id', $conditionIds)
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        // Haal de geselecteerde waarderingscategorie op uit de URL.
        $selectedConditionId = $request->integer('condition_id');

        // Controleer of de waarderingscategorie bij deze catalogus hoort.
        if ($selectedConditionId > 0 && ! $conditionIds->contains($selectedConditionId)) {
            abort(404);
        }

        // Haal de catalogusvermeldingen van deze editie op,
        // inclusief de postzegel en de bestaande waarderingen.
        $catalogEntries = StampCatalogEntry::query()
            ->where('catalog_edition_id', $edition->id)
            ->whereHas('stamp', function ($query) use ($countryIds) {
                $query->whereIn('country_id', $countryIds);
            })
            ->with(['stamp', 'values'])
            ->get();

        // Bereken ontbrekende cataloguswaarderingen.
        $missingCatalogValues = 0;
        $expectedCatalogValues = 0;

        // Bewaar welke waarderingscategorieën per postzegel ontbreken.
        $missingCatalogValueDetails = [];

        foreach ($catalogEntries as $catalogEntry) {
            $stamp = $catalogEntry->stamp;

            if ($stamp === null) {
                continue;
            }

            // Bepaal welke waarderingscategorieën van toepassing zijn.
            $applicableConditionIds = $conditionRules
                ->filter(function ($rule) use ($stamp) {
                    return ($rule->country_id === null || $rule->country_id === $stamp->country_id)
                        && ($rule->issue_year_from === null || $stamp->issue_year >= $rule->issue_year_from)
                        && ($rule->issue_year_to === null || $stamp->issue_year <= $rule->issue_year_to);
                })
                ->pluck('condition_id')
                ->unique();

            // Beperk de waarderingen tot de geselecteerde waarderingscategorie.
            if ($selectedConditionId > 0) {
                $applicableConditionIds = $applicableConditionIds
                    ->filter(fn ($conditionId) => $conditionId === $selectedConditionId);
            }

            // Tel het aantal verwachte waarderingen voor deze postzegel.
            $expectedCatalogValues += $applicableConditionIds->count();

            // Bepaal welke waarderingscategorieën een ingevulde waarde hebben.
            $filledConditionIds = $catalogEntry->values
                ->filter(fn ($value) => $value->catalog_value !== null)
                ->pluck('condition_id')
                ->unique();

            // Bepaal welke waarderingscategorieën ontbreken.
            $missingConditionIds = $applicableConditionIds
                ->diff($filledConditionIds);

            // Tel de ontbrekende waarderingen.
            $missingCatalogValues += $missingConditionIds->count();

            // Bewaar de ontbrekende waarderingen per postzegel.
            foreach ($missingConditionIds as $conditionId) {
                $missingCatalogValueDetails[] = [
                    'stamp_id' => $stamp->id,
                    'stamp_name' => $stamp->name,
                    'issue_year' => $stamp->issue_year,
                    'condition_id' => $conditionId,
                ];
            }
        }

        // Haal de vertaalde namen van de ontbrekende waarderingscategorieën op.
        $missingConditionIds = collect($missingCatalogValueDetails)
            ->pluck('condition_id')
            ->unique();

        $missingConditions = Condition::query()
            ->whereIn('id', $missingConditionIds)
            ->get()
            ->keyBy('id');

        foreach ($missingCatalogValueDetails as &$detail) {
            $condition = $missingConditions->get($detail['condition_id']);

            if ($condition === null) {
                $detail['condition_name'] = '—';

                continue;
            }

            $translation = $condition->translationWithFallback();

            $detail['condition_name'] = $translation !== null
                ? $translation->name
                : $condition->slug;
        }

        unset($detail);

        // Bereken het aantal ingevulde cataloguswaarderingen.
        $filledCatalogValues = $expectedCatalogValues - $missingCatalogValues;

        // Bereken het volledigheidspercentage van de cataloguswaarderingen.
        $catalogValueCompleteness = $expectedCatalogValues > 0
            ? round(($filledCatalogValues / $expectedCatalogValues) * 100, 1)
            : 0;

        return view('backend.catalog-editions.completeness', [
            'catalog' => $catalog,
            'edition' => $edition,
            'totalStamps' => $totalStamps,
            'stampsWithCatalogNumber' => $stampsWithCatalogNumber,
            'stampsWithoutCatalogNumber' => $stampsWithoutCatalogNumber,
            'catalogNumberCompleteness' => $catalogNumberCompleteness,
            'stampsMissingCatalogNumber' => $stampsMissingCatalogNumber,
            'missingCatalogValues' => $missingCatalogValues,
            'missingCatalogValueDetails' => $missingCatalogValueDetails,
            'expectedCatalogValues' => $expectedCatalogValues,
            'filledCatalogValues' => $filledCatalogValues,
            'catalogValueCompleteness' => $catalogValueCompleteness,
            'countries' => $countries,
            'selectedCountryId' => $selectedCountryId,
            'conditions' => $conditions,
            'selectedConditionId' => $selectedConditionId,
        ]);
    }
}
