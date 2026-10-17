<x-layouts::app :title="__('Postzegelseries beheren')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Postzegelseries beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de postzegelseries waartoe je postzegels behoren.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.stamp-series.create')" variant="primary" icon="plus">
                {{ __('Postzegelserie toevoegen') }}
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
                    {{ __('Postzegelseries') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ trans_choice(__(':count stamp series found|:count stamp series found'), $series->total()) }}
                </flux:text>
            </div>

            @if ($series->isEmpty())
                <div class="p-8 text-center">
                    <flux:heading>
                        {{ __('Geen Postzegelseries gevonden') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een postzegelserie toe om je postzegels te groeperen.') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button :href="route('backend.stamp-series.create')" variant="primary">
                            {{ __('Eerste postzegelserie toevoegen') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">
                                    {{ __('Naam') }}
                                </th>

                                <th class="px-4 py-3 font-medium">
                                    {{ __('Land') }}
                                </th>

                                <th class="px-4 py-3 font-medium">
                                    {{ __('Uitgiftejaar') }}
                                </th>

                                <th class="px-4 py-3 font-medium">
                                    {{ __('Aantal postzegels') }}
                                </th>

                                <th class="px-4 py-3 text-right font-medium">
                                    {{ __('Acties') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($series as $item)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('backend.stamp-series.show', $item) }}"
                                            class="underline decoration-neutral-300 underline-offset-4 hover:decoration-current">
                                            {{ $item->translationWithFallback()?->name ?? $item->name }}
                                        </a>
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $item->country?->translationWithFallback()?->name ?? ($item->country?->name ?? '—') }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $item->issue_year ?? '—' }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $item->stamps_count }}
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-3">
                                            <a href="{{ route('backend.stamp-series.show', $item) }}"
                                                class="underline underline-offset-4">
                                                {{ __('Bekijken') }}
                                            </a>

                                            <a href="{{ route('backend.stamp-series.edit', $item) }}"
                                                class="underline underline-offset-4">
                                                {{ __('Bewerken') }}
                                            </a>

                                            <form method="POST"
                                                action="{{ route('backend.stamp-series.destroy', $item) }}"
                                                class="delete-confirm-form">
                                                @csrf
                                                @method('DELETE')

                                                <button type="button"
                                                    class="text-red-600 underline underline-offset-4 hover:text-red-800"
                                                    onclick="openDeleteModal(this.form)">
                                                    {{ __('Verwijderen') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginering --}}
                <div class="border-t border-neutral-200 p-4">
                    {{ $series->links() }}
                </div>
            @endif

        </div>
    </div>

    @include('components.delete-confirm-modal')
</x-layouts::app>
