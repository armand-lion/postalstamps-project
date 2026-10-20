<x-layouts::app :title="__('Cataloguseditie bewerken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Cataloguseditie bewerken') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Wijzig de gegevens van de geselecteerde cataloguseditie.') }}
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $catalog->name }}
                </flux:text>
            </div>

            {{-- Terug naar cataloguseditie --}}
            <flux:button
                :href="route('backend.catalogs.editions.show', [$catalog, $edition])"
                variant="ghost"
                icon="arrow-left"
            >
                {{ __('Terug naar cataloguseditie') }}
            </flux:button>

        </div>

        {{-- Livewire-formulier cataloguseditie bewerken --}}
        <livewire:backend.catalog-editions.edit-catalog-edition
            :catalog="$catalog"
            :edition="$edition"
        />

    </div>

</x-layouts::app>
