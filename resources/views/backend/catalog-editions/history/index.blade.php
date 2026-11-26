<x-layouts::app :title="__('Wijzigingsgeschiedenis')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div
            class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Wijzigingsgeschiedenis') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Overzicht van alle geregistreerde wijzigingen binnen deze cataloguseditie.') }}
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $catalog->name }} — {{ $edition->edition_name }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-3">

                {{-- Terug naar cataloguseditie --}}
                <flux:button :href="route('backend.catalogs.editions.show', [$catalog, $edition])" variant="ghost"
                    icon="arrow-left">
                    {{ __('Terug naar cataloguseditie') }}
                </flux:button>

            </div>

        </div>

        {{-- Geschiedenisoverzicht --}}
        <section
            class="flex flex-col gap-4 overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">

                <flux:heading size="lg">
                    {{ __('Geregistreerde bewerkingen') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Hier vind je alle bewerkingen die op deze cataloguseditie zijn uitgevoerd.') }}
                </flux:text>

            </div>

            <div>

                @if ($batches->isEmpty())

                    {{-- Geen geschiedenis --}}
                    <div class="p-4 py-8 text-center">

                        <flux:icon.arrow-path class="mx-auto mb-4 size-10 text-neutral-400" />

                        <flux:heading size="lg">
                            {{ __('Geen wijzigingsgeschiedenis gevonden') }}
                        </flux:heading>

                        <flux:text class="mt-2">
                            {{ __('Voor deze cataloguseditie zijn nog geen wijzigingen geregistreerd.') }}
                        </flux:text>

                    </div>
                @else
                    {{-- Tabel met geschiedenis --}}
                    <div class="w-full overflow-x-auto">

                        <table class="w-full text-left text-sm">

                            {{-- Kolomkoppen --}}
                            <thead class="bg-brown-5 text-brown-2 dark:bg-neutral-900 dark:text-neutral-300">

                                <tr>

                                    <th class="px-4 py-3 font-medium">
                                        {{ __('Datum en tijd') }}
                                    </th>

                                    <th class="px-4 py-3 font-medium">
                                        {{ __('Bewerking') }}
                                    </th>

                                    <th class="px-4 py-3 font-medium">
                                        {{ __('Omschrijving') }}
                                    </th>

                                    @if ($isAdmin)
                                        <th class="px-4 py-3 font-medium">
                                            {{ __('Uitgevoerd door') }}
                                        </th>
                                    @endif

                                    <th class="px-4 py-3 text-center font-medium">
                                        {{ __('Wijzigingen') }}
                                    </th>

                                    <th class="px-4 py-3 text-right font-medium">
                                        {{ __('Acties') }}
                                    </th>

                                </tr>

                            </thead>

                            {{-- Tabelrijen --}}
                            <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">

                                @foreach ($batches as $batch)
                                    <tr class="transition-colors hover:bg-brown-5!">

                                        {{-- Datum en tijd --}}
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            {{ $batch->created_at?->format('d-m-Y H:i') ?? '—' }}
                                        </td>

                                        {{-- Bewerking --}}
                                        <td class="px-4 py-3">
                                            <span class="font-medium">
                                                {{ $batch->operationLabel() }}
                                            </span>
                                        </td>

                                        {{-- Omschrijving --}}
                                        <td class="px-4 py-3">
                                            {{ $batch->description ?: '—' }}
                                        </td>

                                        {{-- Uitgevoerd door (alleen beheerders) --}}
                                        @if ($isAdmin)
                                            <td class="px-4 py-3">
                                                {{ $batch->user_name ?: '—' }}
                                            </td>
                                        @endif

                                        {{-- Aantal wijzigingen --}}
                                        <td class="px-4 py-3 text-center">
                                            {{ $batch->logs_count }}
                                        </td>

                                        {{-- Acties --}}
                                        <td class="px-4 py-3">

                                            <div class="flex items-center justify-end">

                                                <flux:button
                                                    :href="route('backend.catalogs.editions.history.show', [
                                                        $catalog,
                                                        $edition,
                                                        $batch
                                                    ])"
                                                    size="sm" variant="ghost" icon="eye">

                                                    {{ __('Details bekijken') }}

                                                </flux:button>

                                            </div>

                                        </td>

                                    </tr>
                                @endforeach

                            </tbody>

                        </table>

                    </div>

                    {{-- Paginering --}}
                    @if ($batches->hasPages())
                        <div class="border-t border-theme-accent-1 px-4 py-4 dark:border-neutral-700">

                            {{ $batches->links() }}

                        </div>
                    @endif

                @endif

            </div>

        </section>

    </div>

</x-layouts::app>
