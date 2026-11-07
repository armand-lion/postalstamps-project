<x-layouts::app :title="__('Catalogusedities')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Catalogusedities') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Overzicht van de edities die bij deze catalogus horen.') }}
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $catalog->name }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-3">

                <flux:button :href="route('backend.catalogs.show', $catalog)" variant="ghost" icon="arrow-left">
                    {{ __('Terug naar catalogus') }}
                </flux:button>

                <flux:button :href="route('backend.catalogs.editions.create', $catalog)" variant="primary" icon="plus">
                    {{ __('Editie toevoegen') }}
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

        {{-- Overzicht catalogusedities --}}
        <section
            class="    flex flex-col gap-4 rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">

                <flux:heading size="lg">
                    {{ __('Catalogusedities') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Overzicht van de edities die bij deze catalogus horen.') }}
                </flux:text>

            </div>

            <div>

                @if ($editions->isEmpty())

                    {{-- Geen catalogusedities --}}
                    <div class="p-4 py-8 text-center">

                        <flux:icon.book-open class="mx-auto mb-4 size-10 text-neutral-400" />

                        <flux:heading size="lg">
                            {{ __('Geen catalogusedities gevonden') }}
                        </flux:heading>

                        <flux:text class="mt-2">
                            {{ __('Er zijn nog geen edities aan deze catalogus toegevoegd.') }}
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

                                        {{-- Editienaam --}}
                                        <td class="px-4 py-3 font-medium">
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
                                                    :href="route('backend.catalogs.editions.show', [$catalog, $edition])"
                                                    size="sm" variant="ghost" icon="eye">
                                                    {{ __('Bekijken') }}
                                                </flux:button>

                                                {{-- Bewerken --}}
                                                <flux:button
                                                    :href="route('backend.catalogs.editions.edit', [$catalog, $edition])"
                                                    size="sm" variant="ghost" icon="pencil">
                                                    {{ __('Bewerken') }}
                                                </flux:button>

                                                {{-- Verwijderen --}}
                                                <div x-data>
                                                    <flux:button type="button" size="sm" variant="ghost"
                                                        icon="trash" class="text-red-600!"
                                                        x-on:click="$dispatch('open-delete-edition-modal', {
                                    id: {{ $edition->id }},
                                    name: @js($edition->edition_name),
                                    url: @js(route('backend.catalogs.editions.destroy', [$catalog, $edition]))
                                })">
                                                        {{ __('Verwijderen') }}
                                                    </flux:button>
                                                </div>

                                            </div>

                                        </td>

                                    </tr>
                                @endforeach

                            </tbody>

                        </table>

                    </div>

                @endif

            </div>

        </section>

        {{-- Bevestigingsvenster cataloguseditie verwijderen --}}
        <div x-data="{
            open: false,
            editionName: '',
            deleteUrl: ''
        }"
            x-on:open-delete-edition-modal.window="
                editionName = $event.detail.name;
                deleteUrl = $event.detail.url;
                open = true;
            "
            x-on:keydown.escape.window="open = false">

            <div x-cloak x-show="open" x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                x-on:click.self="open = false" role="presentation">

                <div role="dialog" aria-modal="true" aria-labelledby="delete-edition-title"
                    class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900">

                    <flux:heading id="delete-edition-title" size="lg">
                        {{ __('Cataloguseditie verwijderen') }}
                    </flux:heading>

                    <flux:text class="mt-3">
                        {{ __('Weet je zeker dat je deze cataloguseditie wilt verwijderen?') }}
                    </flux:text>

                    <p class="mt-2 font-semibold text-zinc-900 dark:text-white" x-text="editionName"></p>

                    <flux:text class="mt-3">
                        {{ __('Deze actie kan niet ongedaan worden gemaakt.') }}
                    </flux:text>

                    <form method="POST" x-bind:action="deleteUrl" class="mt-6">
                        @csrf
                        @method('DELETE')

                        <div class="flex flex-wrap justify-end gap-3">

                            <flux:button type="button" variant="ghost" icon="x-mark" x-on:click="open = false">
                                {{ __('Annuleren') }}
                            </flux:button>

                            <flux:button type="submit" variant="danger" icon="trash">
                                {{ __('Verwijderen') }}
                            </flux:button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</x-layouts::app>
