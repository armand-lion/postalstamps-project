<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AcquisitionMethod;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Currency;
use App\Models\GumCondition;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCopy;
use App\Models\Watermark;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StampCopyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        // Standaardcataloguseditie van de ingelogde gebruiker.
        /** @var CatalogEdition|null $defaultCatalogEdition */
        $defaultCatalogEdition = auth()->user()
            ->defaultCatalogEdition()
            ->with('catalog')
            ->first();

        // Beschikbare catalogi voor het filter.
        $catalogs = Catalog::query()
            ->orderBy('name')
            ->get();

        // Beschikbare landen voor het filter.
        $countries = Country::query()
            ->with('translations')
            ->orderBy('id')
            ->get();

        // Beschikbare condities voor het filter.
        $conditions = Condition::query()
            ->with('translations')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Beschikbare catalogusedities voor het filter.
        $catalogEditions = CatalogEdition::with('catalog')
            ->orderByDesc('edition_year')
            ->get();

        // Bepaal de actieve cataloguseditie voor het overzicht.
        $selectedCatalogId = request('catalog_id');
        $selectedCatalogEditionId = request('catalog_edition_id');

        if (
            ! request()->has('catalog_id') &&
            ! request()->has('catalog_edition_id')
        ) {
            // Zonder filters gebruiken we de standaardcataloguseditie.
            $activeCatalogEditionId = $defaultCatalogEdition?->id;
        } elseif (
            $selectedCatalogEditionId !== null &&
            $selectedCatalogEditionId !== ''
        ) {
            /** @var CatalogEdition|null $selectedEdition */
            // Een expliciet geselecteerde cataloguseditie heeft voorrang.
            $selectedEdition = $catalogEditions->firstWhere(
                'id',
                $selectedCatalogEditionId
            );

            // Controleer of de editie bij de gekozen catalogus hoort.
            $activeCatalogEditionId = $selectedEdition &&
                (
                    ! $selectedCatalogId ||
                    (string) $selectedEdition->catalog_id === (string) $selectedCatalogId
                )
                    ? $selectedEdition->id
                    : null;
        } else {
            // Er is geen specifieke cataloguseditie geselecteerd.
            $activeCatalogEditionId = null;
        }

        $stampCopies = StampCopy::with([
            'stamp.country',
            'stamp.translations',
            'stamp.images',
            'stamp.catalogEntries.catalogEdition.catalog',
            'condition.translations',
        ])
            ->when(
                request()->filled('country_id'),
                function ($query) {
                    $query->whereHas('stamp', function ($stampQuery) {
                        $stampQuery->where(
                            'country_id',
                            request('country_id')
                        );
                    });
                }
            )
            ->when(
                request()->filled('issue_year'),
                function ($query) {
                    $query->whereHas('stamp', function ($stampQuery) {
                        $stampQuery->where(
                            'issue_year',
                            request('issue_year')
                        );
                    });
                }
            )
            ->when(
                request()->filled('condition_id'),
                function ($query) {
                    $query->where(
                        'condition_id',
                        request('condition_id')
                    );
                }
            )
            ->when(
                request()->filled('search'),
                function ($query) use ($activeCatalogEditionId, $selectedCatalogId) {
                    $search = trim(request('search'));

                    $query->whereHas('stamp', function ($stampQuery) use ($search, $activeCatalogEditionId, $selectedCatalogId) {
                        $stampQuery->where(function ($searchQuery) use ($search, $activeCatalogEditionId, $selectedCatalogId) {

                            // Zoeken op oorspronkelijke postzegelnaam.
                            $searchQuery->where('name', 'like', "%{$search}%")

                                // Zoeken binnen alle beschikbare vertalingen.
                                ->orWhereHas('translations', function ($translationQuery) use ($search) {
                                    $translationQuery->where('name', 'like', "%{$search}%");
                                });

                            // Zoeken op catalogusnummer binnen de geselecteerde catalogus en editie.
                            $searchQuery->orWhereHas(
                                'catalogEntries',
                                function ($entryQuery) use ($search, $activeCatalogEditionId, $selectedCatalogId) {
                                    $entryQuery->where('catalog_number', 'like', "%{$search}%");

                                    // Alleen zoeken binnen de actieve cataloguseditie, indien geselecteerd.
                                    if ($activeCatalogEditionId !== null) {
                                        $entryQuery->where(
                                            'catalog_edition_id',
                                            $activeCatalogEditionId
                                        );
                                    }

                                    // Alleen zoeken binnen de geselecteerde catalogus, indien opgegeven.
                                    if ($selectedCatalogId !== null && $selectedCatalogId !== '') {
                                        $entryQuery->whereHas(
                                            'catalogEdition',
                                            function ($editionQuery) use ($selectedCatalogId) {
                                                $editionQuery->where('catalog_id', $selectedCatalogId);
                                            }
                                        );
                                    }
                                }
                            );
                        });
                    });
                }
            )
            ->when(
                $selectedCatalogId !== null && $selectedCatalogId !== '',
                function ($query) use ($selectedCatalogId) {
                    $query->whereHas(
                        'stamp.catalogEntries.catalogEdition',
                        function ($editionQuery) use ($selectedCatalogId) {
                            $editionQuery->where('catalog_id', $selectedCatalogId);
                        }
                    );
                }
            )
            ->when(
                request()->filled('catalog_edition_id') && $activeCatalogEditionId !== null,
                function ($query) use ($activeCatalogEditionId) {
                    $query->whereHas(
                        'stamp.catalogEntries',
                        function ($entryQuery) use ($activeCatalogEditionId) {
                            $entryQuery->where(
                                'catalog_edition_id',
                                $activeCatalogEditionId
                            );
                        }
                    );
                }
            )
            ->when(
                request('sort') === 'name_asc',
                fn ($query) => $query->orderBy(
                    Stamp::select('name')
                        ->whereColumn('stamps.id', 'stamp_copies.stamp_id')
                        ->limit(1),
                    'asc'
                )
            )
            ->when(
                request('sort') === 'name_desc',
                fn ($query) => $query->orderBy(
                    Stamp::select('name')
                        ->whereColumn('stamps.id', 'stamp_copies.stamp_id')
                        ->limit(1),
                    'desc'
                )
            )
            ->when(
                request('sort') === 'year_asc',
                fn ($query) => $query->orderBy(
                    Stamp::select('issue_year')
                        ->whereColumn('stamps.id', 'stamp_copies.stamp_id')
                        ->limit(1),
                    'asc'
                )
            )
            ->when(
                request('sort') === 'year_desc',
                fn ($query) => $query->orderBy(
                    Stamp::select('issue_year')
                        ->whereColumn('stamps.id', 'stamp_copies.stamp_id')
                        ->limit(1),
                    'desc'
                )
            )
            ->when(
                request('sort') === 'catalog_asc' && $activeCatalogEditionId !== null,
                function ($query) use ($activeCatalogEditionId) {
                    $catalogNumber = StampCatalogEntry::query()
                        ->select('catalog_number')
                        ->whereColumn('stamp_catalog_entries.stamp_id', 'stamp_copies.stamp_id')
                        ->where('catalog_edition_id', $activeCatalogEditionId)
                        ->limit(1);

                    $query
                        ->orderByRaw(
                            '(SELECT catalog_number FROM stamp_catalog_entries
                  WHERE stamp_catalog_entries.stamp_id = stamp_copies.stamp_id
                  AND catalog_edition_id = ?
                  LIMIT 1) IS NULL ASC',
                            [$activeCatalogEditionId]
                        )
                        ->orderByRaw(
                            'CAST((SELECT catalog_number FROM stamp_catalog_entries
                  WHERE stamp_catalog_entries.stamp_id = stamp_copies.stamp_id
                  AND catalog_edition_id = ?
                  LIMIT 1) AS UNSIGNED) ASC',
                            [$activeCatalogEditionId]
                        )
                        ->orderBy($catalogNumber, 'asc');
                }
            )
            ->when(
                request('sort') === 'catalog_desc' && $activeCatalogEditionId !== null,
                function ($query) use ($activeCatalogEditionId) {
                    $catalogNumber = StampCatalogEntry::query()
                        ->select('catalog_number')
                        ->whereColumn('stamp_catalog_entries.stamp_id', 'stamp_copies.stamp_id')
                        ->where('catalog_edition_id', $activeCatalogEditionId)
                        ->limit(1);

                    $query
                        ->orderByRaw(
                            '(SELECT catalog_number FROM stamp_catalog_entries
                  WHERE stamp_catalog_entries.stamp_id = stamp_copies.stamp_id
                  AND catalog_edition_id = ?
                  LIMIT 1) IS NULL ASC',
                            [$activeCatalogEditionId]
                        )
                        ->orderByRaw(
                            'CAST((SELECT catalog_number FROM stamp_catalog_entries
                  WHERE stamp_catalog_entries.stamp_id = stamp_copies.stamp_id
                  AND catalog_edition_id = ?
                  LIMIT 1) AS UNSIGNED) DESC',
                            [$activeCatalogEditionId]
                        )
                        ->orderBy($catalogNumber, 'desc');
                }
            )
            ->when(
                ! in_array(request('sort'), [
                    'name_asc',
                    'name_desc',
                    'year_asc',
                    'year_desc',
                    'catalog_asc',
                    'catalog_desc',
                ], true),
                fn ($query) => $query->orderBy('created_at', 'desc')
            )
            ->orderBy('stamp_copies.id', 'desc')
            ->paginate(20)
            ->withQueryString();

        return view('backend.stamp-copies.index', compact(
            'stampCopies',
            'defaultCatalogEdition',
            'catalogs',
            'countries',
            'conditions',
            'catalogEditions',
            'activeCatalogEditionId'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // Laad landen voor het filter.
        $countries = Country::query()
            ->with('translations')
            ->orderBy('name')
            ->get();

        // Laad beschikbare uitgiftejaren.
        $issueYears = Stamp::query()
            ->whereNotNull('issue_year')
            ->distinct()
            ->orderByDesc('issue_year')
            ->pluck('issue_year');

        // Laad catalogi voor het filter.
        $catalogs = Catalog::query()
            ->orderBy('name')
            ->get();

        // Laad catalogusedities inclusief bijbehorende catalogus.
        $catalogEditions = CatalogEdition::query()
            ->with('catalog')
            ->orderByDesc('edition_year')
            ->orderBy('edition_name')
            ->get();

        // Laad postzegels inclusief hun cataloguskoppelingen.
        $stamps = Stamp::query()
            ->with('catalogEntries')
            ->orderBy('issue_year', 'desc')
            ->orderBy('name')
            ->get();

        // Laad actieve condities.
        $conditions = Condition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('backend.stamp-copies.create', compact(
            'countries',
            'issueYears',
            'catalogs',
            'catalogEditions',
            'stamps',
            'conditions'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'stamp_id' => ['required', 'integer', 'exists:stamps,id'],
            'condition_id' => ['required', 'integer', 'exists:conditions,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        StampCopy::create($validated);

        return redirect()
            ->route('backend.stamp-copies.index')
            ->with('success', __('Postzegel succesvol toegevoegd aan je verzameling.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(StampCopy $stampCopy): View
    {
        $stampCopy->load([
            'stamp.country',
            'stamp.translations',
            'stamp.images',
            'condition.translations',
            'images',
            'valuations',
        ]);

        return view('backend.stamp-copies.show', compact('stampCopy'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StampCopy $stampCopy): View
    {
        $stampCopy->load([
            'stamp.country',
            'stamp.translations',
            'condition.translations',
            'images',
        ]);

        $conditions = Condition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $gumConditions = GumCondition::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $watermarks = Watermark::query()
            ->where('country_id', $stampCopy->stamp->country_id)
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $acquisitionMethods = AcquisitionMethod::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $currencies = Currency::query()
            ->where('is_active', true)
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        return view('backend.stamp-copies.edit', compact(
            'stampCopy',
            'conditions',
            'gumConditions',
            'watermarks',
            'acquisitionMethods',
            'currencies'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StampCopy $stampCopy): RedirectResponse
    {
        $stampCopy->load('stamp');

        $validated = $request->validate([
            'condition_id' => ['required', 'integer', 'exists:conditions,id'],

            'quantity' => ['required', 'integer', 'min:1'],

            'acquisition_date' => ['nullable', 'date'],

            // Aankoopprijs en valuta
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],

            'purchase_currency' => ['nullable', 'string', 'size:3'],

            'acquisition_method' => [
                'nullable',
                'string',
                Rule::exists('acquisition_methods', 'slug')
                    ->where('is_active', true),
            ],

            'gum_condition' => [
                'nullable',
                'string',
                Rule::exists('gum_conditions', 'slug')
                    ->where('is_active', true),
            ],

            'watermark' => [
                'nullable',
                'string',
                Rule::exists('watermarks', 'slug')
                    ->where('country_id', $stampCopy->stamp->country_id)
                    ->where('is_active', true),
            ],

            // Tanding
            'perforation' => ['nullable', 'string', 'max:255'],

            'is_variant' => ['sometimes', 'boolean'],

            'translations' => ['nullable', 'array'],

            'translations.nl.variant_description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'translations.en.variant_description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'translations.de.variant_description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'translations.fr.variant_description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'translations.es.variant_description' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        // Bij een uitgevinkte checkbox wordt geen waarde meegestuurd.
        $validated['is_variant'] = $request->boolean('is_variant');

        // Haal de vertalingen uit de gevalideerde gegevens.
        // Deze worden apart opgeslagen in stamp_copy_translations.
        $translations = $validated['translations'] ?? [];

        unset($validated['translations']);

        // De bestaande variantbeschrijving blijft voorlopig behouden.
        // De meertalige beschrijvingen worden apart beheerd.
        unset($validated['variant_description']);

        // Sla de gewone gegevens van het postzegelexemplaar op.
        $stampCopy->update($validated);

        // Sla de vertalingen alleen op als het een bijzondere variant is.
        if ($validated['is_variant']) {
            foreach (['nl', 'en', 'de', 'fr', 'es'] as $locale) {
                $description = trim(
                    $translations[$locale]['variant_description'] ?? ''
                );

                $stampCopy->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['variant_description' => $description !== '' ? $description : null]
                );
            }
        }

        return redirect()
            ->route('backend.stamp-copies.index')
            ->with('success', __('Postzegel succesvol bijgewerkt in je verzameling.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StampCopy $stampCopy): RedirectResponse
    {
        $stampCopy->delete();

        return redirect()
            ->route('backend.stamp-copies.index')
            ->with('success', __('Postzegel succesvol uit je verzameling verwijderd.'));
    }
}
