<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampSeries;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StampController extends Controller
{
    private const LOCALES = [
        'nl',
        'en',
        'de',
        'fr',
        'es',
    ];

    /**
     * Validation rules for stamp translations.
     *
     * @return array<string, array<int, string>>
     */
    private function translationValidationRules(): array
    {
        $rules = [
            'translations' => ['required', 'array'],
        ];

        foreach (self::LOCALES as $locale) {
            $rules["translations.{$locale}"] = [
                'sometimes',
                'array',
            ];

            $rules["translations.{$locale}.name"] = [
                $locale === app()->getLocale() ? 'required' : 'nullable',
                'string',
                'max:255',
            ];

            $rules["translations.{$locale}.description"] = [
                'nullable',
                'string',
            ];
        }

        return $rules;
    }

    /**
     * Display a listing of the stamps.
     */
    public function index(Request $request): View
    {
        $query = Stamp::with([
            'country',
            'country.translations',
            'series',
            'translations',
            'catalogEntries.catalogEdition.catalog',
            'images',
            'catalogEntries.values.condition.translations',
        ]);

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

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->integer('country_id'));
        }

        if ($request->filled('issue_year')) {
            $query->where('issue_year', $request->integer('issue_year'));
        }

        // Filter postzegels op de geselecteerde catalogus.
        if ($request->filled('catalog_id')) {
            $catalogId = $request->integer('catalog_id');

            $query->whereHas('catalogEntries.catalogEdition', function ($editionQuery) use ($catalogId) {
                $editionQuery->where('catalog_id', $catalogId);
            });
        }

        // Filter postzegels op de geselecteerde cataloguseditie.
        if ($request->filled('catalog_edition_id')) {
            $catalogEditionId = $request->integer('catalog_edition_id');

            $query->whereHas('catalogEntries', function ($entryQuery) use ($catalogEditionId) {
                $entryQuery->where('catalog_edition_id', $catalogEditionId);
            });
        }

        $sort = $request->input('sort', 'year_desc');

        switch ($sort) {
            case 'catalog_number_asc':
                if (! $request->filled('catalog_edition_id')) {
                    $query->orderByDesc('issue_year')->orderBy('name');
                    break;
                }
                $query->orderByRaw("
                    (
                        SELECT CASE
                            WHEN sce.catalog_number REGEXP '^[0-9]' THEN 0
                            ELSE 1
                        END
                        FROM stamp_catalog_entries AS sce
                        WHERE sce.stamp_id = stamps.id
                          AND sce.catalog_edition_id = ?
                        LIMIT 1
                    ) ASC,
                    (
                        SELECT CAST(
                            NULLIF(
                                REGEXP_SUBSTR(sce.catalog_number, '^[0-9]+'),
                                ''
                            ) AS UNSIGNED
                        )
                        FROM stamp_catalog_entries AS sce
                        WHERE sce.stamp_id = stamps.id
                          AND sce.catalog_edition_id = ?
                        LIMIT 1
                    ) ASC,
                    (
                        SELECT sce.catalog_number
                        FROM stamp_catalog_entries AS sce
                        WHERE sce.stamp_id = stamps.id
                          AND sce.catalog_edition_id = ?
                        LIMIT 1
                    ) ASC,
                    stamps.name ASC
                ", [
                    $request->integer('catalog_edition_id'),
                    $request->integer('catalog_edition_id'),
                    $request->integer('catalog_edition_id'),
                ]);
                break;
            case 'year_asc':
                $query->orderBy('issue_year')->orderBy('name');
                break;

            case 'name_asc':
                $query->orderBy('name')->orderByDesc('issue_year');
                break;

            case 'name_desc':
                $query->orderByDesc('name')->orderByDesc('issue_year');
                break;

            default:
                $query->orderByDesc('issue_year')->orderBy('name');
                break;
        }

        $stamps = $query
            ->paginate(20)
            ->withQueryString();

        $countries = Country::orderBy('name')->get();

        // Beschikbare catalogi en catalogusedities voor de filters.
        $catalogs = Catalog::orderBy('name')->get();

        $catalogEditions = CatalogEdition::with('catalog')
            ->orderByDesc('edition_year')
            ->orderBy('edition_name')
            ->get();

        return view('backend.stamps.index', compact(
            'stamps',
            'countries',
            'catalogs',
            'catalogEditions'
        ));
    }

    /**
     * Show the form for creating a stamp.
     */
    public function create(): View
    {
        $countries = Country::orderBy('name')->get();

        $series = StampSeries::with('country')
            ->orderBy('name')
            ->get();

        $catalogEditions = CatalogEdition::with('catalog')
            ->orderByDesc('edition_year')
            ->orderBy('edition_name')
            ->get();

        // Laad beschikbare hoofdpostzegels voor het koppelen van varianten.
        $parentStamps = Stamp::with('translations')
            ->whereNull('parent_stamp_id')
            ->orderBy('name')
            ->get();

        // Laad beschikbare thema's inclusief vertalingen.
        $themes = Theme::with('translations')
            ->orderBy('name')
            ->get();

        return view('backend.stamps.create', [
            'countries' => $countries,
            'series' => $series,
            'locales' => self::LOCALES,
            'catalogEditions' => $catalogEditions,
            'parentStamps' => $parentStamps,
            'themes' => $themes,
        ]);
    }

    /**
     * Store a newly created stamp.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'country_id' => [
                'required',
                'integer',
                'exists:countries,id',
            ],
            'parent_stamp_id' => [
                'nullable',
                'integer',
                Rule::exists('stamps', 'id')
                    ->where('country_id', $request->input('country_id'))
                    ->whereNull('parent_stamp_id'),
            ],
            'stamp_series_id' => [
                'nullable',
                'integer',
                Rule::exists('stamp_series', 'id')
                    ->where('country_id', $request->input('country_id')),
            ],
            'theme_ids' => [
                'sometimes',
                'array',
            ],
            'theme_ids.*' => [
                'integer',
                'distinct',
                'exists:themes,id',
            ],
            'issue_year' => [
                'required',
                'integer',
                'min:1840',
                'max:2200',
            ],
            'issue_date' => [
                'nullable',
                'date',
            ],
            'face_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
            'designer' => [
                'nullable',
                'string',
                'max:255',
            ],
            'printing_method' => [
                'nullable',
                'string',
                'max:255',
            ],
            'catalog_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'catalog_edition_id' => [
                'nullable',
                'required_with:catalog_number',
                'integer',
                'exists:catalog_editions,id',
            ],
            'catalog_number' => [
                'nullable',
                'required_with:catalog_edition_id',
                'string',
                'max:100',
            ],
            'catalog_values' => [
                'nullable',
                'array:postfris,gestempeld',
            ],
            'catalog_values.postfris' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
            'catalog_values.gestempeld' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'image_type' => [
                'required_with:image',
                'nullable',
                'in:front,back,detail',
            ],
            'alt_text' => [
                'nullable',
                'string',
                'max:255',
            ],
        ], $this->translationValidationRules()), [
            'catalog_number.required_with' => 'Het catalogusnummer is verplicht wanneer je een cataloguseditie selecteert.',
            'catalog_edition_id.required_with' => 'Selecteer een cataloguseditie wanneer je een catalogusnummer invult.',
        ]);

        // Haal de vertalingen uit de gevalideerde gegevens.
        $translations = $validated['translations'];

        unset($validated['translations']);

        // Haal de geselecteerde thema's apart uit de gevalideerde gegevens.
        $themeIds = $validated['theme_ids'] ?? [];

        unset($validated['theme_ids']);

        // Haal de catalogusgegevens uit de gevalideerde gegevens.
        $catalogEditionId = $validated['catalog_edition_id'] ?? null;
        $catalogNumber = $validated['catalog_number'] ?? null;

        // Haal de cataloguswaarden apart uit de gevalideerde gegevens.
        $catalogValues = array_filter(
            $validated['catalog_values'] ?? [],
            fn ($value) => $value !== null && $value !== ''
        );

        // Deze gegevens horen niet rechtstreeks bij de tabel 'stamps'.
        unset(
            $validated['catalog_edition_id'],
            $validated['catalog_number'],
            $validated['catalog_values']
        );

        // Cataloguswaarden mogen alleen worden opgeslagen
        // wanneer een cataloguseditie en catalogusnummer zijn ingevuld.
        if (
            $catalogValues !== []
            && ($catalogEditionId === null || $catalogNumber === null)
        ) {
            throw ValidationException::withMessages([
                'catalog_edition_id' => __(
                    'Selecteer een cataloguseditie en vul een catalogusnummer in wanneer je cataloguswaarden opgeeft.'
                ),
            ]);
        }

        // Bewaar de afbeeldingsgegevens apart van de postzegelgegevens.
        $imageFile = $validated['image'] ?? null;
        $imageType = $validated['image_type'] ?? 'front';
        $imageAltText = $validated['alt_text'] ?? null;

        // Deze velden horen niet bij de tabel 'stamps'.
        unset(
            $validated['image'],
            $validated['image_type'],
            $validated['alt_text']
        );

        // Gebruik de actieve applicatietaal voor de algemene gegevens.
        $locale = app()->getLocale();

        $validated['name'] = trim(
            $translations[$locale]['name']
        );

        $validated['description'] = $translations[$locale]['description'] ?? null;

        // Bewaar het pad voor eventuele opruiming bij een fout.
        $storedImagePath = null;

        try {

            $stamp = DB::transaction(function () use (
                $validated,
                $translations,
                $themeIds,
                $catalogEditionId,
                $catalogNumber,
                $catalogValues,
                $imageFile,
                $imageType,
                $imageAltText,
                &$storedImagePath
            ) {

                // Sla de algemene postzegelgegevens op.
                $stamp = Stamp::create($validated);

                // Koppel de geselecteerde thema's aan de postzegel.
                $stamp->themes()->sync($themeIds);

                // Sla de vertalingen op.
                foreach (self::LOCALES as $locale) {

                    $translation = $translations[$locale] ?? [];

                    $name = trim((string) ($translation['name'] ?? ''));

                    // Maak geen vertaling aan zonder naam.
                    if ($name === '') {
                        continue;
                    }

                    $stamp->translations()->create([
                        'locale' => $locale,
                        'name' => $name,
                        'description' => $translation['description'] ?? null,
                    ]);
                }

                // Koppel de postzegel aan de geselecteerde cataloguseditie.
                if ($catalogEditionId !== null && $catalogNumber !== null) {
                    $catalogEntry = $stamp->catalogEntries()->create([
                        'catalog_edition_id' => $catalogEditionId,
                        'catalog_number' => trim($catalogNumber),
                    ]);

                    // Sla de cataloguswaarden per conditie op.
                    if ($catalogValues !== []) {
                        $conditions = Condition::query()
                            ->whereIn('slug', array_keys($catalogValues))
                            ->get()
                            ->keyBy('slug');

                        foreach ($catalogValues as $slug => $amount) {
                            $condition = $conditions->get($slug);

                            if ($condition === null) {
                                throw ValidationException::withMessages([
                                    'catalog_values' => __('De geselecteerde conditie bestaat niet.'),
                                ]);
                            }

                            $catalogEntry->values()->create([
                                'condition_id' => $condition->id,
                                'catalog_value' => $amount,
                                'currency' => 'EUR',
                            ]);
                        }
                    }
                }

                // Koppel de afbeelding aan de nieuwe postzegel.
                if ($imageFile !== null) {
                    $directory = 'stamps/'.$stamp->country->slug.'/'.$stamp->issue_year;

                    $path = $imageFile->store($directory, 'public');

                    if ($path === false) {
                        throw ValidationException::withMessages([
                            'image' => __('De afbeelding kon niet worden opgeslagen. Probeer het opnieuw.'),
                        ]);
                    }

                    // Bewaar het bestandspad voor eventuele foutafhandeling.
                    $storedImagePath = $path;

                    $stamp->images()->create([
                        'path' => $path,
                        'image_type' => $imageType,
                        'alt_text' => $imageAltText,
                        'is_primary' => false,
                    ]);
                }

                return $stamp;
            });
        } catch (\Throwable $exception) {
            // Verwijder een eventueel opgeslagen bestand
            // wanneer de database-transactie mislukt.
            if ($storedImagePath !== null) {
                Storage::disk('public')->delete($storedImagePath);
            }

            throw $exception;
        }

        return redirect()
            ->route('backend.stamps.index')
            ->with(
                'success',
                "Postzegel '{$stamp->name}' is toegevoegd."
            );
    }

    /**
     * Display a stamp.
     */
    public function show(Stamp $stamp): View
    {
        $stamp->load([
            'country',
            'series',
            'themes',
            'copies.condition.translation',
            'images',
            'translations',
            'catalogEntries.catalogEdition.catalog',
            'catalogEntries.values.condition.translations',
        ]);

        return view('backend.stamps.show', compact('stamp'));
    }

    /**
     * Show the form for editing a stamp.
     */
    public function edit(Stamp $stamp): View
    {
        // Laad de bestaande vertalingen en cataloguskoppelingen.
        $stamp->load([
            'translations',
            'themes',
            'catalogEntries.catalogEdition.catalog',
            'catalogEntries.values.condition.translations',
        ]);

        $countries = Country::orderBy('name')->get();

        $series = StampSeries::with('country')
            ->orderBy('name')
            ->get();

        $catalogEditions = CatalogEdition::with('catalog')
            ->orderByDesc('edition_year')
            ->orderBy('edition_name')
            ->get();

        $parentStamps = Stamp::with('translations')
            ->whereNull('parent_stamp_id')
            ->where('id', '!=', $stamp->id)
            ->orderBy('name')
            ->get();

        // Laad beschikbare thema's inclusief vertalingen.
        $themes = Theme::with('translations')
            ->orderBy('name')
            ->get();

        return view('backend.stamps.edit', [
            'stamp' => $stamp,
            'countries' => $countries,
            'series' => $series,
            'locales' => self::LOCALES,
            'catalogEditions' => $catalogEditions,
            'parentStamps' => $parentStamps,
            'themes' => $themes,
        ]);
    }

    /**
     * Update a stamp.
     */
    public function update(
        Request $request,
        Stamp $stamp
    ): RedirectResponse {
        $validated = $request->validate(array_merge([
            'country_id' => [
                'required',
                'integer',
                'exists:countries,id',
            ],
            'parent_stamp_id' => [
                'nullable',
                'integer',
                Rule::exists('stamps', 'id')
                    ->where('country_id', $request->input('country_id'))
                    ->whereNull('parent_stamp_id')
                    ->whereNot('id', $stamp->id),
            ],
            'stamp_series_id' => [
                'nullable',
                'integer',
                Rule::exists('stamp_series', 'id')
                    ->where('country_id', $request->input('country_id')),
            ],
            'theme_ids' => [
                'sometimes',
                'array',
            ],
            'theme_ids.*' => [
                'integer',
                'distinct',
                'exists:themes,id',
            ],
            'issue_year' => [
                'required',
                'integer',
                'min:1840',
                'max:2200',
            ],
            'issue_date' => [
                'nullable',
                'date',
            ],
            'face_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
            'designer' => [
                'nullable',
                'string',
                'max:255',
            ],
            'printing_method' => [
                'nullable',
                'string',
                'max:255',
            ],
            'catalog_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'catalog_edition_id' => [
                'nullable',
                'required_with:catalog_number',
                'integer',
                'exists:catalog_editions,id',
            ],
            'catalog_number' => [
                'nullable',
                'required_with:catalog_edition_id',
                'string',
                'max:100',
            ],
            'catalog_values' => [
                'nullable',
                'array:postfris,gestempeld',
            ],
            'catalog_values.postfris' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
            'catalog_values.gestempeld' => [
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ], $this->translationValidationRules()), [
            'catalog_number.required_with' => 'Het catalogusnummer is verplicht wanneer je een cataloguseditie selecteert.',
            'catalog_edition_id.required_with' => 'Selecteer een cataloguseditie wanneer je een catalogusnummer invult.',
        ]);

        // Voorkom dat een hoofdpostzegel met bestaande varianten
        // zelf aan een andere hoofdpostzegel wordt gekoppeld.
        if (
            ! empty($validated['parent_stamp_id'])
            && $stamp->variants()->exists()
        ) {
            throw ValidationException::withMessages([
                'parent_stamp_id' => __(
                    'Deze postzegel heeft al varianten en kan daarom niet zelf een variant worden.'
                ),
            ]);
        }

        // Haal de vertalingen uit de gevalideerde gegevens.
        $translations = $validated['translations'];

        // Haal de geselecteerde thema's apart uit de gevalideerde gegevens.
        $themeIds = $validated['theme_ids'] ?? [];

        unset($validated['theme_ids']);

        // Haal de catalogusgegevens uit de gevalideerde gegevens.
        $catalogEditionId = $validated['catalog_edition_id'] ?? null;
        $catalogNumber = $validated['catalog_number'] ?? null;

        // Haal de cataloguswaarden apart uit de gevalideerde gegevens.
        // Lege waarden blijven behouden, zodat we deze later kunnen verwijderen.
        $catalogValues = $validated['catalog_values'] ?? [];

        // Deze gegevens horen niet rechtstreeks bij de tabel 'stamps'.
        unset(
            $validated['catalog_edition_id'],
            $validated['catalog_number'],
            $validated['catalog_values']
        );

        // Cataloguswaarden mogen alleen worden opgeslagen
        // wanneer een cataloguseditie en catalogusnummer zijn ingevuld.
        if (
            $catalogValues !== []
            && ($catalogEditionId === null || $catalogNumber === null)
        ) {
            throw ValidationException::withMessages([
                'catalog_edition_id' => __(
                    'Selecteer een cataloguseditie en vul een catalogusnummer in wanneer je cataloguswaarden opgeeft.'
                ),
            ]);
        }

        // Gebruik de actieve applicatietaal voor de algemene gegevens.
        $locale = app()->getLocale();

        $validated['name'] = trim(
            $translations[$locale]['name']
        );

        $validated['description'] = $translations[$locale]['description'] ?? null;

        DB::transaction(function () use (
            $stamp,
            $validated,
            $translations,
            $themeIds,
            $catalogEditionId,
            $catalogNumber,
            $catalogValues
        ) {

            // Werk de algemene postzegelgegevens bij.
            $stamp->update($validated);

            // Werk de gekoppelde thema's bij.
            $stamp->themes()->sync($themeIds);

            // Werk bestaande vertalingen bij of voeg nieuwe toe.
            foreach (self::LOCALES as $locale) {

                $translation = $translations[$locale] ?? [];

                $name = trim((string) ($translation['name'] ?? ''));

                // Behoud bestaande vertalingen als de naam leeg is.
                if ($name === '') {
                    continue;
                }

                $stamp->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'name' => $name,
                        'description' => $translation['description'] ?? null,
                    ]
                );
            }
            // Werk een bestaande cataloguskoppeling bij of voeg een nieuwe toe.
            if ($catalogEditionId !== null && $catalogNumber !== null) {
                $catalogEntry = $stamp->catalogEntries()->updateOrCreate(
                    [
                        'catalog_edition_id' => $catalogEditionId,
                    ],
                    [
                        'catalog_number' => trim($catalogNumber),
                    ]
                );

                // Werk de cataloguswaarden per conditie bij.
                if ($catalogValues !== []) {
                    $conditions = Condition::query()
                        ->whereIn('slug', array_keys($catalogValues))
                        ->get()
                        ->keyBy('slug');
                    foreach ($catalogValues as $slug => $amount) {
                        $condition = $conditions->get($slug);

                        if ($condition === null) {
                            throw ValidationException::withMessages([
                                'catalog_values' => __('De geselecteerde conditie bestaat niet.'),
                            ]);
                        }

                        // Verwijder de bestaande cataloguswaarde als het veld leeg is.
                        if ($amount === null || $amount === '') {
                            $catalogEntry->values()
                                ->where('condition_id', $condition->id)
                                ->delete();

                            continue;
                        }

                        // Voeg een nieuwe cataloguswaarde toe of werk de bestaande bij.
                        $catalogEntry->values()->updateOrCreate(
                            [
                                'condition_id' => $condition->id,
                            ],
                            [
                                'catalog_value' => $amount,
                                'currency' => 'EUR',
                            ]
                        );
                    }
                }
            }
        });

        return redirect()
            ->route('backend.stamps.index')
            ->with(
                'success',
                "Postzegel '{$stamp->name}' is bijgewerkt."
            );
    }

    /**
     * Delete a stamp.
     */
    public function destroy(Stamp $stamp): RedirectResponse
    {
        $name = $stamp->name;

        // Controleer of er gekoppelde gegevens bestaan.
        $hasRelatedData =
            $stamp->copies()->exists()
            || $stamp->images()->exists()
            || $stamp->catalogEntries()->exists()
            || $stamp->catalogValues()->exists()
            || $stamp->valuations()->exists()
            || $stamp->variants()->exists();

        // Verwijder de postzegel niet als er gegevens gekoppeld zijn.
        if ($hasRelatedData) {
            return redirect()
                ->route('backend.stamps.index')
                ->with(
                    'error',
                    "Postzegel '{$name}' kan niet worden verwijderd, omdat er nog gekoppelde gegevens bestaan."
                );
        }

        // Verwijder de koppelingen met thema's en daarna de postzegel.
        DB::transaction(function () use ($stamp) {
            $stamp->themes()->detach();
            $stamp->delete();
        });

        return redirect()
            ->route('backend.stamps.index')
            ->with(
                'success',
                "Postzegel '{$name}' is verwijderd."
            );
    }
}
