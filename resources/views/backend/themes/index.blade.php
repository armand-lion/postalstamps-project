<x-layouts::app :title="__('Thema\'s beheren')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __("Thema's beheren") }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de thema’s die aan postzegels gekoppeld kunnen worden.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.themes.create')" variant="primary" icon="plus">
                {{ __('Thema toevoegen') }}
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

        {{-- Overzicht --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __("Thema's") }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Aantal thema’s: :count', ['count' => $themes->total()]) }}
                </flux:text>
            </div>

            @if ($themes->isEmpty())
                <div class="p-8 text-center">
                    <flux:heading>
                        {{ __('Geen thema’s gevonden') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een thema toe om te beginnen.') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button :href="route('backend.themes.create')" variant="primary">
                            {{ __('Eerste thema toevoegen') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Naam') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Slug') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Beschrijving') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Acties') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($themes as $theme)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">

                                    {{-- Naam --}}
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('backend.themes.show', $theme) }}"
                                            class="underline decoration-neutral-300 underline-offset-4 hover:decoration-current">
                                            {{ $theme->translationWithFallback()?->name ?? $theme->name }}
                                        </a>
                                    </td>

                                    {{-- Slug --}}
                                    <td class="px-4 py-3">
                                        {{ $theme->slug }}
                                    </td>

                                    {{-- Beschrijving --}}
                                    <td class="px-4 py-3">
                                        {{ $theme->translationWithFallback()?->description ?? ($theme->description ?? '—') }}
                                    </td>

                                    {{-- Acties --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">

                                            <flux:button :href="route('backend.themes.show', $theme)" size="sm"
                                                variant="ghost" icon="eye">
                                                {{ __('Bekijken') }}
                                            </flux:button>

                                            <flux:button :href="route('backend.themes.edit', $theme)" size="sm"
                                                variant="ghost" icon="pencil">
                                                {{ __('Bewerken') }}
                                            </flux:button>

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginering --}}
                <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                    {{ $themes->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
