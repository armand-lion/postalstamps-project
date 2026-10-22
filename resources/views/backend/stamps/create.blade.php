<x-layouts::app :title="__('Postzegel toevoegen')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div>
            <flux:heading size="xl">
                {{ __('Postzegel toevoegen') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Voeg een postzegel toe aan je catalogus.') }}
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
        <form method="POST" action="{{ route('backend.stamps.store') }}" class="flex flex-col gap-6" novalidate>
            @csrf

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
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
                        <flux:select name="country_id" required>
                            <option value="">{{ __('Selecteer een land') }}</option>

                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" @selected((string) old('country_id') === (string) $country->id)>
                                    {{ $country->name }}
                                </option>
                            @endforeach
                        </flux:select>
                        <div class="min-h-5">
                            <flux:error name="country_id" />
                        </div>
                    </flux:field>

                    {{-- Serie --}}
                    <flux:field>
                        <flux:label>{{ __('Postzegelserie') }}</flux:label>

                        <flux:select name="stamp_series_id">
                            <option value="">{{ __('Geen serie') }}</option>

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
                            value="{{ old('issue_year') }}" />
                        <div class="min-h-5">
                            <flux:error name="issue_year" />
                        </div>
                    </flux:field>

                    {{-- Uitgiftedatum --}}
                    <flux:field>
                        <flux:label>{{ __('Uitgiftedatum') }}</flux:label>
                        <flux:input type="date" name="issue_date" value="{{ old('issue_date') }}" />
                        <div class="min-h-5">
                            <flux:error name="issue_date" />
                        </div>
                    </flux:field>

                    {{-- Nominale waarde --}}
                    <flux:field>
                        <flux:label>{{ __('Nominale waarde') }}</flux:label>
                        <flux:input type="number" name="face_value" min="0" step="0.01"
                            value="{{ old('face_value') }}" />
                        <div class="min-h-5">
                            <flux:error name="face_value" />
                        </div>
                    </flux:field>

                    {{-- Valuta --}}
                    <flux:field>
                        <flux:label>{{ __('Valuta') }}</flux:label>
                        <flux:input name="currency" maxlength="3" value="{{ old('currency') }}" placeholder="EUR" />
                        <div class="min-h-5">
                            <flux:error name="currency" />
                        </div>
                    </flux:field>

                    {{-- Ontwerper --}}
                    <flux:field>
                        <flux:label>{{ __('Ontwerper') }}</flux:label>
                        <flux:input name="designer" value="{{ old('designer') }}" />
                        <div class="min-h-5">
                            <flux:error name="designer" />
                        </div>
                    </flux:field>

                    {{-- Druktechniek --}}
                    <flux:field>
                        <flux:label>{{ __('Druktechniek') }}</flux:label>
                        <flux:input name="printing_method" value="{{ old('printing_method') }}" />
                        <div class="min-h-5">
                            <flux:error name="printing_method" />
                        </div>
                    </flux:field>

                    {{-- Catalogusreferentie --}}
                    <flux:field>
                        <flux:label>{{ __('Catalogusreferentie') }}</flux:label>
                        <flux:input name="catalog_reference" value="{{ old('catalog_reference') }}" />
                        <div class="min-h-5">
                            <flux:error name="catalog_reference" />
                        </div>
                    </flux:field>


                    {{-- Notities --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __('Notities') }}</flux:label>
                        <flux:textarea name="notes" rows="4">{{ old('notes') }}</flux:textarea>
                        <div class="min-h-5">
                            <flux:error name="notes" />
                        </div>
                    </flux:field>

                </div>
            </div>

            {{-- Vertalingen --}}
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

                    @foreach ($locales as $locale)
                        <div
                            class="grid gap-5 border-b border-neutral-200 pb-6 last:border-b-0 last:pb-0 dark:border-neutral-700 md:grid-cols-2">

                            <div class="md:col-span-2">
                                <flux:heading>
                                    {{ $languageNames[$locale] ?? strtoupper($locale) }}
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

                                <flux:input name="translations[{{ $locale }}][name]"
                                    :value="old('translations.'.$locale.
                                        '.name')"
                                    maxlength="255" @required($locale === app()->getLocale()) />

                                <flux:error name="translations.{{ $locale }}.name" />
                            </flux:field>

                            {{-- Vertaalde beschrijving --}}
                            <flux:field>
                                <flux:label>
                                    {{ __('Beschrijving') }}
                                </flux:label>

                                <flux:textarea name="translations[{{ $locale }}][description]" rows="3">
                                    {{ old("translations.$locale.description") }}</flux:textarea>

                                <flux:error name="translations.{{ $locale }}.description" />
                            </flux:field>

                        </div>
                    @endforeach

                </div>
            </section>

            {{-- Acties --}}
            <div class="flex flex-wrap justify-end gap-3">
                <flux:button :href="route('backend.stamps.index')" variant="ghost" icon="x-mark">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Postzegel opslaan') }}
                </flux:button>
            </div>
        </form>
    </div>


    <script>
        const countrySelect = document.querySelector('select[name="country_id"]');
        const seriesSelect = document.querySelector('select[name="stamp_series_id"]');

        function filterStampSeries() {
            const selectedCountryId = countrySelect.value;
            const selectedSeriesId = seriesSelect.value;

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

        countrySelect.addEventListener('change', filterStampSeries);

        filterStampSeries();
    </script>


</x-layouts::app>
