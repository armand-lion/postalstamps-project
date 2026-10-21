<x-layouts::app :title="__('Bijzondere verzamelobjecten beheren')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center bg-brown-6 rounded-lg border border-theme-accent-1 p-5">

            <div>
                <flux:heading size="xl">
                    {{ __('Bijzondere verzamelobjecten beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de bijzondere verzamelobjecten in je catalogus.') }}
                </flux:text>
            </div>
            {{-- Knop verzamelobject toevoegen --}}
            <flux:button :href="route('backend.collectibles.create')" variant="primary" icon="plus" class="bg-brown-2!">
                {{ __('Verzamelobject toevoegen') }}
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

        {{-- Filters --}}
        <form method="GET" action="{{ route('backend.collectibles.index') }}"
            class="flex flex-col gap-4 rounded-xl border border-theme-accent-1 bg-brown-6 p-4 dark:border-neutral-700 sm:flex-row sm:items-end">

            {{-- Land --}}
            <flux:field class="flex-1">
                <flux:label>
                    {{ __('Land') }}
                </flux:label>

                <flux:select name="country_id">
                    <option value="">
                        {{ __('Alle landen') }}
                    </option>

                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected((string) request('country_id') === (string) $country->id)>
                            {{ $country->translationWithFallback()?->name ?? $country->name }}
                        </option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{-- Uitgiftejaar --}}
            <flux:field class="flex-1">
                <flux:label>
                    {{ __('Uitgiftejaar') }}
                </flux:label>

                <flux:input type="number" name="issue_year" min="1840" max="2200"
                    value="{{ request('issue_year') }}" placeholder="{{ __('Bijvoorbeeld 2026') }}" />
            </flux:field>

            {{-- Type verzamelobject --}}
            <flux:field class="flex-1">
                <flux:label>
                    {{ __('Type verzamelobject') }}
                </flux:label>

                <flux:select name="collectible_type_id">
                    <option value="">
                        {{ __('Alle typen') }}
                    </option>

                    @foreach ($collectibleTypes as $type)
                        <option value="{{ $type->id }}" @selected((string) request('collectible_type_id') === (string) $type->id)>
                            {{ $type->translationWithFallback()?->name ?? $type->slug }}
                        </option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{-- Acties --}}
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" class="bg-brown-2">
                    {{ __('Filteren') }}
                </flux:button>

                <flux:button :href="route('backend.collectibles.index')" variant="ghost">
                    {{ __('Wissen') }}
                </flux:button>
            </div>
        </form>

        {{-- Overzicht bijzondere verzamelobjecten --}}
        <div class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            {{-- Kop van het overzicht --}}
            <div class="border-b border-brown-2 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Catalogus') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Bijzondere verzamelobjecten') }}: {{ $collectibles->total() }}
                </flux:text>
            </div>

            {{-- Geen verzamelobjecten gevonden --}}
            @if ($collectibles->isEmpty())

                <div class="p-8 text-center">

                    <flux:heading>
                        {{ __('Geen bijzondere verzamelobjecten gevonden') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een bijzonder verzamelobject toe of pas de filters aan.') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button :href="route('backend.collectibles.create')" variant="primary">
                            {{ __('Eerste verzamelobject toevoegen') }}
                        </flux:button>
                    </div>

                </div>
            @else
                {{-- Tabel met bijzondere verzamelobjecten --}}
                <div class="overflow-x-auto">

                    <table class="w-full text-left text-sm">

                        <thead class="bg-brown-5 text-brown-2 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-semibold">
                                    {{ __('Naam') }}
                                </th>

                                <th class="px-4 py-3 font-semibold">
                                    {{ __('Type verzamelobject') }}
                                </th>

                                <th class="px-4 py-3 font-semibold">
                                    {{ __('Land') }}
                                </th>

                                <th class="px-4 py-3 font-semibold">
                                    {{ __('Uitgiftejaar') }}
                                </th>

                                <th class="px-4 py-3 font-semibold">
                                    {{ __('Acties') }}
                                </th>
                            </tr>
                        </thead>
                        {{-- Gegevens van de verzamelobjecten --}}
                        <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">

                            @foreach ($collectibles as $collectible)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">

                                    {{-- Naam --}}
                                    <td class="px-4 py-3">
                                        {{ $collectible->name }}
                                    </td>

                                    {{-- Type verzamelobject --}}
                                    <td class="px-4 py-3">
                                        {{ $collectible->collectibleType?->translationWithFallback()?->name ??
                                            ($collectible->collectibleType?->slug ?? '—') }}
                                    </td>

                                    {{-- Land --}}
                                    <td class="px-4 py-3">
                                        {{ $collectible->country?->translationWithFallback()?->name ?? ($collectible->country?->name ?? '—') }}
                                    </td>

                                    {{-- Uitgiftejaar --}}
                                    <td class="px-4 py-3">
                                        {{ $collectible->issue_year }}
                                    </td>

                                    {{-- Acties --}}
                                    <td class="px-4 py-3">

                                        <div class="flex items-center gap-2">

                                            {{-- Bekijken --}}
                                            <flux:button :href="route('backend.collectibles.show', $collectible)"
                                                variant="ghost" size="sm" icon="eye" :title="__('Bekijken')" />
                                            {{-- Bewerken --}}
                                            <flux:button :href="route('backend.collectibles.edit', $collectible)"
                                                variant="ghost" size="sm" icon="pencil" :title="__('Bewerken')" />
                                            {{-- Verwijderen --}}
                                            <form method="POST"
                                                action="{{ route('backend.collectibles.destroy', $collectible) }}"
                                                class="delete-confirm-form">
                                                @csrf
                                                @method('DELETE')

                                                <flux:button type="button" variant="ghost" size="sm"
                                                    icon="trash" :title="__('Verwijderen')"
                                                    onclick="openDeleteModal(this.closest('form'))" />
                                            </form>
                                        </div>

                                    </td>

                                </tr>
                            @endforeach

                        </tbody>
                    </table>

                </div>
                {{-- Paginering --}}
                <div class="border-t border-brown-4 p-4 dark:border-neutral-700">
                    {{ $collectibles->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Bevestigingsvenster voor verwijderen --}}
    @include('components.delete-confirm-modal')
</x-layouts::app>
