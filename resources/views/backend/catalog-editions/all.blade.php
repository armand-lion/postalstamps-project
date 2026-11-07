<x-layouts::app :title="__('Catalogusedities')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Catalogusedities') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Overzicht van alle catalogusedities.') }}
                </flux:text>
            </div>

            {{-- Cataloguseditie toevoegen --}}
            <flux:dropdown position="bottom" align="end">

                <flux:button type="button" variant="primary" icon="plus" icon-trailing="chevron-down"
                    class="border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-4!">

                    {{ __('Editie toevoegen') }}

                </flux:button>

                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                    @foreach ($catalogs as $catalog)
                        <flux:menu.item :href="route('backend.catalogs.editions.create', $catalog)"
                            class="cursor-pointer text-zinc-900! hover:bg-brown-5!">

                            {{ $catalog->name }}

                        </flux:menu.item>
                    @endforeach

                </flux:menu>

            </flux:dropdown>

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

        {{-- Foutmelding --}}
        @if (session('error'))
            <div role="alert"
                class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">

                <div class="flex items-center gap-2">
                    <flux:icon.exclamation-circle class="size-5 shrink-0" />

                    <span>
                        {{ session('error') }}
                    </span>
                </div>

            </div>
        @endif

        {{-- Filter op catalogus --}}
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 dark:border-neutral-700">

            <form method="GET" action="{{ route('backend.catalog-editions.index') }}"
                class="flex flex-col gap-4 sm:flex-row sm:items-end">

                {{-- Catalogusfilter --}}
                <div class="w-full sm:w-80">
                    <flux:field>
                        <flux:label>{{ __('Catalogus') }}</flux:label>

                        @php
                            $selectedCatalogId = request('catalog_id');

                            $selectedCatalog = $catalogs->firstWhere('id', $selectedCatalogId);
                        @endphp

                        <div x-data="{
                            selectedId: @js((string) ($selectedCatalogId ?? '')),
                            selectedName: @js($selectedCatalog?->name ?? __('Alle catalogussen'))
                        }">
                            <input type="hidden" name="catalog_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">
                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    <flux:menu.item as="button" type="button"
                                        x-on:click="selectedId = ''; selectedName = @js(__('Alle catalogussen'))"
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Alle catalogussen') }}

                                    </flux:menu.item>

                                    @foreach ($catalogs as $catalog)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedId = @js((string) $catalog->id); selectedName = @js($catalog->name)"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $catalog->name }}

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:field>
                </div>

                <flux:button type="submit" variant="primary"
                    class="border-theme-accent-3! bg-brown-3! text-zinc-900! hover:bg-brown-5!">
                    {{ __('Filteren') }}
                </flux:button>

                <flux:button :href="route('backend.catalog-editions.index')" variant="ghost"
                    class="border border-theme-accent-3! bg-brown-3! text-zinc-900! hover:bg-brown-5!">
                    {{ __('Reset') }}
                </flux:button>

            </form>

        </section>

        {{-- Overzicht catalogusedities --}}
        <section class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">

                <flux:heading size="lg">
                    {{ __('Catalogusedities') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Overzicht van alle catalogusedities en bijbehorende catalogussen.') }}
                </flux:text>

            </div>

            @if ($editions->isEmpty())

                {{-- Geen catalogusedities --}}
                <div class="p-8 text-center">

                    <flux:icon.book-open class="mx-auto mb-4 size-10 text-neutral-400" />

                    <flux:heading size="lg">
                        {{ __('Geen catalogusedities gevonden') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Er zijn nog geen catalogusedities toegevoegd.') }}
                    </flux:text>

                </div>
            @else
                {{-- Tabel met catalogusedities --}}
                <div class="w-full overflow-x-auto">

                    <table class="w-full text-left text-sm">

                        {{-- Kolomkoppen --}}
                        <thead class="bg-brown-5 text-brown-2 dark:bg-neutral-900 dark:text-neutral-300">

                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    {{ __('Catalogus') }}
                                </th>

                                <th class="px-4 py-3 font-medium">
                                    {{ __('Editienaam') }}
                                </th>

                                <th class="px-4 py-3 font-medium">
                                    {{ __('Editiejaar') }}
                                </th>

                                <th class="px-4 py-3 font-medium">
                                    {{ __('Publicatiedatum') }}
                                </th>

                                <th class="px-4 py-3 font-medium">
                                    {{ __('Valuta') }}
                                </th>

                                <th class="px-4 py-3 text-right font-medium">
                                    {{ __('Acties') }}
                                </th>
                            </tr>

                        </thead>

                        {{-- Tabelrijen --}}
                        <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">

                            @foreach ($editions as $edition)
                                <tr class="transition-colors hover:bg-brown-5!">

                                    {{-- Catalogus --}}
                                    <td class="px-4 py-3 font-medium">
                                        {{ $edition->catalog->name }}
                                    </td>

                                    {{-- Editienaam --}}
                                    <td class="px-4 py-3">
                                        {{ $edition->edition_name }}
                                    </td>

                                    {{-- Editiejaar --}}
                                    <td class="px-4 py-3">
                                        {{ $edition->edition_year }}
                                    </td>

                                    {{-- Publicatiedatum --}}
                                    <td class="px-4 py-3">
                                        {{ $edition->publication_date?->format('d-m-Y') ?? '—' }}
                                    </td>

                                    {{-- Valuta --}}
                                    <td class="px-4 py-3">
                                        {{ $edition->currency ?? '—' }}
                                    </td>

                                    {{-- Acties --}}
                                    <td class="px-4 py-3">

                                        <div class="flex flex-wrap items-center justify-end gap-2">

                                            {{-- Bekijken --}}
                                            <flux:button
                                                :href="route('backend.catalogs.editions.show', [$edition->catalog, $edition])"
                                                size="sm" variant="ghost" icon="eye">
                                                {{ __('Bekijken') }}
                                            </flux:button>

                                            {{-- Bewerken --}}
                                            <flux:button
                                                :href="route('backend.catalogs.editions.edit', [$edition->catalog, $edition])"
                                                size="sm" variant="ghost" icon="pencil">
                                                {{ __('Bewerken') }}
                                            </flux:button>

                                            {{-- Verwijderen --}}
                                            <form method="POST"
                                                action="{{ route('backend.catalogs.editions.destroy', [$edition->catalog, $edition]) }}">

                                                @csrf
                                                @method('DELETE')

                                                <flux:button type="button" size="sm" variant="ghost"
                                                    icon="trash" class="text-red-600!"
                                                    onclick="openDeleteModal(this.closest('form'))">

                                                    {{ __('Verwijderen') }}
                                                </flux:button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>

            @endif

        </section>


        {{-- Bevestigingsvenster verwijderen --}}
        <x-delete-confirm-modal />


    </div>

</x-layouts::app>
