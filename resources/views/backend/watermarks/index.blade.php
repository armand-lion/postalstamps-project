<x-layouts::app :title="__('Watermerken beheren')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Watermerken beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer watermerken per land, inclusief afbeeldingen en vertalingen.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.watermarks.create')" variant="primary" icon="plus" class="bg-brown-2!">
                {{ __('Watermerk toevoegen') }}
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
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">
            <form method="GET" action="{{ route('backend.watermarks.index') }}">
                <div class="flex flex-wrap items-end gap-4">

                    {{-- Zoekterm --}}
                    <div class="w-full sm:w-80">
                        <flux:field>
                            <flux:label>{{ __('Zoeken') }}</flux:label>

                            <flux:input type="search" name="search" value="{{ request('search') }}"
                                placeholder="{{ __('Naam of slug van watermerk') }}" icon="magnifying-glass"
                                class:input="border-theme-accent-3! bg-brown-5! text-zinc-900! placeholder:text-zinc-500! focus:border-theme-accent-3! focus-visible:outline-theme-accent-3!" />
                        </flux:field>
                    </div>

                    {{-- Land --}}
                    <div class="w-full sm:w-80">
                        <flux:field>
                            <flux:label>{{ __('Land') }}</flux:label>

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

                                    <flux:menu
                                        class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedId = ''; selectedName = @js(__('Alle landen'))"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ __('Alle landen') }}

                                        </flux:menu.item>

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
                    </div>

                    {{-- Status --}}
                    <div class="w-full sm:w-48">
                        <flux:field>
                            <flux:label>{{ __('Status') }}</flux:label>

                            @php
                                $selectedStatus = request('status', '');

                                $selectedStatusName = match ($selectedStatus) {
                                    'active' => __('Actief'),
                                    'inactive' => __('Inactief'),
                                    default => __('Alle statussen'),
                                };
                            @endphp

                            <div x-data="{
                                selectedStatus: @js($selectedStatus),
                                selectedName: @js($selectedStatusName)
                            }">
                                <input type="hidden" name="status" x-model="selectedStatus">

                                <flux:dropdown position="bottom" align="start" class="block w-full">
                                    <flux:button type="button" icon-trailing="chevron-down"
                                        class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                        <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                    </flux:button>

                                    <flux:menu class="min-w-48 border-theme-accent-3! bg-brown-6!">

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedStatus = ''; selectedName = @js(__('Alle statussen'))"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ __('Alle statussen') }}

                                        </flux:menu.item>

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedStatus = 'active'; selectedName = @js(__('Actief'))"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ __('Actief') }}

                                        </flux:menu.item>

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedStatus = 'inactive'; selectedName = @js(__('Inactief'))"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ __('Inactief') }}

                                        </flux:menu.item>

                                    </flux:menu>
                                </flux:dropdown>
                            </div>
                        </flux:field>
                    </div>

                    {{-- Acties --}}
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary" class="bg-brown-2!">
                            {{ __('Filteren') }}
                        </flux:button>

                        <flux:button :href="route('backend.watermarks.index')" variant="ghost">
                            {{ __('Wissen') }}
                        </flux:button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Overzicht --}}
        <div class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-brown-2 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Watermerken') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Aantal watermerken') }}: {{ $watermarks->total() }}
                </flux:text>
            </div>

            @if ($watermarks->isEmpty())
                <div class="p-8 text-center">
                    <flux:heading>
                        {{ __('Geen watermerken gevonden') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een watermerk toe of pas de filters aan.') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button :href="route('backend.watermarks.create')" variant="primary">
                            {{ __('Eerste watermerk toevoegen') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-brown-5 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Afbeelding') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Naam') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Land') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Slug') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Sorteervolgorde') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Acties') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">
                            @foreach ($watermarks as $watermark)
                                <tr class="transition-colors hover:bg-brown-4!">

                                    {{-- Afbeelding --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $primaryImage =
                                                $watermark->images->firstWhere('is_primary', true) ??
                                                $watermark->images->first();
                                        @endphp

                                        @if ($primaryImage)
                                            <img src="{{ asset('storage/' . $primaryImage->path) }}"
                                                alt="{{ $primaryImage->alt_text ?? ($watermark->translationWithFallback()?->name ?? $watermark->slug) }}"
                                                class="h-16 w-16 rounded-lg border border-theme-accent-3 bg-brown-5 object-cover"
                                                loading="lazy">
                                        @else
                                            <div
                                                class="flex h-16 w-16 items-center justify-center rounded-lg border border-neutral-200 bg-neutral-50 text-xs text-neutral-400 dark:border-neutral-700 dark:bg-neutral-900">
                                                {{ __('Geen afbeelding') }}
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Naam --}}
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('backend.watermarks.show', $watermark) }}"
                                            class="underline decoration-neutral-300 underline-offset-4 hover:decoration-current">
                                            {{ $watermark->translationWithFallback()?->name ?? $watermark->slug }}
                                        </a>
                                    </td>

                                    {{-- Land --}}
                                    <td class="px-4 py-3">
                                        {{ $watermark->country?->translationWithFallback()?->name ?? ($watermark->country?->name ?? '—') }}
                                    </td>

                                    {{-- Slug --}}
                                    <td class="px-4 py-3">
                                        {{ $watermark->slug }}
                                    </td>

                                    {{-- Sorteervolgorde --}}
                                    <td class="px-4 py-3">
                                        {{ $watermark->sort_order }}
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3">
                                        @if ($watermark->is_active)
                                            <span class="text-green-700 dark:text-green-400">
                                                {{ __('Actief') }}
                                            </span>
                                        @else
                                            <span class="text-neutral-500">
                                                {{ __('Inactief') }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Acties --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">

                                            {{-- Bekijken --}}
                                            <flux:button :href="route('backend.watermarks.show', $watermark)"
                                                size="sm" variant="ghost" icon="eye">
                                                {{ __('Bekijken') }}
                                            </flux:button>

                                            {{-- Bewerken --}}
                                            <flux:button :href="route('backend.watermarks.edit', $watermark)"
                                                size="sm" variant="ghost" icon="pencil">
                                                {{ __('Bewerken') }}
                                            </flux:button>

                                            {{-- Verwijderen --}}
                                            <form method="POST"
                                                action="{{ route('backend.watermarks.destroy', $watermark) }}"
                                                class="delete-confirm-form">
                                                @csrf
                                                @method('DELETE')

                                                <flux:button type="button" size="sm" variant="ghost"
                                                    icon="trash" class="text-red-600 dark:text-red-400"
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

                {{-- Paginering --}}
                <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                    {{ $watermarks->links() }}
                </div>
            @endif
        </div>
    </div>

    @include('components.delete-confirm-modal')
</x-layouts::app>
