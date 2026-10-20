<x-layouts::app :title="__('Cataloguseditie toevoegen')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Cataloguseditie toevoegen') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Voeg een nieuwe editie toe aan de geselecteerde catalogus.') }}
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $catalog->name }}
                </flux:text>
            </div>

            <flux:button
                :href="route('backend.catalogs.editions.index', $catalog)"
                variant="ghost"
                icon="arrow-left"
            >
                {{ __('Terug naar catalogusedities') }}
            </flux:button>

        </div>

        {{-- Livewire-formulier --}}
        <livewire:backend.catalog-editions.create-catalog-edition
            :catalog="$catalog"
        />

    </div>

</x-layouts::app>
