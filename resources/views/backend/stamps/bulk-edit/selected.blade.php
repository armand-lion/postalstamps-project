<x-layouts::app :title="__('Geselecteerde postzegels')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div
            class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Geselecteerde postzegels') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Bekijk en beheer de postzegels die zijn geselecteerd voor bulkbewerking.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.stamps.bulk-edit.index')" icon="arrow-left"
                class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                {{ __('Terug naar bulkbewerking') }}

            </flux:button>

        </div>

        {{-- Resultaten --}}
        <div class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            {{-- Resultatenkop --}}
            <div class="border-b border-brown-2 px-4 py-3 dark:border-neutral-700">

                <flux:heading size="lg">
                    {{ __('Huidige selectie') }}
                </flux:heading>

                <flux:text class="mt-2 font-medium text-zinc-900!">
                    {{ __('Geselecteerde postzegels') }}: {{ $selectedCount }}
                </flux:text>

                @if ($selectedCount > 0)
                    <div class="mt-3 flex flex-wrap items-center gap-2">

                        {{-- Geselecteerde postzegels bewerken --}}
                        <flux:button :href="route('backend.stamps.bulk-edit.edit')" size="sm" icon="pencil-square"
                            class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-4!">

                            {{ __('Geselecteerde postzegels bewerken') }}

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

                    </div>
                @endif

            </div>

            {{-- Centrale resultatentabel --}}
            @include('partials.stamp-table', [
                'selectionRoute' => 'backend.stamps.bulk-edit.selection.update',
                'detailRoute' => 'backend.stamps.show',
                'emptyHeading' => __('Geen postzegels geselecteerd'),
                'emptyDescription' => __('Ga terug naar bulkbewerking om postzegels te selecteren.'),
            ])

        </div>

    </div>

</x-layouts::app>
