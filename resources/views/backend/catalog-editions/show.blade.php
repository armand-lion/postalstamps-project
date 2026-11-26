<x-layouts::app :title="__('Cataloguseditie bekijken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-theme-accent-3 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl" class="text-zinc-900!">
                    {{ __('Cataloguseditie bekijken') }}
                </flux:heading>

                <flux:text class="mt-2 text-zinc-600!">
                    {{ __('Gegevens van de geselecteerde cataloguseditie.') }}
                </flux:text>

                <flux:text class="mt-1 font-medium text-zinc-900!">
                    {{ $catalog->name }}
                </flux:text>
            </div>

            {{-- Navigatieknoppen bovenaan --}}
            <div class="flex flex-wrap justify-end gap-3">

                <flux:button :href="route('backend.catalogs.editions.index', $catalog)" variant="ghost" icon="arrow-left"
                    class="text-zinc-900!">
                    {{ __('Terug naar catalogusedities') }}
                </flux:button>

                {{-- Catalogus volledigheid --}}
                <flux:button :href="route('backend.catalogs.editions.completeness', [$catalog, $edition])"
                    icon="chart-bar" class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                    {{ __('Volledigheid bekijken') }}
                </flux:button>

                {{-- Excel-export cataloguseditie --}}
                <flux:button :href="route('backend.catalogs.editions.export.excel', [$catalog, $edition])"
                    icon="arrow-up-tray"
                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                    {{ __('Excel exporteren') }}
                </flux:button>

                {{-- Cataloguswaarden controleren --}}
                @if (auth()->user()?->is_admin && $valuesNeedingReview > 0)
                    <flux:button :href="route('backend.catalogs.editions.review', [$catalog, $edition])"
                        icon="clipboard-document-check"
                        class="border border-amber-400! bg-amber-50! text-amber-900! hover:bg-amber-100!">

                        {{ __('Te controleren waarden') }}

                        <span class="ml-1 font-semibold">
                            ({{ $valuesNeedingReview }})
                        </span>

                    </flux:button>
                @endif

                <flux:button :href="route('backend.catalogs.editions.history.index', [$catalog, $edition])"
                    icon="arrow-path"
                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                    {{ __('Wijzigingsgeschiedenis') }}
                </flux:button>

                <flux:button :href="route('backend.catalogs.editions.edit', [$catalog, $edition])" variant="primary"
                    icon="pencil" class="bg-brown-1 hover:bg-brown-2">
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

        @if (session('error'))
            <div role="alert"
                class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300">
                {{ session('error') }}
            </div>
        @endif

        {{-- Gegevens cataloguseditie --}}
        <section class="overflow-hidden rounded-xl border border-theme-accent-3 bg-brown-5">

            <div class="border-b border-theme-accent-3 bg-brown-6 px-4 py-3">

                <flux:heading size="lg" class="text-zinc-900!">
                    {{ __('Cataloguseditie') }}
                </flux:heading>

                <flux:text class="mt-1 text-zinc-900!">
                    {{ __('Overzicht van de gegevens van deze editie.') }}
                </flux:text>

            </div>

            <div class="grid gap-6 p-4 text-zinc-900 md:grid-cols-2">

                {{-- Editienaam --}}
                <div>
                    <flux:text class="font-medium text-zinc-900!">
                        {{ __('Editienaam') }}
                    </flux:text>

                    <flux:text class="mt-1 text-zinc-900!">
                        {{ $edition->edition_name }}
                    </flux:text>
                </div>

                {{-- Editiejaar --}}
                <div>
                    <flux:text class="font-medium text-zinc-900!">
                        {{ __('Editiejaar') }}
                    </flux:text>

                    <flux:text class="mt-1 text-zinc-900!">
                        {{ $edition->edition_year }}
                    </flux:text>
                </div>

                {{-- Publicatiedatum --}}
                <div>
                    <flux:text class="font-medium text-zinc-900!">
                        {{ __('Publicatiedatum') }}
                    </flux:text>

                    <flux:text class="mt-1 text-zinc-900!">
                        {{ $edition->publication_date?->format('d-m-Y') ?? '—' }}
                    </flux:text>
                </div>

                {{-- Valuta --}}
                <div>
                    <flux:text class="font-medium text-zinc-900!">
                        {{ __('Valuta') }}
                    </flux:text>

                    <flux:text class="mt-1 text-zinc-900!">
                        {{ $edition->currency ?: '—' }}
                    </flux:text>
                </div>

                {{-- Opmerkingen --}}
                <div class="md:col-span-2">

                    <flux:text class="font-medium text-zinc-900!">
                        {{ __('Opmerkingen') }}
                    </flux:text>

                    <flux:text class="mt-1 whitespace-pre-line text-zinc-900!">
                        {{ $edition->notes ?: '—' }}
                    </flux:text>

                </div>

            </div>

        </section>

        {{-- Controleoverzicht cataloguswaarden --}}
        <section class="overflow-hidden rounded-xl border border-theme-accent-3 bg-brown-5">

            <div class="border-b border-theme-accent-3 bg-brown-6 px-4 py-3">

                <flux:heading size="lg" class="text-zinc-900!">
                    {{ __('Controleoverzicht cataloguswaarden') }}
                </flux:heading>

                <flux:text class="mt-1 text-zinc-900!">
                    {{ __('Overzicht van cataloguswaarden die nog gecontroleerd moeten worden.') }}
                </flux:text>

            </div>

            <div class="grid gap-4 p-4 md:grid-cols-3">

                {{-- Nog controleren --}}
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-4">

                    <flux:text class="font-medium text-amber-900!">
                        {{ __('Nog controleren') }}
                    </flux:text>

                    <flux:heading size="xl" class="mt-2 text-amber-900!">
                        {{ $valuesNeedingReview }}
                    </flux:heading>

                </div>

                {{-- Geen controlemarkering --}}
                <div class="rounded-lg border border-green-300 bg-green-50 p-4">

                    <flux:text class="font-medium text-green-900!">
                        {{ __('Geen controlemarkering') }}
                    </flux:text>

                    <flux:heading size="xl" class="mt-2 text-green-900!">
                        {{ $reviewedCatalogValues }}
                    </flux:heading>

                </div>

                {{-- Totaal --}}
                <div class="rounded-lg border border-theme-accent-3 bg-brown-6 p-4">

                    <flux:text class="font-medium text-zinc-900!">
                        {{ __('Totaal cataloguswaarden') }}
                    </flux:text>

                    <flux:heading size="xl" class="mt-2 text-zinc-900!">
                        {{ $totalCatalogValues }}
                    </flux:heading>

                </div>

            </div>

        </section>

        {{-- Navigatieknoppen onderaan --}}
        <div class="flex flex-wrap justify-end gap-3 rounded-xl border border-theme-accent-3 bg-brown-6 p-5">

            <flux:button :href="route('backend.catalogs.editions.index', $catalog)" variant="ghost" icon="arrow-left"
                class="text-zinc-900!">
                {{ __('Terug naar catalogusedities') }}
            </flux:button>

            {{-- Catalogusvolledigheid --}}
            <flux:button :href="route('backend.catalogs.editions.completeness', [$catalog, $edition])" icon="chart-bar"
                class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                {{ __('Volledigheid bekijken') }}
            </flux:button>

            <flux:button :href="route('backend.catalogs.editions.edit', [$catalog, $edition])" variant="primary"
                icon="pencil" class="bg-brown-1 hover:bg-brown-2">
                {{ __('Bewerken') }}
            </flux:button>

        </div>

    </div>

</x-layouts::app>
