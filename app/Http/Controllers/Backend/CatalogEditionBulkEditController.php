<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogEditionBulkEditController extends Controller
{
    public function edit(
        Request $request,
        Catalog $catalog,
        CatalogEdition $edition
    ): View {
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        $countryIds = $catalog->countries()->pluck('countries.id');

        $stamps = Stamp::query()
            ->whereIn('country_id', $countryIds)
            ->with([
                'catalogEntries' => function ($query) use ($edition) {
                    $query->where('catalog_edition_id', $edition->id)
                        ->with('values');
                },
            ])
            ->orderBy('issue_year')
            ->orderBy('id')
            ->paginate(25);

        $conditionRules = $catalog->conditionRules()
            ->with('condition')
            ->orderBy('sort_order')
            ->get();

        foreach ($stamps as $stamp) {
            $applicableConditionRules = $conditionRules
                ->filter(function ($rule) use ($stamp) {
                    return
                        ($rule->country_id === null || $rule->country_id === $stamp->country_id)
                        && ($rule->issue_year_from === null || $rule->issue_year_from <= $stamp->issue_year)
                        && ($rule->issue_year_to === null || $rule->issue_year_to >= $stamp->issue_year);
                })
                ->values();

            $stamp->setRelation('applicableConditionRules', $applicableConditionRules);
        }

        return view('backend.catalog-editions.bulk-edit', [
            'catalog' => $catalog,
            'edition' => $edition,
            'stamps' => $stamps,
        ]);
    }

    public function update(
        Request $request,
        Catalog $catalog,
        CatalogEdition $edition
    ): RedirectResponse {
        // Controleer of de cataloguseditie bij deze catalogus hoort.
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        // Stel begrijpelijke veldnamen samen voor validatiefouten.
        $attributeNames = [];

        $stampIds = array_keys($request->input('stamps', []));

        $stampsForValidation = Stamp::query()
            ->whereIn('id', $stampIds)
            ->get()
            ->keyBy('id');

        $conditionsForValidation = Condition::query()
            ->with('translations')
            ->get()
            ->keyBy('id');

        foreach ($stampsForValidation as $stamp) {
            $attributeNames["stamps.{$stamp->id}.catalog_number"] =
                __('Catalogusnummer').' - '.$stamp->name;

            foreach ($conditionsForValidation as $condition) {
                $conditionName = $condition->translationWithFallback()->name
                    ?? $condition->slug;

                $attributeNames["stamps.{$stamp->id}.values.{$condition->id}"] =
                    __('Cataloguswaarde').' ('.$conditionName.') - '.$stamp->name;
            }
        }

        // Valideer de ingevoerde catalogusnummers en cataloguswaarden.
        $validated = $request->validate(
            [
                'stamps' => ['required', 'array'],
                'stamps.*' => ['required', 'array'],
                'stamps.*.catalog_number' => ['nullable', 'string', 'max:100'],
                'stamps.*.values' => ['nullable', 'array'],
                'stamps.*.values.*' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'decimal:0,2',
                ],
            ],
            [],
            $attributeNames
        );

        // Controleer of cataloguswaarden een catalogusnummer hebben.
        foreach ($validated['stamps'] as $stampId => $stampData) {
            $catalogNumber = trim($stampData['catalog_number'] ?? '');

            $hasCatalogValues = false;

            foreach ($stampData['values'] ?? [] as $value) {
                if ($value !== null && $value !== '') {
                    $hasCatalogValues = true;

                    break;
                }
            }

            if ($catalogNumber === '' && $hasCatalogValues) {
                throw ValidationException::withMessages([
                    "stamps.{$stampId}.catalog_number" => __('Vul eerst een catalogusnummer in voordat je cataloguswaarden toevoegt.'),
                ]);
            }
        }

        // Controleer of alle ingestuurde postzegels bij de catalogus horen.
        $submittedStampIds = array_keys($validated['stamps']);

        $countryIds = $catalog->countries()->pluck('countries.id');

        $validStampIds = Stamp::query()
            ->whereIn('country_id', $countryIds)
            ->whereIn('id', $submittedStampIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $invalidStampIds = array_diff(
            array_map('strval', $submittedStampIds),
            $validStampIds
        );

        if (! empty($invalidStampIds)) {
            throw ValidationException::withMessages([
                'stamps' => __('Een of meer postzegels behoren niet tot deze catalogus.'),
            ]);
        }

        // Haal de waarderingsregels van deze catalogus op.
        $conditionRules = $catalog->conditionRules()->get();

        // Haal de ingestuurde postzegels op.
        $submittedStamps = Stamp::query()
            ->whereIn('id', $submittedStampIds)
            ->get();

        foreach ($submittedStamps as $stamp) {

            // Bepaal welke waarderingscategorieën van toepassing zijn.
            $allowedConditionIds = $conditionRules
                ->filter(function ($rule) use ($stamp) {
                    return
                        ($rule->country_id === null || $rule->country_id === $stamp->country_id)
                        && ($rule->issue_year_from === null || $rule->issue_year_from <= $stamp->issue_year)
                        && ($rule->issue_year_to === null || $rule->issue_year_to >= $stamp->issue_year);
                })
                ->pluck('condition_id')
                ->map(fn ($id) => (string) $id)
                ->all();

            // Verzamel de ingestuurde waarderingscategorieën.
            $submittedConditionIds = array_keys(
                $validated['stamps'][$stamp->id]['values'] ?? []
            );

            // Controleer of alle categorieën zijn toegestaan.
            $invalidConditionIds = array_diff(
                array_map('strval', $submittedConditionIds),
                $allowedConditionIds
            );

            if (! empty($invalidConditionIds)) {
                throw ValidationException::withMessages([
                    'stamps' => __('Een of meer waarderingscategorieën zijn niet toegestaan voor deze postzegel.'),
                ]);
            }
        }

        // Sla alle wijzigingen veilig op binnen één databasetransactie.
        DB::transaction(function () use ($validated, $edition) {

            foreach ($validated['stamps'] as $stampId => $stampData) {

                // Zoek de bestaande catalogusvermelding voor deze editie.
                $catalogEntry = StampCatalogEntry::query()
                    ->where('catalog_edition_id', $edition->id)
                    ->where('stamp_id', $stampId)
                    ->first();

                // Haal het ingevoerde catalogusnummer op.
                $catalogNumber = trim($stampData['catalog_number'] ?? '');

                // Controleer of er cataloguswaarden zijn ingevuld.
                $hasCatalogValues = false;

                foreach ($stampData['values'] ?? [] as $value) {
                    if ($value !== null && $value !== '') {
                        $hasCatalogValues = true;

                        break;
                    }
                }

                // Verwijder de cataloguskoppeling als alle velden leeg zijn.
                if ($catalogEntry && $catalogNumber === '' && ! $hasCatalogValues) {

                    // Verwijder eerst alle bijbehorende cataloguswaarden.
                    $catalogEntry->values()->delete();

                    // Verwijder daarna de catalogusvermelding.
                    $catalogEntry->delete();

                    continue;
                }

                // Werk een bestaande catalogusvermelding bij.
                if ($catalogEntry) {

                    if ($catalogNumber !== '') {
                        $catalogEntry->update([
                            'catalog_number' => $catalogNumber,
                        ]);
                    }

                } elseif ($catalogNumber !== '') {

                    // Maak een nieuwe catalogusvermelding aan.
                    $catalogEntry = StampCatalogEntry::create([
                        'catalog_edition_id' => $edition->id,
                        'stamp_id' => $stampId,
                        'catalog_number' => $catalogNumber,
                    ]);

                }

                // Zonder catalogusvermelding kunnen we geen waarden opslaan.
                if (! $catalogEntry) {
                    continue;
                }

                // Verwerk de ingevoerde cataloguswaarden.
                foreach ($stampData['values'] ?? [] as $conditionId => $value) {
                    if ($value === null || $value === '') {
                        $catalogEntry->values()
                            ->where('condition_id', $conditionId)
                            ->delete();

                        continue;
                    }

                    $catalogValue = $catalogEntry->values()
                        ->firstOrNew(['condition_id' => $conditionId]);

                    if (! $catalogValue->exists) {
                        $catalogValue->currency = 'EUR';
                    }

                    $catalogValue->catalog_value = $value;
                    $catalogValue->save();
                }
            }

        });

        return redirect()
            ->route('backend.catalogs.editions.bulk-edit', [$catalog, $edition])
            ->with('success', __('De catalogusnummers en cataloguswaarden zijn succesvol opgeslagen.'));
    }
}
