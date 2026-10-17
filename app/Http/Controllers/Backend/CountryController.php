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
                    ->orWhere('slug', 'like', "%{$search}%");
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
            $this->validationRules()
        );

        if ($this->hasInvalidYearRange($validated)) {
            return back()
                ->withErrors([
                    'end_year' => 'Het eindjaar mag niet vóór het beginjaar liggen.',
                ])
                ->withInput();
        }

        $translations = $validated['translations'] ?? [];

        unset($validated['translations']);

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
                'Het land of gebied is succesvol toegevoegd.'
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
            $this->validationRules($country)
        );

        if ($this->hasInvalidYearRange($validated)) {
            return back()
                ->withErrors([
                    'end_year' => 'Het eindjaar mag niet vóór het beginjaar liggen.',
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
            $validated['slug'] = $this->generateUniqueSlug(
                $validated['name'],
                $country->id
            );

            $validated['is_active'] = $request->boolean('is_active');

            $country->update($validated);

            $this->saveTranslations($country, $translations);
        });

        return redirect()
            ->route('backend.countries.show', $country)
            ->with(
                'success',
                'De gegevens zijn succesvol bijgewerkt.'
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
                    'Dit land of gebied kan niet worden verwijderd omdat er nog postzegels, series of catalogi aan gekoppeld zijn.'
                );
        }

        $country->delete();

        return redirect()
            ->route('backend.countries.index')
            ->with(
                'success',
                'Het land of gebied is verwijderd.'
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
                'required',
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
                'sometimes',
                'array',
            ],
        ];

        foreach (self::LOCALES as $locale) {
            $rules["translations.{$locale}"] = [
                'sometimes',
                'array',
            ];

            $rules["translations.{$locale}.name"] = [
                'nullable',
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

            if ($locale === 'nl') {
                $existingTranslation = $country->translations()
                    ->where('locale', 'nl')
                    ->first();

                $country->translations()->updateOrCreate(
                    ['locale' => 'nl'],
                    [
                        'name' => $country->name,
                        'description' => $descriptionWasProvided
                            ? $translatedDescription
                            : $existingTranslation?->description,
                    ]
                );

                continue;
            }

            /*
             * An empty translated name means:
             * retain any existing translation and do not create
             * a new, incomplete translation.
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
