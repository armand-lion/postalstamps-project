<x-layouts::app :title="__('Verzamelobject toevoegen')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div>
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
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">

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

                        <flux:input name="name" value="{{ old('name') }}" maxlength="200" required autofocus />

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

                        <flux:select name="collectible_type_id" required>
                            <option value="">
                                {{ __('Selecteer een type') }}
                            </option>

                            @foreach ($collectibleTypes as $type)
                                <option value="{{ $type->id }}" @selected((string) old('collectible_type_id') === (string) $type->id)>
                                    {{ $type->translationWithFallback()?->name ?? $type->slug }}
                                </option>
                            @endforeach
                        </flux:select>

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

                        <flux:select name="country_id" required>
                            <option value="">
                                {{ __('Selecteer een land') }}
                            </option>

                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" @selected((string) old('country_id') === (string) $country->id)>
                                    {{ $country->translationWithFallback()?->name ?? $country->name }}
                                </option>
                            @endforeach
                        </flux:select>

                        <div class="min-h-5">
                            <flux:error name="country_id" />
                        </div>
                    </flux:field>

                    {{-- Postzegelserie --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Postzegelserie') }}
                        </flux:label>

                        <flux:select name="stamp_series_id">
                            <option value="">
                                {{ __('Geen serie') }}
                            </option>

                            @foreach ($series as $item)
                                <option value="{{ $item->id }}" data-country-id="{{ $item->country_id }}"
                                    @selected((string) old('stamp_series_id') === (string) $item->id)>
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </flux:select>

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

                        <flux:input type="number" name="issue_year" min="1840" max="2200"
                            value="{{ old('issue_year') }}" required />

                        <div class="min-h-5">
                            <flux:error name="issue_year" />
                        </div>
                    </flux:field>

                    {{-- Uitgiftedatum --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Uitgiftedatum') }}
                        </flux:label>

                        <flux:input type="date" name="issue_date" value="{{ old('issue_date') }}" />

                        <div class="min-h-5">
                            <flux:error name="issue_date" />
                        </div>
                    </flux:field>

                    {{-- Beschrijving --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>
                            {{ __('Beschrijving') }}
                        </flux:label>

                        <flux:textarea name="description" rows="4">{{ old('description') }}</flux:textarea>

                        <div class="min-h-5">
                            <flux:error name="description" />
                        </div>
                    </flux:field>

                    {{-- Notities --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>
                            {{ __('Notities') }}
                        </flux:label>

                        <flux:textarea name="notes" rows="4">{{ old('notes') }}</flux:textarea>

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
                                    data-country-id="{{ $stamp->country_id }}">
                                    <input type="checkbox" name="stamp_ids[]" value="{{ $stamp->id }}"
                                        @checked(in_array($stamp->id, old('stamp_ids', []))) class="h-4 w-4 rounded border-theme-accent-1">

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
            <div class="flex flex-wrap justify-end gap-3">

                {{-- Annuleren --}}
                <flux:button :href="route('backend.collectibles.index')" variant="ghost" icon="x-mark">
                    {{ __('Annuleren') }}
                </flux:button>

                {{-- Opslaan --}}
                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Verzamelobject opslaan') }}
                </flux:button>

            </div>

        </form>
    </div>
    {{-- Postzegelseries filteren op geselecteerd land --}}
    <script>
        // Land en postzegelserie selecteren.
        const countrySelect = document.querySelector('select[name="country_id"]');
        const seriesSelect = document.querySelector('select[name="stamp_series_id"]');

        // Postzegelseries filteren op geselecteerd land.
        function filterStampSeries() {
            if (!countrySelect || !seriesSelect) {
                return;
            }

            const selectedCountryId = countrySelect.value;

            Array.from(seriesSelect.options).forEach(option => {
                if (option.value === '') {
                    option.hidden = false;
                    return;
                }

                option.hidden =
                    option.dataset.countryId !== selectedCountryId;
            });

            const selectedOption = seriesSelect.selectedOptions[0];

            if (
                selectedOption &&
                selectedOption.value !== '' &&
                selectedOption.dataset.countryId !== selectedCountryId
            ) {
                seriesSelect.value = '';
            }
        }

        // Postzegels filteren op geselecteerd land.
        const stampItems = document.querySelectorAll('.collectible-stamp-item');

        function filterStampsByCountry() {
            if (!countrySelect) {
                return;
            }

            const selectedCountryId = countrySelect.value;

            stampItems.forEach(item => {
                const belongsToCountry =
                    selectedCountryId !== '' &&
                    item.dataset.countryId === selectedCountryId;

                // Alleen postzegels van het geselecteerde land tonen.
                item.classList.toggle('hidden', !belongsToCountry);

                // Selecties van andere landen verwijderen.
                const checkbox = item.querySelector('input[type="checkbox"]');

                if (checkbox) {
                    checkbox.disabled = !belongsToCountry;

                    if (!belongsToCountry) {
                        checkbox.checked = false;
                    }
                }
            });
        }

        // Beide filters uitvoeren wanneer het land verandert.
        if (countrySelect) {
            countrySelect.addEventListener('change', () => {
                filterStampSeries();
                filterStampsByCountry();
            });

            // Filters ook uitvoeren bij het openen van de pagina.
            filterStampSeries();
            filterStampsByCountry();
        }
    </script>
</x-layouts::app>
