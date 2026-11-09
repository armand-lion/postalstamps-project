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
            {{-- Actieknoppen --}}
            <div class="flex flex-wrap items-center gap-3">

                {{-- Verzamelobjecttypen beheren --}}
                <flux:button :href="route('backend.collectible-types.index')" variant="primary" icon="cog-6-tooth"
                    class="bg-brown-2!" wire:navigate>
                    {{ __('Verzamelobjecttypen beheren') }}
                </flux:button>

                {{-- Verzamelobject toevoegen --}}
                <flux:button :href="route('backend.collectibles.create')" variant="primary" icon="plus"
                    class="bg-brown-2!" wire:navigate>
                    {{ __('Verzamelobject toevoegen') }}
                </flux:button>

            </div>
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

                @php
                    $selectedCountryId = request('country_id');
                    $selectedCountry = $countries->firstWhere('id', $selectedCountryId);
                @endphp

                <div x-data="{
                    selectedId: @js((string) ($selectedCountryId ?? '')),
                    selectedName: @js($selectedCountry?->translationWithFallback()?->name ?? __('Alle landen'))
                }">

                    <input type="hidden" name="country_id" x-model="selectedId">

                    <flux:dropdown position="bottom" align="start" class="block w-full">

                        <flux:button type="button" icon-trailing="chevron-down"
                            class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                            <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                        </flux:button>

                        <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                            {{-- Alle landen --}}
                            <flux:menu.item as="button" type="button"
                                x-on:click="selectedId = ''; selectedName = @js(__('Alle landen'))"
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                {{ __('Alle landen') }}

                            </flux:menu.item>

                            {{-- Beschikbare landen --}}
                            @foreach ($countries as $country)
                                <flux:menu.item as="button" type="button"
                                    x-on:click="selectedId = @js((string) $country->id); selectedName = @js($country->translationWithFallback()?->name ?? $country->name)"
                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                    {{ $country->translationWithFallback()?->name ?? $country->name }}

                                </flux:menu.item>
                            @endforeach

                        </flux:menu>

                    </flux:dropdown>
                </div>
            </flux:field>

            {{-- Uitgiftejaar --}}
            <flux:field class="flex-1">
                <flux:label>
                    {{ __('Uitgiftejaar') }}
                </flux:label>

                <div x-data="{
                    year: @js(request('issue_year', '')),
                    minYear: 1840,
                    maxYear: 2200,

                    increase() {
                        if (this.year === '') {
                            this.year = this.maxYear;
                        } else {
                            this.year = Math.min(this.maxYear, Number(this.year) + 1);
                        }
                    },

                    decrease() {
                        if (this.year === '') {
                            this.year = this.maxYear;
                        } else {
                            this.year = Math.max(this.minYear, Number(this.year) - 1);
                        }
                    }
                }" class="relative">

                    <input type="number" name="issue_year" min="1840" max="2200" x-model="year"
                        placeholder="{{ __('Bijvoorbeeld 2026') }}"
                        class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-0 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]" />

                    <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                        <button type="button" x-on:click="increase()" aria-label="{{ __('Jaar verhogen') }}"
                            class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                            <flux:icon.chevron-up class="size-3" />
                        </button>

                        <button type="button" x-on:click="decrease()" aria-label="{{ __('Jaar verlagen') }}"
                            class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                            <flux:icon.chevron-down class="size-3" />
                        </button>

                    </div>
                </div>
            </flux:field>

            {{-- Type verzamelobject --}}
            <flux:field class="flex-1">
                <flux:label>
                    {{ __('Type verzamelobject') }}
                </flux:label>

                @php
                    $selectedCollectibleTypeId = request('collectible_type_id');
                    $selectedCollectibleType = $collectibleTypes->firstWhere('id', $selectedCollectibleTypeId);
                @endphp

                <div x-data="{
                    selectedId: @js((string) ($selectedCollectibleTypeId ?? '')),
                    selectedName: @js($selectedCollectibleType?->translationWithFallback()?->name ?? ($selectedCollectibleType?->slug ?? __('Alle typen')))
                }">

                    <input type="hidden" name="collectible_type_id" x-model="selectedId">

                    <flux:dropdown position="bottom" align="start" class="block w-full">

                        <flux:button type="button" icon-trailing="chevron-down"
                            class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                            <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                        </flux:button>

                        <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                            {{-- Alle typen --}}
                            <flux:menu.item as="button" type="button"
                                x-on:click="selectedId = ''; selectedName = @js(__('Alle typen'))"
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                {{ __('Alle typen') }}

                            </flux:menu.item>

                            {{-- Beschikbare verzamelobjecttypen --}}
                            @foreach ($collectibleTypes as $type)
                                <flux:menu.item as="button" type="button"
                                    x-on:click="selectedId = @js((string) $type->id); selectedName = @js($type->translationWithFallback()?->name ?? $type->slug)"
                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                    {{ $type->translationWithFallback()?->name ?? $type->slug }}

                                </flux:menu.item>
                            @endforeach

                        </flux:menu>

                    </flux:dropdown>
                </div>
            </flux:field>

            {{-- Acties --}}
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary" class="bg-brown-2!">
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
                        <flux:button :href="route('backend.collectibles.create')" class="bg-brown-2!" variant="primary">
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

                                <th class="px-4 py-3 text-right">
                                    {{ __('Acties') }}
                                </th>
                            </tr>
                        </thead>
                        {{-- Gegevens van de verzamelobjecten --}}
                        <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">

                            @foreach ($collectibles as $collectible)
                                <tr class="hover:bg-brown-5!">

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
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">

                                            {{-- Bekijken --}}
                                            <flux:button :href="route('backend.collectibles.show', $collectible)"
                                                variant="ghost" size="sm" icon="eye">
                                                {{ __('Bekijken') }}
                                            </flux:button>

                                            {{-- Bewerken --}}
                                            <flux:button :href="route('backend.collectibles.edit', $collectible)"
                                                variant="ghost" size="sm" icon="pencil">
                                                {{ __('Bewerken') }}
                                            </flux:button>

                                            {{-- Verwijderen --}}
                                            <form method="POST"
                                                action="{{ route('backend.collectibles.destroy', $collectible) }}"
                                                class="delete-confirm-form">

                                                @csrf
                                                @method('DELETE')

                                                <flux:button type="button" variant="ghost" size="sm"
                                                    icon="trash" onclick="openDeleteModal(this.closest('form'))">
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
                <div class="border-t border-brown-4 p-4 dark:border-neutral-700">
                    {{ $collectibles->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Bevestigingsvenster voor verwijderen --}}
    @include('components.delete-confirm-modal')
</x-layouts::app>
