<x-layouts::app :title="__('Postzegel bewerken')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center bg-brown-6 rounded-lg border border-theme-accent-1 p-5">
            <div>
                <flux:heading size="xl">
                    {{ __('Postzegel bewerken') }}: {{ $stamp->name }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Pas de gegevens van deze postzegel aan.') }}
                </flux:text>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle" class="mb-6">
                {{ session('success') }}
            </flux:callout>
        @endif

        {{-- Validatiefouten --}}
        @if ($errors->any())
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Formulier --}}
        <form method="POST" action="{{ route('backend.stamps.update', $stamp) }}"
            class="flex flex-col gap-6 rounded-lg border border-theme-accent-1 bg-brown-6 p-4 md:p-6" novalidate>
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">
                <flux:heading size="lg">
                    {{ __('Algemene gegevens') }}
                </flux:heading>

                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Land --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Land') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        @php
                            $selectedCountryId = old('country_id', $stamp->country_id);
                            $selectedCountry = $countries->firstWhere('id', $selectedCountryId);
                        @endphp

                        <div x-data="{
                            selectedId: @js((string) ($selectedCountryId ?? '')),
                            selectedName: @js($selectedCountry?->name ?? __('Selecteer een land'))
                        }">

                            <input type="hidden" name="country_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                        selectedId = '';
                        selectedName = @js(__('Selecteer een land'));
                        $dispatch('stamp-country-changed', { countryId: 0 });
                    "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Selecteer een land') }}

                                    </flux:menu.item>

                                    @foreach ($countries as $country)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                            selectedId = @js((string) $country->id);
                            selectedName = @js($country->name);
                            $dispatch('stamp-country-changed', { countryId: {{ $country->id }} });
                        "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $country->name }}

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        <div class="min-h-5">
                            <flux:error name="country_id" />
                        </div>
                    </flux:field>

                    {{-- Postzegelserie --}}
                    <flux:field>
                        <flux:label>{{ __('Postzegelserie') }}</flux:label>

                        @php
                            $selectedSeriesId = old('stamp_series_id', $stamp->stamp_series_id);
                            $selectedSeries = $series->firstWhere('id', $selectedSeriesId);
                        @endphp

                        <div x-data="{
                            selectedId: @js((string) ($selectedSeriesId ?? '')),
                            selectedName: @js($selectedSeries?->name ?? __('Geen serie'))
                        }">

                            <input type="hidden" name="stamp_series_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                        selectedId = '';
                        selectedName = @js(__('Geen serie'));
                    "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Geen serie') }}

                                    </flux:menu.item>

                                    @foreach ($series as $item)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                            selectedId = @js((string) $item->id);
                            selectedName = @js($item->name);
                        "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $item->name }}

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        <div class="min-h-5">
                            <flux:error name="stamp_series_id" />
                        </div>
                    </flux:field>

                    {{-- Waarschuwing bij verwijderen van catalogusgegevens --}}
                    @php
                        $existingCatalogCountryIds = $stamp->catalogEntries
                            ->map(
                                fn($entry) => $entry->catalogEdition?->catalog?->countries
                                    ?->pluck('id')
                                    ->map(fn($id) => (int) $id)
                                    ->values()
                                    ->all() ?? [],
                            )
                            ->values()
                            ->all();
                    @endphp

                    <div class="md:col-span-2" x-data="{
                        countryId: @js((int) old('country_id', $stamp->country_id)),
                        existingCatalogCountryIds: @js($existingCatalogCountryIds),
                    
                        get willRemoveCatalogEntries() {
                            if (!this.countryId) {
                                return false;
                            }
                    
                            return this.existingCatalogCountryIds.some(
                                countryIds => !countryIds.includes(this.countryId)
                            );
                        }
                    }"
                        x-on:stamp-country-changed.window="countryId = $event.detail.countryId"
                        x-show="willRemoveCatalogEntries" x-cloak>
                        <div role="alert" class="rounded-lg border border-amber-400 bg-amber-50 p-4 text-amber-900">
                            <div class="flex items-start gap-3">
                                <flux:icon.exclamation-triangle class="mt-0.5 size-5 shrink-0" />

                                <div>
                                    <p class="font-semibold">
                                        {{ __('Let op: catalogusgegevens worden verwijderd') }}
                                    </p>

                                    <p class="mt-1 text-sm">
                                        {{ __('Een of meer bestaande cataloguskoppelingen horen niet bij het geselecteerde land. Wanneer je de postzegel opslaat, worden deze cataloguskoppelingen en de bijbehorende cataloguswaarden verwijderd.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Thema's --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __("Thema's") }}</flux:label>

                        @php
                            $selectedThemeIds = array_map(
                                'strval',
                                old('theme_ids', $stamp->themes->pluck('id')->all()),
                            );
                        @endphp

                        <div
                            class="grid grid-cols-1 gap-3 rounded-lg border border-theme-accent-3 bg-brown-5 p-4 sm:grid-cols-2 lg:grid-cols-3">
                            @forelse ($themes as $theme)
                                @php
                                    $translation = $theme->translationWithFallback();
                                    $themeName = $translation?->name ?? $theme->name;
                                @endphp

                                <flux:checkbox name="theme_ids[]" value="{{ $theme->id }}"
                                    :checked="in_array((string) $theme->id, $selectedThemeIds, true)"
                                    :label="$themeName"
                                    class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Geen thema’s gevonden') }}
                                </p>
                            @endforelse
                        </div>

                        <div class="min-h-5">
                            <flux:error name="theme_ids" />
                            <flux:error name="theme_ids.*" />
                        </div>
                    </flux:field>



                    {{-- Uitgiftejaar --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Uitgiftejaar') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <div x-data="{
                            year: @js(old('issue_year', $stamp->issue_year)),
                            minYear: 1840,
                            maxYear: 2200,
                        
                            updateYear() {
                                $dispatch('stamp-issue-year-changed', {
                                    year: this.year
                                });
                            },
                        
                            increase() {
                                if (this.year === '') {
                                    this.year = this.minYear;
                                } else {
                                    this.year = Math.min(this.maxYear, Number(this.year) + 1);
                                }
                        
                                this.updateYear();
                            },
                        
                            decrease() {
                                if (this.year === '') {
                                    this.year = this.minYear;
                                } else {
                                    this.year = Math.max(this.minYear, Number(this.year) - 1);
                                }
                        
                                this.updateYear();
                            }
                        }" class="relative">

                            <input type="number" name="issue_year" min="1840" max="2200" x-model="year"
                                x-on:input="updateYear()"
                                class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                <button type="button" x-on:click="increase()" aria-label="{{ __('Jaar verhogen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">

                                    <flux:icon.chevron-up class="size-3" />
                                </button>

                                <button type="button" x-on:click="decrease()" aria-label="{{ __('Jaar verlagen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">

                                    <flux:icon.chevron-down class="size-3" />
                                </button>

                            </div>
                        </div>

                        <div class="min-h-5">
                            <flux:error name="issue_year" />
                        </div>
                    </flux:field>

                    {{-- Uitgiftedatum --}}
                    <x-forms.date-picker name="issue_date" :label="__('Uitgiftedatum')" :value="old('issue_date', $stamp->issue_date?->format('Y-m-d'))" />

                    {{-- Nominale waarde --}}
                    <flux:field>
                        <flux:label>{{ __('Nominale waarde') }}</flux:label>

                        <div x-data="{
                            value: @js(old('face_value', $stamp->face_value) ?? ''),
                        
                            adjust(amount) {
                                const current = parseFloat(this.value) || 0;
                        
                                this.value = Math.max(
                                    0,
                                    Math.round((current + amount) * 100) / 100
                                ).toFixed(2);
                            }
                        }" class="relative w-full">

                            <input type="number" name="face_value" min="0" step="0.01" x-model="value"
                                class="quantity-input block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 pr-10 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30">

                            {{-- Pijltjes binnen het invoerveld --}}
                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                {{-- Nominale waarde verhogen --}}
                                <button type="button" x-on:click="adjust(0.01)"
                                    aria-label="{{ __('Nominale waarde verhogen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">

                                    <flux:icon.chevron-up class="size-3" />
                                </button>

                                {{-- Nominale waarde verlagen --}}
                                <button type="button" x-on:click="adjust(-0.01)"
                                    :disabled="value === '' || (parseFloat(value) || 0) <= 0"
                                    aria-label="{{ __('Nominale waarde verlagen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5 disabled:cursor-not-allowed disabled:opacity-40">

                                    <flux:icon.chevron-down class="size-3" />
                                </button>

                            </div>
                        </div>

                        <flux:error name="face_value" />

                    </flux:field>

                    {{-- Valuta --}}
                    <flux:field>
                        <flux:label>{{ __('Valuta') }}</flux:label>

                        <input type="text" name="currency" id="currency"
                            value="{{ old('currency', $stamp->currency) }}"
                            class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                        <flux:error name="currency" />
                    </flux:field>

                    {{-- Ontwerper --}}
                    <flux:field>
                        <flux:label>{{ __('Ontwerper') }}</flux:label>

                        <input type="text" name="designer" id="designer"
                            value="{{ old('designer', $stamp->designer) }}"
                            class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                        <flux:error name="designer" />
                    </flux:field>

                    {{-- Druktechniek --}}
                    <flux:field>
                        <flux:label>{{ __('Druktechniek') }}</flux:label>

                        <input type="text" name="printing_method" id="printing_method"
                            value="{{ old('printing_method', $stamp->printing_method) }}"
                            class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />
                        <flux:error name="printing_method" />
                    </flux:field>

                    {{-- Basispostzegel --}}
                    <flux:field>
                        <flux:label>{{ __('Basispostzegel') }}</flux:label>

                        @php
                            $selectedParentId = old('parent_stamp_id', $stamp->parent_stamp_id);
                            $selectedParent = $parentStamps->firstWhere('id', $selectedParentId);
                        @endphp

                        <div x-data="{
                            selectedId: @js((string) ($selectedParentId ?? '')),
                            selectedName: @js($selectedParent?->name ?? __('Geen basispostzegel'))
                        }">

                            <input type="hidden" name="parent_stamp_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                </flux:button>

                                <flux:menu
                                    class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Geen basispostzegel --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="selectedId = ''; selectedName = @js(__('Geen basispostzegel'));"
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Geen basispostzegel') }}

                                    </flux:menu.item>

                                    {{-- Beschikbare basispostzegels --}}
                                    @foreach ($parentStamps as $parentStamp)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedId = @js((string) $parentStamp->id); selectedName = @js($parentStamp->name);"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $parentStamp->name }}

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>

                        <div class="min-h-5">
                            <flux:error name="parent_stamp_id" />
                        </div>
                    </flux:field>

                    {{-- Catalogusreferentie --}}
                    <flux:field>
                        <flux:label>{{ __('Catalogusreferentie') }}</flux:label>

                        <input type="text" name="catalog_reference" id="catalog_reference"
                            value="{{ old('catalog_reference', $stamp->catalog_reference) }}"
                            class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                        <div class="min-h-5">
                            <flux:error name="catalog_reference" />
                        </div>
                    </flux:field>

                    @php
                        // Koppel bestaande catalogusvermeldingen aan hun cataloguseditie.
                        $catalogEntriesByEdition = $stamp->catalogEntries->keyBy('catalog_edition_id');
                    @endphp

                    {{-- Bestaande cataloguskoppelingen --}}
                    @if ($catalogEditions->isNotEmpty())
                        <div class="md:col-span-2 rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

                            <flux:heading size="sm">
                                {{ __('Bestaande catalogusnummers en cataloguswaarden') }}
                            </flux:heading>

                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-theme-accent-1 text-left">
                                            <th class="px-3 py-2">{{ __('Catalogus') }}</th>
                                            <th class="px-3 py-2">{{ __('Cataloguseditie') }}</th>
                                            <th class="px-3 py-2">{{ __('Catalogusnummer') }}</th>
                                            @foreach ($catalogConditionRules->pluck('condition')->filter()->unique('id') as $condition)
                                                <th class="px-3 py-2">
                                                    {{ $condition->translationWithFallback()?->name ?? $condition->slug }}
                                                </th>
                                            @endforeach
                                            <th class="px-3 py-2">{{ __('Status') }}</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($catalogEditions as $edition)
                                            @php
                                                // Zoek de bestaande catalogusvermelding voor deze editie.
                                                $entry = $catalogEntriesByEdition->get($edition->id);

                                                // Bepaal welke waarderingsregels gelden voor deze cataloguseditie.
                                                $applicableConditionRules = $catalogConditionRules->filter(function (
                                                    $rule,
                                                ) use ($edition, $stamp) {
                                                    return $rule->catalog_id === $edition->catalog_id &&
                                                        ($rule->country_id === null ||
                                                            $rule->country_id === $stamp->country_id) &&
                                                        ($rule->issue_year_from === null ||
                                                            $stamp->issue_year >= $rule->issue_year_from) &&
                                                        ($rule->issue_year_to === null ||
                                                            $stamp->issue_year <= $rule->issue_year_to);
                                                });

                                                // Zoek de bestaande cataloguswaarden per conditie.
                                                $catalogValuesByCondition =
                                                    $entry?->values->keyBy(fn($value) => $value->condition?->slug) ??
                                                    collect();
                                            @endphp

                                            <tr class="border-b border-theme-accent-1/30" x-show="isCatalogVisible"
                                                x-cloak x-data="{
                                                    issueYear: @js((int) old('issue_year', $stamp->issue_year)),
                                                    countryId: @js((int) old('country_id', $stamp->country_id)),
                                                
                                                    catalogId: @js((int) $edition->catalog_id),
                                                
                                                    catalogCountryIds: @js($edition->catalog->countries->pluck('id')->map(fn($id) => (int) $id)->values()->all()),
                                                
                                                    get isCatalogVisible() {
                                                        return this.catalogCountryIds.includes(this.countryId);
                                                    },
                                                
                                                    hasEntry: @js($entry !== null),
                                                    catalogNumber: @js(old("catalog_editions.{$edition->id}.catalog_number", $entry?->catalog_number) ?? ''),
                                                
                                                    get isLinked() {
                                                        return this.hasEntry || this.catalogNumber.trim() !== '';
                                                    },
                                                    rules: @js(
    $catalogConditionRules->where('catalog_id', $edition->catalog_id)->values()->map(
        fn($rule) => [
            'condition_id' => $rule->condition_id,
            'country_id' => $rule->country_id,
            'issue_year_from' => $rule->issue_year_from,
            'issue_year_to' => $rule->issue_year_to,
        ],
    ),
),
                                                
                                                    get applicableConditionIds() {
                                                        if (!Number.isInteger(this.issueYear) ||
                                                            this.issueYear < 1840 ||
                                                            this.issueYear > 2200) {
                                                            return [];
                                                        }
                                                
                                                        return [...new Set(
                                                            this.rules
                                                            .filter(rule =>
                                                                (rule.country_id === null ||
                                                                    Number(rule.country_id) === this.countryId) &&
                                                                (rule.issue_year_from === null ||
                                                                    this.issueYear >= Number(rule.issue_year_from)) &&
                                                                (rule.issue_year_to === null ||
                                                                    this.issueYear <= Number(rule.issue_year_to))
                                                            )
                                                            .map(rule => Number(rule.condition_id))
                                                        )];
                                                    },
                                                
                                                    get requiredCount() {
                                                        return this.applicableConditionIds.length;
                                                    },
                                                
                                                    catalogValues: @js($entry?->values->mapWithKeys(fn($value) => [$value->condition_id => $value->catalog_value])->all() ?? []),
                                                
                                                    get filledCount() {
                                                        return this.applicableConditionIds.filter(conditionId => {
                                                            const value = this.catalogValues[conditionId];
                                                
                                                            return value !== null &&
                                                                value !== undefined &&
                                                                value !== '';
                                                        }).length;
                                                    },
                                                
                                                    get catalogStatus() {
                                                        if (!this.isLinked) {
                                                            return 'not_linked';
                                                        }
                                                
                                                        if (this.requiredCount === 0) {
                                                            return 'no_rules';
                                                        }
                                                
                                                        return this.filledCount === this.requiredCount ?
                                                            'complete' :
                                                            'incomplete';
                                                    }
                                                }"
                                                x-on:stamp-issue-year-changed.window="issueYear = $event.detail.year === ''? null: Number($event.detail.year);"
                                                x-on:stamp-country-changed.window="countryId = $event.detail.countryId;">
                                                <td class="px-3 py-2">
                                                    {{ $edition->catalog?->abbreviation ?: $edition->catalog?->name }}
                                                </td>

                                                <td class="px-3 py-2">
                                                    {{ $edition->edition_name }}
                                                </td>

                                                <td class="px-3 py-2">
                                                    <input type="text"
                                                        name="catalog_editions[{{ $edition->id }}][catalog_number]"
                                                        x-model="catalogNumber" x-bind:disabled="!isCatalogVisible"
                                                        class="block h-10 w-32 rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3"
                                                        aria-label="{{ __('Catalogusnummer') }}">
                                                </td>

                                                {{-- Dynamische cataloguswaarden --}}
                                                @foreach ($catalogConditionRules->pluck('condition')->filter()->unique('id') as $condition)
                                                    @php
                                                        $conditionSlug = $condition->slug;

                                                        $conditionName =
                                                            $condition->translationWithFallback()?->name ??
                                                            $conditionSlug;

                                                        $existingValue = $catalogValuesByCondition->get($conditionSlug)
                                                            ?->catalog_value;

                                                        $isApplicable = $applicableConditionRules->contains(
                                                            'condition_id',
                                                            $condition->id,
                                                        );
                                                    @endphp

                                                    <td class="px-3 py-2" x-data="{
                                                        issueYear: @js((int) old('issue_year', $stamp->issue_year)),
                                                    
                                                        rules: @js(
    $catalogConditionRules->where('catalog_id', $edition->catalog_id)->where('condition_id', $condition->id)->values()->map(
        fn($rule) => [
            'country_id' => $rule->country_id,
            'issue_year_from' => $rule->issue_year_from,
            'issue_year_to' => $rule->issue_year_to,
        ],
    ),
),
                                                    
                                                        countryId: @js((int) old('country_id', $stamp->country_id)),
                                                    
                                                        get isApplicable() {
                                                            if (!Number.isInteger(this.issueYear) || this.issueYear < 1840 || this.issueYear > 2200) {
                                                                return false;
                                                            }
                                                    
                                                            return this.rules.some(rule =>
                                                                (rule.country_id === null || Number(rule.country_id) === this.countryId) &&
                                                                (rule.issue_year_from === null || this.issueYear >= Number(rule.issue_year_from)) &&
                                                                (rule.issue_year_to === null || this.issueYear <= Number(rule.issue_year_to))
                                                            );
                                                        }
                                                    }"
                                                        x-on:stamp-issue-year-changed.window="issueYear = $event.detail.year === ''? null : Number($event.detail.year);"
                                                        x-on:stamp-country-changed.window="countryId = $event.detail.countryId;">
                                                        <div x-show="isApplicable" x-cloak>
                                                            <div x-data="{
                                                                value: (() => {
                                                                    const initial = @js(old("catalog_editions.{$edition->id}.values.{$conditionSlug}", $existingValue));
                                                            
                                                                    return initial === null || initial === '' ?
                                                                        '' :
                                                                        Number(initial).toFixed(2);
                                                                })(),
                                                            
                                                                increase() {
                                                                    const cents = Math.round(Number(this.value || 0) * 100);
                                                                    this.value = ((cents + 1) / 100).toFixed(2);
                                                            
                                                                    catalogValues[{{ $condition->id }}] = this.value;
                                                                },
                                                            
                                                                decrease() {
                                                                    const cents = Math.round(Number(this.value || 0) * 100);
                                                                    this.value = (Math.max(0, cents - 1) / 100).toFixed(2);
                                                            
                                                                    catalogValues[{{ $condition->id }}] = this.value;
                                                                }
                                                            }" class="relative w-32">
                                                                <input type="text"
                                                                    name="catalog_editions[{{ $edition->id }}][values][{{ $conditionSlug }}]"
                                                                    x-model="value" inputmode="decimal"
                                                                    x-on:input="catalogValues[{{ $condition->id }}] = value"
                                                                    x-bind:disabled="!isApplicable || !isCatalogVisible"
                                                                    aria-label="{{ $conditionName }}"
                                                                    class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                                                                <div
                                                                    class="absolute inset-y-0 right-1 flex flex-col justify-center">
                                                                    <button type="button" x-on:click="increase()"
                                                                        aria-label="{{ __('Cataloguswaarde verhogen') }}"
                                                                        class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                                        <flux:icon.chevron-up class="size-3" />
                                                                    </button>

                                                                    <button type="button" x-on:click="decrease()"
                                                                        aria-label="{{ __('Cataloguswaarde verlagen') }}"
                                                                        class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                                        <flux:icon.chevron-down class="size-3" />
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <span x-show="!isApplicable" x-cloak class="text-zinc-400">
                                                            —
                                                        </span>
                                                    </td>
                                                @endforeach

                                                {{-- Status van de cataloguswaarden --}}
                                                <td class="px-3 py-2">

                                                    <span x-show="catalogStatus === 'not_linked'" x-cloak
                                                        class="font-medium text-zinc-500 dark:text-zinc-400">
                                                        {{ __('Niet gekoppeld') }}
                                                    </span>

                                                    <span x-show="catalogStatus === 'complete'" x-cloak
                                                        class="font-medium text-green-600 dark:text-green-400">
                                                        {{ __('Compleet') }}
                                                    </span>

                                                    <span x-show="catalogStatus === 'incomplete'" x-cloak
                                                        class="font-medium text-amber-600 dark:text-amber-400">
                                                        {{ __('Onvolledig') }}
                                                    </span>

                                                    <span x-show="catalogStatus === 'no_rules'" x-cloak
                                                        class="font-medium text-zinc-500 dark:text-zinc-400">
                                                        {{ __('Geen waarderingsregels') }}
                                                    </span>

                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    @endif


                    {{-- Notities --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __('Notities') }}</flux:label>

                        <textarea name="notes" id="notes" rows="4"
                            class="block w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 py-2 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30">{{ old('notes', $stamp->notes) }}</textarea>
                        <flux:error name="notes" />
                    </flux:field>

                </div>
            </div>

            {{-- Vertalingen --}}
            <section class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

                <flux:heading size="lg">
                    {{ __('Vertalingen') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de naam en beschrijving van de postzegel per taal.') }}
                </flux:text>

                @php
                    $languageNames = [
                        'nl' => 'Nederlands',
                        'en' => 'English',
                        'de' => 'Deutsch',
                        'fr' => 'Français',
                        'es' => 'Español',
                    ];
                @endphp

                <div class="mt-6 space-y-6">

                    @foreach ($locales as $locale)
                        @php
                            $translation = $stamp->translations->firstWhere('locale', $locale);

                            $name = $translation?->name ?? ($locale === app()->getLocale() ? $stamp->name : '');

                            $description =
                                $translation?->description ??
                                ($locale === app()->getLocale() ? $stamp->description : '');
                        @endphp

                        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

                            <flux:heading size="md" class="mb-4">
                                {{ $languageNames[$locale] }}
                            </flux:heading>

                            <div class="grid grid-cols-1 gap-4">

                                <flux:field>
                                    <flux:label>
                                        <span class="flex items-center gap-1">
                                            {{ __('Naam') }}


                                            @if ($locale === app()->getLocale())
                                                <span class="text-red-600">*</span>
                                            @endif
                                            <span>
                                    </flux:label>

                                    <input type="text" name="translations[{{ $locale }}][name]"
                                        id="translation-name-{{ $locale }}"
                                        value="{{ old('translations.' . $locale . '.name', $name) }}" maxlength="255"
                                        @required($locale === app()->getLocale())
                                        class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                                    <flux:error name="translations.{{ $locale }}.name" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>{{ __('Beschrijving') }}</flux:label>

                                    <textarea name="translations[{{ $locale }}][description]" id="translation-description-{{ $locale }}"
                                        rows="3"
                                        class="block w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 py-2 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30">{{ old('translations.' . $locale . '.description', $description) }}</textarea>

                                    <flux:error name="translations.{{ $locale }}.description" />
                                </flux:field>

                            </div>
                        </div>
                    @endforeach

                </div>
            </section>

            {{-- Acties --}}
            <div class="flex flex-wrap justify-end gap-3">
                <flux:button :href="route('backend.stamps.show', $stamp)" variant="ghost" icon="x-mark">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check"
                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                    {{ __('Wijzigingen opslaan') }}
                </flux:button>
            </div>

        </form>

        {{-- Postzegelafbeeldingen --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <section class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">
                <flux:heading size="lg">
                    {{ __('Postzegelafbeeldingen beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Voeg afbeeldingen toe of beheer de bestaande afbeeldingen van deze postzegel.') }}
                </flux:text>

                {{-- Reeds opgeslagen afbeeldingen --}}
                @if ($stamp->images->isNotEmpty())
                    <div class="mt-6">
                        <flux:heading size="md">
                            {{ __('Bestaande afbeeldingen') }}
                        </flux:heading>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($stamp->images as $image)
                                <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">
                                    <img src="{{ asset('storage/' . $image->path) }}"
                                        alt="{{ $image->alt_text ?: __('Postzegelafbeelding') }}"
                                        class="h-48 w-full rounded-lg object-contain" />

                                    <div class="mt-3">
                                        <span class="inline-flex rounded-md bg-brown-6 px-3 py-1 text-sm font-medium">
                                            @switch($image->image_type)
                                                @case('front')
                                                    {{ __('Voorzijde') }}
                                                @break

                                                @case('back')
                                                    {{ __('Achterzijde') }}
                                                @break

                                                @case('detail')
                                                    {{ __('Detail') }}
                                                @break

                                                @default
                                                    {{ $image->image_type }}
                                            @endswitch
                                        </span>
                                    </div>
                                    <flux:text class="mt-3 text-sm">
                                        {{ $image->alt_text ?: __('Geen beschrijving') }}
                                    </flux:text>

                                    {{-- Afbeeldingsgegevens wijzigen --}}
                                    <form action="{{ route('backend.stamps.images.update', [$stamp, $image]) }}"
                                        method="POST" enctype="multipart/form-data" class="mt-4 space-y-3">
                                        @csrf
                                        @method('PATCH')

                                        {{-- Afbeeldingstype wijzigen --}}
                                        <flux:field>
                                            <flux:label>{{ __('Afbeeldingstype wijzigen') }}</flux:label>

                                            <div x-data="{
                                                selectedType: @js($image->image_type),
                                                selectedName: @js(
    __(
        match ($image->image_type) {
            'back' => 'Achterzijde',
            'detail' => 'Detail',
            default => 'Voorzijde',
        },
    ),
)
                                            }">

                                                <input type="hidden" name="image_type" x-model="selectedType">

                                                <flux:dropdown position="bottom" align="start" class="block w-full">

                                                    <flux:button type="button" icon-trailing="chevron-down"
                                                        class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                                        <span x-text="selectedName"
                                                            class="min-w-0 truncate text-left"></span>

                                                    </flux:button>

                                                    <flux:menu
                                                        class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                                        <flux:menu.item as="button" type="button"
                                                            x-on:click="selectedType = 'front'; selectedName = @js(__('Voorzijde'));"
                                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                                            {{ __('Voorzijde') }}
                                                        </flux:menu.item>

                                                        <flux:menu.item as="button" type="button"
                                                            x-on:click="selectedType = 'back'; selectedName = @js(__('Achterzijde'));"
                                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                                            {{ __('Achterzijde') }}
                                                        </flux:menu.item>

                                                        <flux:menu.item as="button" type="button"
                                                            x-on:click="selectedType = 'detail'; selectedName = @js(__('Detail'));"
                                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                                            {{ __('Detail') }}
                                                        </flux:menu.item>

                                                    </flux:menu>

                                                </flux:dropdown>
                                            </div>
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>{{ __('Afbeeldingsbeschrijving wijzigen') }}</flux:label>

                                            <input type="text" name="alt_text"
                                                id="image-alt-text-{{ $image->id }}"
                                                value="{{ $image->alt_text }}" maxlength="255"
                                                class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />
                                        </flux:field>

                                        <flux:field>
                                            <flux:label>
                                                {{ __('Afbeelding vervangen (optioneel)') }}
                                            </flux:label>

                                            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"
                                                data-stamp-replacement-input
                                                data-preview-target="stamp-replacement-preview-{{ $image->id }}"
                                                class="block w-full rounded-lg border border-theme-accent-3! bg-brown-5! p-2 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30 file:mr-4 file:rounded-md file:border-0 file:bg-brown-6 file:px-3 file:py-1 file:text-sm file:text-zinc-900 file:cursor-pointer hover:file:bg-brown-5" />

                                            {{-- Preview van de vervangende afbeelding --}}
                                            <div id="stamp-replacement-preview-{{ $image->id }}"
                                                class="mt-4 hidden rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                                                <flux:text class="mb-3">
                                                    {{ __('Voorbeeld van de nieuwe afbeelding') }}
                                                </flux:text>

                                                <img alt="{{ __('Voorbeeld van de nieuwe afbeelding') }}"
                                                    class="max-h-64 max-w-full rounded-lg object-contain" />
                                            </div>

                                            <flux:description>
                                                {{ __('Laat dit veld leeg om de bestaande afbeelding te behouden.') }}
                                            </flux:description>
                                        </flux:field>

                                        <flux:button type="submit" variant="primary" icon="check"
                                            class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                                            {{ __('Wijzigingen opslaan') }}
                                        </flux:button>
                                    </form>


                                    {{-- Afbeelding verwijderen met Flux-bevestiging --}}
                                    <flux:modal.trigger :name="'delete-stamp-image-' . $image->id">
                                        <flux:button variant="danger" icon="trash" class="mt-3">
                                            {{ __('Afbeelding verwijderen') }}
                                        </flux:button>
                                    </flux:modal.trigger>

                                    <flux:modal :name="'delete-stamp-image-' . $image->id" class="md:w-96">
                                        <div class="space-y-6">
                                            <div>
                                                <flux:heading size="lg">
                                                    {{ __('Afbeelding verwijderen') }}
                                                </flux:heading>

                                                <flux:text class="mt-2">
                                                    {{ __('Weet je zeker dat je deze afbeelding wilt verwijderen? Dit kan niet ongedaan worden gemaakt.') }}
                                                </flux:text>
                                            </div>

                                            <div class="flex justify-end gap-3">
                                                <flux:modal.close>
                                                    <flux:button variant="ghost"
                                                        class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                                                        {{ __('Annuleren') }}
                                                    </flux:button>
                                                </flux:modal.close>

                                                <form
                                                    action="{{ route('backend.stamps.images.destroy', [$stamp, $image]) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('DELETE')

                                                    <flux:button type="submit" variant="danger" icon="trash"
                                                        class="border border-red-700! bg-red-600! text-white! hover:bg-red-700!">
                                                        {{ __('Definitief verwijderen') }}
                                                    </flux:button>
                                                </form>
                                            </div>
                                        </div>
                                    </flux:modal>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <flux:text class="mt-4">
                        {{ __('Er zijn nog geen afbeeldingen aan deze postzegel gekoppeld.') }}
                    </flux:text>
                @endif

                <form action="{{ route('backend.stamps.images.store', $stamp) }}" method="POST"
                    enctype="multipart/form-data" class="mt-6 space-y-4">
                    @csrf

                    <flux:field>
                        <flux:label>{{ __('Afbeelding') }}</flux:label>

                        <input type="file" id="stamp-image-input" name="image" accept=".jpg,.jpeg,.png,.webp"
                            required
                            class="block w-full rounded-lg border border-theme-accent-3! bg-brown-5! p-2 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30 file:mr-4 file:cursor-pointer file:rounded-md file:border-0 file:bg-brown-6 file:px-3 file:py-1 file:text-sm file:text-zinc-900 hover:file:bg-brown-5" />

                        {{-- Preview van de geselecteerde afbeelding --}}
                        <div id="stamp-image-preview-container"
                            class="mt-4 hidden rounded-lg border border-theme-accent-1 bg-brown-6 p-4">
                            <flux:text class="mb-3">
                                {{ __('Voorbeeld van de geselecteerde afbeelding') }}
                            </flux:text>

                            <img id="stamp-image-preview" src=""
                                alt="{{ __('Voorbeeld van de geselecteerde afbeelding') }}"
                                class="max-h-64 max-w-full rounded-lg object-contain" />
                        </div>

                        <flux:error name="image" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Afbeeldingstype') }}</flux:label>

                        {{-- Afbeeldingstype --}}
                        <flux:field>
                            <flux:label>{{ __('Afbeeldingstype') }}</flux:label>

                            <div x-data="{
                                selectedType: @js(old('image_type', 'front')),
                                selectedName: @js(
    __(
        match (old('image_type', 'front')) {
            'back' => 'Achterzijde',
            'detail' => 'Detail',
            default => 'Voorzijde',
        },
    ),
)
                            }">

                                <input type="hidden" name="image_type" x-model="selectedType">

                                <flux:dropdown position="bottom" align="start" class="block w-full">

                                    <flux:button type="button" icon-trailing="chevron-down"
                                        class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                        <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                    </flux:button>

                                    <flux:menu
                                        class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedType = 'front'; selectedName = @js(__('Voorzijde'));"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ __('Voorzijde') }}
                                        </flux:menu.item>

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedType = 'back'; selectedName = @js(__('Achterzijde'));"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ __('Achterzijde') }}
                                        </flux:menu.item>

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedType = 'detail'; selectedName = @js(__('Detail'));"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ __('Detail') }}
                                        </flux:menu.item>

                                    </flux:menu>

                                </flux:dropdown>
                            </div>

                            <flux:error name="image_type" />
                        </flux:field>

                        <flux:error name="image_type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Afbeeldingsbeschrijving') }}</flux:label>

                        <input type="text" name="alt_text" id="stamp-image-alt-text"
                            value="{{ old('alt_text') }}"
                            placeholder="{{ __('Beschrijf wat er op de afbeelding te zien is') }}" maxlength="255"
                            class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors placeholder:text-zinc-500 hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                        <flux:error name="alt_text" />
                    </flux:field>

                    <flux:button type="submit" variant="primary" icon="arrow-up-tray"
                        class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                        {{ __('Afbeelding uploaden') }}
                    </flux:button>
                </form>
            </section>

        </div>


        <script>
            // Preview van de geselecteerde postzegelafbeelding
            const stampImageInput = document.getElementById('stamp-image-input');
            const stampImagePreview = document.getElementById('stamp-image-preview');
            const stampImagePreviewContainer = document.getElementById(
                'stamp-image-preview-container'
            );

            let stampImagePreviewUrl = null;

            stampImageInput.addEventListener('change', function() {
                // Vorige preview opruimen
                if (stampImagePreviewUrl !== null) {
                    URL.revokeObjectURL(stampImagePreviewUrl);
                    stampImagePreviewUrl = null;
                }

                const file = this.files[0];

                if (!file || !file.type.startsWith('image/')) {
                    stampImagePreview.removeAttribute('src');
                    stampImagePreviewContainer.classList.add('hidden');
                    return;
                }

                // Tijdelijke URL voor de geselecteerde afbeelding maken
                stampImagePreviewUrl = URL.createObjectURL(file);

                stampImagePreview.src = stampImagePreviewUrl;
                stampImagePreviewContainer.classList.remove('hidden');
            });

            // Live preview bij het vervangen van bestaande afbeeldingen.
            document.querySelectorAll('[data-stamp-replacement-input]').forEach((input) => {
                const previewId = input.dataset.previewTarget;
                const previewContainer = document.getElementById(previewId);
                const previewImage = previewContainer.querySelector('img');

                let previewUrl = null;

                input.addEventListener('change', function() {
                    // Vorige tijdelijke preview opruimen.
                    if (previewUrl !== null) {
                        URL.revokeObjectURL(previewUrl);
                        previewUrl = null;
                    }

                    const file = this.files[0];

                    // Geen geldig afbeeldingsbestand geselecteerd.
                    if (!file || !file.type.startsWith('image/')) {
                        previewImage.removeAttribute('src');
                        previewContainer.classList.add('hidden');
                        return;
                    }

                    // Nieuwe afbeelding tonen zonder deze al te uploaden.
                    previewUrl = URL.createObjectURL(file);

                    previewImage.src = previewUrl;
                    previewContainer.classList.remove('hidden');
                });
            });
        </script>
</x-layouts::app>
