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

                @if ($editions->isEmpty())

                    {{-- Geen catalogusedities --}}
                    <div class="py-8 text-center">

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
                    <div class="overflow-x-auto">

                        <flux:table>

                            <flux:table.columns>

                                <flux:table.column>
                                    {{ __('Editienaam') }}
                                </flux:table.column>

                                <flux:table.column>
                                    {{ __('Editiejaar') }}
                                </flux:table.column>

                                <flux:table.column>
                                    {{ __('Publicatiedatum') }}
                                </flux:table.column>

                                <flux:table.column>
                                    {{ __('Valuta') }}
                                </flux:table.column>

                                <flux:table.column>
                                    {{ __('Acties') }}
                                </flux:table.column>

                            </flux:table.columns>

                            <flux:table.rows>

                                @foreach ($editions as $edition)
                                    <flux:table.row :key="$edition->id">

                                        <flux:table.cell>
                                            {{ $edition->edition_name }}
                                        </flux:table.cell>

                                        <flux:table.cell>
                                            {{ $edition->edition_year }}
                                        </flux:table.cell>

                                        <flux:table.cell>
                                            {{ $edition->publication_date?->format('d-m-Y') ?? '—' }}
                                        </flux:table.cell>

                                        <flux:table.cell>
                                            {{ $edition->currency ?? '—' }}
                                        </flux:table.cell>

                                        <flux:table.cell>

                                            <div class="flex items-center justify-end gap-2">

                                                <flux:button
                                                    :href="route('backend.catalogs.editions.show', [$catalog, $edition])"
                                                    size="sm" variant="ghost" icon="eye">
                                                    {{ __('Bekijken') }}
                                                </flux:button>

                                                <flux:button
                                                    :href="route('backend.catalogs.editions.edit', [$catalog, $edition])"
                                                    size="sm" variant="ghost" icon="pencil">
                                                    {{ __('Bewerken') }}
                                                </flux:button>

                                                <div x-data>
                                                    <flux:button
                                                        type="button"
                                                        size="sm"
                                                        variant="ghost"
                                                        icon="trash"
                                                        class="text-red-600!"
                                                        x-on:click="$dispatch('open-delete-edition-modal', {
                                                            id: {{ $edition->id }},
                                                            name: @js($edition->edition_name),
                                                            url: @js(route('backend.catalogs.editions.destroy', [$catalog, $edition]))
                                                        })"
                                                    >
                                                        {{ __('Verwijderen') }}
                                                    </flux:button>
                                                </div>

                                            </div>

                                        </flux:table.cell>

                                    </flux:table.row>
                                @endforeach

                            </flux:table.rows>

                        </flux:table>

                    </div>

                @endif

            </div>

        </section>

        {{-- Bevestigingsvenster cataloguseditie verwijderen --}}
        <div
            x-data="{
                open: false,
                editionName: '',
                deleteUrl: ''
            }"
            x-on:open-delete-edition-modal.window="
                editionName = $event.detail.name;
                deleteUrl = $event.detail.url;
                open = true;
            "
            x-on:keydown.escape.window="open = false"
        >

            <div
                x-cloak
                x-show="open"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                x-on:click.self="open = false"
                role="presentation"
            >

                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="delete-edition-title"
                    class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-900"
                >

                    <flux:heading
                        id="delete-edition-title"
                        size="lg"
                    >
                        {{ __('Cataloguseditie verwijderen') }}
                    </flux:heading>

                    <flux:text class="mt-3">
                        {{ __('Weet je zeker dat je deze cataloguseditie wilt verwijderen?') }}
                    </flux:text>

                    <p
                        class="mt-2 font-semibold text-zinc-900 dark:text-white"
                        x-text="editionName"
                    ></p>

                    <flux:text class="mt-3">
                        {{ __('Deze actie kan niet ongedaan worden gemaakt.') }}
                    </flux:text>

                    <form
                        method="POST"
                        x-bind:action="deleteUrl"
                        class="mt-6"
                    >
                        @csrf
                        @method('DELETE')

                        <div class="flex flex-wrap justify-end gap-3">

                            <flux:button
                                type="button"
                                variant="ghost"
                                icon="x-mark"
                                x-on:click="open = false"
                            >
                                {{ __('Annuleren') }}
                            </flux:button>

                            <flux:button
                                type="submit"
                                variant="danger"
                                icon="trash"
                            >
                                {{ __('Verwijderen') }}
                            </flux:button>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>

</x-layouts::app>
