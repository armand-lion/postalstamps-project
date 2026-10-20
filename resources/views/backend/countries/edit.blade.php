<x-layouts::app :title="__('Land of gebied bewerken')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginaheader --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Land of gebied bewerken') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Bewerk de gegevens en vertalingen van') }}
                    {{ $country->translationWithFallback()?->name ?? $country->name }}.
                </flux:text>
            </div>

            <flux:button :href="route('backend.countries.show', $country)" variant="ghost" icon="arrow-left">
                {{ __('Terug naar details') }}
            </flux:button>
        </div>

        {{-- Validatiefouten --}}
        @if ($errors->any())
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <p class="font-medium">
                    {{ __('Terug naar details') }}
                </p>

                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('backend.countries.update', $country) }}" class="flex flex-col gap-6" novalidate>
            @csrf
            @method('PUT')

            {{-- Basisgegevens --}}
            <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">
                <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <flux:heading size="lg">
                        {{ __('Basisgegevens') }}
                    </flux:heading>
                </div>

                <div class="grid gap-5 p-4 md:grid-cols-2">

                    {{-- ISO-code --}}
                    <flux:field>
                        <flux:label>
                            {{ __('ISO-code') }}
                        </flux:label>

                        <flux:input name="iso_code" value="{{ old('iso_code', $country->iso_code) }}" maxlength="3" />

                        <flux:error name="iso_code" />
                    </flux:field>

                    {{-- Type --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Type entiteit') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <flux:select name="entity_type" required>
                            @foreach ($entityTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('entity_type', $country->entity_type) === $value)>
                                    {{ __($label) }}
                                </option>
                            @endforeach
                        </flux:select>

                        <flux:error name="entity_type" />
                    </flux:field>

                    {{-- Beginjaar --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Beginjaar') }}
                        </flux:label>

                        <flux:input type="number" name="start_year"
                            value="{{ old('start_year', $country->start_year) }}" min="0" max="9999" />

                        <flux:error name="start_year" />
                    </flux:field>

                    {{-- Eindjaar --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Eindjaar') }}
                        </flux:label>

                        <flux:input type="number" name="end_year" value="{{ old('end_year', $country->end_year) }}"
                            min="0" max="9999" />

                        <flux:error name="end_year" />
                    </flux:field>

                    {{-- Status --}}
                    <div class="md:col-span-2">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $country->is_active ? '1' : '0') === '1')
                                class="mt-1 rounded border-neutral-300 text-accent focus:ring-accent">

                            <span>
                                <span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ __('Actief') }}
                                </span>

                                <span class="mt-1 block text-sm text-neutral-500 dark:text-neutral-400">
                                    {{ __('Geef aan of deze entiteit actief in de catalogus gebruikt mag worden.') }}
                                </span>
                            </span>
                        </label>
                    </div>
                </div>
            </section>

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

                @php
                    $languageNames = [
                        'nl' => 'Nederlands',
                        'en' => 'English',
                        'de' => 'Deutsch',
                        'fr' => 'Français',
                        'es' => 'Español',
                    ];

                    $translationsByLocale = $country->translations->keyBy('locale');
                @endphp

                <div class="flex flex-col gap-6 p-4">
                    @foreach ($locales as $locale)
                        @php
                            $translation = $translationsByLocale->get($locale);
                        @endphp

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
                                    value="{{ old('translations.' . $locale . '.name', $translation?->name) }}"
                                    maxlength="100"
                                    @required($locale === app()->getLocale()) />

                                    @if ($locale === app()->getLocale())
                                        <flux:description>
                                            {{ __('Deze naam is verplicht omdat dit de actieve applicatietaal is.') }}
                                        </flux:description>
                                    @else
                                        <flux:description>
                                            {{ __('Optioneel. Laat leeg als je deze vertaling nog niet hebt.') }}
                                        </flux:description>
                                    @endif

                                <flux:error name="translations.{{ $locale }}.name" />
                            </flux:field>

                            {{-- Vertaalde beschrijving --}}
                            <flux:field>
                                <flux:label>
                                    {{ __('Beschrijving') }}
                                </flux:label>

                                <flux:textarea name="translations[{{ $locale }}][description]" rows="3">
                                    {{ old("translations.$locale.description", $translation?->description) }}
                                </flux:textarea>

                                <flux:error name="translations.{{ $locale }}.description" />
                            </flux:field>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Acties --}}
            <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                <flux:button :href="route('backend.countries.show', $country)" variant="ghost" icon="x-mark">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Wijzigingen opslaan') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
