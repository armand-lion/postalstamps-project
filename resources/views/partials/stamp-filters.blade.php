        {{-- Filters --}}
        <form method="GET" action="{{ route($filterRoute) }}" @class([
            'flex flex-col gap-4 rounded-xl border border-theme-accent-1 bg-brown-6 p-4 dark:border-neutral-700',
            'sm:flex-row sm:items-end' => !($showAdvancedFilters ?? false),
        ])>

            @if ($showAdvancedFilters ?? false)
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            @endif

            {{-- Zoeken --}}
            <flux:field class="flex-1">
                <flux:label>
                    {{ __('Zoeken') }}
                </flux:label>

                <div class="relative w-full">
                    <flux:icon.magnifying-glass
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-zinc-500" />

                    <input type="search" name="search" value="{{ request('search') }}"
                        placeholder="{{ __('Zoek op naam of catalogusnummer') }}"
                        class="w-full rounded-lg border border-theme-accent-3 bg-brown-5 py-2 pr-3 pl-10 text-sm text-zinc-900
               placeholder:text-zinc-500
               focus:border-theme-accent-3
               focus:outline-none
               focus:ring-2
               focus:ring-theme-accent-3
               focus:ring-offset-2
               focus:ring-offset-brown-5" />
                </div>
            </flux:field>

            {{-- Land --}}
            <flux:field class="min-w-0 flex-1">
                <flux:label>
                    {{ __('Land') }}
                </flux:label>

                @php
                    $selectedCountryId = request('country_id');
                    $selectedCountry = $countries->firstWhere('id', $selectedCountryId);
                @endphp

                <div class="w-full min-w-0 max-w-full" x-data="{
                    selectedId: @js((string) ($selectedCountryId ?? '')),
                    selectedName: @js($selectedCountry?->translationWithFallback()?->name ?? __('Alle landen'))
                }">

                    <input type="hidden" name="country_id" x-model="selectedId"
                        x-effect="$dispatch('stamp-filter-country-changed', { countryId: selectedId })">

                    <flux:dropdown position="bottom" align="start" class="block w-full min-w-0 max-w-full">

                        <flux:button type="button" icon-trailing="chevron-down" icon-trailing:class="shrink-0!"
                            class="w-full! min-w-0! max-w-full! overflow-hidden justify-between! [&>span]:min-w-0 [&>span]:flex-1 border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                            <span x-text="selectedName" x-bind:title="selectedName"
                                class="block w-full truncate text-left"></span>
                        </flux:button>

                        <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                            <flux:menu.item as="button" type="button"
                                x-on:click="selectedId = ''; selectedName = @js(__('Alle landen'))"
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                {{ __('Alle landen') }}
                            </flux:menu.item>

                            @foreach ($countries as $country)
                                <flux:menu.item as="button" type="button"
                                    x-on:click="selectedId = @js((string) $country->id); selectedName = @js($country->translationWithFallback()?->name)"
                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                    {{ $country->translationWithFallback()?->name }}
                                </flux:menu.item>
                            @endforeach

                        </flux:menu>

                    </flux:dropdown>
                </div>
            </flux:field>

            {{-- Catalogus --}}
            <flux:field class="min-w-0 flex-1">
                <flux:label>
                    {{ __('Catalogus') }}
                </flux:label>

                @php
                    $selectedCatalogId = request('catalog_id');
                    $selectedCatalog = $catalogs->firstWhere('id', $selectedCatalogId);
                @endphp

                <div class="w-full min-w-0 max-w-full" x-data="{
                    selectedId: @js((string) ($selectedCatalogId ?? '')),
                    selectedName: @js($selectedCatalog?->name ?? __('Alle catalogi'))
                }">

                    <input type="hidden" name="catalog_id" x-model="selectedId">

                    <flux:dropdown position="bottom" align="start" class="block w-full min-w-0 max-w-full">

                        <flux:button type="button" icon-trailing="chevron-down" icon-trailing:class="shrink-0!"
                            class="w-full! min-w-0! max-w-full! overflow-hidden justify-between! [&>span]:min-w-0 [&>span]:flex-1 border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                            <span x-text="selectedName" x-bind:title="selectedName"
                                class="block w-full truncate text-left"></span>
                        </flux:button>

                        <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                            {{-- Alle catalogi --}}
                            <flux:menu.item as="button" type="button"
                                x-on:click="selectedId = ''; selectedName = @js(__('Alle catalogi'))"
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                {{ __('Alle catalogi') }}
                            </flux:menu.item>

                            {{-- Beschikbare catalogi --}}
                            @foreach ($catalogs as $catalog)
                                <flux:menu.item as="button" type="button"
                                    x-on:click="selectedId = @js((string) $catalog->id); selectedName = @js($catalog->name)"
                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                    {{ $catalog->name }}
                                </flux:menu.item>
                            @endforeach

                        </flux:menu>

                    </flux:dropdown>
                </div>
            </flux:field>

            {{-- Cataloguseditie --}}
            <flux:field class="min-w-0 flex-1">
                <flux:label>
                    {{ __('Cataloguseditie') }}
                </flux:label>

                @php
                    $selectedEditionId = request('catalog_edition_id');
                    $selectedEdition = $catalogEditions->firstWhere('id', $selectedEditionId);

                    $selectedEditionName = $selectedEdition
                        ? $selectedEdition->catalog?->name .
                            ' — ' .
                            $selectedEdition->edition_name .
                            ' (' .
                            $selectedEdition->edition_year .
                            ')'
                        : __('Alle edities');
                @endphp

                <div class="w-full min-w-0 max-w-full" x-data="{
                    selectedId: @js((string) ($selectedEditionId ?? '')),
                    selectedName: @js($selectedEditionName)
                }">

                    <input type="hidden" name="catalog_edition_id" x-model="selectedId">

                    <flux:dropdown position="bottom" align="start" class="block w-full min-w-0 max-w-full">

                        <flux:button type="button" icon-trailing="chevron-down" icon-trailing:class="shrink-0!"
                            class="w-full! min-w-0! max-w-full! overflow-hidden justify-between! [&>span]:min-w-0 [&>span]:flex-1 border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                            <span x-text="selectedName" x-bind:title="selectedName"
                                class="block w-full truncate text-left"></span>
                        </flux:button>

                        <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                            {{-- Alle edities --}}
                            <flux:menu.item as="button" type="button"
                                x-on:click="selectedId = ''; selectedName = @js(__('Alle edities'))"
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                {{ __('Alle edities') }}
                            </flux:menu.item>

                            {{-- Beschikbare catalogusedities --}}
                            @foreach ($catalogEditions as $edition)
                                @php
                                    $editionLabel =
                                        $edition->catalog?->name .
                                        ' — ' .
                                        $edition->edition_name .
                                        ' (' .
                                        $edition->edition_year .
                                        ')';
                                @endphp

                                <flux:menu.item as="button" type="button"
                                    x-on:click="selectedId = @js((string) $edition->id); selectedName = @js($editionLabel)"
                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                    {{ $editionLabel }}
                                </flux:menu.item>
                            @endforeach

                        </flux:menu>

                    </flux:dropdown>
                </div>
            </flux:field>

            {{-- Uitgiftejaar --}}
            <flux:field class="min-w-0 flex-1">
                <flux:label>
                    {{ __('Uitgiftejaar') }}
                </flux:label>

                <div x-data="{
                    year: @js(request('issue_year', '')),
                    minYear: 1840,
                    maxYear: 2200,

                    increase() {
                        if (this.year === '') {
                            this.year = this.maxYear;
                        } else {
                            this.year = Math.min(this.maxYear, Number(this.year) + 1);
                        }
                    },

                    decrease() {
                        if (this.year === '') {
                            this.year = this.maxYear;
                        } else {
                            this.year = Math.max(this.minYear, Number(this.year) - 1);
                        }
                    }
                }" class="relative w-full">

                    <input type="number" name="issue_year" min="1840" max="2200" x-model="year"
                        x-effect="$dispatch('stamp-filter-year-changed', { issueYear: String(year ?? '') })"
                        placeholder="{{ __('Bijvoorbeeld 2026') }}"
                        class="w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5
                   px-3 py-2 pr-10 text-sm text-zinc-900
                   placeholder:text-zinc-500
                   focus:border-theme-accent-3
                   focus:outline-none
                   focus:ring-2
                   focus:ring-theme-accent-3
                   focus:ring-offset-2
                   focus:ring-offset-brown-5
                   [&::-webkit-inner-spin-button]:appearance-none
                   [&::-webkit-outer-spin-button]:appearance-none
                   [-moz-appearance:textfield]" />

                    {{-- Pijltjes omhoog en omlaag --}}
                    <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                        <button type="button" x-on:click="increase()" aria-label="{{ __('Jaar verhogen') }}"
                            class="flex h-4 w-7 items-center justify-center rounded-t
                       border border-theme-accent-3 bg-brown-6 text-zinc-900
                       hover:bg-brown-5">
                            <flux:icon.chevron-up class="size-3" />
                        </button>

                        <button type="button" x-on:click="decrease()" aria-label="{{ __('Jaar verlagen') }}"
                            class="flex h-4 w-7 items-center justify-center rounded-b
                       border border-theme-accent-3 bg-brown-6 text-zinc-900
                       hover:bg-brown-5">
                            <flux:icon.chevron-down class="size-3" />
                        </button>

                    </div>
                </div>
            </flux:field>

            {{-- Sorteren --}}
            @if ($showSort ?? true)
                <flux:field class="min-w-0 flex-1">
                    <flux:label>
                        {{ __('Sorteren op') }}
                    </flux:label>

                    @php
                        $sortOptions = $sortOptions ?? [
                            'year_desc' => __('Uitgiftejaar (nieuwste eerst)'),
                            'year_asc' => __('Uitgiftejaar (oudste eerst)'),
                            'name_asc' => __('Naam (A-Z)'),
                            'name_desc' => __('Naam (Z-A)'),
                            'catalog_number_asc' => __('Catalogusnummer (oplopend)'),
                        ];

                        $defaultSort = $defaultSort ?? 'year_desc';

                        $selectedSort = request('sort', $defaultSort);

                        if (!is_string($selectedSort) || !array_key_exists($selectedSort, $sortOptions)) {
                            $selectedSort = $defaultSort;
                        }

                        $selectedSortName = $sortOptions[$selectedSort];
                    @endphp

                    <div class="w-full min-w-0 max-w-full" x-data="{
                        selectedId: @js($selectedSort),
                        selectedName: @js($selectedSortName)
                    }">

                        <input type="hidden" name="sort" x-model="selectedId">

                        <flux:dropdown position="bottom" align="start" class="block w-full min-w-0 max-w-full">

                            <flux:button type="button" icon-trailing="chevron-down" icon-trailing:class="shrink-0!"
                                class="w-full! min-w-0! max-w-full! overflow-hidden justify-between! [&>span]:min-w-0 [&>span]:flex-1 border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                <span x-text="selectedName" x-bind:title="selectedName"
                                    class="block w-full truncate text-left"></span>
                            </flux:button>

                            <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                @foreach ($sortOptions as $value => $label)
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="selectedId = @js($value); selectedName = @js($label)"
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ $label }}
                                    </flux:menu.item>
                                @endforeach

                            </flux:menu>

                        </flux:dropdown>
                    </div>
                </flux:field>
            @endif

            @if ($showAdvancedFilters ?? false)
                </div>
            @endif

            {{-- Aanvullende filters --}}
            @if ($showAdvancedFilters ?? false)
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">

                    @include('partials.stamp-filter-dropdown', [
                        'fieldName' => 'stamp_series_id',
                        'label' => __('Postzegelserie'),
                        'placeholder' => __('Alle postzegelseries'),
                        'options' => $stampSeries->mapWithKeys(
                                fn($series) => [
                                    $series->id => $series->translationWithFallback()?->name ?? $series->name,
                                ])->all(),
                        'optionCountryIds' => $stampSeries->mapWithKeys(
                                fn($series) => [
                                    $series->id => (string) $series->country_id,
                                ])->all(),
                        'optionIssueYears' => $stampSeries->mapWithKeys(
                                fn($series) => [
                                    $series->id => (string) $series->issue_year,
                                ])->all(),
                    ])

                    {{-- Thema --}}
                    @include('partials.stamp-filter-dropdown', [
                        'fieldName' => 'theme_id',
                        'label' => __('Thema'),
                        'placeholder' => __('Alle thema’s'),
                        'options' => $themes->mapWithKeys(
                                fn($theme) => [
                                    $theme->id => $theme->translationWithFallback()?->name ?? $theme->name,
                                ])->all(),
                    ])

                    {{-- Druktechniek --}}
                    @include('partials.stamp-filter-dropdown', [
                        'fieldName' => 'printing_method',
                        'label' => __('Druktechniek'),
                        'placeholder' => __('Alle druktechnieken'),
                        'options' => $printingMethods->mapWithKeys(
                                fn($method) => [
                                    $method => $method,
                                ])->all(),
                    ])

                    {{-- Ontbrekende gegevens --}}
                    @include('partials.stamp-filter-dropdown', [
                        'fieldName' => 'missing_field',
                        'label' => __('Ontbrekende gegevens'),
                        'placeholder' => __('Geen beperking'),
                        'options' => [
                            'issue_date' => __('Uitgiftedatum'),
                            'stamp_series_id' => __('Postzegelserie'),
                            'themes' => __('Thema’s'),
                            'face_value' => __('Nominale waarde'),
                            'currency' => __('Valuta'),
                            'designer' => __('Ontwerper'),
                            'printing_method' => __('Druktechniek'),
                            'notes' => __('Opmerkingen'),
                        ],
                    ])

                    {{-- Postzegeltype --}}
                    @include('partials.stamp-filter-dropdown', [
                        'fieldName' => 'stamp_type',
                        'label' => __('Postzegeltype'),
                        'placeholder' => __('Alle postzegels'),
                        'options' => [
                            'main' => __('Hoofdpostzegels'),
                            'parent' => __('Hoofdpostzegels met varianten'),
                            'variant' => __('Varianten'),
                            'standalone' => __('Zelfstandige postzegels'),
                        ],
                    ])

                </div>
            @endif

            {{-- Acties --}}
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" class="bg-brown-2 hover:bg-brown-1">
                    {{ __('Filteren') }}
                </flux:button>

                <flux:button :href="route($filterRoute)" variant="ghost"
                    class="hover:text-brown-2! hover:bg-transparent!">
                    {{ __('Wissen') }}
                </flux:button>
            </div>
        </form>
