<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Backend\StampBulkApplyRequest;
use App\Http\Requests\Backend\StampBulkEditRequest;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampSeries;
use App\Models\Theme;
use App\Services\CatalogConditionService;
use App\Services\StampBulkEditService;
use App\Services\StampBulkSelectionService;
use App\Services\StampChangeHistoryService;
use App\Services\StampFilterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class StampBulkEditController extends Controller
{
    /**
     * Toon de bulkbeheerpagina voor postzegels.
     */
    public function index(
        Request $request,
        StampFilterService $stampFilterService,
        StampBulkSelectionService $stampBulkSelectionService
    ): View {
        $query = Stamp::with([
            'country.translations',
            'series.translations',
            'translations',
            'themes.translations',
            'images',
            'catalogEntries.catalogEdition.catalog',
        ]);

        // Pas de algemene postzegelfilters toe.
        $query = $stampFilterService->applyFilters($query, $request);

        // Haal de gefilterde postzegels op.
        $stamps = $query
            ->orderByDesc('issue_year')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        // Beschikbare landen, catalogi en catalogusedities.
        $countries = Country::orderBy('name')->get();

        $catalogs = Catalog::orderBy('name')->get();

        $catalogEditions = CatalogEdition::with('catalog')
            ->orderByDesc('edition_year')
            ->orderBy('edition_name')
            ->get();

        // Beschikbare postzegelseries.
        $stampSeries = StampSeries::with('translations')
            ->orderBy('name')
            ->get();

        // Beschikbare thema's.
        $themes = Theme::with('translations')
            ->orderBy('name')
            ->get();

        // Beschikbare druktechnieken.
        $printingMethods = Stamp::query()
            ->whereNotNull('printing_method')
            ->whereRaw('TRIM(printing_method) <> ?', [''])
            ->distinct()
            ->orderBy('printing_method')
            ->pluck('printing_method');

        // Haal de huidige gebruikersgebonden selectie op.
        $userId = (int) $request->user()->id;

        $selectedIds = $stampBulkSelectionService->getSelectedIds($userId);
        $selectedCount = count($selectedIds);

        // Tel de geselecteerde postzegels binnen de huidige filters.
        $selectedWithinFiltersCount = $stampFilterService
            ->applyFilters(Stamp::query(), $request)
            ->whereIntegerInRaw('id', $selectedIds)
            ->count();

        // Bereken het aantal geselecteerde postzegels buiten de filters.
        $selectedOutsideFiltersCount = $selectedCount - $selectedWithinFiltersCount;

        return view('backend.stamps.bulk-edit.index', compact(
            'stamps',
            'countries',
            'catalogs',
            'catalogEditions',
            'stampSeries',
            'themes',
            'printingMethods',
            'selectedIds',
            'selectedCount',
            'selectedWithinFiltersCount',
            'selectedOutsideFiltersCount'
        ));
    }

    /**
     * Toon alle geselecteerde postzegels.
     */
    public function selected(
        Request $request,
        StampBulkSelectionService $stampBulkSelectionService
    ): View {
        $userId = (int) $request->user()->id;

        $selectedIds = $stampBulkSelectionService->getSelectedIds($userId);

        $stamps = Stamp::with([
            'country.translations',
            'series.translations',
            'translations',
            'themes.translations',
            'images',
            'catalogEntries.catalogEdition.catalog',
        ])
            ->whereIntegerInRaw('id', $selectedIds)
            ->orderByDesc('issue_year')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $selectedCount = $stamps->total();

        return view('backend.stamps.bulk-edit.selected', compact(
            'stamps',
            'selectedIds',
            'selectedCount'
        ));
    }

    /**
     * Toon het bulkbewerkingsformulier voor de geselecteerde postzegels.
     */
    public function edit(
        Request $request,
        StampBulkSelectionService $stampBulkSelectionService
    ): View|RedirectResponse {
        $userId = (int) $request->user()->id;

        $selectedIds = $stampBulkSelectionService->getSelectedIds($userId);
        $selectedCount = count($selectedIds);

        // Voorkom bulkbewerking zonder geselecteerde postzegels.
        if ($selectedCount === 0) {
            return redirect()
                ->route('backend.stamps.bulk-edit.index');
        }

        $savedOperations = $request->session()->get(
            "stamp_bulk_operations.{$userId}",
            []
        );

        if (
            $savedOperations !== []
            && ! $request->session()->hasOldInput()
        ) {
            $request->session()->flashInput($savedOperations);
        }

        // Beschikbare bewerkingen voor algemene postzegelgegevens.
        $operationOptions = [
            'unchanged' => __('Ongewijzigd'),
            'set' => __('Instellen'),
            'fill' => __('Lege velden aanvullen'),
            'clear' => __('Wissen'),
        ];

        // Beschikbare bewerkingen voor verplichte velden.
        $requiredOperationOptions = [
            'unchanged' => __('Ongewijzigd'),
            'set' => __('Instellen'),
        ];

        // Beschikbare bewerkingen voor thema's.
        $themeOperationOptions = [
            'unchanged' => __('Ongewijzigd'),
            'add' => __('Toevoegen'),
            'remove' => __('Verwijderen'),
            'replace' => __('Vervangen'),
            'clear' => __('Alles wissen'),
        ];

        // Beschikbare landen en gebieden voor bulkbewerking.
        $countries = Country::with('translations')
            ->orderBy('name')
            ->get();

        // Beschikbare thema's voor bulkbewerking.
        $themes = Theme::with('translations')
            ->orderBy('name')
            ->get();

        // Bepaal het gemeenschappelijke land en uitgiftejaar van de selectie.
        $selectedStamps = Stamp::query()
            ->whereIntegerInRaw('id', $selectedIds)
            ->get(['country_id', 'issue_year']);

        $selectedCountryIds = $selectedStamps->pluck('country_id')->unique();
        $selectedIssueYears = $selectedStamps->pluck('issue_year')->unique();

        $commonCountryId = $selectedCountryIds->count() === 1
            ? $selectedCountryIds->first()
            : null;

        $commonIssueYear = $selectedIssueYears->count() === 1
            ? $selectedIssueYears->first()
            : null;

        return view('backend.stamps.bulk-edit.edit', compact(
            'selectedIds',
            'selectedCount',
            'operationOptions',
            'requiredOperationOptions',
            'countries',
            'commonCountryId',
            'commonIssueYear',
            'themeOperationOptions',
            'themes',
        ));
    }

    /**
     * Valideer en bewaar de bewerkingsinstellingen.
     */
    public function preview(
        StampBulkEditRequest $request,
        StampBulkSelectionService $stampBulkSelectionService
    ): RedirectResponse {
        $userId = (int) $request->user()->id;

        $selectedIds = $stampBulkSelectionService->getSelectedIds(
            $userId
        );

        if ($selectedIds === []) {
            return redirect()
                ->route('backend.stamps.bulk-edit.index');
        }

        $operations = $request->validated();

        $request->session()->put(
            "stamp_bulk_operations.{$userId}",
            $operations
        );

        $request->session()->forget(
            "stamp_bulk_excluded.{$userId}"
        );

        return redirect()
            ->route('backend.stamps.bulk-edit.preview.show');
    }

    /**
     * Bereken en toon het opgeslagen wijzigingsoverzicht.
     */
    public function showPreview(
        Request $request,
        StampBulkSelectionService $stampBulkSelectionService,
        StampBulkEditService $stampBulkEditService,
        CatalogConditionService $catalogConditionService
    ): View|RedirectResponse {
        $userId = (int) $request->user()->id;

        $selectedIds = $stampBulkSelectionService->getSelectedIds(
            $userId
        );

        if ($selectedIds === []) {
            return redirect()
                ->route('backend.stamps.bulk-edit.index');
        }

        $operations = $request->session()->get(
            "stamp_bulk_operations.{$userId}",
            []
        );

        if ($operations === []) {
            return redirect()
                ->route('backend.stamps.bulk-edit.edit');
        }

        $excludedIds = $request->session()->get(
            "stamp_bulk_excluded.{$userId}",
            []
        );

        $excludedIds = array_values(array_intersect(
            $selectedIds,
            $excludedIds
        ));

        $preview = $stampBulkEditService->buildPreview(
            $selectedIds,
            $operations,
            $catalogConditionService,
            $excludedIds
        );
        $summary = $stampBulkEditService->summarizePreview(
            $preview
        );

        $canApply = $stampBulkEditService->canApplyPreview(
            $summary
        );

        $previewHash = $stampBulkEditService->generatePreviewHash(
            $preview,
            $selectedIds,
            $operations,
            $excludedIds
        );

        $statusOptions = [
            'all' => __('Alle postzegels'),
            'changed' => __('Gewijzigd'),
            'unchanged' => __('Ongewijzigd'),
            'conflict' => __('Met conflicten'),
            'excluded' => __('Uitgesloten'),
        ];

        $selectedStatus = $request->query('status', 'all');

        if (
            ! is_string($selectedStatus)
            || ! array_key_exists($selectedStatus, $statusOptions)
        ) {
            $selectedStatus = 'all';
        }

        $filteredPreview = array_values(array_filter(
            $preview,
            fn (array $row): bool => $selectedStatus === 'all'
                || $row['status'] === $selectedStatus
        ));

        $perPage = 20;
        $currentPage = max(1, $request->integer('page', 1));

        $filteredPreview = new LengthAwarePaginator(
            array_slice(
                $filteredPreview,
                ($currentPage - 1) * $perPage,
                $perPage
            ),
            count($filteredPreview),
            $perPage,
            $currentPage,
            [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ]
        );

        $countryIds = [];
        $seriesIds = [];

        foreach ($preview as $row) {
            foreach ($row['attribute_changes'] as $field => $change) {
                foreach (['old', 'new'] as $key) {
                    $value = $change[$key];

                    if ($value === null) {
                        continue;
                    }

                    if ($field === 'country_id') {
                        $countryIds[] = (int) $value;
                    }

                    if ($field === 'stamp_series_id') {
                        $seriesIds[] = (int) $value;
                    }
                }
            }
        }

        $countries = Country::query()
            ->with('translations')
            ->whereIntegerInRaw('id', array_unique($countryIds))
            ->get()
            ->keyBy('id');

        $series = StampSeries::query()
            ->with('translations')
            ->whereIntegerInRaw('id', array_unique($seriesIds))
            ->get()
            ->keyBy('id');

        $themeIds = [];

        foreach ($preview as $row) {
            if ($row['theme_changes'] === null) {
                continue;
            }

            $themeIds = array_merge(
                $themeIds,
                $row['theme_changes']['old'],
                $row['theme_changes']['new']
            );
        }

        $themes = Theme::query()
            ->with('translations')
            ->whereIntegerInRaw('id', array_unique($themeIds))
            ->get()
            ->keyBy('id');

        return view('backend.stamps.bulk-edit.preview', compact(
            'selectedIds',
            'operations',
            'preview',
            'summary',
            'canApply',
            'countries',
            'series',
            'themes',
            'statusOptions',
            'selectedStatus',
            'filteredPreview',
            'excludedIds',
            'previewHash',
        ));
    }

    public function apply(
        StampBulkApplyRequest $request,
        StampBulkSelectionService $stampBulkSelectionService,
        StampBulkEditService $stampBulkEditService,
        CatalogConditionService $catalogConditionService,
        StampChangeHistoryService $stampChangeHistoryService
    ): RedirectResponse {
        $userId = (int) $request->user()->id;

        $selectedIds = $stampBulkSelectionService->getSelectedIds(
            $userId
        );

        if ($selectedIds === []) {
            return redirect()
                ->route('backend.stamps.bulk-edit.index');
        }

        $operations = $request->session()->get(
            "stamp_bulk_operations.{$userId}",
            []
        );

        if ($operations === []) {
            return redirect()
                ->route('backend.stamps.bulk-edit.edit');
        }

        $excludedIds = $request->session()->get(
            "stamp_bulk_excluded.{$userId}",
            []
        );

        $excludedIds = array_values(array_intersect(
            $selectedIds,
            $excludedIds
        ));

        $result = DB::transaction(function () use (
            $request,
            $selectedIds,
            $operations,
            $excludedIds,
            $stampBulkEditService,
            $catalogConditionService,
            $stampChangeHistoryService
        ) {
            $preview = $stampBulkEditService->buildPreview(
                $selectedIds,
                $operations,
                $catalogConditionService,
                $excludedIds,
                lockForUpdate: true
            );

            $validator = Validator::make(
                $operations,
                StampBulkEditRequest::operationRules()
            );

            if ($validator->fails()) {
                return redirect()
                    ->route('backend.stamps.bulk-edit.edit')
                    ->withErrors($validator)
                    ->withInput($operations)
                    ->with('error', __(
                        'De voorgestelde bulkbewerkingen zijn niet meer geldig. Controleer de instellingen opnieuw.'
                    ));
            }

            $currentPreviewHash = $stampBulkEditService->generatePreviewHash(
                $preview,
                $selectedIds,
                $operations,
                $excludedIds
            );

            if (! hash_equals(
                $currentPreviewHash,
                $request->validated('preview_hash')
            )) {
                return redirect()
                    ->route('backend.stamps.bulk-edit.preview.show')
                    ->with('error', __(
                        'Het wijzigingsoverzicht is gewijzigd. Controleer de wijzigingen opnieuw.'
                    ));
            }

            $summary = $stampBulkEditService->summarizePreview(
                $preview
            );

            if (! $stampBulkEditService->canApplyPreview($summary)) {
                return redirect()
                    ->route('backend.stamps.bulk-edit.preview.show')
                    ->with('error', __(
                        'De bulkbewerking bevat conflicten of geen uitvoerbare wijzigingen.'
                    ));
            }

            return $stampBulkEditService->applyPreview(
                $preview,
                $operations,
                $request->user(),
                $stampChangeHistoryService
            );
        });

        if ($result instanceof RedirectResponse) {
            return $result;
        }

        $stampBulkSelectionService->clear($userId);

        $request->session()->forget([
            "stamp_bulk_operations.{$userId}",
            "stamp_bulk_excluded.{$userId}",
        ]);

        return redirect()
            ->route('backend.stamps.bulk-edit.index')
            ->with('success', __(
                ':count postzegels zijn succesvol gewijzigd.',
                ['count' => $result->stamp_count]
            ));
    }

    /**
     * Sluit een postzegel uit of neem deze opnieuw op
     * in het wijzigingsoverzicht.
     */
    public function updateExclusion(
        Request $request,
        StampBulkSelectionService $stampBulkSelectionService
    ): RedirectResponse {
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'stamp_id' => ['required', 'integer', 'exists:stamps,id'],
            'action' => ['required', 'in:exclude,include'],
        ]);

        $selectedIds = $stampBulkSelectionService->getSelectedIds(
            $userId
        );

        if ($selectedIds === []) {
            return redirect()
                ->route('backend.stamps.bulk-edit.index');
        }

        if (! $request->session()->has("stamp_bulk_operations.{$userId}")) {
            return redirect()
                ->route('backend.stamps.bulk-edit.edit');
        }

        $stampId = (int) $validated['stamp_id'];

        if (! in_array($stampId, $selectedIds, true)) {
            abort(422);
        }

        $sessionKey = "stamp_bulk_excluded.{$userId}";

        $excludedIds = $request->session()->get(
            $sessionKey,
            []
        );

        $excludedIds = match ($validated['action']) {
            'exclude' => array_merge($excludedIds, [$stampId]),
            'include' => array_diff($excludedIds, [$stampId]),
            default => abort(422),
        };

        $excludedIds = array_values(array_unique(
            array_intersect($selectedIds, $excludedIds)
        ));

        $request->session()->put(
            $sessionKey,
            $excludedIds
        );

        return redirect()
            ->route('backend.stamps.bulk-edit.preview.show');
    }

    /**
     * Voeg postzegels toe aan de selectie of verwijder ze.
     */
    public function updateSelection(
        Request $request,
        StampBulkSelectionService $stampBulkSelectionService
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => ['required', 'in:add,remove'],
            'stamp_ids' => ['required', 'array', 'min:1'],
            'stamp_ids.*' => ['required', 'integer', 'distinct', 'exists:stamps,id'],
        ]);

        $userId = (int) $request->user()->id;

        $stampIds = array_map(
            'intval',
            $validated['stamp_ids']
        );

        if ($validated['action'] === 'add') {
            $stampBulkSelectionService->add($userId, $stampIds);
        } else {
            $stampBulkSelectionService->remove($userId, $stampIds);
        }

        return back();
    }

    /**
     * Wis de volledige gebruikersgebonden selectie.
     */
    public function clearSelection(
        Request $request,
        StampBulkSelectionService $stampBulkSelectionService
    ): RedirectResponse {
        $userId = (int) $request->user()->id;

        $stampBulkSelectionService->clear($userId);

        return back();
    }

    /**
     * Selecteer alle postzegels binnen de huidige filters.
     */
    public function selectAllFiltered(
        Request $request,
        StampFilterService $stampFilterService,
        StampBulkSelectionService $stampBulkSelectionService
    ): RedirectResponse {
        $userId = (int) $request->user()->id;

        $query = $stampFilterService->applyFilters(
            Stamp::query(),
            $request
        );

        $stampIds = $query
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $stampBulkSelectionService->add($userId, $stampIds);

        return back();
    }
}
