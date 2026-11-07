<?php

use App\Models\Catalog;
use App\Models\Condition;
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

    /*
     * Haal de actieve waarderingscategorieën op,
     * inclusief hun vertalingen.
     */

    public function getConditionsProperty()
    {
        return Condition::query()->with('translations')->where('is_active', true)->orderBy('sort_order')->get();
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

            // Cataloguswaarderingen
            'conditionRules' => ['array'],

            'conditionRules.*.condition_id' => ['required', 'integer', Rule::exists('conditions', 'id')],

            'conditionRules.*.country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],

            'conditionRules.*.issue_year_from' => ['nullable', 'integer', 'between:0,9999'],

            'conditionRules.*.issue_year_to' => ['nullable', 'integer', 'between:0,9999'],

            'conditionRules.*.sort_order' => ['required', 'integer', 'min:1'],
        ];
    }

    public function save()
    {
        // Valideer de catalogusgegevens.
        $validated = $this->validate($this->catalogValidationRules());

        // Haal de geselecteerde landen uit de gegevens.
        $selectedCountries = $validated['selectedCountries'] ?? [];

        // Haal de cataloguswaarderingen uit de gegevens.
        $conditionRules = $validated['conditionRules'] ?? [];

        unset($validated['selectedCountries'], $validated['conditionRules']);
        // Sla de catalogus en de landenkoppelingen op.
        $catalog = DB::transaction(function () use ($validated, $selectedCountries, $conditionRules) {
            $catalog = Catalog::create([
                'name' => trim($validated['name']),
                'abbreviation' => $validated['abbreviation'] ?: null,
                'publisher' => $validated['publisher'] ?: null,
                'website' => $validated['website'] ?: null,
                'description' => $validated['description'] ?: null,
            ]);

            // Koppel de geselecteerde landen en gebieden.
            $catalog->countries()->sync($selectedCountries);

            // Sla de cataloguswaarderingen op.
            foreach ($conditionRules as $index => $rule) {
                $catalog->conditionRules()->create([
                    'condition_id' => $rule['condition_id'],
                    'country_id' => $rule['country_id'] ?? null,
                    'issue_year_from' => $rule['issue_year_from'] ?? null,
                    'issue_year_to' => $rule['issue_year_to'] ?? null,
                    'sort_order' => $rule['sort_order'] ?? $index + 1,
                ]);
            }

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

    /**
     * Configureerbare waarderingscategorieën voor deze catalogus.
     *
     * @var array<int, array<string, mixed>>
     */

    public array $conditionRules = [];

    /**
     * Voeg een nieuwe waarderingsregel toe aan het formulier.
     */
    public function addConditionRule(): void
    {
        $this->conditionRules[] = [
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'condition_id' => null,
            'country_id' => null,
            'issue_year_from' => null,
            'issue_year_to' => null,
            'sort_order' => count($this->conditionRules) + 1,
        ];
    }

    /**
     * Verwijder een waarderingsregel uit het formulier.
     */
    public function removeConditionRule(int $index): void
    {
        if (!array_key_exists($index, $this->conditionRules)) {
            return;
        }

        unset($this->conditionRules[$index]);

        // Herstel de array-indexen.
        $this->conditionRules = array_values($this->conditionRules);

        // Nummer de overgebleven waarderingsregels opnieuw.
        foreach ($this->conditionRules as $position => &$rule) {
            $rule['sort_order'] = $position + 1;
        }

        unset($rule);
    }

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
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700  rounded-lg   ">

            <div class="border-b border-theme-accent-1 px-4 py-3 dark:border-neutral-700">
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

                    <input type="text" wire:model="name" maxlength="150" required
                        class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />
                    <flux:error name="name" />
                </flux:field>

                {{-- Afkorting --}}
                <flux:field>
                    <flux:label>
                        {{ __('Afkorting') }}
                    </flux:label>

                    <input type="text" wire:model="abbreviation" maxlength="50"
                        class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />

                    <flux:error name="abbreviation" />
                </flux:field>

                {{-- Uitgever --}}
                <flux:field>
                    <flux:label>
                        {{ __('Uitgever') }}
                    </flux:label>

                    <input type="text" wire:model="publisher"
                        class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />

                    <flux:error name="publisher" />
                </flux:field>

                {{-- Website --}}
                <flux:field>
                    <flux:label>
                        {{ __('Website') }}
                    </flux:label>

                    <input type="url" wire:model="website"
                        class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />

                    <flux:error name="website" />
                </flux:field>

                {{-- Beschrijving --}}
                <div class="md:col-span-2">
                    <flux:field>
                        <flux:label>
                            {{ __('Beschrijving') }}
                        </flux:label>

                        <textarea wire:model="description" rows="4"
                            class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5"></textarea>

                        <flux:error name="description" />
                    </flux:field>
                </div>

            </div>
        </section>

        {{-- Landen en gebieden --}}
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-theme-accent-1 px-4 py-3 dark:border-neutral-700">

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
                        wire:click="$set('showCountryModal', true)" class="bg-brown-2">
                        {{ __('Nieuw land of gebied toevoegen') }}
                    </flux:button>

                </div>

            </div>

            <div class="p-4">

                {{-- Landen zoeken --}}
                <div class="mb-5">
                    <flux:field>
                        <flux:label>
                            {{ __('Land of gebied zoeken') }}
                        </flux:label>

                        <div class="relative">

                            <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
                                <flux:icon.magnifying-glass class="size-4 text-zinc-500" />
                            </div>

                            <input type="search" wire:model.live.debounce.300ms="countrySearch"
                                placeholder="{{ __('Zoek een land of gebied...') }}"
                                class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 py-2 pr-3 pl-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />

                        </div>

                        <flux:error name="countrySearch" />
                    </flux:field>
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
                                class="flex items-center gap-3 rounded-lg border border-theme-accent-1 bg-brown-6 p-3">

                                <flux:checkbox wire:model="selectedCountries" value="{{ $country->id }}"
                                    class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                                <span class="text-sm text-zinc-900">
                                    {{ $countryName }}
                                </span>

                            </label>
                        @endforeach

                    </div>

                @endif

                <flux:error name="selectedCountries" />

            </div>

        </section>

        {{-- Cataloguswaarderingen --}}
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">

                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

                    <div>
                        <flux:heading size="lg">
                            {{ __('Cataloguswaarderingen') }}
                        </flux:heading>

                        <flux:text class="mt-1">
                            {{ __('Bepaal welke waarderingscategorieën deze catalogus gebruikt.') }}
                        </flux:text>
                    </div>

                    <flux:button type="button" variant="primary" icon="plus" wire:click="addConditionRule"
                        class="bg-brown-2">
                        {{ __('Waarderingscategorie toevoegen') }}
                    </flux:button>

                </div>

            </div>

            <div class="p-4">

                @if (empty($conditionRules))

                    <flux:text>
                        {{ __('Er zijn nog geen waarderingscategorieën ingesteld.') }}
                    </flux:text>
                @else
                    <div class="flex flex-col gap-4">

                        @foreach ($conditionRules as $index => $rule)
                            <div wire:key="condition-rule-{{ $rule['uuid'] }}"
                                class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

                                {{-- Waarderingsregel verwijderen --}}
                                <div class="mb-4 flex justify-end">
                                    <flux:modal.trigger :name="'delete-condition-rule-'.$rule['uuid']">
                                        <flux:button type="button" size="sm" icon="trash"
                                            class="border-theme-accent-3! bg-brown-5! text-red-700! hover:bg-brown-3!">
                                            {{ __('Verwijderen') }}
                                        </flux:button>
                                    </flux:modal.trigger>
                                </div>

                                {{-- Bevestigingsmodal --}}
                                <flux:modal :name="'delete-condition-rule-'.$rule['uuid']"
                                    class="w-full max-w-md bg-brown-3!">
                                    <div class="space-y-6">

                                        <div>
                                            <flux:heading size="lg" class="text-zinc-900!">
                                                {{ __('Waarderingscategorie verwijderen') }}
                                            </flux:heading>

                                            <flux:text class="mt-2 text-zinc-900!">
                                                {{ __('Weet je zeker dat je deze waarderingscategorie wilt verwijderen?') }}
                                            </flux:text>
                                        </div>

                                        <div class="flex justify-end gap-3">

                                            <flux:modal.close>
                                                <flux:button type="button"
                                                    class="border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                                                    {{ __('Annuleren') }}
                                                </flux:button>
                                            </flux:modal.close>

                                            <flux:button type="button" icon="trash"
                                                wire:click="removeConditionRule({{ $index }})"
                                                class="border-theme-accent-3! bg-brown-5! text-red-700! hover:bg-brown-6!">
                                                {{ __('Definitief verwijderen') }}
                                            </flux:button>

                                        </div>

                                    </div>
                                </flux:modal>

                                <flux:field>
                                    <flux:label>
                                        <span class="flex items-center gap-1">
                                            {{ __('Waarderingscategorie') }}
                                            <span class="text-red-600">*</span>
                                        </span>
                                    </flux:label>

                                    <div wire:key="condition-dropdown-{{ $rule['uuid'] }}-{{ $index }}"
                                        x-data="{
                                            selectedId: $wire.entangle('conditionRules.{{ $index }}.condition_id'),
                                            selectedName: '',

                                            updateSelectedName() {
                                                this.selectedName = this.selectedId ?
                                                    ($el.querySelector('[data-condition-id=' + CSS.escape(String(this.selectedId)) + ']')?.textContent.trim() || '') :
                                                    '';
                                            }
                                        }" x-init="updateSelectedName()" x-effect="updateSelectedName()">
                                        <flux:dropdown position="bottom" align="start" class="block w-full">

                                            <flux:button type="button" icon-trailing="chevron-down"
                                                class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                                <span x-text="selectedName || @js(__('Selecteer een waarderingscategorie'))"
                                                    class="min-w-0 truncate text-left"></span>
                                            </flux:button>

                                            <flux:menu
                                                class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">
                                                @foreach ($this->conditions as $condition)
                                                    @php
                                                        $translation = $condition->translationWithFallback();
                                                        $conditionName = $translation?->name ?? $condition->slug;
                                                    @endphp

                                                    <flux:menu.item as="button" type="button"
                                                        data-condition-id="{{ $condition->id }}"
                                                        x-on:click="
                        selectedId = @js((string) $condition->id);
                        selectedName = @js($conditionName);
                    "
                                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                                        {{ $conditionName }}
                                                    </flux:menu.item>
                                                @endforeach
                                            </flux:menu>

                                        </flux:dropdown>
                                    </div>

                                    <flux:error name="conditionRules.{{ $index }}.condition_id" />
                                </flux:field>

                                {{-- Uitgiftejaren --}}
                                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">

                                    {{-- Beginjaar --}}
                                    <flux:field>
                                        <flux:label>
                                            {{ __('Beginjaar') }}
                                        </flux:label>

                                        <div x-data="{
                                            increase() {
                                                    let value = Number($wire.conditionRules[{{ $index }}].issue_year_from || 0);
                                                    $wire.conditionRules[{{ $index }}].issue_year_from = Math.min(9999, value + 1);
                                                },
                                                decrease() {
                                                    let value = Number($wire.conditionRules[{{ $index }}].issue_year_from || 0);
                                                    $wire.conditionRules[{{ $index }}].issue_year_from = Math.max(0, value - 1);
                                                }
                                        }" class="relative">
                                            <input type="number"
                                                wire:model="conditionRules.{{ $index }}.issue_year_from"
                                                min="0" max="9999"
                                                placeholder="{{ __('Bijvoorbeeld: 1949') }}"
                                                class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]" />

                                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                                <button type="button" x-on:click="increase()"
                                                    aria-label="{{ __('Jaar verhogen') }}"
                                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                    <flux:icon.chevron-up class="size-3" />
                                                </button>

                                                <button type="button" x-on:click="decrease()"
                                                    aria-label="{{ __('Jaar verlagen') }}"
                                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                    <flux:icon.chevron-down class="size-3" />
                                                </button>

                                            </div>
                                        </div>

                                        <flux:error name="conditionRules.{{ $index }}.issue_year_from" />
                                    </flux:field>

                                    {{-- Eindjaar --}}
                                    <flux:field>
                                        <flux:label>
                                            {{ __('Eindjaar') }}
                                        </flux:label>

                                        <div x-data="{
                                            increase() {
                                                    let value = Number($wire.conditionRules[{{ $index }}].issue_year_to || 0);
                                                    $wire.conditionRules[{{ $index }}].issue_year_to = Math.min(9999, value + 1);
                                                },
                                                decrease() {
                                                    let value = Number($wire.conditionRules[{{ $index }}].issue_year_to || 0);
                                                    $wire.conditionRules[{{ $index }}].issue_year_to = Math.max(0, value - 1);
                                                }
                                        }" class="relative">
                                            <input type="number"
                                                wire:model="conditionRules.{{ $index }}.issue_year_to"
                                                min="0" max="9999"
                                                placeholder="{{ __('Bijvoorbeeld: 1990') }}"
                                                class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]" />

                                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                                <button type="button" x-on:click="increase()"
                                                    aria-label="{{ __('Jaar verhogen') }}"
                                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                    <flux:icon.chevron-up class="size-3" />
                                                </button>

                                                <button type="button" x-on:click="decrease()"
                                                    aria-label="{{ __('Jaar verlagen') }}"
                                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                    <flux:icon.chevron-down class="size-3" />
                                                </button>

                                            </div>
                                        </div>

                                        <flux:error name="conditionRules.{{ $index }}.issue_year_to" />
                                    </flux:field>

                                </div>

                            </div>
                        @endforeach

                    </div>

                @endif

            </div>

        </section>

        {{-- Formulieracties catalogus --}}
        <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row border border-theme-accent-1 bg-brown-6 p-5">

            <flux:button :href="route('backend.catalogs.index')" variant="ghost" icon="x-mark">
                {{ __('Annuleren') }}
            </flux:button>

            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled"
                wire:target="save" class="bg-brown-2">
                {{ __('Catalogus opslaan') }}
            </flux:button>

        </div>
    </form>

    {{-- Modal voor nieuw land of gebied --}}
    <flux:modal wire:model.self="showCountryModal" name="create-country" class="w-full max-w-3xl bg-brown-3!">

        <div class="flex flex-col gap-6 rounded-xl bg-brown-6 p-4 text-zinc-900">
            <div class="flex flex-col gap-6 rounded-xl bg-brown-6 p-4 text-zinc-900">

                <div>
                    <flux:heading size="lg">
                        {{ __('Nieuw land of gebied toevoegen') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een nieuw land of gebied toe zonder het catalogusformulier te verlaten.') }}
                    </flux:text>
                </div>

                {{-- Basisgegevens nieuw land of gebied --}}
                <section class="rounded-xl border border-theme-accent-1 bg-brown-6">

                    <div class="border-b border-theme-accent-1 px-4 py-3">
                        <flux:heading size="lg">
                            {{ __('Basisgegevens') }}
                        </flux:heading>

                        <flux:text class="mt-1">
                            {{ __('Algemene gegevens van het land of gebied.') }}
                        </flux:text>
                    </div>

                    <div class="grid items-start gap-5 p-4 md:grid-cols-2">

                        {{-- ISO-code --}}
                        <div>
                            <flux:field>
                                <flux:label>
                                    {{ __('ISO-code') }}
                                </flux:label>

                                <input type="text" wire:model="newCountryIsoCode" maxlength="3"
                                    placeholder="{{ __('Bijvoorbeeld: NL') }}"
                                    class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />

                                <flux:error name="newCountryIsoCode" />
                            </flux:field>

                            <p class="mt-2 text-sm text-zinc-600">
                                {{ __('Optioneel. Maximaal drie tekens.') }}
                            </p>
                        </div>

                        {{-- Type entiteit --}}
                        <div>
                            <flux:field>
                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Type entiteit') }}
                                        <span class="text-red-600">*</span>
                                    </span>
                                </flux:label>

                                <div x-data="{ selectedType: $wire.entangle('newCountryEntityType') }" class="w-full">
                                    <flux:dropdown position="bottom" align="start" class="block w-full">

                                        <flux:button type="button" icon-trailing="chevron-down"
                                            class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                            <span class="min-w-0 truncate text-left"
                                                x-text="@js(collect($this->entityTypes)->mapWithKeys(fn($label, $value) => [(string) $value => __($label)])->all())[selectedType] ?? @js(__('Selecteer een type'))"></span>
                                        </flux:button>

                                        <flux:menu
                                            class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">
                                            @foreach ($this->entityTypes as $value => $label)
                                                <flux:menu.item as="button" type="button"
                                                    x-on:click="selectedType = @js((string) $value)"
                                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                                    {{ __($label) }}
                                                </flux:menu.item>
                                            @endforeach
                                        </flux:menu>

                                    </flux:dropdown>
                                </div>

                                <flux:error name="newCountryEntityType" />
                            </flux:field>
                        </div>

                        {{-- Beginjaar --}}
                        <flux:field>
                            <flux:label>
                                {{ __('Beginjaar') }}
                            </flux:label>

                            <div x-data="{
                                increase() {
                                        let value = Number($wire.newCountryStartYear || 0);
                                        $wire.newCountryStartYear = Math.min(9999, value + 1);
                                    },
                                    decrease() {
                                        let value = Number($wire.newCountryStartYear || 0);
                                        $wire.newCountryStartYear = Math.max(0, value - 1);
                                    }
                            }" class="relative">
                                <input type="number" wire:model="newCountryStartYear" min="0" max="9999"
                                    placeholder="{{ __('Bijvoorbeeld: 1949') }}"
                                    class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]" />

                                <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                    <button type="button" x-on:click="increase()"
                                        aria-label="{{ __('Jaar verhogen') }}"
                                        class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                        <flux:icon.chevron-up class="size-3" />
                                    </button>

                                    <button type="button" x-on:click="decrease()"
                                        aria-label="{{ __('Jaar verlagen') }}"
                                        class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                        <flux:icon.chevron-down class="size-3" />
                                    </button>

                                </div>
                            </div>

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

                            <div x-data="{
                                increase() {
                                        let value = Number($wire.newCountryEndYear || 0);
                                        $wire.newCountryEndYear = Math.min(9999, value + 1);
                                    },
                                    decrease() {
                                        let value = Number($wire.newCountryEndYear || 0);
                                        $wire.newCountryEndYear = Math.max(0, value - 1);
                                    }
                            }" class="relative">
                                <input type="number" wire:model="newCountryEndYear" min="0" max="9999"
                                    placeholder="{{ __('Bijvoorbeeld: 1990') }}"
                                    class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]" />

                                <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                    <button type="button" x-on:click="increase()"
                                        aria-label="{{ __('Jaar verhogen') }}"
                                        class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                        <flux:icon.chevron-up class="size-3" />
                                    </button>

                                    <button type="button" x-on:click="decrease()"
                                        aria-label="{{ __('Jaar verlagen') }}"
                                        class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                        <flux:icon.chevron-down class="size-3" />
                                    </button>

                                </div>
                            </div>

                            <flux:description>
                                {{ __('Laat leeg als de entiteit nog bestaat of het eindjaar onbekend is.') }}
                            </flux:description>

                            <flux:error name="newCountryEndYear" />
                        </flux:field>

                        {{-- Actief --}}
                        <div class="md:col-span-2">
                            <label class="flex items-start gap-3">

                                <flux:checkbox wire:model="newCountryIsActive"
                                    class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                                <span>
                                    <span class="block text-sm font-medium text-zinc-900">
                                        {{ __('Actief') }}
                                    </span>

                                    <span class="mt-1 block text-sm text-zinc-600">
                                        {{ __('Geef aan of deze entiteit actief in de catalogus gebruikt mag worden.') }}
                                    </span>
                                </span>

                            </label>
                        </div>

                    </div>
                </section>

                {{-- Vertalingen nieuw land of gebied --}}
                <section class="rounded-xl border border-theme-accent-1 bg-brown-6">

                    <div class="border-b border-theme-accent-1 px-4 py-3">
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
                                class="grid gap-5 border-b border-theme-accent-1 pb-6 last:border-b-0 last:pb-0 md:grid-cols-2">

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

                                    <input type="text"
                                        wire:model="newCountryTranslations.{{ $locale }}.name" maxlength="100"
                                        @required($locale === app()->getLocale()) placeholder="{{ $languageName }}"
                                        class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />

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

                                    <textarea wire:model="newCountryTranslations.{{ $locale }}.description" rows="3"
                                        class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5"></textarea>

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
                        wire:click="$set('showCountryModal', false)"
                        class="border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                        {{ __('Annuleren') }}
                    </flux:button>

                    <flux:button type="button" variant="primary" icon="check" wire:click="saveCountry"
                        class="bg-brown-2!">
                        {{ __('Land of gebied opslaan') }}
                    </flux:button>

                </div>

            </div>
    </flux:modal>
</div>
