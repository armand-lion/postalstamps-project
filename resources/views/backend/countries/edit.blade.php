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
                    {{ __('Controleer de volgende velden:') }}
                </p>

                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('backend.countries.update', $country) }}" class="flex flex-col gap-6">
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

                    {{-- Nederlandse naam --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Nederlandse naam') }}
                            <span class="text-red-600">*</span>
                        </flux:label>

                        <flux:input name="name" value="{{ old('name', $country->name) }}" required maxlength="100" />

                        <flux:error name="name" />
                    </flux:field>

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
                            {{ __('Type entiteit') }}
                            <span class="text-red-600"> *</span>
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

                    {{-- Nederlandse beschrijving --}}
                    <div class="md:col-span-2">
                        <flux:field>
                            <flux:label>
                                {{ __('Nederlandse beschrijving') }}
                            </flux:label>

                            <flux:textarea name="description" rows="4">
                                {{ old('description', $country->description) }}</flux:textarea>

                            <flux:error name="description" />
                        </flux:field>
                    </div>

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
                                    {{ __('Naam') }}
                                    @if ($locale === 'nl')
                                        *
                                    @endif
                                </flux:label>

                                <flux:input name="translations[{{ $locale }}][name]"
                                    value="{{ old('translations.' . $locale . '.name', $locale === 'nl' ? $country->name : $translation?->name) }}"
                                    maxlength="100" @required($locale === 'nl') />

                                @if ($locale === 'nl')
                                    <flux:description>
                                        {{ __('Deze naam wordt gelijk gehouden aan de Nederlandse naam hierboven.') }}
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
                                    {{ old('translations.$locale.description', $locale === 'nl' ? $country->description : $translation?->description) }}
                                </flux:textarea>

                                <flux:error name="translations.{{ $locale }}.description" />
                            </flux:field>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Acties --}}
            <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                <flux:button :href="route('backend.countries.show', $country)" variant="ghost">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Wijzigingen opslaan') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
