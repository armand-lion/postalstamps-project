<x-layouts::app :title="$country->translationWithFallback()?->name ?? $country->name">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginaheader --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ $country->translationWithFallback()?->name ?? $country->name }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Details van dit land of gebied.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('backend.countries.index')" variant="ghost" icon="arrow-left">
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button :href="route('backend.countries.edit', $country)" variant="primary" icon="pencil">
                    {{ __('Bewerken') }}
                </flux:button>
            </div>
        </div>

        {{-- Meldingen --}}
        @if (session('success'))
            <div
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Algemene gegevens --}}
        <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Algemene gegevens') }}
                </flux:heading>
            </div>

            <dl class="grid gap-5 p-4 sm:grid-cols-2 lg:grid-cols-3">

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Naam') }}
                    </dt>

                    <dd class="mt-1 font-medium">
                        {{ $country->translationWithFallback()?->name ?? $country->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('ISO-code') }}
                    </dt>

                    <dd class="mt-1 font-medium">
                        {{ $country->iso_code ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Type entiteit') }}
                    </dt>

                    <dd class="mt-1 font-medium">
                        {{ __($entityTypes[$country->entity_type] ?? $country->entity_type) }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Beginjaar') }}
                    </dt>

                    <dd class="mt-1 font-medium">
                        {{ $country->start_year ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Eindjaar') }}
                    </dt>

                    <dd class="mt-1 font-medium">
                        {{ $country->end_year ?? __('Onbekend of nog bestaand') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Status') }}
                    </dt>

                    <dd class="mt-1">
                        @if ($country->is_active)
                            <span class="text-green-700 dark:text-green-400">
                                {{ __('Actief') }}
                            </span>
                        @else
                            <span class="text-neutral-500">
                                {{ __('Inactief') }}
                            </span>
                        @endif
                    </dd>
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Beschrijving') }}
                    </dt>

                    <dd class="mt-1 whitespace-pre-line">
                        {{ $country->translationWithFallback()?->description ?: '—' }}
                    </dd>
                </div>

                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Slug') }}
                    </dt>

                    <dd class="mt-1 break-all font-mono text-sm">
                        {{ $country->slug }}
                    </dd>
                </div>
            </dl>
        </section>

        {{-- Gekoppelde gegevens --}}
        <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">
            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Gekoppelde gegevens') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Aantal records dat aan dit land of gebied gekoppeld is.') }}
                </flux:text>
            </div>

            <div class="grid gap-4 p-4 sm:grid-cols-3">

                <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:text>
                        {{ __('Postzegels') }}
                    </flux:text>

                    <flux:heading size="xl" class="mt-2">
                        {{ $country->stamps_count }}
                    </flux:heading>
                </div>

                <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:text>
                        {{ __('Series') }}
                    </flux:text>

                    <flux:heading size="xl" class="mt-2">
                        {{ $country->stamp_series_count }}
                    </flux:heading>
                </div>

                <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <flux:text>
                        {{ __('Catalogi') }}
                    </flux:text>

                    <flux:heading size="xl" class="mt-2">
                        {{ $country->catalogs_count }}
                    </flux:heading>
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
                    {{ __('Beschikbare namen en beschrijvingen per taal.') }}
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

            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @foreach ($locales as $locale)
                    @php
                        $translation = $translationsByLocale->get($locale);
                    @endphp

                    <div class="grid gap-4 p-4 md:grid-cols-2">
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Taal') }}
                            </dt>

                            <dd class="mt-1 font-medium">
                                {{ $languageNames[$locale] ?? strtoupper($locale) }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Naam') }}
                            </dt>

                            <dd class="mt-1 font-medium">
                                {{ $translation?->name ?: '—' }}
                            </dd>
                        </div>

                        <div class="md:col-span-2">
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Beschrijving') }}
                            </dt>

                            <dd class="mt-1 whitespace-pre-line">
                                {{ $translation?->description ?: '—' }}
                            </dd>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Acties --}}
        <div class="flex flex-wrap justify-between gap-3">
            <flux:button :href="route('backend.countries.index')" variant="ghost" icon="arrow-left">
                {{ __('Terug naar overzicht') }}
            </flux:button>

            <flux:button :href="route('backend.countries.edit', $country)" variant="primary" icon="pencil">
                {{ __('Land of gebied bewerken') }}
            </flux:button>
        </div>

    </div>
</x-layouts::app>
