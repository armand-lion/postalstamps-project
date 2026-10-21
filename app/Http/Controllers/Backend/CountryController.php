<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    private const ENTITY_TYPES = [
        'country' => 'Huidig land',
        'historical_state' => 'Historische staat',
        'territory' => 'Gebied',
        'colonial_territory' => 'Koloniaal gebied',
        'other_issuer' => 'Overige postzegeluitgever',
    ];

    private const LOCALES = [
        'nl',
        'en',
        'de',
        'fr',
        'es',
    ];

    /**
     * Display countries and territories.
     */
    public function index(Request $request)
    {
        $query = Country::query();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('iso_code', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('translations', function ($translationQuery) use ($search) {
                        $translationQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('entity_type')) {
            $query->where(
                'entity_type',
                $request->input('entity_type')
            );
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $countries = $query
            ->withCount(['stamps', 'stampSeries', 'catalogs'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('backend.countries.index', [
            'countries' => $countries,
            'entityTypes' => self::ENTITY_TYPES,
        ]);
    }

    /**
     * Show the creation form.
     */
    public function create()
    {
        return view('backend.countries.create', [
            'entityTypes' => self::ENTITY_TYPES,
            'locales' => self::LOCALES,
        ]);
    }

    /**
     * Store a new country or territory.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->validationRules(),
            [],
            [
                'translations.nl.name' => __('Nederlandse naam'),
                'translations.en.name' => __('Engelse naam'),
                'translations.de.name' => __('Duitse naam'),
                'translations.fr.name' => __('Franse naam'),
                'translations.es.name' => __('Spaanse naam'),
            ]
        );

        if ($this->hasInvalidYearRange($validated)) {
            return back()
                ->withErrors([
                    'end_year' => __('Het eindjaar mag niet vóór het beginjaar liggen.'),
                ])
                ->withInput();
        }

        $translations = $validated['translations'] ?? [];

        unset($validated['translations']);

        // Gebruik de naam en beschrijving uit de actieve applicatietaal.
        $locale = app()->getLocale();

        $validated['name'] = trim(
            $translations[$locale]['name']
        );

        $validated['description'] = trim(
            $translations[$locale]['description'] ?? ''
        );

        // Controleer of de algemene naam al bestaat.
        if (Country::where('name', $validated['name'])->exists()) {
            return back()
                ->withErrors([
                    "translations.{$locale}.name" => __('Deze naam bestaat al.'),
                ])
                ->withInput();
        }

        $country = DB::transaction(function () use (
            $validated,
            $translations,
            $request
        ) {
            $validated['slug'] = $this->generateUniqueSlug(
                $validated['name']
            );

            $validated['is_active'] = $request->boolean('is_active');

            $country = Country::create($validated);

            $this->saveTranslations($country, $translations);

            return $country;
        });

        return redirect()
            ->route('backend.countries.show', $country)
            ->with(
                'success',
                __('Het land of gebied is succesvol toegevoegd.')
            );
    }

    /**
     * Display a country or territory.
     */
    public function show(Country $country)
    {
        $country->load('translations');

        $country->loadCount([
            'stamps',
            'stampSeries',
            'catalogs',
        ]);

        return view('backend.countries.show', [
            'country' => $country,
            'entityTypes' => self::ENTITY_TYPES,
            'locales' => self::LOCALES,
        ]);
    }

    /**
     * Show the editing form.
     */
    public function edit(Country $country)
    {
        $country->load('translations');

        return view('backend.countries.edit', [
            'country' => $country,
            'entityTypes' => self::ENTITY_TYPES,
            'locales' => self::LOCALES,
        ]);
    }

    /**
     * Update a country or territory.
     */
    public function update(Request $request, Country $country)
    {
        $validated = $request->validate(
            $this->validationRules($country),
            [],
            [
                'translations.nl.name' => __('Nederlandse naam'),
                'translations.en.name' => __('Engelse naam'),
                'translations.de.name' => __('Duitse naam'),
                'translations.fr.name' => __('Franse naam'),
                'translations.es.name' => __('Spaanse naam'),
            ]
        );

        if ($this->hasInvalidYearRange($validated)) {
            return back()
                ->withErrors([
                    'end_year' => __('Het eindjaar mag niet vóór het beginjaar liggen.'),
                ])
                ->withInput();
        }

        $translations = $validated['translations'] ?? [];

        unset($validated['translations']);

        DB::transaction(function () use (
            $country,
            $validated,
            $translations,
            $request
        ) {
            // Behoud de bestaande algemene naam en slug.
            // Alleen de vertalingen worden taalspecifiek gewijzigd.
            unset($validated['name'], $validated['description']);

            $validated['is_active'] = $request->boolean('is_active');

            $country->update($validated);

            $this->saveTranslations($country, $translations);
        });

        return redirect()
            ->route('backend.countries.show', $country)
            ->with(
                'success',
                __('De gegevens zijn succesvol bijgewerkt.')
            );
    }

    /**
     * Delete only when no dependent records exist.
     */
    public function destroy(Country $country)
    {
        if (
            $country->stamps()->exists()
            || $country->stampSeries()->exists()
            || $country->catalogs()->exists()
        ) {
            return redirect()
                ->route('backend.countries.show', $country)
                ->with(
                    'error',
                    __('Dit land of gebied kan niet worden verwijderd omdat er nog postzegels, series of catalogi aan gekoppeld zijn.')
                );
        }

        $country->delete();

        return redirect()
            ->route('backend.countries.index')
            ->with(
                'success',
                __('Het land of gebied is verwijderd.')
            );
    }

    /**
     * Validation rules for country data and translations.
     */
    private function validationRules(?Country $country = null): array
    {
        $nameRule = Rule::unique('countries', 'name');

        $isoCodeRule = Rule::unique('countries', 'iso_code');

        if ($country !== null) {
            $nameRule->ignore($country->id);
            $isoCodeRule->ignore($country->id);
        }

        $rules = [
            'name' => [
                'nullable',
                'string',
                'max:100',
                $nameRule,
            ],
            'iso_code' => [
                'nullable',
                'string',
                'max:3',
                $isoCodeRule,
            ],
            'entity_type' => [
                'required',
                Rule::in(array_keys(self::ENTITY_TYPES)),
            ],
            'start_year' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
            'end_year' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'translations' => [
                'required',
                'array',
            ],
        ];

        foreach (self::LOCALES as $locale) {
            $rules["translations.{$locale}"] = [
                'sometimes',
                'array',
            ];

            $rules["translations.{$locale}.name"] = [
                $locale === app()->getLocale() ? 'required' : 'nullable',
                'string',
                'max:100',
            ];

            $rules["translations.{$locale}.description"] = [
                'nullable',
                'string',
            ];
        }

        return $rules;
    }

    /**
     * Check that the end year does not precede the start year.
     */
    private function hasInvalidYearRange(array $validated): bool
    {
        return isset(
            $validated['start_year'],
            $validated['end_year']
        ) && $validated['end_year'] < $validated['start_year'];
    }

    /**
     * Save translations without deleting existing translations
     * when a translated name is left blank.
     */
    private function saveTranslations(
        Country $country,
        array $translations
    ): void {
        foreach (self::LOCALES as $locale) {
            $translation = $translations[$locale] ?? [];

            $translatedName = trim(
                (string) ($translation['name'] ?? '')
            );

            $descriptionWasProvided = array_key_exists(
                'description',
                $translation
            );

            $translatedDescription = $translation['description'] ?? null;

            /*
            * Een lege vertaalde naam betekent:
            * behoud een bestaande vertaling en maak
            * geen nieuwe, onvolledige vertaling aan.
            */
            if ($translatedName === '') {
                continue;
            }

            $existingTranslation = $country->translations()
                ->where('locale', $locale)
                ->first();

            $country->translations()->updateOrCreate(
                ['locale' => $locale],
                [
                    'name' => $translatedName,
                    'description' => $descriptionWasProvided
                        ? $translatedDescription
                        : $existingTranslation?->description,
                ]
            );
        }
    }

    /**
     * Generate a unique slug.
     */
    private function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'entiteit';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            Country::query()
                ->where('slug', $slug)
                ->when(
                    $ignoreId !== null,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $ignoreId
                    )
                )
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
