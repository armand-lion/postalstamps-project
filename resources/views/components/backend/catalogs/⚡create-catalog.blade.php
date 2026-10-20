<?php

use App\Models\Catalog;
use App\Models\Country;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

new class extends Component {
    private const ENTITY_TYPES = [
        'country' => 'Huidig land',
        'historical_state' => 'Historische staat',
        'territory' => 'Gebied',
        'colonial_territory' => 'Koloniaal gebied',
        'other_issuer' => 'Overige postzegeluitgever',
    ];

    public function getEntityTypesProperty(): array
    {
        return self::ENTITY_TYPES;
    }

    private function catalogValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],

            'abbreviation' => ['nullable', 'string', 'max:30'],

            'publisher' => ['nullable', 'string', 'max:150'],

            'website' => ['nullable', 'url', 'max:255'],

            'description' => ['nullable', 'string'],

            'selectedCountries' => ['array'],

            'selectedCountries.*' => ['required', 'integer', 'distinct', Rule::exists('countries', 'id')],
        ];
    }

    public function save()
    {
        // Valideer de catalogusgegevens.
        $validated = $this->validate($this->catalogValidationRules());

        // Haal de geselecteerde landen uit de gegevens.
        $selectedCountries = $validated['selectedCountries'] ?? [];

        unset($validated['selectedCountries']);

        // Sla de catalogus en de landenkoppelingen op.
        $catalog = DB::transaction(function () use ($validated, $selectedCountries) {
            $catalog = Catalog::create([
                'name' => trim($validated['name']),
                'abbreviation' => $validated['abbreviation'] ?: null,
                'publisher' => $validated['publisher'] ?: null,
                'website' => $validated['website'] ?: null,
                'description' => $validated['description'] ?: null,
            ]);

            // Koppel de geselecteerde landen en gebieden.
            $catalog->countries()->sync($selectedCountries);

            return $catalog;
        });

        // Toon een bevestiging na het opslaan.
        session()->flash('success', __('De catalogus is succesvol toegevoegd.'));

        // Ga terug naar het catalogusoverzicht.
        return redirect()->route('backend.catalogs.index');
    }

    private function countryValidationRules(): array
    {
        $rules = [
            'newCountryIsoCode' => ['nullable', 'string', 'max:3', Rule::unique('countries', 'iso_code')],

            'newCountryEntityType' => ['required', Rule::in(array_keys(self::ENTITY_TYPES))],

            'newCountryStartYear' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'newCountryEndYear' => ['nullable', 'integer', 'min:0', 'max:9999'],

            'newCountryIsActive' => ['boolean'],

            'newCountryTranslations' => ['required', 'array'],
        ];

        foreach (['nl', 'en', 'de', 'fr', 'es'] as $locale) {
            $rules["newCountryTranslations.{$locale}"] = ['sometimes', 'array'];

            $rules["newCountryTranslations.{$locale}.name"] = [$locale === app()->getLocale() ? 'required' : 'nullable', 'string', 'max:100'];

            $rules["newCountryTranslations.{$locale}.description"] = ['nullable', 'string'];
        }

        return $rules;
    }

    public function saveCountry(): void
    {
        // Valideer de ingevoerde gegevens.
        $validated = $this->validate(
            $this->countryValidationRules(),
            [],
            [
                'newCountryTranslations.nl.name' => __('Nederlandse naam'),
                'newCountryTranslations.en.name' => __('Engelse naam'),
                'newCountryTranslations.de.name' => __('Duitse naam'),
                'newCountryTranslations.fr.name' => __('Franse naam'),
                'newCountryTranslations.es.name' => __('Spaanse naam'),
            ],
        );

        // Controleer de begin- en eindjaren.
        if ($validated['newCountryStartYear'] !== null && $validated['newCountryEndYear'] !== null && $validated['newCountryEndYear'] < $validated['newCountryStartYear']) {
            throw ValidationException::withMessages([
                'newCountryEndYear' => __('Het eindjaar mag niet vóór het beginjaar liggen.'),
            ]);
        }

        // Gebruik de landnaam uit de actieve applicatietaal.
        $locale = app()->getLocale();

        $countryName = trim($validated['newCountryTranslations'][$locale]['name']);

        // Controleer of de landnaam al bestaat.
        if (Country::where('name', $countryName)->exists()) {
            throw ValidationException::withMessages([
                "newCountryTranslations.{$locale}.name" => __('Deze naam bestaat al.'),
            ]);
        }

        // Sla het land en de vertalingen op in één databasetransactie.
        $country = DB::transaction(function () use ($validated, $countryName, $locale) {
            // Genereer een unieke slug.
            $baseSlug = Str::slug($countryName);

            if ($baseSlug === '') {
                $baseSlug = 'entiteit';
            }

            $slug = $baseSlug;
            $counter = 2;

            while (Country::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Maak het nieuwe land aan.
            $country = Country::create([
                'name' => $countryName,
                'slug' => $slug,
                'iso_code' => $validated['newCountryIsoCode'] ?: null,
                'entity_type' => $validated['newCountryEntityType'],
                'start_year' => $validated['newCountryStartYear'],
                'end_year' => $validated['newCountryEndYear'],
                'description' => trim($validated['newCountryTranslations'][$locale]['description'] ?? ''),
                'is_active' => $validated['newCountryIsActive'],
            ]);

            // Sla de vertalingen op.
            foreach (['nl', 'en', 'de', 'fr', 'es'] as $translationLocale) {
                $translation = $validated['newCountryTranslations'][$translationLocale] ?? [];

                $translatedName = trim($translation['name'] ?? '');

                // Sla alleen vertalingen op waarvoor een naam is ingevuld.
                if ($translatedName === '') {
                    continue;
                }

                $country->translations()->create([
                    'locale' => $translationLocale,
                    'name' => $translatedName,
                    'description' => $translation['description'] ?? null,
                ]);
            }

            return $country;
        });

        // Selecteer het nieuwe land automatisch in het catalogusformulier.
        $this->selectedCountries[] = (string) $country->id;
        $this->selectedCountries = array_values(array_unique($this->selectedCountries));

        // Sluit de modal.
        $this->showCountryModal = false;

        // Maak de invoervelden van de modal leeg.
        $this->reset('newCountryIsoCode', 'newCountryEntityType', 'newCountryStartYear', 'newCountryEndYear', 'newCountryIsActive', 'newCountryTranslations');

        // Verwijder eventuele oude validatiefouten.
        $this->resetValidation();
    }

    public string $name = '';

    public string $abbreviation = '';

    public string $publisher = '';

    public string $website = '';

    public string $description = '';

    public array $selectedCountries = [];

    public string $countrySearch = '';

    public bool $showCountryModal = false;

    /**
     * New country or territory data.
     */
    public string $newCountryIsoCode = '';

    public string $newCountryEntityType = 'country';

    public ?int $newCountryStartYear = null;

    public ?int $newCountryEndYear = null;

    public bool $newCountryIsActive = true;

    /**
     * Translations for the new country or territory.
     */
    public array $newCountryTranslations = [
        'nl' => [
            'name' => '',
            'description' => '',
        ],
        'en' => [
            'name' => '',
            'description' => '',
        ],
        'de' => [
            'name' => '',
            'description' => '',
        ],
        'fr' => [
            'name' => '',
            'description' => '',
        ],
        'es' => [
            'name' => '',
            'description' => '',
        ],
    ];

    /**
     * Get all available countries and territories.
     */
    public function getCountriesProperty()
    {
        $search = trim($this->countrySearch);

        return Country::query()
            ->with('translations')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")->orWhereHas('translations', function ($translationQuery) use ($search) {
                        $translationQuery->where('name', 'like', "%{$search}%");
                    });
                });
            })
            ->orderBy('name')
            ->get();
    }
};
?>

<div>
    <form wire:submit="save" class="flex flex-col gap-6">

        {{-- Basisgegevens --}}
        <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Basisgegevens') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Algemene gegevens van de catalogus.') }}
                </flux:text>
            </div>

            <div class="grid gap-5 p-4 md:grid-cols-2">

                {{-- Naam --}}
                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-1">
                            {{ __('Naam') }}
                            <span class="text-red-600">*</span>
                        </span>
                    </flux:label>

                    <flux:input wire:model="name" maxlength="150" required />

                    <flux:error name="name" />
                </flux:field>

                {{-- Afkorting --}}
                <flux:field>
                    <flux:label>
                        {{ __('Afkorting') }}
                    </flux:label>

                    <flux:input wire:model="abbreviation" maxlength="30" />

                    <flux:error name="abbreviation" />
                </flux:field>

                {{-- Uitgever --}}
                <flux:field>
                    <flux:label>
                        {{ __('Uitgever') }}
                    </flux:label>

                    <flux:input wire:model="publisher" maxlength="150" />

                    <flux:error name="publisher" />
                </flux:field>

                {{-- Website --}}
                <flux:field>
                    <flux:label>
                        {{ __('Website') }}
                    </flux:label>

                    <flux:input wire:model="website" type="url" maxlength="255" placeholder="https://" />

                    <flux:error name="website" />
                </flux:field>

                {{-- Beschrijving --}}
                <div class="md:col-span-2">
                    <flux:field>
                        <flux:label>
                            {{ __('Beschrijving') }}
                        </flux:label>

                        <flux:textarea wire:model="description" rows="4" />

                        <flux:error name="description" />
                    </flux:field>
                </div>

            </div>
        </section>

        {{-- Landen en gebieden --}}
        <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">

                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

                    <div>
                        <flux:heading size="lg">
                            {{ __('Landen en gebieden') }}
                        </flux:heading>

                        <flux:text class="mt-1">
                            {{ __('Selecteer de landen en gebieden die in deze catalogus voorkomen.') }}
                        </flux:text>
                    </div>

                    <flux:button type="button" variant="primary" icon="plus"
                        wire:click="$set('showCountryModal', true)">
                        {{ __('Nieuw land of gebied toevoegen') }}
                    </flux:button>

                </div>

            </div>

            <div class="p-4">

                {{-- Landen zoeken --}}
                <div class="mb-5">
                    <flux:input wire:model.live.debounce.300ms="countrySearch" :label="__('Land of gebied zoeken')"
                        :placeholder="__('Zoek op landnaam...')" icon="magnifying-glass" clearable />
                </div>

                @if ($this->countries->isEmpty())

                    <flux:text>
                        {{ __('Er zijn nog geen landen of gebieden beschikbaar.') }}
                    </flux:text>
                @else
                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">

                        @foreach ($this->countries as $country)
                            @php
                                $locale = app()->getLocale();
                                $fallbackLocale = config('app.fallback_locale', 'nl');

                                $translation =
                                    $country->translations->firstWhere('locale', $locale) ??
                                    $country->translations->firstWhere('locale', $fallbackLocale);

                                $countryName = $translation?->name ?? $country->name;
                            @endphp

                            <label wire:key="country-{{ $country->id }}"
                                class="flex items-center gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                                <input type="checkbox" wire:model="selectedCountries" value="{{ $country->id }}"
                                    class="rounded border-neutral-300 text-accent focus:ring-accent">

                                <span class="text-sm text-neutral-900 dark:text-neutral-100">
                                    {{ $countryName }}
                                </span>
                            </label>
                        @endforeach

                    </div>

                @endif

                <flux:error name="selectedCountries" />

            </div>

        </section>

        {{-- Formulieracties catalogus --}}
        <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">

            <flux:button :href="route('backend.catalogs.index')" variant="ghost" icon="x-mark">
                {{ __('Annuleren') }}
            </flux:button>

            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled"
                wire:target="save">
                {{ __('Catalogus opslaan') }}
            </flux:button>

        </div>
    </form>

    {{-- Modal voor nieuw land of gebied --}}
    <flux:modal wire:model.self="showCountryModal" name="create-country" class="w-full max-w-3xl">
        <div class="flex flex-col gap-6">

            <div>
                <flux:heading size="lg">
                    {{ __('Nieuw land of gebied toevoegen') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Voeg een nieuw land of gebied toe zonder het catalogusformulier te verlaten.') }}
                </flux:text>
            </div>

            {{-- Basisgegevens nieuw land of gebied --}}
            <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">

                <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <flux:heading size="lg">
                        {{ __('Basisgegevens') }}
                    </flux:heading>

                    <flux:text class="mt-1">
                        {{ __('Algemene gegevens van het land of gebied.') }}
                    </flux:text>
                </div>

                <div class="grid gap-5 p-4 md:grid-cols-2">

                    {{-- ISO-code --}}
                    <flux:field>
                        <flux:label>
                            {{ __('ISO-code') }}
                        </flux:label>

                        <flux:input wire:model="newCountryIsoCode" maxlength="3"
                            :placeholder="__('Bijvoorbeeld: NL')" />

                        <flux:description>
                            {{ __('Optioneel. Maximaal drie tekens.') }}
                        </flux:description>

                        <flux:error name="newCountryIsoCode" />
                    </flux:field>

                    {{-- Type entiteit --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Type entiteit') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <flux:select wire:model="newCountryEntityType" required>
                            @foreach ($this->entityTypes as $value => $label)
                                <option value="{{ $value }}">
                                    {{ __($label) }}
                                </option>
                            @endforeach
                        </flux:select>

                        <flux:error name="newCountryEntityType" />
                    </flux:field>

                    {{-- Beginjaar --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Beginjaar') }}
                        </flux:label>

                        <flux:input type="number" wire:model="newCountryStartYear" min="0" max="9999"
                            :placeholder="__('Bijvoorbeeld: 1949')" />

                        <flux:description>
                            {{ __('Het jaar waarin deze entiteit begon.') }}
                        </flux:description>

                        <flux:error name="newCountryStartYear" />
                    </flux:field>

                    {{-- Eindjaar --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Eindjaar') }}
                        </flux:label>

                        <flux:input type="number" wire:model="newCountryEndYear" min="0" max="9999"
                            :placeholder="__('Bijvoorbeeld: 1990')" />

                        <flux:description>
                            {{ __('Laat leeg als de entiteit nog bestaat of het eindjaar onbekend is.') }}
                        </flux:description>

                        <flux:error name="newCountryEndYear" />
                    </flux:field>

                    {{-- Actief --}}
                    <div class="md:col-span-2">
                        <label class="flex items-start gap-3">

                            <input type="checkbox" wire:model="newCountryIsActive"
                                class="mt-1 rounded border-neutral-300 text-accent focus:ring-accent">

                            <span>
                                <span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ __('Actief') }}
                                </span>

                                <span class="mt-1 block text-sm text-neutral-500 dark:text-neutral-400">
                                    {{ __('Geef aan of deze entiteit actief in de catalogus gebruikt mag worden.') }}
                                </span>
                            </span>

                        </label>
                    </div>

                </div>
            </section>

            {{-- Vertalingen nieuw land of gebied --}}
            <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">

                <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <flux:heading size="lg">
                        {{ __('Vertalingen') }}
                    </flux:heading>

                    <flux:text class="mt-1">
                        {{ __('Vul de naam en eventueel de beschrijving in voor iedere taal.') }}
                    </flux:text>
                </div>

                <div class="flex flex-col gap-6 p-4">

                    @php
                        $languageNames = [
                            'nl' => 'Nederlands',
                            'en' => 'English',
                            'de' => 'Deutsch',
                            'fr' => 'Français',
                            'es' => 'Español',
                        ];
                    @endphp

                    @foreach ($languageNames as $locale => $languageName)
                        <div wire:key="new-country-translation-{{ $locale }}"
                            class="grid gap-5 border-b border-neutral-200 pb-6 last:border-b-0 last:pb-0 dark:border-neutral-700 md:grid-cols-2">

                            <div class="md:col-span-2">
                                <flux:heading>
                                    {{ $languageName }}
                                </flux:heading>
                            </div>

                            {{-- Vertaalde naam --}}
                            <flux:field>
                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Naam') }}

                                        @if ($locale === app()->getLocale())
                                            <span class="text-red-600">*</span>
                                        @endif
                                    </span>
                                </flux:label>

                                <flux:input wire:model="newCountryTranslations.{{ $locale }}.name"
                                    maxlength="100" :required="$locale === app()->getLocale()"
                                    :placeholder="$languageName" />

                                @if ($locale === app()->getLocale())
                                    <flux:description>
                                        {{ __('Deze naam is verplicht omdat dit de actieve applicatietaal is.') }}
                                    </flux:description>
                                @else
                                    <flux:description>
                                        {{ __('Optioneel. Laat leeg als je deze vertaling nog niet hebt.') }}
                                    </flux:description>
                                @endif

                                <flux:error name="newCountryTranslations.{{ $locale }}.name" />
                            </flux:field>

                            {{-- Vertaalde beschrijving --}}
                            <flux:field>
                                <flux:label>
                                    {{ __('Beschrijving') }}
                                </flux:label>

                                <flux:textarea wire:model="newCountryTranslations.{{ $locale }}.description"
                                    rows="3" />

                                <flux:error name="newCountryTranslations.{{ $locale }}.description" />
                            </flux:field>

                        </div>
                    @endforeach

                </div>
            </section>

            {{-- Knop Annuleren --}}
            {{-- Formulieracties nieuw land of gebied --}}
            <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">

                <flux:button type="button" variant="ghost" icon="x-mark"
                    wire:click="$set('showCountryModal', false)">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="button" variant="primary" icon="check" wire:click="saveCountry">
                    {{ __('Land of gebied opslaan') }}
                </flux:button>

            </div>

        </div>
    </flux:modal>
</div>
