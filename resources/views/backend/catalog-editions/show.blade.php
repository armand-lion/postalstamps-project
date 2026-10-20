<x-layouts::app :title="__('Cataloguseditie bekijken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Cataloguseditie bekijken') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Gegevens van de geselecteerde cataloguseditie.') }}
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $catalog->name }}
                </flux:text>
            </div>

            {{-- Navigatieknoppen bovenaan --}}
            <div class="flex flex-wrap justify-end gap-3">

                <flux:button
                    :href="route('backend.catalogs.editions.index', $catalog)"
                    variant="ghost"
                    icon="arrow-left"
                >
                    {{ __('Terug naar catalogusedities') }}
                </flux:button>

                <flux:button
                    :href="route('backend.catalogs.editions.edit', [$catalog, $edition])"
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

        @if (session('error'))
            <div
                role="alert"
                class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/30 dark:text-red-300"
            >
                {{ session('error') }}
            </div>
        @endif

        {{-- Gegevens cataloguseditie --}}
        <section class="rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">

                <flux:heading size="lg">
                    {{ __('Cataloguseditie') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Overzicht van de gegevens van deze editie.') }}
                </flux:text>

            </div>

            <div class="grid gap-6 p-4 md:grid-cols-2">

                {{-- Editienaam --}}
                <div>
                    <flux:text class="font-medium">
                        {{ __('Editienaam') }}
                    </flux:text>

                    <flux:text class="mt-1">
                        {{ $edition->edition_name }}
                    </flux:text>
                </div>

                {{-- Editiejaar --}}
                <div>
                    <flux:text class="font-medium">
                        {{ __('Editiejaar') }}
                    </flux:text>

                    <flux:text class="mt-1">
                        {{ $edition->edition_year }}
                    </flux:text>
                </div>

                {{-- Publicatiedatum --}}
                <div>
                    <flux:text class="font-medium">
                        {{ __('Publicatiedatum') }}
                    </flux:text>

                    <flux:text class="mt-1">
                        {{ $edition->publication_date?->format('d-m-Y') ?? '—' }}
                    </flux:text>
                </div>

                {{-- Valuta --}}
                <div>
                    <flux:text class="font-medium">
                        {{ __('Valuta') }}
                    </flux:text>

                    <flux:text class="mt-1">
                        {{ $edition->currency ?: '—' }}
                    </flux:text>
                </div>

                {{-- Opmerkingen --}}
                <div class="md:col-span-2">

                    <flux:text class="font-medium">
                        {{ __('Opmerkingen') }}
                    </flux:text>

                    <flux:text class="mt-1 whitespace-pre-line">
                        {{ $edition->notes ?: '—' }}
                    </flux:text>

                </div>

            </div>

        </section>

        {{-- Navigatieknoppen onderaan --}}
        <div class="flex flex-wrap justify-end gap-3">

            <flux:button
                :href="route('backend.catalogs.editions.index', $catalog)"
                variant="ghost"
                icon="arrow-left"
            >
                {{ __('Terug naar catalogusedities') }}
            </flux:button>

            <flux:button
                :href="route('backend.catalogs.editions.edit', [$catalog, $edition])"
                variant="primary"
                icon="pencil"
            >
                {{ __('Bewerken') }}
            </flux:button>

        </div>

    </div>

</x-layouts::app>
