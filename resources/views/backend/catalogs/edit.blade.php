<x-layouts::app :title="__('Catalogus bewerken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Catalogus bewerken') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Bewerk de gegevens van de catalogus.') }}
                </flux:text>
            </div>

            <flux:button
                :href="route('backend.catalogs.show', $catalog)"
                variant="ghost"
                icon="arrow-left"
            >
                {{ __('Terug naar catalogus') }}
            </flux:button>

        </div>

        {{-- Bewerkformulier --}}
        <livewire:backend.catalogs.edit-catalog :catalog="$catalog" />

    </div>

</x-layouts::app>
