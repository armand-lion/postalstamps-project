<x-layouts::app :title="__('Catalogi beheren')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Catalogi beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer postzegelcatalogi en hun edities.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.catalogs.create')" variant="primary" icon="plus">
                {{ __('Catalogus toevoegen') }}
            </flux:button>
        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- Foutmelding --}}
        @if (session('error'))
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Zoekfunctie --}}
        <form method="GET" action="{{ route('backend.catalogs.index') }}"
            class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 sm:flex-row sm:items-end">

            <flux:field class="flex-1">
                <flux:label>{{ __('Zoeken') }}</flux:label>

                <flux:input
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="{{ __('Naam, afkorting of uitgever') }}"
                />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">
                    {{ __('Filteren') }}
                </flux:button>

                <flux:button :href="route('backend.catalogs.index')" variant="ghost">
                    {{ __('Wissen') }}
                </flux:button>
            </div>
        </form>

        {{-- Catalogusoverzicht --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Catalogi') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ trans_choice(':count catalogus gevonden|:count catalogi gevonden', $catalogs->total()) }}
                </flux:text>
            </div>

            @if ($catalogs->isEmpty())

                <div class="p-8 text-center">
                    <flux:heading>
                        {{ __('Geen catalogi gevonden') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een catalogus toe of pas de zoekopdracht aan.') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button :href="route('backend.catalogs.create')" variant="primary">
                            {{ __('Eerste catalogus toevoegen') }}
                        </flux:button>
                    </div>
                </div>

            @else

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">

                        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Naam') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Afkorting') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Uitgever') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Landen') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Edities') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Acties') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">

                            @foreach ($catalogs as $catalog)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">

                                    {{-- Naam --}}
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('backend.catalogs.show', $catalog) }}"
                                            class="underline decoration-neutral-300 underline-offset-4 hover:decoration-current">
                                            {{ $catalog->name }}
                                        </a>
                                    </td>

                                    {{-- Afkorting --}}
                                    <td class="px-4 py-3">
                                        {{ $catalog->abbreviation ?? '—' }}
                                    </td>

                                    {{-- Uitgever --}}
                                    <td class="px-4 py-3">
                                        {{ $catalog->publisher ?? '—' }}
                                    </td>

                                    {{-- Aantal landen --}}
                                    <td class="px-4 py-3">
                                        {{ $catalog->countries_count }}
                                    </td>

                                    {{-- Aantal edities --}}
                                    <td class="px-4 py-3">
                                        {{ $catalog->editions_count }}
                                    </td>

                                    {{-- Acties --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">

                                            {{-- Bekijken --}}
                                            <flux:button
                                                :href="route('backend.catalogs.show', $catalog)"
                                                size="sm"
                                                variant="ghost"
                                                icon="eye"
                                            >
                                                {{ __('Bekijken') }}
                                            </flux:button>

                                            {{-- Bewerken --}}
                                            <flux:button
                                                :href="route('backend.catalogs.edit', $catalog)"
                                                size="sm"
                                                variant="ghost"
                                                icon="pencil"
                                            >
                                                {{ __('Bewerken') }}
                                            </flux:button>

                                            {{-- Verwijderen --}}
                                            <form
                                                method="POST"
                                                action="{{ route('backend.catalogs.destroy', $catalog) }}"
                                                class="delete-confirm-form"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <flux:button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="trash"
                                                    class="text-red-600 dark:text-red-400"
                                                    onclick="openDeleteModal(this.closest('form'))"
                                                >
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

                {{-- Paginering --}}
                <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                    {{ $catalogs->links() }}
                </div>

            @endif

        </div>

    </div>

    @include('components.delete-confirm-modal')

</x-layouts::app>
