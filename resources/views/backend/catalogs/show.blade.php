<x-layouts::app :title="__('Catalogus bekijken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ $catalog->name }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Bekijk de gegevens van deze postzegelcatalogus.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-3">

                <flux:button
                    :href="route('backend.catalogs.index')"
                    variant="ghost"
                    icon="arrow-left"
                >
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button
                    :href="route('backend.catalogs.edit', $catalog)"
                    variant="primary"
                    icon="pencil"
                >
                    {{ __('Bewerken') }}
                </flux:button>

            </div>
        </div>

                {{-- Succesmelding --}}
        @if (session('success'))
            <div
                role="status"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200"
            >
                <div class="flex items-center gap-2">
                    <flux:icon.check-circle class="size-5 shrink-0" />

                    <span>
                        {{ session('success') }}
                    </span>
                </div>
            </div>
        @endif

        {{-- Basisgegevens --}}
        <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">

                <flux:heading size="lg">
                    {{ __('Basisgegevens') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Algemene gegevens van de catalogus.') }}
                </flux:text>

            </div>

            <div class="grid gap-6 p-4 md:grid-cols-2">

                {{-- Naam --}}
                <div>
                    <flux:text class="mb-1">
                        {{ __('Naam') }}
                    </flux:text>

                    <div class="font-medium">
                        {{ $catalog->name }}
                    </div>
                </div>

                {{-- Afkorting --}}
                <div>
                    <flux:text class="mb-1">
                        {{ __('Afkorting') }}
                    </flux:text>

                    <div class="font-medium">
                        {{ $catalog->abbreviation ?: '—' }}
                    </div>
                </div>

                {{-- Uitgever --}}
                <div>
                    <flux:text class="mb-1">
                        {{ __('Uitgever') }}
                    </flux:text>

                    <div class="font-medium">
                        {{ $catalog->publisher ?: '—' }}
                    </div>
                </div>

                {{-- Website --}}
                <div>
                    <flux:text class="mb-1">
                        {{ __('Website') }}
                    </flux:text>

                    @if ($catalog->website)

                        <a
                            href="{{ $catalog->website }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-accent hover:underline"
                        >
                            {{ $catalog->website }}
                        </a>

                    @else

                        <div class="font-medium">
                            —
                        </div>

                    @endif
                </div>

                {{-- Beschrijving --}}
                <div class="md:col-span-2">

                    <flux:text class="mb-1">
                        {{ __('Beschrijving') }}
                    </flux:text>

                    <div class="whitespace-pre-line font-medium">
                        {{ $catalog->description ?: '—' }}
                    </div>

                </div>

            </div>

        </section>

        {{-- Gekoppelde landen en gebieden --}}
        <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">

                <flux:heading size="lg">
                    {{ __('Landen en gebieden') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Landen en gebieden die aan deze catalogus zijn gekoppeld.') }}
                </flux:text>

            </div>

            <div class="p-4">

                @if ($catalog->countries->isEmpty())

                    <flux:text>
                        {{ __('Er zijn nog geen landen of gebieden aan deze catalogus gekoppeld.') }}
                    </flux:text>

                @else

                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">

                        @foreach ($catalog->countries as $country)

                            @php
                                $locale = app()->getLocale();

                                $fallbackLocale = config('app.fallback_locale', 'nl');

                                $translation = $country->translations->firstWhere('locale', $locale)
                                    ?? $country->translations->firstWhere('locale', $fallbackLocale);

                                $countryName = $translation?->name ?? $country->name;
                            @endphp

                            <div
                                class="flex items-center gap-3 rounded-lg border border-neutral-200 p-3 dark:border-neutral-700"
                            >

                                <flux:icon.globe-alt class="size-5 text-neutral-500" />

                                <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ $countryName }}
                                </span>

                            </div>

                        @endforeach

                    </div>

                @endif

            </div>

        </section>

        {{-- Catalogusedities --}}
        <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">

                <flux:heading size="lg">
                    {{ __('Catalogusedities') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Overzicht van de edities die bij deze catalogus horen.') }}
                </flux:text>

            </div>

            <div class="p-4">

                @if ($catalog->editions->isEmpty())

                    <flux:text>
                        {{ __('Er zijn nog geen edities aan deze catalogus toegevoegd.') }}
                    </flux:text>

                @else

                    <flux:text>
                        {{ __('Aantal catalogusedities') }}:
                        {{ $catalog->editions->count() }}
                    </flux:text>

                @endif

            </div>

        </section>
        {{-- Navigatieknoppen onderaan --}}
        <div class="mt-8 flex flex-wrap justify-end gap-3">

            {{-- Terug naar overzicht --}}
            <flux:button
                :href="route('backend.catalogs.index')"
                variant="ghost"
                icon="arrow-left"
            >
                {{ __('Terug naar overzicht') }}
            </flux:button>

            {{-- Bewerken --}}
            <flux:button
                :href="route('backend.catalogs.edit', $catalog)"
                variant="primary"
                icon="pencil"
            >
                {{ __('Bewerken') }}
            </flux:button>

        </div>
    </div>

</x-layouts::app>
