<?php

namespace App\Services;

use App\Models\Stamp;
use App\Models\StampChangeBatch;
use App\Models\StampSeries;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StampBulkEditService
{
    /**
     * Bepaal het voorgestelde land of gebied van een postzegel.
     *
     * @param  array<string, mixed>  $operations
     */
    public function resolveCountryId(Stamp $stamp, array $operations): int
    {
        if (($operations['country_operation'] ?? 'unchanged') === 'set') {
            return (int) $operations['country_id'];
        }

        return (int) $stamp->country_id;
    }

    /**
     * Bepaal het voorgestelde land voor alle geselecteerde postzegels.
     *
     * @param  Collection<int, Stamp>  $stamps
     * @param  array<string, mixed>  $operations
     * @return array<int, int>
     */
    public function resolveCountryIds(
        Collection $stamps,
        array $operations
    ): array {
        $countryIds = [];

        foreach ($stamps as $stamp) {
            $countryIds[$stamp->id] = $this->resolveCountryId(
                $stamp,
                $operations
            );
        }

        return $countryIds;
    }

    /**
     * Controleer landconflicten bij hoofdzegels en varianten.
     *
     * @param  array<int, int>  $proposedCountryIds
     * @return array<int, string>
     */
    public function detectParentCountryConflicts(
        Stamp $stamp,
        array $proposedCountryIds
    ): array {
        $conflicts = [];

        $countryId = $proposedCountryIds[$stamp->id]
            ?? (int) $stamp->country_id;

        // Controleer de gekoppelde hoofdzegel.
        if ($stamp->parentStamp !== null) {
            $parentCountryId = $proposedCountryIds[$stamp->parentStamp->id]
                ?? (int) $stamp->parentStamp->country_id;

            if ($parentCountryId !== $countryId) {
                $conflicts[] = __(
                    'De gekoppelde hoofdzegel behoort na de wijziging tot een ander land of gebied.'
                );
            }
        }

        // Controleer de gekoppelde varianten.
        foreach ($stamp->variants as $variant) {
            $variantCountryId = $proposedCountryIds[$variant->id]
                ?? (int) $variant->country_id;

            if ($variantCountryId !== $countryId) {
                $conflicts[] = __(
                    'Een gekoppelde variant behoort na de wijziging tot een ander land of gebied.'
                );

                break;
            }
        }

        return $conflicts;
    }

    /**
     * Controleer of bestaande cataloguskoppelingen bij het voorgestelde land horen.
     *
     * @return array<int, string>
     */
    public function detectCatalogCountryConflicts(
        Stamp $stamp,
        int $proposedCountryId
    ): array {
        $conflicts = [];

        foreach ($stamp->catalogEntries as $catalogEntry) {
            $edition = $catalogEntry->catalogEdition;
            $catalog = $edition->catalog;

            if (! $catalog->countries->contains('id', $proposedCountryId)) {
                $conflicts[] = __(
                    'De cataloguseditie :edition van :catalog behoort niet tot het voorgestelde land of gebied.',
                    [
                        'edition' => $edition->edition_name,
                        'catalog' => $catalog->name,
                    ]
                );
            }
        }

        return $conflicts;
    }

    /**
     * Controleer bestaande cataloguswaarden tegen de voorgestelde
     * combinatie van land en uitgiftejaar.
     *
     * @param  array<string, array<int, int>>  $conditionCache
     * @return array<int, string>
     */
    public function detectCatalogConditionConflicts(
        Stamp $stamp,
        int $proposedCountryId,
        int $proposedIssueYear,
        CatalogConditionService $catalogConditionService,
        array &$conditionCache
    ): array {
        $conflicts = [];

        foreach ($stamp->catalogEntries as $catalogEntry) {
            $edition = $catalogEntry->catalogEdition;
            $catalog = $edition->catalog;

            $cacheKey = implode(':', [
                $catalog->id,
                $proposedCountryId,
                $proposedIssueYear,
            ]);

            if (! array_key_exists($cacheKey, $conditionCache)) {
                $allowedConditions = $catalogConditionService->availableConditions(
                    $catalog,
                    $proposedCountryId,
                    $proposedIssueYear
                );

                $conditionCache[$cacheKey] = array_map(
                    'intval',
                    $allowedConditions->modelKeys()
                );
            }

            $allowedConditionIds = $conditionCache[$cacheKey];

            foreach ($catalogEntry->values as $catalogValue) {
                if (! in_array(
                    (int) $catalogValue->condition_id,
                    $allowedConditionIds,
                    true
                )) {
                    $condition = $catalogValue->condition;

                    $conditionName = $condition->translationWithFallback()->name
                        ?? $condition->slug;

                    $conflicts[] = __(
                        'De waarderingscategorie :condition in cataloguseditie :edition van :catalog is niet toegestaan voor het voorgestelde land en uitgiftejaar.',
                        [
                            'condition' => $conditionName,
                            'edition' => $edition->edition_name,
                            'catalog' => $catalog->name,
                        ]
                    );
                }
            }
        }

        return $conflicts;
    }

    /**
     * Verzamel alle conflicten die voortkomen uit een landwijziging.
     *
     * @param  array<int, int>  $proposedCountryIds
     * @return array<int, string>
     */
    public function detectCountryConflicts(
        Stamp $stamp,
        array $proposedCountryIds
    ): array {
        $proposedCountryId = $proposedCountryIds[$stamp->id]
            ?? (int) $stamp->country_id;

        if ($proposedCountryId === (int) $stamp->country_id) {
            return [];
        }

        return array_merge(
            $this->detectParentCountryConflicts(
                $stamp,
                $proposedCountryIds
            ),
            $this->detectCatalogCountryConflicts(
                $stamp,
                $proposedCountryId
            )
        );
    }

    /**
     * Bepaal het voorgestelde uitgiftejaar van een postzegel.
     *
     * @param  array<string, mixed>  $operations
     */
    public function resolveIssueYear(Stamp $stamp, array $operations): int
    {
        if (($operations['issue_year_operation'] ?? 'unchanged') === 'set') {
            return (int) $operations['issue_year'];
        }

        return (int) $stamp->issue_year;
    }

    /**
     * Bepaal de voorgestelde uitgiftedatum van een postzegel.
     *
     * @param  array<string, mixed>  $operations
     */
    public function resolveIssueDate(Stamp $stamp, array $operations): ?string
    {
        $currentDate = $stamp->issue_date?->format('Y-m-d');

        return match ($operations['issue_date_operation'] ?? 'unchanged') {
            'set' => (string) $operations['issue_date'],
            'fill' => $currentDate ?? (string) $operations['issue_date'],
            'clear' => null,
            default => $currentDate,
        };
    }

    /**
     * Controleer of de voorgestelde uitgiftedatum overeenkomt
     * met het voorgestelde uitgiftejaar.
     *
     * @return array<int, string>
     */
    public function detectIssueDateConflicts(
        int $proposedIssueYear,
        ?string $proposedIssueDate
    ): array {
        if ($proposedIssueDate === null) {
            return [];
        }

        $dateYear = (int) Carbon::parse(
            $proposedIssueDate
        )->format('Y');

        if ($dateYear !== $proposedIssueYear) {
            return [
                __(
                    'De voorgestelde uitgiftedatum komt niet overeen met het voorgestelde uitgiftejaar.'
                ),
            ];
        }

        return [];
    }

    /**
     * Controleer datumconflicten uitsluitend wanneer het
     * uitgiftejaar of de uitgiftedatum daadwerkelijk verandert.
     *
     * @return array<int, string>
     */
    public function detectChangedIssueDateConflicts(
        Stamp $stamp,
        int $proposedIssueYear,
        ?string $proposedIssueDate
    ): array {
        $currentIssueYear = (int) $stamp->issue_year;
        $currentIssueDate = $stamp->issue_date?->format('Y-m-d');

        if (
            $currentIssueYear === $proposedIssueYear
            && $currentIssueDate === $proposedIssueDate
        ) {
            return [];
        }

        return $this->detectIssueDateConflicts(
            $proposedIssueYear,
            $proposedIssueDate
        );
    }

    /**
     * Controleer waarderingsconflicten uitsluitend wanneer
     * het land of het uitgiftejaar daadwerkelijk verandert.
     *
     * @param  array<string, array<int, int>>  $conditionCache
     * @return array<int, string>
     */
    public function detectValuationConflicts(
        Stamp $stamp,
        int $proposedCountryId,
        int $proposedIssueYear,
        CatalogConditionService $catalogConditionService,
        array &$conditionCache
    ): array {
        if (
            $proposedCountryId === (int) $stamp->country_id
            && $proposedIssueYear === (int) $stamp->issue_year
        ) {
            return [];
        }

        return $this->detectCatalogConditionConflicts(
            $stamp,
            $proposedCountryId,
            $proposedIssueYear,
            $catalogConditionService,
            $conditionCache
        );
    }

    /**
     * Bepaal de voorgestelde postzegelserie van een postzegel.
     *
     * @param  array<string, mixed>  $operations
     */
    public function resolveStampSeriesId(
        Stamp $stamp,
        array $operations
    ): ?int {
        $currentSeriesId = $stamp->stamp_series_id !== null
            ? (int) $stamp->stamp_series_id
            : null;

        return match ($operations['stamp_series_operation'] ?? 'unchanged') {
            'set' => (int) $operations['stamp_series_id'],
            'fill' => $currentSeriesId ?? (int) $operations['stamp_series_id'],
            'clear' => null,
            default => $currentSeriesId,
        };
    }

    /**
     * Controleer of de voorgestelde postzegelserie overeenkomt
     * met het voorgestelde land en uitgiftejaar.
     *
     * @return array<int, string>
     */
    public function detectStampSeriesConflicts(
        ?int $proposedSeriesId,
        ?StampSeries $proposedSeries,
        int $proposedCountryId,
        int $proposedIssueYear
    ): array {
        if ($proposedSeriesId === null) {
            return [];
        }

        if (
            $proposedSeries === null
            || (int) $proposedSeries->id !== $proposedSeriesId
        ) {
            return [
                __('De voorgestelde postzegelserie kon niet worden gevonden.'),
            ];
        }

        $conflicts = [];

        if ((int) $proposedSeries->country_id !== $proposedCountryId) {
            $conflicts[] = __(
                'De postzegelserie behoort niet tot het voorgestelde land of gebied.'
            );
        }

        if ((int) $proposedSeries->issue_year !== $proposedIssueYear) {
            $conflicts[] = __(
                'Het uitgiftejaar van de postzegelserie komt niet overeen met het voorgestelde uitgiftejaar.'
            );
        }

        return $conflicts;
    }

    /**
     * Controleer serieconflicten uitsluitend wanneer het land,
     * het uitgiftejaar of de seriekoppeling daadwerkelijk verandert.
     *
     * @return array<int, string>
     */
    public function detectChangedStampSeriesConflicts(
        Stamp $stamp,
        ?int $proposedSeriesId,
        ?StampSeries $proposedSeries,
        int $proposedCountryId,
        int $proposedIssueYear
    ): array {
        $currentSeriesId = $stamp->stamp_series_id !== null
            ? (int) $stamp->stamp_series_id
            : null;

        if (
            $currentSeriesId === $proposedSeriesId
            && (int) $stamp->country_id === $proposedCountryId
            && (int) $stamp->issue_year === $proposedIssueYear
        ) {
            return [];
        }

        return $this->detectStampSeriesConflicts(
            $proposedSeriesId,
            $proposedSeries,
            $proposedCountryId,
            $proposedIssueYear
        );
    }

    /**
     * Bepaal de voorgestelde themakoppelingen van een postzegel.
     *
     * @param  array<string, mixed>  $operations
     * @return array<int, int>
     */
    public function resolveThemeIds(Stamp $stamp, array $operations): array
    {
        $currentThemeIds = $stamp->themes
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $selectedThemeIds = array_map(
            'intval',
            $operations['theme_ids'] ?? []
        );

        $themeIds = match ($operations['theme_operation'] ?? 'unchanged') {
            'add' => array_merge($currentThemeIds, $selectedThemeIds),
            'remove' => array_diff($currentThemeIds, $selectedThemeIds),
            'replace' => $selectedThemeIds,
            'clear' => [],
            default => $currentThemeIds,
        };

        $themeIds = array_values(array_unique($themeIds));

        sort($themeIds, SORT_NUMERIC);

        return $themeIds;
    }

    /**
     * Bepaal de voorgestelde waarde van een optioneel tekstveld.
     *
     * @param  array<string, mixed>  $operations
     */
    public function resolveTextValue(
        ?string $currentValue,
        string $field,
        array $operations
    ): ?string {
        return match ($operations[$field.'_operation'] ?? 'unchanged') {
            'set' => (string) $operations[$field],
            'fill' => $currentValue === null || trim($currentValue) === ''
                ? (string) $operations[$field]
                : $currentValue,
            'clear' => null,
            default => $currentValue,
        };
    }

    /**
     * Bepaal alle voorgestelde algemene postzegelgegevens.
     *
     * @param  array<string, mixed>  $operations
     * @return array<string, int|string|null>
     */
    public function resolveStampAttributes(
        Stamp $stamp,
        array $operations
    ): array {
        return [
            'country_id' => $this->resolveCountryId($stamp, $operations),

            'issue_year' => $this->resolveIssueYear($stamp, $operations),

            'issue_date' => $this->resolveIssueDate($stamp, $operations),

            'stamp_series_id' => $this->resolveStampSeriesId($stamp, $operations),

            'face_value' => $this->resolveTextValue(
                $stamp->face_value,
                'face_value',
                $operations
            ),

            'currency' => $this->resolveTextValue(
                $stamp->currency,
                'currency',
                $operations
            ),

            'designer' => $this->resolveTextValue(
                $stamp->designer,
                'designer',
                $operations
            ),

            'printing_method' => $this->resolveTextValue(
                $stamp->printing_method,
                'printing_method',
                $operations
            ),

            'notes' => $this->resolveTextValue(
                $stamp->notes,
                'notes',
                $operations
            ),
        ];
    }

    /**
     * Bepaal welke algemene postzegelgegevens daadwerkelijk veranderen.
     *
     * @param  array<string, mixed>  $operations
     * @return array<string, array{old: int|string|null, new: int|string|null}>
     */
    public function resolveAttributeChanges(
        Stamp $stamp,
        array $operations
    ): array {
        $currentAttributes = $this->resolveStampAttributes($stamp, []);

        $proposedAttributes = $this->resolveStampAttributes(
            $stamp,
            $operations
        );

        $changes = [];

        foreach ($proposedAttributes as $field => $newValue) {
            $oldValue = $currentAttributes[$field];

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Bepaal welke themakoppelingen daadwerkelijk veranderen.
     *
     * @param  array<string, mixed>  $operations
     * @return array{
     *     old: array<int, int>,
     *     new: array<int, int>,
     *     added: array<int, int>,
     *     removed: array<int, int>
     * }|null
     */
    public function resolveThemeChanges(
        Stamp $stamp,
        array $operations
    ): ?array {
        $currentThemeIds = $this->resolveThemeIds($stamp, []);

        $proposedThemeIds = $this->resolveThemeIds(
            $stamp,
            $operations
        );

        if ($currentThemeIds === $proposedThemeIds) {
            return null;
        }

        return [
            'old' => $currentThemeIds,
            'new' => $proposedThemeIds,
            'added' => array_values(
                array_diff($proposedThemeIds, $currentThemeIds)
            ),
            'removed' => array_values(
                array_diff($currentThemeIds, $proposedThemeIds)
            ),
        ];
    }

    /**
     * Verzamel alle conflicten voor de voorgestelde postzegelgegevens.
     *
     * @param  array<int, int>  $proposedCountryIds
     * @param  array<string, array<int, int>>  $conditionCache
     * @return array<int, string>
     */
    public function detectConflicts(
        Stamp $stamp,
        array $proposedCountryIds,
        int $proposedIssueYear,
        ?string $proposedIssueDate,
        ?int $proposedSeriesId,
        ?StampSeries $proposedSeries,
        CatalogConditionService $catalogConditionService,
        array &$conditionCache
    ): array {
        $proposedCountryId = $proposedCountryIds[$stamp->id]
            ?? (int) $stamp->country_id;

        return array_merge(
            $this->detectCountryConflicts(
                $stamp,
                $proposedCountryIds
            ),

            $this->detectChangedIssueDateConflicts(
                $stamp,
                $proposedIssueYear,
                $proposedIssueDate
            ),

            $this->detectChangedStampSeriesConflicts(
                $stamp,
                $proposedSeriesId,
                $proposedSeries,
                $proposedCountryId,
                $proposedIssueYear
            ),

            $this->detectValuationConflicts(
                $stamp,
                $proposedCountryId,
                $proposedIssueYear,
                $catalogConditionService,
                $conditionCache
            )
        );
    }

    /**
     * Vergrendel de bestaande themakoppelingen van de geselecteerde postzegels.
     *
     * @param  array<int, int>  $stampIds
     */
    private function lockStampThemeRelations(array $stampIds): void
    {
        DB::table('stamp_themes')
            ->whereIntegerInRaw('stamp_id', $stampIds)
            ->orderBy('stamp_id')
            ->orderBy('theme_id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Vergrendel de catalogusvermeldingen en bijbehorende cataloguswaarden.
     *
     * @param  array<int, int>  $stampIds
     */
    private function lockStampCatalogRelations(array $stampIds): void
    {
        $entryIds = DB::table('stamp_catalog_entries')
            ->whereIntegerInRaw('stamp_id', $stampIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if ($entryIds === []) {
            return;
        }

        DB::table('stamp_catalog_values')
            ->whereIntegerInRaw('stamp_catalog_entry_id', $entryIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Vergrendel de catalogusconfiguratie die van invloed is
     * op de conflictdetectie.
     *
     * @param  array<int, int>  $catalogIds
     */
    private function lockCatalogConfiguration(array $catalogIds): void
    {
        if ($catalogIds === []) {
            return;
        }

        DB::table('catalogs')
            ->whereIntegerInRaw('id', $catalogIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        DB::table('catalog_countries')
            ->whereIntegerInRaw('catalog_id', $catalogIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        DB::table('catalog_condition_rules')
            ->whereIntegerInRaw('catalog_id', $catalogIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Vergrendel de gekoppelde catalogusedities en hun catalogusconfiguratie.
     *
     * @param  array<int, int>  $stampIds
     */
    private function lockCatalogEditionRelations(array $stampIds): void
    {
        $editionIds = DB::table('stamp_catalog_entries')
            ->whereIntegerInRaw('stamp_id', $stampIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('catalog_edition_id')
            ->unique()
            ->values()
            ->all();

        if ($editionIds === []) {
            return;
        }

        $catalogIds = DB::table('catalog_editions')
            ->whereIntegerInRaw('id', $editionIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('catalog_id')
            ->unique()
            ->values()
            ->all();

        $this->lockCatalogConfiguration($catalogIds);
    }

    /**
     * Vergrendel de gekoppelde hoofdzegels en varianten.
     *
     * @param  array<int, int>  $stampIds
     */
    private function lockParentAndVariantRelations(array $stampIds): void
    {
        $parentIds = DB::table('stamps')
            ->whereIntegerInRaw('id', $stampIds)
            ->whereNotNull('parent_stamp_id')
            ->lockForUpdate()
            ->pluck('parent_stamp_id')
            ->unique()
            ->values()
            ->all();

        if ($parentIds !== []) {
            DB::table('stamps')
                ->whereIntegerInRaw('id', $parentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }

        DB::table('stamps')
            ->whereIntegerInRaw('parent_stamp_id', $stampIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Vergrendel de huidige en voorgestelde postzegelseries.
     *
     * @param  array<int, int>  $stampIds
     * @param  array<string, mixed>  $operations
     */
    private function lockStampSeriesRelations(
        array $stampIds,
        array $operations
    ): void {
        $seriesIds = DB::table('stamps')
            ->whereIntegerInRaw('id', $stampIds)
            ->whereNotNull('stamp_series_id')
            ->lockForUpdate()
            ->pluck('stamp_series_id')
            ->all();

        if (isset($operations['stamp_series_id'])) {
            $seriesIds[] = (int) $operations['stamp_series_id'];
        }

        $seriesIds = array_values(array_unique($seriesIds));

        if ($seriesIds === []) {
            return;
        }

        DB::table('stamp_series')
            ->whereIntegerInRaw('id', $seriesIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Vergrendel de huidige en voorgestelde landen en thema's.
     *
     * @param  array<int, int>  $stampIds
     * @param  array<string, mixed>  $operations
     */
    private function lockCountryAndThemeRelations(
        array $stampIds,
        array $operations
    ): void {
        $countryIds = DB::table('stamps')
            ->whereIntegerInRaw('id', $stampIds)
            ->lockForUpdate()
            ->pluck('country_id')
            ->all();

        if (isset($operations['country_id'])) {
            $countryIds[] = (int) $operations['country_id'];
        }

        $countryIds = array_values(array_unique($countryIds));

        if ($countryIds !== []) {
            DB::table('countries')
                ->whereIntegerInRaw('id', $countryIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }

        $themeIds = DB::table('stamp_themes')
            ->whereIntegerInRaw('stamp_id', $stampIds)
            ->lockForUpdate()
            ->pluck('theme_id')
            ->all();

        $themeIds = array_values(array_unique(array_merge(
            $themeIds,
            array_map('intval', $operations['theme_ids'] ?? [])
        )));

        if ($themeIds !== []) {
            DB::table('themes')
                ->whereIntegerInRaw('id', $themeIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
        }
    }

    /**
     * Vergrendel de waarderingscategorieën die van invloed zijn
     * op de conflictdetectie.
     */
    private function lockConditions(): void
    {
        DB::table('conditions')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Vergrendel de gegevens die relevant zijn voor de bulkbewerking.
     *
     * @param  array<int, int>  $stampIds
     * @param  array<string, mixed>  $operations
     */
    private function lockPreviewRelations(
        array $stampIds,
        array $operations
    ): void {
        $this->lockParentAndVariantRelations($stampIds);

        $this->lockStampThemeRelations($stampIds);

        $this->lockStampCatalogRelations($stampIds);

        $this->lockCatalogEditionRelations($stampIds);

        $this->lockStampSeriesRelations($stampIds, $operations);

        $this->lockCountryAndThemeRelations($stampIds, $operations);

        $this->lockConditions();
    }

    /**
     * Haal de geselecteerde postzegels op met alle relaties
     * die nodig zijn voor de berekening van het wijzigingsoverzicht.
     *
     * @param  array<int, int>  $stampIds
     * @return Collection<int, Stamp>
     */
    public function loadSelectedStamps(
        array $stampIds,
        bool $lockForUpdate = false
    ): Collection {
        $query = Stamp::query()
            ->with([
                'translations',
                'country.translations',
                'series.translations',
                'themes.translations',
                'parentStamp',
                'variants',
                'catalogEntries.catalogEdition.catalog.countries',
                'catalogEntries.values.condition.translations',
            ])
            ->whereIntegerInRaw('id', $stampIds)
            ->orderByDesc('issue_year')
            ->orderBy('name')
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * Haal alle voorgestelde postzegelseries gezamenlijk op.
     *
     * @param  Collection<int, Stamp>  $stamps
     * @param  array<string, mixed>  $operations
     * @return Collection<int, StampSeries>
     */
    public function loadProposedStampSeries(
        Collection $stamps,
        array $operations
    ): Collection {
        $seriesIds = [];

        foreach ($stamps as $stamp) {
            $seriesId = $this->resolveStampSeriesId(
                $stamp,
                $operations
            );

            if ($seriesId !== null) {
                $seriesIds[] = $seriesId;
            }
        }

        $seriesIds = array_values(array_unique($seriesIds));

        if ($seriesIds === []) {
            return new Collection;
        }

        return StampSeries::query()
            ->with('translations')
            ->whereIntegerInRaw('id', $seriesIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * Stel het wijzigingsoverzicht voor één postzegel samen.
     *
     * @param  array<string, mixed>  $operations
     * @param  array<int, int>  $proposedCountryIds
     * @param  Collection<int, StampSeries>  $proposedSeries
     * @param  array<string, array<int, int>>  $conditionCache
     * @return array<string, mixed>
     */
    public function buildStampPreview(
        Stamp $stamp,
        array $operations,
        array $proposedCountryIds,
        Collection $proposedSeries,
        CatalogConditionService $catalogConditionService,
        array &$conditionCache
    ): array {
        $attributeChanges = $this->resolveAttributeChanges(
            $stamp,
            $operations
        );

        $themeChanges = $this->resolveThemeChanges(
            $stamp,
            $operations
        );

        $proposedIssueYear = $this->resolveIssueYear(
            $stamp,
            $operations
        );

        $proposedIssueDate = $this->resolveIssueDate(
            $stamp,
            $operations
        );

        $proposedSeriesId = $this->resolveStampSeriesId(
            $stamp,
            $operations
        );

        $series = $proposedSeriesId !== null
            ? $proposedSeries->get($proposedSeriesId)
            : null;

        $conflicts = $this->detectConflicts(
            $stamp,
            $proposedCountryIds,
            $proposedIssueYear,
            $proposedIssueDate,
            $proposedSeriesId,
            $series,
            $catalogConditionService,
            $conditionCache
        );

        $hasChanges = $attributeChanges !== []
            || $themeChanges !== null;

        $status = match (true) {
            $conflicts !== [] => 'conflict',
            $hasChanges => 'changed',
            default => 'unchanged',
        };

        return [
            'stamp' => $stamp,
            'attribute_changes' => $attributeChanges,
            'theme_changes' => $themeChanges,
            'conflicts' => $conflicts,
            'has_changes' => $hasChanges,
            'status' => $status,
        ];
    }

    /**
     * Bereken het wijzigingsoverzicht voor alle geselecteerde postzegels.
     *
     * @param  array<int, int>  $stampIds
     * @param  array<string, mixed>  $operations
     * @param  array<int, int>  $excludedIds
     * @return array<int, array<string, mixed>>
     */
    public function buildPreview(
        array $stampIds,
        array $operations,
        CatalogConditionService $catalogConditionService,
        array $excludedIds = [],
        bool $lockForUpdate = false
    ): array {
        if ($lockForUpdate) {
            DB::table('stamps')
                ->whereIntegerInRaw('id', $stampIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $this->lockPreviewRelations(
                $stampIds,
                $operations
            );
        }

        $stamps = $this->loadSelectedStamps(
            $stampIds,
            $lockForUpdate
        );

        $includedStamps = $stamps->reject(
            fn (Stamp $stamp): bool => in_array(
                (int) $stamp->id,
                $excludedIds,
                true
            )
        );

        $proposedCountryIds = $this->resolveCountryIds(
            $includedStamps,
            $operations
        );

        $proposedSeries = $this->loadProposedStampSeries(
            $includedStamps,
            $operations
        );

        $conditionCache = [];

        $preview = [];

        foreach ($stamps as $stamp) {
            if (in_array((int) $stamp->id, $excludedIds, true)) {
                $preview[] = [
                    'stamp' => $stamp,
                    'attribute_changes' => [],
                    'theme_changes' => null,
                    'conflicts' => [],
                    'has_changes' => false,
                    'status' => 'excluded',
                ];

                continue;
            }

            $preview[] = $this->buildStampPreview(
                $stamp,
                $operations,
                $proposedCountryIds,
                $proposedSeries,
                $catalogConditionService,
                $conditionCache
            );
        }

        return $preview;
    }

    /**
     * Bereken de aantallen per status in het wijzigingsoverzicht.
     *
     * @param  array<int, array<string, mixed>>  $preview
     * @return array<string, int>
     */
    public function summarizePreview(array $preview): array
    {
        $summary = [
            'total' => count($preview),
            'changed' => 0,
            'unchanged' => 0,
            'conflict' => 0,
            'excluded' => 0,
        ];

        foreach ($preview as $row) {
            $status = $row['status'];

            if (in_array(
                $status,
                ['changed', 'unchanged', 'conflict', 'excluded'],
                true
            )) {
                $summary[$status]++;
            }
        }

        return $summary;
    }

    /**
     * Bepaal of het wijzigingsoverzicht opgeslagen mag worden.
     *
     * @param  array<string, int>  $summary
     */
    public function canApplyPreview(array $summary): bool
    {
        return ($summary['changed'] ?? 0) > 0
            && ($summary['conflict'] ?? 0) === 0;
    }

    /**
     * Genereer een digitale vingerafdruk van het wijzigingsoverzicht.
     *
     * @param  array<int, array<string, mixed>>  $preview
     * @param  array<int, int>  $selectedIds
     * @param  array<string, mixed>  $operations
     * @param  array<int, int>  $excludedIds
     */
    public function generatePreviewHash(
        array $preview,
        array $selectedIds,
        array $operations,
        array $excludedIds
    ): string {
        $selectedIds = array_map('intval', $selectedIds);
        $excludedIds = array_map('intval', $excludedIds);

        sort($selectedIds, SORT_NUMERIC);
        sort($excludedIds, SORT_NUMERIC);

        if (isset($operations['theme_ids'])) {
            $operations['theme_ids'] = array_map(
                'intval',
                $operations['theme_ids']
            );

            sort($operations['theme_ids'], SORT_NUMERIC);
        }

        ksort($operations);

        $snapshot = [];

        foreach ($preview as $row) {
            $stamp = $row['stamp'];

            $snapshot[(int) $stamp->id] = [
                'current_attributes' => $this->resolveStampAttributes($stamp, []),
                'current_theme_ids' => $this->resolveThemeIds($stamp, []),
                'status' => $row['status'],
                'attribute_changes' => $row['attribute_changes'],
                'theme_changes' => $row['theme_changes'],
                'conflicts' => $row['conflicts'],
            ];
        }

        ksort($snapshot, SORT_NUMERIC);

        return hash(
            'sha256',
            json_encode([
                'selected_ids' => $selectedIds,
                'operations' => $operations,
                'excluded_ids' => $excludedIds,
                'preview' => $snapshot,
            ], JSON_THROW_ON_ERROR)
        );
    }

    /**
     * Verwerk de bevestigde wijzigingen en registreer de geschiedenis.
     *
     * @param  array<int, array<string, mixed>>  $preview
     * @param  array<string, mixed>  $operations
     */
    public function applyPreview(
        array $preview,
        array $operations,
        User $user,
        StampChangeHistoryService $historyService
    ): StampChangeBatch {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException(
                'Een bulkbewerking moet binnen een databasetransactie worden uitgevoerd.'
            );
        }

        if (! $this->canApplyPreview($this->summarizePreview($preview))) {
            throw new \LogicException(
                'Het wijzigingsoverzicht bevat conflicten of geen uitvoerbare wijzigingen.'
            );
        }

        $batch = $historyService->createBatch(
            $user,
            'bulk_edit',
            'Bulkbewerking algemene postzegelgegevens',
            $operations
        );

        foreach ($preview as $row) {
            if ($row['status'] !== 'changed') {
                continue;
            }

            $stamp = $row['stamp'];

            $oldValues = [];
            $newValues = [];
            $attributes = [];

            foreach ($row['attribute_changes'] as $field => $change) {
                $oldValues[$field] = $change['old'];
                $newValues[$field] = $change['new'];

                $attributes[$field] = $change['new'];
            }

            if ($attributes !== []) {
                if (! $stamp->update($attributes)) {
                    throw new \RuntimeException(
                        'De postzegelwijzigingen konden niet worden opgeslagen.'
                    );
                }
            }

            if ($row['theme_changes'] !== null) {
                $oldValues['theme_ids'] = $row['theme_changes']['old'];
                $newValues['theme_ids'] = $row['theme_changes']['new'];

                $stamp->themes()->sync(
                    $row['theme_changes']['new']
                );
            }

            $historyService->recordChange(
                $batch,
                $stamp,
                $oldValues,
                $newValues
            );
        }

        $historyService->finalizeBatch($batch);

        return $batch;
    }
}
