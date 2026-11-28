<x-layouts::app :title="__('Bulkbewerking postzegels')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div
            class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Bulkbewerking postzegels') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Bewerk algemene gegevens van meerdere postzegels tegelijkertijd.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.stamps.index')" icon="arrow-left"
                class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                {{ __('Terug naar postzegels') }}

            </flux:button>

        </div>

        @include('partials.flash-messages')

        <div>
            {{-- Filters --}}
            @include('partials.stamp-filters', [
                'filterRoute' => 'backend.stamps.bulk-edit.index',
                'showSort' => false,
                'showAdvancedFilters' => true,
            ])
        </div>

        {{-- Resultaten --}}
        <div class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            {{-- Resultatenkop --}}
            <div class="border-b border-brown-2 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Postzegels') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ trans_choice(__(':count stamp found|:count stamps found'), $stamps->total()) }}
                </flux:text>
                <flux:text class="mt-2 font-medium text-zinc-900!">
                    {{ __('Geselecteerde postzegels') }}: {{ $selectedCount }}
                </flux:text>

                @if ($selectedOutsideFiltersCount > 0)
                    <flux:text class="mt-1 text-zinc-900!">
                        {{ __('Geselecteerde postzegels buiten de huidige filters') }}:
                        {{ $selectedOutsideFiltersCount }}
                    </flux:text>
                @endif

                {{-- Selectieacties --}}
                <div class="mt-3 flex flex-wrap items-center gap-2">

                    {{-- Alle gefilterde postzegels selecteren --}}
                    @if ($stamps->total() > 0 && $selectedWithinFiltersCount < $stamps->total())
                        <form method="POST"
                            action="{{ route('backend.stamps.bulk-edit.selection.all-filtered', request()->except('page')) }}">

                            @csrf

                            <flux:button type="submit" size="sm" icon="check"
                                class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-4!">

                                {{ __('Alle gefilterde postzegels selecteren') }}
                                ({{ $stamps->total() }})

                            </flux:button>
                        </form>
                    @endif

                    @if ($selectedCount > 0)
                        {{-- Selectie bekijken --}}
                        <flux:button :href="route('backend.stamps.bulk-edit.selected')" size="sm"
                            icon="list-bullet"
                            class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-4!">

                            {{ __('Selectie bekijken') }} ({{ $selectedCount }})

                        </flux:button>

                        {{-- Selectie wissen --}}
                        <form method="POST" action="{{ route('backend.stamps.bulk-edit.selection.clear') }}">

                            @csrf
                            @method('DELETE')

                            <flux:button type="submit" size="sm" icon="x-mark"
                                class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-4!">

                                {{ __('Selectie wissen') }}

                            </flux:button>
                        </form>
                    @endif

                </div>

            </div>

            @include('partials.stamp-table', [
                'selectionRoute' => 'backend.stamps.bulk-edit.selection.update',
                'detailRoute' => 'backend.stamps.show',
            ])

        </div>
    </div>

</x-layouts::app>
