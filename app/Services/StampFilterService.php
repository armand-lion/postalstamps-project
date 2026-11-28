<?php

namespace App\Services;

use App\Models\Stamp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class StampFilterService
{
    /**
     * Pas de algemene postzegelfilters toe.
     *
     * @param  Builder<Stamp>  $query
     * @return Builder<Stamp>
     */
    public function applyFilters(Builder $query, Request $request): Builder
    {
        // Zoek in postzegelnamen, vertalingen en catalogusreferenties.
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('catalog_reference', 'like', "%{$search}%")
                    ->orWhereHas('translations', function ($translationQuery) use ($search) {
                        $translationQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter op land of gebied.
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->integer('country_id'));
        }

        // Filter op uitgiftejaar.
        if ($request->filled('issue_year')) {
            $query->where('issue_year', $request->integer('issue_year'));
        }

        // Filter op catalogus.
        if ($request->filled('catalog_id')) {
            $catalogId = $request->integer('catalog_id');

            $query->whereHas('catalogEntries.catalogEdition', function ($editionQuery) use ($catalogId) {
                $editionQuery->where('catalog_id', $catalogId);
            });
        }

        // Filter op cataloguseditie.
        if ($request->filled('catalog_edition_id')) {
            $catalogEditionId = $request->integer('catalog_edition_id');

            $query->whereHas('catalogEntries', function ($entryQuery) use ($catalogEditionId) {
                $entryQuery->where('catalog_edition_id', $catalogEditionId);
            });
        }

        // Filter op postzegelserie.
        if ($request->filled('stamp_series_id')) {
            $query->where('stamp_series_id', $request->integer('stamp_series_id'));
        }

        // Filter op thema.
        if ($request->filled('theme_id')) {
            $themeId = $request->integer('theme_id');

            $query->whereHas('themes', function ($themeQuery) use ($themeId) {
                $themeQuery->where('themes.id', $themeId);
            });
        }

        // Filter op druktechniek.
        if ($request->filled('printing_method')) {
            $printingMethod = $request->string('printing_method')->toString();

            $query->where('printing_method', $printingMethod);
        }

        // Filter op ontbrekende gegevens.
        if ($request->filled('missing_field')) {
            $missingField = $request->string('missing_field')->toString();

            match ($missingField) {
                'issue_date' => $query->whereNull('issue_date'),
                'stamp_series_id' => $query->whereNull('stamp_series_id'),
                'themes' => $query->whereDoesntHave('themes'),

                'face_value',
                'currency',
                'designer',
                'printing_method',
                'notes' => $query->where(function ($missingQuery) use ($missingField) {
                    $missingQuery->whereNull($missingField)
                        ->orWhereRaw("TRIM({$missingField}) = ?", ['']);
                }),

                default => null,
            };
        }

        // Filter op hoofdpostzegels en varianten.
        if ($request->filled('stamp_type')) {
            $stampType = $request->string('stamp_type')->toString();

            match ($stampType) {
                'main' => $query->whereNull('parent_stamp_id'),
                'parent' => $query->whereHas('variants'),
                'variant' => $query->whereNotNull('parent_stamp_id'),
                'standalone' => $query->whereNull('parent_stamp_id')
                    ->whereDoesntHave('variants'),

                default => null,
            };
        }

        return $query;
    }
}
