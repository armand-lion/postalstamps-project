<x-layouts::app :title="__('Postzegels beheren')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="rounded-xl flex flex-col justify-between gap-4 sm:flex-row sm:items-center bg-brown-6 rounded-lg border border-theme-accent-1 p-5">
            <div>
                <flux:heading size="xl">
                    {{ __('Postzegels beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de postzegels in je catalogus.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.stamps.create')" variant="primary" icon="plus"
                class="bg-brown-2 hover:bg-brown-1">
                {{ __('Postzegel toevoegen') }}
            </flux:button>
        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- foutmelding --}}
        @if (session('error'))
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('backend.stamps.index') }}"
            class="flex flex-col gap-4 rounded-xl border border-theme-accent-1 bg-brown-6 p-4 dark:border-neutral-700 sm:flex-row sm:items-end">

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
               focus:ring-offset-brown-6" />
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

                <div x-data="{
                    selectedId: @js((string) ($selectedCountryId ?? '')),
                    selectedName: @js($selectedCountry?->translationWithFallback()?->name ?? __('Alle landen'))
                }">

                    <input type="hidden" name="country_id" x-model="selectedId">

                    <flux:dropdown position="bottom" align="start" class="block w-full">

                        <flux:button type="button" icon-trailing="chevron-down"
                            class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                            <span x-text="selectedName" class="min-w-0 truncate text-left"></span>
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

                <div x-data="{
                    selectedId: @js((string) ($selectedCatalogId ?? '')),
                    selectedName: @js($selectedCatalog?->name ?? __('Alle catalogi'))
                }">

                    <input type="hidden" name="catalog_id" x-model="selectedId">

                    <flux:dropdown position="bottom" align="start" class="block w-full">

                        <flux:button type="button" icon-trailing="chevron-down"
                            class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                            <span x-text="selectedName" class="min-w-0 truncate text-left"></span>
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

                <div x-data="{
                    selectedId: @js((string) ($selectedEditionId ?? '')),
                    selectedName: @js($selectedEditionName)
                }">

                    <input type="hidden" name="catalog_edition_id" x-model="selectedId">

                    <flux:dropdown position="bottom" align="start" class="block w-full">

                        <flux:button type="button" icon-trailing="chevron-down"
                            class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                            <span x-text="selectedName" class="min-w-0 truncate text-left"></span>
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
                        placeholder="{{ __('Bijvoorbeeld 2026') }}"
                        class="w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5
                   px-3 py-2 pr-10 text-sm text-zinc-900
                   placeholder:text-zinc-500
                   focus:border-theme-accent-3
                   focus:outline-none
                   focus:ring-2
                   focus:ring-theme-accent-3
                   focus:ring-offset-2
                   focus:ring-offset-brown-6
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
            <flux:field class="min-w-0 flex-1">
                <flux:label>
                    {{ __('Sorteren op') }}
                </flux:label>

                @php
                    $sortOptions = [
                        'year_desc' => __('Uitgiftejaar (nieuwste eerst)'),
                        'year_asc' => __('Uitgiftejaar (oudste eerst)'),
                        'name_asc' => __('Naam (A-Z)'),
                        'name_desc' => __('Naam (Z-A)'),
                        'catalog_number_asc' => __('Catalogusnummer (oplopend)'),
                    ];

                    $selectedSort = request('sort', 'year_desc');
                    $selectedSortName = $sortOptions[$selectedSort] ?? $sortOptions['year_desc'];
                @endphp

                <div x-data="{
                    selectedId: @js($selectedSort),
                    selectedName: @js($selectedSortName)
                }">

                    <input type="hidden" name="sort" x-model="selectedId">

                    <flux:dropdown position="bottom" align="start" class="block w-full">

                        <flux:button type="button" icon-trailing="chevron-down"
                            class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                            <span x-text="selectedName" class="min-w-0 truncate text-left"></span>
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

            {{-- Acties --}}
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" class="bg-brown-2 hover:bg-brown-1">
                    {{ __('Filteren') }}
                </flux:button>

                <flux:button :href="route('backend.stamps.index')" variant="ghost"
                    class="hover:text-brown-2! hover:bg-transparent!">
                    {{ __('Wissen') }}
                </flux:button>
            </div>
        </form>

        {{-- Resultaten --}}
        <div class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-brown-2 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Catalogus') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ trans_choice(__(':count stamp found|:count stamps found'), $stamps->total()) }}
                </flux:text>
            </div>

            @if ($stamps->isEmpty())
                <div class="p-8 text-center">
                    <flux:heading>{{ __('Geen postzegels gevonden') }}</flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een postzegel toe of pas de filters aan.') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button :href="route('backend.stamps.create')" variant="primary">
                            {{ __('Eerste postzegel toevoegen') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-brown-5 text-brown-2 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Afbeelding') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Naam') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Land') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Jaar') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Catalogusnummer') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Cataloguswaarde') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Nominale waarde') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Acties') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">
                            @foreach ($stamps as $stamp)
                                <tr
                                    class="transition-colors hover:bg-brown-4! {{ $stamp->is_owned ? '' : 'opacity-50 hover:opacity-75' }}">
                                    {{-- Catalogusafbeelding als thumbnail --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $thumbnail = $stamp->images
                                                ->where('image_type', 'front')
                                                ->sortByDesc('is_primary')
                                                ->first();
                                        @endphp

                                        @if ($thumbnail)
                                            <a href="{{ route('backend.stamps.show', $stamp) }}">
                                                <img src="{{ asset('storage/' . $thumbnail->path) }}"
                                                    alt="{{ $thumbnail->alt_text ?? $stamp->name }}"
                                                    class="h-15 w-15 rounded object-contain" loading="lazy">
                                            </a>
                                        @else
                                            <span class="text-neutral-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('backend.stamps.show', $stamp) }}"
                                            class="underline decoration-neutral-300 underline-offset-4 hover:decoration-current">
                                            {{ $stamp->translationWithFallback()?->name ?? $stamp->name }}
                                        </a>
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $stamp->country?->translationWithFallback()?->name ?? ($stamp->country?->name ?? '—') }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $stamp->issue_year }}
                                    </td>

                                    {{-- Officieel catalogusnummer --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $selectedEditionId = request('catalog_edition_id');

                                            $catalogEntry = $stamp->catalogEntries
                                                ->when(
                                                    $selectedEditionId,
                                                    fn($entries) => $entries->where(
                                                        'catalog_edition_id',
                                                        $selectedEditionId,
                                                    ),
                                                )
                                                ->first();
                                        @endphp

                                        {{ $catalogEntry?->catalog_number ?? '—' }}
                                    </td>

                                    {{-- Cataloguswaarden van de geselecteerde cataloguseditie --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $selectedEditionId = request('catalog_edition_id');

                                            $catalogEntry = $selectedEditionId
                                                ? $stamp->catalogEntries->firstWhere(
                                                    'catalog_edition_id',
                                                    (int) $selectedEditionId,
                                                )
                                                : null;
                                        @endphp

                                        @if ($catalogEntry && $catalogEntry->values->isNotEmpty())
                                            <div class="space-y-1">
                                                @foreach ($catalogEntry->values->sortBy(fn($value) => $value->condition?->sort_order ?? 999) as $value)
                                                    @php
                                                        $condition = $value->condition;

                                                        $conditionTranslation = $condition
                                                            ? $condition->translations->firstWhere(
                                                                    'locale',
                                                                    app()->getLocale(),
                                                                ) ??
                                                                $condition->translations->firstWhere(
                                                                    'locale',
                                                                    config('app.fallback_locale', 'nl'),
                                                                )
                                                            : null;
                                                    @endphp

                                                    <div class="text-sm">
                                                        <span class="font-medium">
                                                            {{ $conditionTranslation?->name ?? ($condition?->slug ?? '—') }}:
                                                        </span>

                                                        {{ $value->currency }}
                                                        {{ number_format((float) $value->catalog_value, 2, ',', '.') }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-neutral-400">—</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($stamp->face_value !== null)
                                            {{ number_format((float) $stamp->face_value, 2, ',', '.') }}
                                            {{ $stamp->currency }}
                                        @else
                                            —
                                        @endif
                                    </td>


                                    {{-- Acties --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">

                                            {{-- Toevoegen aan persoonlijke verzameling --}}
                                            @if (!$stamp->is_owned)
                                                <flux:button
                                                    :href="route('backend.stamp-copies.create', ['stamp_id' => $stamp->id])"
                                                    size="sm" variant="ghost" icon="plus">
                                                    {{ __('Toevoegen aan mijn verzameling') }}
                                                </flux:button>
                                            @endif

                                            {{-- Bekijken --}}
                                            <flux:button :href="route('backend.stamps.show', $stamp)" size="sm"
                                                variant="ghost" icon="eye">
                                                {{ __('Bekijken') }}
                                            </flux:button>

                                            {{-- Bewerken --}}
                                            <flux:button :href="route('backend.stamps.edit', $stamp)" size="sm"
                                                variant="ghost" icon="pencil">
                                                {{ __('Bewerken') }}
                                            </flux:button>

                                            {{-- Verwijderen --}}
                                            <form method="POST"
                                                action="{{ route('backend.stamps.destroy', $stamp) }}"
                                                class="delete-confirm-form">
                                                @csrf
                                                @method('DELETE')

                                                <flux:button type="button" size="sm" variant="ghost"
                                                    icon="trash" class="text-red-600 dark:text-red-400"
                                                    onclick="openDeleteModal(this.closest('form'))">
                                                    {{ __('Verwijderen') }}
                                                </flux:button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginering --}}
                <div class="border-t border-brown-4 p-4 dark:border-neutral-700">
                    {{ $stamps->links() }}
                </div>
            @endif
        </div>
    </div>

    @include('components.delete-confirm-modal')

    <script>
        const catalogSelect = document.querySelector('select[name="catalog_id"]');
        const catalogEditionSelect = document.querySelector(
            'select[name="catalog_edition_id"]'
        );

        function filterCatalogEditions() {
            const selectedCatalogId = catalogSelect.value;
            const selectedEditionId = catalogEditionSelect.value;

            Array.from(catalogEditionSelect.options).forEach(option => {
                if (option.value === '') {
                    option.hidden = false;
                    return;
                }

                option.hidden = selectedCatalogId !== '' &&
                    option.dataset.catalogId !== selectedCatalogId;
            });

            const selectedOption = Array.from(catalogEditionSelect.options).find(
                option => option.value === selectedEditionId
            );

            if (selectedOption && selectedOption.hidden) {
                catalogEditionSelect.value = '';
            }
        }

        catalogSelect.addEventListener('change', filterCatalogEditions);

        filterCatalogEditions();
    </script>
</x-layouts::app>
