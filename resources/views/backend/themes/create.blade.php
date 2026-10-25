<x-layouts::app :title="__('Thema toevoegen')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginaheader --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Thema toevoegen') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Voeg een nieuw thema toe aan de postzegelcatalogus.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.themes.index')" variant="ghost" icon="arrow-left">
                {{ __('Terug naar overzicht') }}
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

        <form method="POST" action="{{ route('backend.themes.store') }}" class="flex flex-col gap-6" novalidate>

            @csrf

            {{-- Basisgegevens --}}
            <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">

                <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <flux:heading size="lg">
                        {{ __('Basisgegevens') }}
                    </flux:heading>

                    <flux:text class="mt-1">
                        {{ __('Algemene gegevens van het thema.') }}
                    </flux:text>
                </div>

                <div class="grid gap-5 p-4 md:grid-cols-2">

                    {{-- Slug --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Slug') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <flux:input name="slug" value="{{ old('slug') }}" maxlength="255" required
                            placeholder="{{ __('Bijvoorbeeld: decemberzegels') }}" />

                        <flux:description>
                            {{ __('Unieke identificatie van het thema, bijvoorbeeld decemberzegels.') }}
                        </flux:description>

                        <flux:error name="slug" />
                    </flux:field>

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

                            {{-- Taal --}}
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
                                    value="{{ old('translations.' . $locale . '.name') }}" maxlength="255"
                                    @required($locale === app()->getLocale())
                                    placeholder="{{ $languageNames[$locale] ?? strtoupper($locale) }}" />

                                @if ($locale === app()->getLocale())
                                    <flux:description>
                                        {{ __('Deze naam is verplicht omdat dit de actieve applicatietaal is.') }}
                                    </flux:description>
                                @else
                                    <flux:description>
                                        {{ __('Optioneel. Laat leeg als je deze vertaling nog niet hebt.') }}
                                    </flux:description>
                                @endif

                                <div class="min-h-5">
                                    <flux:error name="translations.{{ $locale }}.name" />
                                </div>
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

            {{-- Formulieracties --}}
            <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">

                <flux:button :href="route('backend.themes.index')" variant="ghost">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Thema opslaan') }}
                </flux:button>

            </div>
        </form>
    </div>
</x-layouts::app>
