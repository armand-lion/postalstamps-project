<x-layouts::app :title="__('Verzamelobject toevoegen')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        {{-- Algemene gegevens --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">
            <flux:heading size="xl">
                {{ __('Verzamelobject toevoegen') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Voeg een bijzonder verzamelobject toe aan je catalogus.') }}
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
        <form method="POST" action="{{ route('backend.collectibles.store') }}" class="flex flex-col gap-6" novalidate>
            @csrf

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

                        <input type="text" name="name" value="{{ old('name') }}" maxlength="200" required
                            autofocus
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

                        <div x-data="{
                            selectedId: @js((string) old('collectible_type_id', '')),
                            selectedName: @js(
    $collectibleTypes
        ->mapWithKeys(
            fn($type) => [
                (string) $type->id => $type->translationWithFallback()?->name ?? $type->slug,
            ],
        )
        ->all(),
),
                        }" class="w-full">

                            <input type="hidden" name="collectible_type_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span class="min-w-0 truncate text-left"
                                        x-text="selectedName[selectedId] ?? @js(__('Selecteer een type'))">
                                    </span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    @foreach ($collectibleTypes as $type)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedId = @js((string) $type->id)"
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

                        <div x-data="{
                            selectedId: @js((string) old('country_id', '')),
                            countryNames: @js(
    $countries
        ->mapWithKeys(
            fn($country) => [
                (string) $country->id => $country->translationWithFallback()?->name ?? $country->name,
            ],
        )
        ->all(),
),
                        }" class="w-full">

                            <input type="hidden" name="country_id" x-model="selectedId" x-ref="countryInput">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span class="min-w-0 truncate text-left"
                                        x-text="countryNames[selectedId] ?? @js(__('Selecteer een land'))">
                                    </span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    @foreach ($countries as $country)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                            selectedId = @js((string) $country->id);
                            $nextTick(() => {
                                $refs.countryInput.dispatchEvent(
                                    new Event('input', { bubbles: true })
                                );
                            });
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

                        <div x-data="{
                            selectedId: @js((string) old('stamp_series_id', '')),
                            selectedCountryId: @js((string) old('country_id', '')),

                            series: @js(
    $series
        ->map(
            fn($item) => [
                'id' => (string) $item->id,
                'name' => $item->name,
                'country_id' => (string) $item->country_id,
            ],
        )
        ->values()
        ->all(),
),

                            get selectedName() {
                                return this.series.find(item => item.id === this.selectedId)?.name ??
                                    @js(__('Geen serie'));
                            },

                            get filteredSeries() {
                                return this.series.filter(
                                    item => item.country_id === this.selectedCountryId
                                );
                            },

                            init() {
                                window.addEventListener('collectible-country-changed', (event) => {
                                    this.selectedCountryId = String(event.detail.countryId ?? '');

                                    if (!this.filteredSeries.some(item => item.id === this.selectedId)) {
                                        this.selectedId = '';
                                    }
                                });
                            }
                        }" class="w-full">

                            <input type="hidden" name="stamp_series_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span class="min-w-0 truncate text-left" x-text="selectedName">
                                    </span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    <flux:menu.item as="button" type="button" x-on:click="selectedId = ''"
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Geen serie') }}

                                    </flux:menu.item>

                                    <template x-for="item in filteredSeries" :key="item.id">
                                        <flux:menu.item as="button" type="button" x-on:click="selectedId = item.id"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            <span x-text="item.name"></span>

                                        </flux:menu.item>
                                    </template>

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
                            value: @js(old('issue_year', '')),

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

                            <input x-ref="yearInput" type="number" name="issue_year" x-model="value" min="1840"
                                max="2200" required
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
                    <x-forms.date-picker name="issue_date" :label="__('Uitgiftedatum')" :value="old('issue_date', '')" />

                    {{-- Beschrijving --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>
                            {{ __('Beschrijving') }}
                        </flux:label>

                        <textarea name="description" rows="4"
                            class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5">{{ old('description') }}</textarea>

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
                            class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5">{{ old('notes') }}</textarea>

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
                                        :checked="in_array($stamp->id, old('stamp_ids', []))"
                                        class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                                    <span class="text-sm">
                                        {{ $stamp->name }}
                                    </span>
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
            <div
                class="flex flex-wrap justify-end gap-3 rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

                {{-- Annuleren --}}
                <flux:button :href="route('backend.collectibles.index')" variant="ghost" icon="x-mark">
                    {{ __('Annuleren') }}
                </flux:button>

                {{-- Opslaan --}}
                <flux:button type="submit" variant="primary" icon="check" class="bg-brown-1 hover:bg-brown-2">
                    {{ __('Verzamelobject opslaan') }}
                </flux:button>

            </div>
    </div>
    {{-- Postzegelseries filteren op geselecteerd land --}}
    <script>
        // Land en postzegelserie selecteren.
        const countrySelect = document.querySelector('input[name="country_id"]');

        // Postzegels filteren op geselecteerd land.
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

                // Flux-checkbox ophalen.
                const checkbox = item.querySelector('ui-checkbox');

                const isChecked =
                    checkbox?.hasAttribute('data-checked') ?? false;

                // Aangevinkte postzegels blijven zichtbaar bij een ander jaar.
                const isVisible =
                    belongsToCountry &&
                    (belongsToYear || isChecked);

                item.classList.toggle('hidden', !isVisible);

                if (checkbox) {
                    // Selecties van andere landen verwijderen.
                    if (!belongsToCountry && isChecked) {
                        checkbox.click();
                    }

                    checkbox.disabled = !isVisible;
                }
            });
        }

        // Postzegels filteren op geselecteerd land.
        const stampItems = document.querySelectorAll('.collectible-stamp-item');
        const issueYearInput = document.querySelector('input[name="issue_year"]');

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
        // Beide filters uitvoeren wanneer het land verandert.
        if (countrySelect) {
            countrySelect.addEventListener('input', () => {
                filterStampSeries();
                filterStampsByCountry();
            });
            // Postzegels opnieuw filteren wanneer het uitgiftejaar verandert.
            if (issueYearInput) {
                issueYearInput.addEventListener('input', () => {
                    filterStampsByCountry();
                });
            }


            // Filters ook uitvoeren bij het openen van de pagina.
            filterStampSeries();
            filterStampsByCountry();
        }
    </script>
</x-layouts::app>
