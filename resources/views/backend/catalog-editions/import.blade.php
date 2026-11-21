<x-layouts::app :title="__('Cataloguseditie importeren')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            {{-- Paginakop --}}
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

                <div>
                    <flux:heading size="xl">
                        {{ __('Cataloguseditie importeren') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Importeer een cataloguseditie vanuit een Excel-uitwisselingsbestand.') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium">
                        {{ $catalog->name }}
                    </flux:text>
                </div>

                <flux:button :href="route('backend.catalogs.editions.index', $catalog)" variant="ghost" icon="arrow-left">
                    {{ __('Terug naar catalogusedities') }}
                </flux:button>

            </div>

        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div role="status" class="rounded-lg border border-green-600 bg-green-100 p-4 text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Importformulier --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Excel-bestand selecteren') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Selecteer een Excel-uitwisselingsbestand (.xlsx) met formaatversie 1.1.') }}
            </flux:text>

            <form method="POST" action="{{ route('backend.catalogs.editions.import.store', $catalog) }}"
                enctype="multipart/form-data" class="mt-6 space-y-6">
                @csrf

                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-1">
                            {{ __('Excel-bestand') }}
                            <span class="text-red-600">*</span>
                        </span>
                    </flux:label>

                    <input type="file" name="file" accept=".xlsx" required
                        class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 p-3 text-zinc-900 focus:border-theme-accent-3 focus:ring-2 focus:ring-theme-accent-3 focus:outline-none placeholder:text-zinc-500
               focus:border-theme-accent-3
               focus:outline-none
               focus:ring-2
               focus:ring-theme-accent-3
               focus:ring-offset-2
               focus:ring-offset-brown-6" />

                    <flux:error name="file" />
                </flux:field>

                <flux:text>
                    {{ __('Het bestand moet het volledige Excel-uitwisselingsformaat met 18 tabbladen bevatten.') }}
                </flux:text>

                <div class="flex justify-end gap-3">

                    <flux:button :href="route('backend.catalogs.editions.index', $catalog)" variant="ghost">
                        {{ __('Annuleren') }}
                    </flux:button>

                    <flux:button type="submit"
                        class="border border-theme-accent-3! bg-brown-6! text-zinc-900! hover:bg-brown-5!">
                        {{ __('Excel-bestand controleren') }}
                    </flux:button>

                </div>

            </form>

        </div>

    </div>

</x-layouts::app>
