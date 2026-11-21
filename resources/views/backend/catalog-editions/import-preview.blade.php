<x-layouts::app :title="__('Excel-import controleren')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Foutmelding bij definitieve Excel-import --}}
        @if ($errors->has('file'))
            <div class="rounded-lg border border-red-500 bg-red-50 p-4 text-red-700" role="alert">

                {{ $errors->first('file') }}

            </div>
        @endif

        {{-- Paginakop --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

                <div>
                    <flux:heading size="xl">
                        {{ __('Excel-import controleren') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Controleer de gegevens uit het Excel-bestand voordat je de cataloguseditie importeert.') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium">
                        {{ $catalog->name }}
                    </flux:text>
                </div>

                <flux:button :href="route('backend.catalogs.editions.import.create', $catalog)" variant="ghost"
                    icon="arrow-left">

                    {{ __('Terug naar Excel-import') }}

                </flux:button>

            </div>

        </div>

        {{-- Catalogus- en editiegegevens --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Catalogus- en editiegegevens') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Onderstaande gegevens zijn afkomstig uit het Excel-bestand.') }}
            </flux:text>

            <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-3">

                {{-- Catalogusnaam --}}
                <div>
                    <flux:text class="font-medium">
                        {{ __('Catalogusnaam') }}
                    </flux:text>

                    <div class="mt-2 text-zinc-900">
                        {{ $preview['catalog_name'] }}
                    </div>
                </div>

                {{-- Editienaam --}}
                <div>
                    <flux:text class="font-medium">
                        {{ __('Editienaam') }}
                    </flux:text>

                    <div class="mt-2 text-zinc-900">
                        {{ $preview['edition_name'] }}
                    </div>
                </div>

                {{-- Editiejaar --}}
                <div>
                    <flux:text class="font-medium">
                        {{ __('Editiejaar') }}
                    </flux:text>

                    <div class="mt-2 text-zinc-900">
                        {{ $preview['edition_year'] }}
                    </div>
                </div>

            </div>

        </div>

        {{-- Overzicht van de gegevens in het Excel-bestand --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Overzicht Excel-bestand') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Onderstaande aantallen geven aan hoeveel records het Excel-bestand bevat.') }}
            </flux:text>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

                {{-- Landen --}}
                <div class="rounded-lg border border-theme-accent-3 bg-brown-5 p-4">
                    <flux:text>
                        {{ __('Landen') }}
                    </flux:text>

                    <div class="mt-2 text-2xl font-semibold text-zinc-900">
                        {{ $preview['country_count'] }}
                    </div>
                </div>

                {{-- Postzegelseries --}}
                <div class="rounded-lg border border-theme-accent-3 bg-brown-5 p-4">
                    <flux:text>
                        {{ __('Postzegelseries') }}
                    </flux:text>

                    <div class="mt-2 text-2xl font-semibold text-zinc-900">
                        {{ $preview['series_count'] }}
                    </div>
                </div>

                {{-- Postzegels --}}
                <div class="rounded-lg border border-theme-accent-3 bg-brown-5 p-4">
                    <flux:text>
                        {{ __('Postzegels') }}
                    </flux:text>

                    <div class="mt-2 text-2xl font-semibold text-zinc-900">
                        {{ $preview['stamp_count'] }}
                    </div>
                </div>

                {{-- Thema's --}}
                <div class="rounded-lg border border-theme-accent-3 bg-brown-5 p-4">
                    <flux:text>
                        {{ __("Thema's") }}
                    </flux:text>

                    <div class="mt-2 text-2xl font-semibold text-zinc-900">
                        {{ $preview['theme_count'] }}
                    </div>
                </div>

            </div>

        </div>

        {{-- Definitieve Excel-import bevestigen --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Import bevestigen') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Controleer of alle bovenstaande gegevens correct zijn. Na bevestiging wordt de cataloguseditie definitief geïmporteerd.') }}
            </flux:text>

            <form method="POST" action="{{ route('backend.catalogs.editions.import.confirm', $catalog) }}"
                class="mt-6">

                @csrf

                <flux:button type="submit" icon="check"
                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                    {{ __('Import bevestigen') }}

                </flux:button>

            </form>

        </div>

    </div>

</x-layouts::app>
