<x-layouts::app :title="__('Verzamelobject bewerken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="xl">
                {{ __('Verzamelobject bewerken') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Bewerk de gegevens van dit bijzondere verzamelobject.') }}
            </flux:text>

        </div>

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
        <form method="POST" action="{{ route('backend.collectibles.update', $collectible) }}" class="flex flex-col gap-6"
            novalidate>
            @csrf
            @method('PUT')

            {{-- Algemene gegevens --}}
            <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

                <flux:heading size="lg">
                    {{ __('Algemene gegevens') }}
                </flux:heading>

                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Naam --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Naam') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <input type="text" name="name" value="{{ old('name', $collectible->name) }}"
                            maxlength="200" required autofocus
                            class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />

                        <div class="min-h-5">
                            <flux:error name="name" />
                        </div>
                    </flux:field>

                    {{-- Type verzamelobject --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Type verzamelobject') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        @php
                            $selectedTypeId = old('collectible_type_id', $collectible->collectible_type_id);
                            $selectedType = $collectibleTypes->firstWhere('id', $selectedTypeId);
                        @endphp

                        <div x-data="{
                            selectedId: @js((string) ($selectedTypeId ?? '')),
                            selectedName: @js($selectedType?->translationWithFallback()?->name ?? ($selectedType?->slug ?? __('Selecteer een type')))
                        }">

                            <input type="hidden" name="collectible_type_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Standaardoptie --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="selectedId = ''; selectedName = @js(__('Selecteer een type'))"
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Selecteer een type') }}

                                    </flux:menu.item>

                                    {{-- Beschikbare verzamelobjecttypen --}}
                                    @foreach ($collectibleTypes as $type)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedId = @js((string) $type->id); selectedName = @js($type->translationWithFallback()?->name ?? $type->slug)"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $type->translationWithFallback()?->name ?? $type->slug }}

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>

                        <div class="min-h-5">
                            <flux:error name="collectible_type_id" />
                        </div>
                    </flux:field>

                    {{-- Land --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Land') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        @php
                            $selectedCountryId = old('country_id', $collectible->country_id);
                            $selectedCountry = $countries->firstWhere('id', $selectedCountryId);
                        @endphp

                        <div x-data="{
                            selectedId: @js((string) ($selectedCountryId ?? '')),
                            selectedName: @js($selectedCountry?->translationWithFallback()?->name ?? ($selectedCountry?->name ?? __('Selecteer een land')))
                        }">

                            <input type="hidden" name="country_id" x-model="selectedId" x-ref="countryInput">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Standaardoptie --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="selectedId = ''; selectedName = @js(__('Selecteer een land'))"
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Selecteer een land') }}

                                    </flux:menu.item>

                                    {{-- Beschikbare landen --}}
                                    @foreach ($countries as $country)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                                                selectedId = @js((string) $country->id);
                                                selectedName = @js($country->translationWithFallback()?->name ?? $country->name);
                                                $nextTick(() => $refs.countryInput.dispatchEvent(new Event('input', { bubbles: true })));
                                            "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $country->translationWithFallback()?->name ?? $country->name }}

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
                        <flux:label>
                            {{ __('Postzegelserie') }}
                        </flux:label>

                        @php
                            $selectedSeriesId = old('stamp_series_id', $collectible->stamp_series_id);
                            $selectedSeries = $series->firstWhere('id', $selectedSeriesId);
                        @endphp

                        <div x-data="{
                            selectedId: @js((string) ($selectedSeriesId ?? '')),
                            selectedName: @js($selectedSeries?->name ?? __('Geen serie')),
                            countryId: @js((string) old('country_id', $collectible->country_id)),

                            changeCountry(newCountryId) {
                                this.countryId = String(newCountryId);

                                const selectedSeries = this.$el.querySelector(
                                    `[data-series-id='${this.selectedId}']`
                                );

                                if (
                                    this.selectedId !== '' &&
                                    (
                                        !selectedSeries ||
                                        selectedSeries.dataset.countryId !== this.countryId
                                    )
                                ) {
                                    this.selectedId = '';
                                    this.selectedName = @js(__('Geen serie'));
                                }
                            }
                        }"
                            x-on:collectible-country-changed.window="changeCountry($event.detail.countryId)">

                            <input type="hidden" name="stamp_series_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Geen serie --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                                            selectedId = '';
                                            selectedName = @js(__('Geen serie'));
                                        "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ __('Geen serie') }}
                                    </flux:menu.item>

                                    {{-- Beschikbare postzegelseries --}}
                                    @foreach ($series as $item)
                                        <flux:menu.item as="button" type="button"
                                            data-series-id="{{ $item->id }}"
                                            data-country-id="{{ $item->country_id }}"
                                            x-show="countryId === @js((string) $item->country_id)"
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

                    {{-- Uitgiftejaar --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Uitgiftejaar') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <div x-data="{
                            value: @js(old('issue_year', $collectible->issue_year)),

                            increase() {
                                let value = Number(this.value || 1840);
                                this.value = Math.min(2200, value + 1);
                                this.updateFilter();
                            },

                            decrease() {
                                let value = Number(this.value || 1840);
                                this.value = Math.max(1840, value - 1);
                                this.updateFilter();
                            },

                            updateFilter() {
                                this.$nextTick(() => {
                                    this.$refs.yearInput.dispatchEvent(
                                        new Event('input', { bubbles: true })
                                    );
                                });
                            }
                        }" class="relative">

                            <input x-ref="yearInput" type="number" name="issue_year" x-model="value"
                                min="1840" max="2200" required
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

                        <div class="min-h-5">
                            <flux:error name="issue_year" />
                        </div>
                    </flux:field>

                    {{-- Uitgiftedatum --}}
                    {{-- Uitgiftedatum --}}
                    <x-forms.date-picker name="issue_date" :label="__('Uitgiftedatum')" :value="old(
                        'issue_date',
                        $collectible->issue_date
                            ? \Illuminate\Support\Carbon::parse($collectible->issue_date)->format('Y-m-d')
                            : '',
                    )" />

                    {{-- Beschrijving --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>
                            {{ __('Beschrijving') }}
                        </flux:label>

                        <textarea name="description" rows="4"
                            class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5">{{ old('description', $collectible->description) }}</textarea>

                        <div class="min-h-5">
                            <flux:error name="description" />
                        </div>
                    </flux:field>

                    {{-- Notities --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>
                            {{ __('Notities') }}
                        </flux:label>

                        <textarea name="notes" rows="4"
                            class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5">{{ old('notes', $collectible->notes) }}</textarea>

                        <div class="min-h-5">
                            <flux:error name="notes" />
                        </div>
                    </flux:field>


                </div>

            </div>
            {{-- Gekoppelde postzegels --}}
            <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-5">

                <flux:heading size="lg">
                    {{ __('Gekoppelde postzegels') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Selecteer de postzegels die bij dit verzamelobject horen.') }}
                </flux:text>

                <div class="mt-5">

                    <flux:field>
                        <flux:label>
                            {{ __('Postzegels') }}
                        </flux:label>

                        {{-- Postzegels selecteren met selectievakjes --}}
                        <div id="collectible-stamps"
                            class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-theme-accent-1 bg-brown-6 p-4">
                            @forelse ($stamps as $stamp)
                                <label
                                    class="collectible-stamp-item flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-brown-5"
                                    data-country-id="{{ $stamp->country_id }}"
                                    data-issue-year="{{ $stamp->issue_year }}">
                                    <flux:checkbox name="stamp_ids[]" value="{{ $stamp->id }}"
                                        :checked="in_array($stamp->id, old('stamp_ids', $collectible->stamps->pluck('id')->all()))"
                                        class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />
                                    <div class="flex flex-col gap-1">
                                        {{-- Naam van de postzegel --}}
                                        <span class="text-sm font-medium text-zinc-900">
                                            {{ $stamp->name }}
                                        </span>

                                        {{-- Catalogusnummers en uitgiftejaar --}}
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-600">
                                            <span>
                                                {{ __('Uitgiftejaar') }}: {{ $stamp->issue_year ?? '—' }}
                                            </span>

                                            @forelse ($stamp->catalogEntries->sortBy(fn ($entry) => $entry->catalogEdition?->edition_year ?? 0) as $entry)
                                                @php
                                                    $edition = $entry->catalogEdition;
                                                    $editionName = $edition?->edition_name;
                                                    $editionYear = $edition?->edition_year;

                                                    $displayEdition =
                                                        $editionName ?: ($editionYear ? (string) $editionYear : '—');

                                                    if (
                                                        $editionName &&
                                                        $editionYear &&
                                                        !str_contains($editionName, (string) $editionYear)
                                                    ) {
                                                        $displayEdition .= ' (' . $editionYear . ')';
                                                    }
                                                @endphp

                                                <span>
                                                    {{ $edition?->catalog?->name ?? __('Catalogus') }}
                                                    — {{ $displayEdition }}
                                                    — {{ $entry->catalog_number }}
                                                </span>
                                            @empty
                                                <span>{{ __('Geen catalogusnummer') }}</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </label>
                            @empty
                                <flux:text>
                                    {{ __('Geen postzegels beschikbaar.') }}
                                </flux:text>
                            @endforelse
                        </div>

                        <div class="min-h-5">
                            <flux:error name="stamp_ids" />
                        </div>

                    </flux:field>

                </div>

            </div>

            {{-- Acties --}}
            <div class="flex flex-wrap justify-end gap-3 rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

                {{-- Annuleren --}}
                <flux:button :href="route('backend.collectibles.index')" variant="ghost" icon="x-mark"
                    class="text-zinc-900!">
                    {{ __('Annuleren') }}
                </flux:button>

                {{-- Opslaan --}}
                <flux:button type="submit" variant="primary" icon="check" class="bg-brown-2!">
                    {{ __('Verzamelobject opslaan') }}
                </flux:button>

            </div>

        </form>
    </div>


    {{-- Postzegelseries filteren op geselecteerd land --}}
    <script>
        // Land en postzegelserie selecteren.
        const countrySelect = document.querySelector('input[name="country_id"]');


        // Postzegelseries filteren op geselecteerd land.
        function filterStampSeries() {
            if (!countrySelect) {
                return;
            }

            window.dispatchEvent(
                new CustomEvent('collectible-country-changed', {
                    detail: {
                        countryId: countrySelect.value
                    }
                })
            );
        }

        // Postzegels selecteren voor filtering op land en uitgiftejaar.
        const stampItems = document.querySelectorAll('.collectible-stamp-item');
        const issueYearInput = document.querySelector('input[name="issue_year"]');

        // Postzegels filteren op geselecteerd land en uitgiftejaar.
        function filterStampsByCountry() {
            if (!countrySelect) {
                return;
            }

            const selectedCountryId = countrySelect.value;
            const selectedYear = issueYearInput?.value ?? '';

            stampItems.forEach(item => {
                const belongsToCountry =
                    selectedCountryId !== '' &&
                    item.dataset.countryId === selectedCountryId;

                const belongsToYear =
                    selectedYear !== '' &&
                    item.dataset.issueYear === selectedYear;

                const checkbox = item.querySelector('ui-checkbox');

                const isVisible =
                    belongsToCountry &&
                    (belongsToYear || checkbox?.hasAttribute('data-checked'));

                // Alleen postzegels van het geselecteerde land en jaar tonen.
                item.classList.toggle('hidden', !isVisible);

                // Bestaande vinkjes behouden bij het wisselen van filters.
                if (checkbox) {
                    checkbox.disabled = !isVisible;
                }
            });
        }
        // Beide filters uitvoeren wanneer het land verandert.
        if (countrySelect) {
            countrySelect.addEventListener('input', () => {
                filterStampSeries();
                filterStampsByCountry();
            });

            // Filters ook uitvoeren bij het openen van de pagina.
            // Filters uitvoeren nadat Alpine de landselectie heeft geïnitialiseerd.
            document.addEventListener('alpine:initialized', () => {
                filterStampSeries();
                filterStampsByCountry();
            });
        }

        // Postzegels opnieuw filteren wanneer het uitgiftejaar verandert.
        if (issueYearInput) {
            issueYearInput.addEventListener('input', () => {
                filterStampsByCountry();
            });
        }
    </script>
</x-layouts::app>
