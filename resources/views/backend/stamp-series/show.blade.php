<x-layouts::app :title="__('Postzegelserie bekijken')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ $series->translationWithFallback()?->name ?? $series->name }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Details van deze postzegelserie.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('backend.stamp-series.index')" variant="ghost" icon="arrow-left">
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button :href="route('backend.stamp-series.edit', $series)" variant="primary" icon="pencil">
                    {{ __('Bewerken') }}
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

        {{-- Algemene gegevens --}}
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <flux:heading size="lg">
                {{ __('Algemene gegevens') }}
            </flux:heading>

            <dl class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Naam') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $series->translationWithFallback()?->name ?? $series->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Land') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $series->country?->translationWithFallback()?->name ?? ($series->country?->name ?? '—') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Uitgiftejaar') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $series->issue_year ?? '—' }}
                    </dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Beschrijving') }}
                    </dt>
                    <dd class="mt-1">
                        <flux:text class="whitespace-pre-line">
                            {{ $series->translationWithFallback()?->description ?? ($series->description ?? '—') }}
                        </flux:text>
                    </dd>
                </div>

            </dl>
        </div>

        {{-- Gekoppelde postzegels --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Postzegels in deze serie') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ trans_choice(__(':count stamp found|:count stamps found'), $series->stamps->count()) }}
                </flux:text>
            </div>

            @if ($series->stamps->isEmpty())
                <div class="p-8 text-center">
                    <flux:heading>
                        {{ __('Geen postzegels gekoppeld') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Er zijn nog geen postzegels aan deze serie gekoppeld.') }}
                    </flux:text>
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
                                    {{ __('Jaar') }}
                                </th>

                                <th class="px-4 py-3 font-medium">
                                    {{ __('Nominale waarde') }}
                                </th>

                                <th class="px-4 py-3 text-right font-medium">
                                    {{ __('Acties') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($series->stamps as $stamp)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">

                                    <td class="px-4 py-3 font-medium">
                                        {{ $stamp->translationWithFallback()?->name ?? $stamp->name }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $stamp->issue_year ?? '—' }}
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($stamp->face_value !== null)
                                            {{ number_format((float) $stamp->face_value, 2, ',', '.') }}
                                            {{ $stamp->currency }}
                                        @else
                                            —
                                        @endif
                                    </td>

                                    {{-- Acties --}}
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end">

                                            <flux:button
                                                :href="route('backend.stamps.show', $stamp)"
                                                size="sm"
                                                variant="ghost"
                                                icon="eye"
                                            >
                                                {{ __('Bekijken') }}
                                            </flux:button>

                                        </div>
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

        </div>
        {{-- Acties onderaan --}}
        <div class="flex flex-wrap justify-end gap-2">

            <flux:button
                :href="route('backend.stamp-series.index')"
                variant="ghost"
                icon="arrow-left"
            >
                {{ __('Terug naar overzicht') }}
            </flux:button>

            <flux:button
                :href="route('backend.stamp-series.edit', $series)"
                variant="primary"
                icon="pencil"
            >
                {{ __('Bewerken') }}
            </flux:button>

        </div>

    </div>
    </div>
</x-layouts::app>
