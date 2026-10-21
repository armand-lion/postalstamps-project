<x-layouts::app :title="$collectible->name">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ $collectible->name }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Bijzonder verzamelobject') }}
                </flux:text>
            </div>

            {{-- Actieknoppen --}}
            <div class="flex flex-wrap gap-2">

                <flux:button :href="route('backend.collectibles.index')" variant="ghost" icon="arrow-left">
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button :href="route('backend.collectibles.edit', $collectible)" variant="primary" icon="pencil"
                    class="bg-brown-2!">
                    {{ __('Bewerken') }}
                </flux:button>

            </div>
        </div>

        {{-- Algemene gegevens --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Algemene gegevens') }}
            </flux:heading>

            <dl class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">

                {{-- Naam --}}
                <div>
                    <dt class="text-sm font-semibold">
                        {{ __('Naam') }}
                    </dt>

                    <dd class="mt-1">
                        {{ $collectible->name }}
                    </dd>
                </div>

                {{-- Type verzamelobject --}}
                <div>
                    <dt class="text-sm font-semibold">
                        {{ __('Type verzamelobject') }}
                    </dt>

                    <dd class="mt-1">
                        {{ $collectible->collectibleType?->translationWithFallback()?->name ??
                            ($collectible->collectibleType?->slug ?? '—') }}
                    </dd>
                </div>

                {{-- Land --}}
                <div>
                    <dt class="text-sm font-semibold">
                        {{ __('Land') }}
                    </dt>

                    <dd class="mt-1">
                        {{ $collectible->country?->translationWithFallback()?->name ?? ($collectible->country?->name ?? '—') }}
                    </dd>
                </div>

                {{-- Uitgiftejaar --}}
                <div>
                    <dt class="text-sm font-semibold">
                        {{ __('Uitgiftejaar') }}
                    </dt>

                    <dd class="mt-1">
                        {{ $collectible->issue_year ?? '—' }}
                    </dd>
                </div>

                {{-- Uitgiftedatum --}}
                <div>
                    <dt class="text-sm font-semibold">
                        {{ __('Uitgiftedatum') }}
                    </dt>

                    <dd class="mt-1">
                        {{ $collectible->issue_date
                            ? \Illuminate\Support\Carbon::parse($collectible->issue_date)->format('d-m-Y')
                            : '—' }}
                    </dd>
                </div>

                {{-- Postzegelserie --}}
                <div>
                    <dt class="text-sm font-semibold">
                        {{ __('Postzegelserie') }}
                    </dt>

                    <dd class="mt-1">
                        {{ $collectible->stampSeries?->name ?? '—' }}
                    </dd>
                </div>

            </dl>
        </div>

        {{-- Beschrijving en opmerkingen --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Beschrijving en opmerkingen') }}
            </flux:heading>

            <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">

                <div>
                    <div class="text-sm font-semibold">
                        {{ __('Beschrijving') }}
                    </div>

                    <p class="mt-2 whitespace-pre-line">
                        {{ $collectible->description ?: '—' }}
                    </p>
                </div>

                <div>
                    <div class="text-sm font-semibold">
                        {{ __('Opmerkingen') }}
                    </div>

                    <p class="mt-2 whitespace-pre-line">
                        {{ $collectible->notes ?: '—' }}
                    </p>
                </div>

            </div>
        </div>
        {{-- Gekoppelde postzegels --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Gekoppelde postzegels') }}
            </flux:heading>

            @if ($collectible->stamps->isEmpty())

                <flux:text class="mt-4">
                    {{ __('Er zijn nog geen postzegels gekoppeld aan dit verzamelobject.') }}
                </flux:text>
            @else
                <div class="mt-4 overflow-x-auto">

                    <table class="w-full text-left text-sm">

                        <thead class="bg-brown-5 text-brown-2 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-semibold">
                                    {{ __('Postzegel') }}
                                </th>

                                <th class="px-4 py-3 font-semibold">
                                    {{ __('Uitgiftejaar') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">

                            @foreach ($collectible->stamps as $stamp)
                                <tr>
                                    <td class="px-4 py-3">
                                        {{ $stamp->name }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $stamp->issue_year ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>

            @endif

        </div>
    </div>

</x-layouts::app>
