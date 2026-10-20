<x-layouts::app :title="__('Catalogus toevoegen')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginaheader --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Catalogus toevoegen') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Voeg een nieuwe postzegelcatalogus toe.') }}
                </flux:text>
            </div>

            <flux:button
                :href="route('backend.catalogs.index')"
                variant="ghost"
                icon="arrow-left"
            >
                {{ __('Terug naar overzicht') }}
            </flux:button>
        </div>

        {{-- Livewire-catalogusformulier --}}
        <livewire:backend.catalogs.create-catalog />

    </div>
</x-layouts::app>
