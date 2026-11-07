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

                <flux:button :href="route('backend.catalogs.index')" variant="ghost" icon="arrow-left">
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button :href="route('backend.catalogs.edit', $catalog)" variant="primary" icon="pencil">
                    {{ __('Bewerken') }}
                </flux:button>

            </div>
        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div role="status"
                class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                <div class="flex items-center gap-2">
                    <flux:icon.check-circle class="size-5 shrink-0" />

                    <span>
                        {{ session('success') }}
                    </span>
                </div>
            </div>
        @endif

        {{-- Basisgegevens --}}
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">

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

                    <div
                        class="flex min-h-10 items-center rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm font-medium text-zinc-900">
                        {{ $catalog->name }}
                    </div>
                </div>

                {{-- Afkorting --}}
                <div>
                    <flux:text class="mb-1">
                        {{ __('Afkorting') }}
                    </flux:text>

                    <div
                        class="flex min-h-10 items-center rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm font-medium text-zinc-900">
                        {{ $catalog->abbreviation ?: '—' }}
                    </div>
                </div>

                {{-- Uitgever --}}
                <div>
                    <flux:text class="mb-1">
                        {{ __('Uitgever') }}
                    </flux:text>

                    <div
                        class="flex min-h-10 items-center rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm font-medium text-zinc-900">
                        {{ $catalog->publisher ?: '—' }}
                    </div>
                </div>

                {{-- Website --}}
                <div>
                    <flux:text class="mb-1">
                        {{ __('Website') }}
                    </flux:text>

                    <div
                        class="flex min-h-10 items-center rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm font-medium text-zinc-900">

                        @if ($catalog->website)
                            <a href="{{ $catalog->website }}" target="_blank" rel="noopener noreferrer"
                                class="break-all text-zinc-900 hover:underline">
                                {{ $catalog->website }}
                            </a>
                        @else
                            <span>—</span>
                        @endif

                    </div>
                </div>

                {{-- Beschrijving --}}
                <div class="md:col-span-2">

                    <flux:text class="mb-1">
                        {{ __('Beschrijving') }}
                    </flux:text>

                    <div
                        class="min-h-20 rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm font-medium whitespace-pre-line text-zinc-900">
                        {{ $catalog->description ?: '—' }}
                    </div>

                </div>

            </div>

        </section>

        {{-- Gekoppelde landen en gebieden --}}
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">

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

                                $translation =
                                    $country->translations->firstWhere('locale', $locale) ??
                                    $country->translations->firstWhere('locale', $fallbackLocale);

                                $countryName = $translation?->name ?? $country->name;
                            @endphp

                            <div class="flex items-center gap-3 rounded-lg border border-theme-accent-3 bg-brown-5 p-3">

                                <flux:icon.globe-alt class="size-5 text-zinc-600" />

                                <span class="text-sm font-medium text-zinc-900">
                                    {{ $countryName }}
                                </span>

                            </div>
                        @endforeach

                    </div>

                @endif

            </div>

        </section>

        {{-- Cataloguswaarderingen --}}
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Cataloguswaarderingen') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Waarderingscategorieën die bij deze catalogus horen.') }}
                </flux:text>
            </div>

            <div class="p-4">

                @if ($catalog->conditionRules->isEmpty())

                    <flux:text>
                        {{ __('Er zijn nog geen waarderingscategorieën aan deze catalogus gekoppeld.') }}
                    </flux:text>
                @else
                    <div class="grid gap-3">

                        @foreach ($catalog->conditionRules->sortBy('sort_order') as $rule)
                            @php
                                $locale = app()->getLocale();
                                $fallbackLocale = config('app.fallback_locale', 'nl');

                                $translation =
                                    $rule->condition->translations->firstWhere('locale', $locale) ??
                                    $rule->condition->translations->firstWhere('locale', $fallbackLocale);

                                $conditionName = $translation?->name ?? $rule->condition->slug;
                            @endphp

                            <div class="rounded-lg border border-theme-accent-3 bg-brown-5 p-3">

                                <div class="flex flex-col gap-2">

                                    <span class="text-sm font-medium text-zinc-900">
                                        {{ $conditionName }}
                                    </span>

                                    <div class="text-sm text-zinc-700">
                                        <span class="font-medium">
                                            {{ __('Beginjaar') }}:
                                        </span>

                                        {{ $rule->issue_year_from ?? '—' }}
                                    </div>

                                    <div class="text-sm text-zinc-700">
                                        <span class="font-medium">
                                            {{ __('Eindjaar') }}:
                                        </span>

                                        {{ $rule->issue_year_to ?? '—' }}
                                    </div>

                                    <div class="text-sm text-zinc-700">
                                        <span class="font-medium">
                                            {{ __('Volgorde') }}:
                                        </span>

                                        {{ $rule->sort_order ?? '—' }}
                                    </div>

                                </div>

                            </div>
                        @endforeach

                    </div>

                @endif

            </div>

        </section>

        {{-- Catalogusedities --}}
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">

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

                {{-- Catalogusedities beheren --}}
                <div class="mt-4 flex justify-end">
                    <flux:button :href="route('backend.catalogs.editions.index', $catalog)" variant="primary"
                        icon="book-open" class="border border-theme-accent-3 bg-brown-3! text-zinc-900 hover:bg-brown-5!">
                        {{ __('Catalogusedities beheren') }}
                    </flux:button>
                </div>
            </div>

        </section>

        {{-- Navigatieknoppen onderaan --}}
        <div
            class="mt-8 flex flex-wrap justify-end gap-3 rounded-xl border border-theme-accent-1 bg-brown-6 p-4 dark:border-neutral-700">
            <flux:button :href="route('backend.catalogs.index')" variant="ghost" icon="arrow-left"
                class="border border-theme-accent-3 bg-brown-3! text-zinc-900 hover:bg-brown-5!">
                {{ __('Terug naar overzicht') }}
            </flux:button>

            <flux:button :href="route('backend.catalogs.edit', $catalog)" variant="primary" icon="pencil"
                class="border border-theme-accent-3 bg-brown-3! text-zinc-900 hover:bg-brown-5!">
                {{ __('Bewerken') }}
            </flux:button>
        </div>
    </div>
    </div>

</x-layouts::app>
