<x-layouts::app :title="__('Mijn postzegelverzameling')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Mijn postzegelverzameling') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de postzegels die je in bezit hebt.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.stamp-copies.create')" variant="primary" icon="plus" class="bg-brown-2!">

                {{ __('Postzegel aan verzameling toevoegen') }}

            </flux:button>
        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">

                {{ session('success') }}

            </div>
        @endif

        {{-- Standaardcatalogus en cataloguseditie --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">
            <div class="flex flex-wrap items-center gap-2">
                <flux:text class="font-semibold">
                    {{ __('Standaardcatalogus') }}:
                </flux:text>

                <flux:text>
                    {{ $defaultCatalogEdition?->catalog?->name ?? '—' }}
                </flux:text>

                <span class="text-neutral-400">|</span>

                <flux:text class="font-semibold">
                    {{ __('Cataloguseditie') }}:
                </flux:text>

                <flux:text>
                    {{ $defaultCatalogEdition?->edition_name ?? '—' }}
                </flux:text>
            </div>
        </div>

        {{-- Filters voor de persoonlijke verzameling --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">
            @php
                $selectedCatalogId = request()->has('catalog_id')
                    ? request('catalog_id')
                    : $defaultCatalogEdition?->catalog_id;

                $selectedCatalog =
                    $selectedCatalogId !== null && $selectedCatalogId !== ''
                        ? $catalogs->firstWhere('id', $selectedCatalogId)
                        : null;
            @endphp
            <form method="GET" action="{{ route('backend.stamp-copies.index') }}" x-data="{
                catalogId: @js((string) ($selectedCatalogId ?? ''))
            }">
                <div class="flex flex-wrap items-end gap-4">

                    {{-- Landfilter --}}
                    <div class="w-full sm:w-80">
                        <flux:field>
                            <flux:label>{{ __('Land') }}</flux:label>

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

                                    <flux:menu
                                        class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

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
                    </div>

                    {{-- Uitgiftejaarfilter --}}
                    <div class="w-full sm:w-40">
                        <flux:field>
                            <flux:label>{{ __('Uitgiftejaar') }}</flux:label>

                            <div x-data="{
                                year: @js(request('issue_year', '')),
                                minYear: 1840,
                                maxYear: {{ date('Y') }},
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
                            }" class="relative">
                                <input type="number" name="issue_year" min="1840" max="{{ date('Y') }}"
                                    x-model="year" placeholder="{{ __('Alle jaren') }}"
                                    class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

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
                        </flux:field>
                    </div>

                    {{-- Conditiefilter --}}
                    <div class="w-full sm:w-80">
                        <flux:field>
                            <flux:label>{{ __('Conditie') }}</flux:label>

                            @php
                                $selectedConditionId = request('condition_id');

                                $selectedCondition = $conditions->firstWhere('id', $selectedConditionId);
                            @endphp

                            <div x-data="{
                                selectedId: @js((string) ($selectedConditionId ?? '')),
                                selectedName: @js($selectedCondition?->translationWithFallback()?->name ?? __('Alle condities'))
                            }">
                                <input type="hidden" name="condition_id" x-model="selectedId">

                                <flux:dropdown position="bottom" align="start" class="block w-full">
                                    <flux:button type="button" icon-trailing="chevron-down"
                                        class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                        <span x-text="selectedName" class="min-w-0 truncate text-left">
                                        </span>

                                    </flux:button>

                                    <flux:menu
                                        class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                        {{-- Alle condities --}}
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedId = ''; selectedName = @js(__('Alle condities'))"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ __('Alle condities') }}

                                        </flux:menu.item>

                                        {{-- Beschikbare condities --}}
                                        @foreach ($conditions as $condition)
                                            <flux:menu.item as="button" type="button"
                                                x-on:click="selectedId = @js((string) $condition->id); selectedName = @js($condition->translationWithFallback()?->name)"
                                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                {{ $condition->translationWithFallback()?->name }}

                                            </flux:menu.item>
                                        @endforeach

                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:field>
                    </div>

                    {{-- Catalogusfilter --}}
                    <div class="w-full sm:w-80">

                        <flux:field>
                            <flux:label>{{ __('Catalogus') }}</flux:label>

                            <div x-data="{
                                selectedName: @js($selectedCatalog?->name ?? __('Alle catalogi'))
                            }">

                                <input type="hidden" name="catalog_id" x-model="catalogId">

                                <flux:dropdown position="bottom" align="start" class="block w-full">

                                    <flux:button type="button" icon-trailing="chevron-down"
                                        class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                        <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                    </flux:button>

                                    <flux:menu
                                        class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="catalogId = ''; selectedName = @js(__('Alle catalogi'))"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ __('Alle catalogi') }}

                                        </flux:menu.item>

                                        @foreach ($catalogs as $catalog)
                                            <flux:menu.item as="button" type="button"
                                                x-on:click="catalogId = @js((string) $catalog->id); selectedName = @js($catalog->name)"
                                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                {{ $catalog->name }}

                                            </flux:menu.item>
                                        @endforeach

                                    </flux:menu>

                                </flux:dropdown>
                            </div>
                        </flux:field>
                    </div>

                    {{-- Cataloguseditiefilter --}}
                    <div class="w-full sm:w-80">
                        <flux:field>
                            <flux:label>{{ __('Cataloguseditie') }}</flux:label>

                            @php
                                $hasCatalogFilters = request()->hasAny(['catalog_id', 'catalog_edition_id']);

                                $selectedEditionId = $hasCatalogFilters
                                    ? request('catalog_edition_id')
                                    : $defaultCatalogEdition?->id;

                                $selectedEdition = $catalogEditions->firstWhere('id', $selectedEditionId);
                            @endphp

                            <div x-data="{
                                selectedId: @js((string) ($selectedEditionId ?? '')),
                                selectedName: @js($selectedEdition?->edition_name ?? __('Alle catalogusedities'))
                            }" x-init="$watch('catalogId', () => {
                                selectedId = '';
                                selectedName = @js(__('Alle catalogusedities'));
                            })">

                                <input type="hidden" name="catalog_edition_id" x-model="selectedId">

                                <flux:dropdown position="bottom" align="start" class="block w-full">

                                    <flux:button type="button" icon-trailing="chevron-down"
                                        class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                        <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                    </flux:button>

                                    <flux:menu
                                        class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedId = ''; selectedName = @js(__('Alle catalogusedities'))"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ __('Alle catalogusedities') }}

                                        </flux:menu.item>

                                        @foreach ($catalogEditions as $edition)
                                            <div x-show="catalogId === '' || catalogId === @js((string) $edition->catalog_id)"
                                                x-cloak>
                                                <flux:menu.item as="button" type="button"
                                                    x-on:click="selectedId = @js((string) $edition->id); selectedName = @js($edition->edition_name)"
                                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                    {{ $edition->edition_name }}

                                                </flux:menu.item>
                                            </div>
                                        @endforeach

                                    </flux:menu>

                                </flux:dropdown>

                            </div>
                        </flux:field>
                    </div>

                    {{-- Zoekfilter --}}
                    <div class="w-full sm:w-80">
                        <flux:field>
                            <flux:label>{{ __('Zoeken') }}</flux:label>

                            <input type="search" name="search" value="{{ request('search') }}"
                                placeholder="{{ __('Naam of catalogusnummer') }}"
                                class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3">
                        </flux:field>
                    </div>

                    {{-- Sorteerfilter --}}
                    <div class="w-full sm:w-80">
                        <flux:field>
                            <flux:label>{{ __('Sorteren op') }}</flux:label>

                            @php
                                $sortOptions = [
                                    'latest' => __('Laatst toegevoegd'),
                                    'name_asc' => __('Postzegelnaam A-Z'),
                                    'name_desc' => __('Postzegelnaam Z-A'),
                                    'year_asc' => __('Uitgiftejaar oplopend'),
                                    'year_desc' => __('Uitgiftejaar aflopend'),
                                    'catalog_asc' => __('Catalogusnummer oplopend'),
                                    'catalog_desc' => __('Catalogusnummer aflopend'),
                                ];

                                $selectedSort = request('sort', 'latest');
                                $selectedSortName = $sortOptions[$selectedSort] ?? $sortOptions['latest'];
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

                                    <flux:menu
                                        class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">
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
                    </div>


                    <flux:button type="submit" variant="primary" class="bg-brown-2!">
                        {{ __('Filteren') }}
                    </flux:button>

                </div>
            </form>
        </div>

        {{-- Overzicht persoonlijke postzegels --}}
        <div class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">

                    <thead class="border-b border-theme-accent-1 dark:border-neutral-700">
                        <tr>
                            <th class="px-4 py-3">{{ __('Postzegel') }}</th>
                            <th class="px-4 py-3">{{ __('Catalogusnummer') }}</th>
                            <th class="px-4 py-3">{{ __('Land') }}</th>

                            <th class="px-4 py-3">
                                {{ __('Jaar') }}
                            </th>

                            <th class="px-4 py-3">
                                {{ __('Conditie') }}
                            </th>

                            <th class="px-4 py-3">
                                {{ __('Aantal') }}
                            </th>

                            <th class="px-4 py-3 text-right">
                                {{ __('Acties') }}
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($stampCopies as $stampCopy)
                            <tr
                                class="border-b border-theme-accent-1 hover:bg-brown-5! dark:border-neutral-700 dark:hover:bg-brown-5!">

                                <td class="px-4 py-3">
                                    @php
                                        $stampImage =
                                            $stampCopy->stamp?->images?->firstWhere('is_primary', true) ??
                                            $stampCopy->stamp?->images?->first();
                                    @endphp

                                    <div class="flex items-center gap-3">
                                        @if ($stampImage)
                                            <img src="{{ asset('storage/' . $stampImage->path) }}"
                                                alt="{{ $stampImage->alt_text ?: $stampCopy->stamp?->name ?? '' }}"
                                                class="h-14 w-14 rounded object-contain" loading="lazy">
                                        @endif

                                        <span>
                                            {{ $stampCopy->stamp?->name ?? '—' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-3">
                                    {{ $stampCopy->stamp?->catalogEntries?->firstWhere('catalog_edition_id', $activeCatalogEditionId)
                                        ?->catalog_number ?? '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $stampCopy->stamp?->country?->translationWithFallback()?->name ?? '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $stampCopy->stamp?->issue_year ?? '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $stampCopy->condition?->translationWithFallback()?->name ?? '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $stampCopy->quantity }}
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">

                                        <flux:button :href="route('backend.stamp-copies.show', $stampCopy)"
                                            size="sm" icon="eye" variant="ghost" />

                                        <flux:button :href="route('backend.stamp-copies.edit', $stampCopy)"
                                            size="sm" icon="pencil" variant="ghost" />

                                        <form method="POST"
                                            action="{{ route('backend.stamp-copies.destroy', $stampCopy) }}"
                                            onsubmit="return confirm('{{ __('Weet je zeker dat je deze postzegel uit je verzameling wilt verwijderen?') }}')">
                                            @csrf
                                            @method('DELETE')

                                            <flux:button type="submit" size="sm" icon="trash"
                                                variant="ghost" />
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center">
                                    <flux:text>
                                        {{ __('Je hebt nog geen postzegels aan je verzameling toegevoegd.') }}
                                    </flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>

            {{-- Paginering --}}
            @if ($stampCopies->hasPages())
                <div class="border-t border-theme-accent-1 p-4 dark:border-neutral-700">
                    {{ $stampCopies->links() }}
                </div>
            @endif

        </div>


    </div>
</x-layouts::app>
