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
        <form method="POST" action="{{ route('backend.stamps.store') }}" enctype="multipart/form-data"
            class="flex flex-col gap-6" novalidate>
            @csrf

            <div class="rounded-xl border border-neutral-200 p-4 bg-brown-6 dark:border-neutral-700 md:p-6">
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

                    {{-- Thema's --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __("Thema's") }}</flux:label>

                        <div
                            class="grid grid-cols-1 gap-3 rounded-lg border border-zinc-200 p-4 sm:grid-cols-2 lg:grid-cols-3 dark:border-zinc-700">
                            @forelse ($themes as $theme)
                                @php
                                    $translation = $theme->translationWithFallback();
                                    $themeName = $translation?->name ?? $theme->name;
                                @endphp

                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="checkbox" name="theme_ids[]" value="{{ $theme->id }}"
                                        @checked(in_array((string) $theme->id, array_map('strval', old('theme_ids', [])), true))
                                        class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600">

                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $themeName }}
                                    </span>
                                </label>
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

                    {{-- Hoofdpostzegel (optioneel, voor varianten) --}}
                    <flux:field>
                        <flux:label>{{ __('Hoofdpostzegel') }}</flux:label>

                        <flux:select name="parent_stamp_id" id="parent_stamp_id">
                            <option value="">
                                {{ __('Geen hoofdpostzegel (zelfstandige postzegel)') }}
                            </option>

                            @foreach ($parentStamps as $parentStamp)
                                <option value="{{ $parentStamp->id }}"
                                    data-country-id="{{ $parentStamp->country_id }}" @selected((string) old('parent_stamp_id') === (string) $parentStamp->id)>
                                    {{ $parentStamp->translationWithFallback()?->name ?? $parentStamp->name }}
                                    ({{ $parentStamp->issue_year }})
                                </option>
                            @endforeach
                        </flux:select>

                        <div class="min-h-5">
                            <flux:error name="parent_stamp_id" />
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

                    {{-- Catalogus en cataloguseditie --}}
                    <div x-data="{
                        catalogId: @js((string) old('catalog_id', '')),
                        editionId: @js((string) old('catalog_edition_id', ''))
                    }">
                        {{-- Catalogus --}}
                        <flux:field>
                            <flux:label>{{ __('Catalogus') }}</flux:label>

                            <flux:select name="catalog_id" x-model="catalogId" x-on:change="editionId = ''">
                                <option value="">
                                    {{ __('Geen catalogus geselecteerd') }}
                                </option>

                                @foreach ($catalogs as $catalog)
                                    <option value="{{ $catalog->id }}">
                                        {{ $catalog->name }}
                                    </option>
                                @endforeach
                            </flux:select>

                            <div class="min-h-5">
                                <flux:error name="catalog_id" />
                            </div>
                        </flux:field>

                        {{-- Cataloguseditie --}}
                        <flux:field>
                            <flux:label>{{ __('Cataloguseditie') }}</flux:label>

                            <flux:select name="catalog_edition_id" x-model="editionId">
                                <option value="">
                                    {{ __('Geen cataloguseditie geselecteerd') }}
                                </option>

                                @foreach ($catalogEditions as $edition)
                                    <option value="{{ $edition->id }}"
                                        x-show="catalogId === @js((string) $edition->catalog_id)">
                                        {{ $edition->edition_name }}
                                        ({{ $edition->edition_year }})
                                    </option>
                                @endforeach
                            </flux:select>

                            <div class="min-h-5">
                                <flux:error name="catalog_edition_id" />
                            </div>
                        </flux:field>
                    </div>

                    {{-- Officieel catalogusnummer --}}
                    <flux:field>
                        <flux:label>{{ __('Catalogusnummer') }}</flux:label>

                        <flux:input name="catalog_number" value="{{ old('catalog_number') }}" maxlength="100"
                            placeholder="{{ __('Bijvoorbeeld 123, 123a, AU 10 of E 25') }}" />

                        <div class="min-h-5">
                            <flux:error name="catalog_number" />
                        </div>
                    </flux:field>

                    {{-- Cataloguswaarde postfris --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Cataloguswaarde — Postfris') }}
                        </flux:label>

                        <flux:input type="number" name="catalog_values[postfris]" min="0" step="0.01"
                            :value="old('catalog_values.postfris')" placeholder="0.00" />

                        <div class="min-h-5">
                            <flux:error name="catalog_values.postfris" />
                        </div>
                    </flux:field>

                    {{-- Cataloguswaarde gestempeld --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Cataloguswaarde — Gestempeld') }}
                        </flux:label>

                        <flux:input type="number" name="catalog_values[gestempeld]" min="0" step="0.01"
                            :value="old('catalog_values.gestempeld')" placeholder="0.00" />

                        <div class="min-h-5">
                            <flux:error name="catalog_values.gestempeld" />
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
            <section class="rounded-xl border border-neutral-200 bg-brown-6 dark:border-neutral-700">

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

            {{-- Postzegelafbeelding --}}
            <section class="rounded-xl border border-neutral-200 p-4 bg-brown-6 dark:border-neutral-700 md:p-6">
                <flux:heading size="lg">
                    {{ __('Postzegelafbeeldingen') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Voeg een afbeelding toe aan deze postzegel.') }}
                </flux:text>

                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Afbeelding selecteren --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Afbeelding uploaden') }}
                        </flux:label>

                        <flux:input type="file" name="image" id="stamp-create-image"
                            accept=".jpg,.jpeg,.png,.webp" />

                        <flux:error name="image" />

                        {{-- Voorbeeld van de geselecteerde afbeelding --}}
                        <div id="stamp-image-preview-container"
                            class="mt-4 hidden rounded-lg border border-amber-900/20 bg-amber-50 p-4">

                            <p class="mb-3 text-sm font-medium text-amber-950">
                                {{ __('Voorbeeld van de afbeelding') }}
                            </p>

                            <img id="stamp-image-preview" src=""
                                alt="{{ __('Voorbeeld van de geselecteerde postzegelafbeelding') }}"
                                class="max-h-64 max-w-full rounded-md border border-amber-900/20 bg-white object-contain">
                            <button type="button" id="stamp-image-remove"
                                class="mt-3 rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                                {{ __('Afbeelding verwijderen') }}
                            </button>

                        </div>
                    </flux:field>

                    {{-- Afbeeldingstype --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Afbeeldingstype') }}
                        </flux:label>

                        <flux:select name="image_type">
                            <option value="front" @selected(old('image_type', 'front') === 'front')>
                                {{ __('Voorzijde') }}
                            </option>

                            <option value="back" @selected(old('image_type') === 'back')>
                                {{ __('Achterzijde') }}
                            </option>

                            <option value="detail" @selected(old('image_type') === 'detail')>
                                {{ __('Detail') }}
                            </option>
                        </flux:select>

                        <flux:error name="image_type" />
                    </flux:field>

                    {{-- Afbeeldingsbeschrijving --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>
                            {{ __('Afbeeldingsbeschrijving') }}
                        </flux:label>

                        <flux:input name="alt_text" :value="old('alt_text')" maxlength="255"
                            placeholder="{{ __('Beschrijf wat er op de afbeelding te zien is') }}" />

                        <flux:error name="alt_text" />
                    </flux:field>

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
        const parentStampSelect = document.querySelector('select[name="parent_stamp_id"]');

        function filterParentStamps() {
            const selectedCountryId = countrySelect.value;
            const selectedParentStampId = parentStampSelect.value;

            Array.from(parentStampSelect.options).forEach(option => {
                if (option.value === '') {
                    option.hidden = false;
                    return;
                }

                option.hidden = !selectedCountryId ||
                    option.dataset.countryId !== selectedCountryId;
            });

            const selectedOption = Array.from(parentStampSelect.options).find(
                option => option.value === selectedParentStampId
            );

            if (
                selectedOption &&
                selectedOption.value !== '' &&
                selectedOption.hidden
            ) {
                parentStampSelect.value = '';
            }
        }

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
        countrySelect.addEventListener('change', filterParentStamps);

        filterStampSeries();
        filterParentStamps();

        // Afbeeldingspreview bij het aanmaken van een postzegel
        const stampImageInput = document.getElementById('stamp-create-image');
        const stampImagePreviewContainer = document.getElementById(
            'stamp-image-preview-container'
        );
        const stampImagePreview = document.getElementById('stamp-image-preview');

        let stampImagePreviewUrl = null;

        stampImageInput.addEventListener('change', function() {
            // Eventuele vorige preview opruimen
            if (stampImagePreviewUrl !== null) {
                URL.revokeObjectURL(stampImagePreviewUrl);
                stampImagePreviewUrl = null;
            }

            const file = this.files[0];

            // Preview verbergen als er geen afbeelding is geselecteerd
            if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                stampImagePreview.removeAttribute('src');
                stampImagePreviewContainer.classList.add('hidden');
                return;
            }

            // Tijdelijke URL maken voor de geselecteerde afbeelding
            stampImagePreviewUrl = URL.createObjectURL(file);

            stampImagePreview.src = stampImagePreviewUrl;
            stampImagePreviewContainer.classList.remove('hidden');
        });

        // Geselecteerde afbeelding en preview verwijderen
        const stampImageRemoveButton = document.getElementById('stamp-image-remove');

        stampImageRemoveButton.addEventListener('click', function() {
            // Geselecteerd bestand uit het uploadveld verwijderen
            stampImageInput.value = '';

            // Tijdelijke afbeeldings-URL opruimen
            if (stampImagePreviewUrl !== null) {
                URL.revokeObjectURL(stampImagePreviewUrl);
                stampImagePreviewUrl = null;
            }

            // Afbeelding en preview verbergen
            stampImagePreview.removeAttribute('src');
            stampImagePreviewContainer.classList.add('hidden');
        });
    </script>


</x-layouts::app>
