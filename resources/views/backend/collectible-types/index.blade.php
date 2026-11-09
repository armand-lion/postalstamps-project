<x-layouts::app :title="__('Verzamelobjecttypen beheren')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Verzamelobjecttypen beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de beschikbare soorten verzamelobjecten.') }}
                </flux:text>
            </div>
            {{-- Verzamelobjecttype toevoegen --}}
            <div class="flex justify-end">
                <flux:button :href="route('backend.collectible-types.create')" variant="primary" icon="plus"
                    class="bg-brown-2!" wire:navigate>
                    {{ __('Verzamelobjecttype toevoegen') }}
                </flux:button>
            </div>
        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div class="rounded-lg border border-green-600 bg-green-100 px-4 py-3 text-sm text-green-800" role="status">
                {{ session('success') }}
            </div>
        @endif

        {{-- Foutmelding --}}
        @if (session('error'))
            <div class="rounded-lg border border-red-600 bg-red-100 px-4 py-3 text-sm text-red-800" role="alert">
                {{ session('error') }}
            </div>
        @endif

        {{-- Overzicht --}}
        <div class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6">

            <div class="border-b border-brown-2 px-4 py-3">

                <flux:heading size="lg">
                    {{ __('Verzamelobjecttypen') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Aantal typen') }}: {{ $collectibleTypes->count() }}
                </flux:text>

            </div>

            @if ($collectibleTypes->isEmpty())

                <div class="p-8 text-center">
                    <flux:text>
                        {{ __('Geen verzamelobjecttypen gevonden.') }}
                    </flux:text>
                </div>
            @else
                <div class="overflow-x-auto">

                    <table class="w-full text-left text-sm">

                        <thead class="border-b border-brown-2">
                            <tr>
                                <th class="px-4 py-3">
                                    {{ __('Naam') }}
                                </th>

                                <th class="px-4 py-3">
                                    {{ __('Slug') }}
                                </th>

                                <th class="px-4 py-3">
                                    {{ __('Volgorde') }}
                                </th>

                                <th class="px-4 py-3">
                                    {{ __('Status') }}
                                </th>

                                <th class="px-4 py-3 text-right">
                                    {{ __('Acties') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach ($collectibleTypes as $type)
                                <tr class="border-b border-brown-2 last:border-b-0">

                                    <td class="px-4 py-3">
                                        {{ $type->translationWithFallback()?->name ?? $type->slug }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $type->slug }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $type->sort_order }}
                                    </td>

                                    <td class="px-4 py-3">

                                        @if ($type->is_active)
                                            <span class="text-green-700">
                                                {{ __('Actief') }}
                                            </span>
                                        @else
                                            <span class="text-red-600">
                                                {{ __('Inactief') }}
                                            </span>
                                        @endif

                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2">

                                            {{-- Bewerken --}}
                                            <flux:button :href="route('backend.collectible-types.edit', $type)"
                                                variant="primary" size="sm" icon="pencil" class="bg-brown-2!"
                                                wire:navigate>
                                                {{ __('Bewerken') }}
                                            </flux:button>

                                            {{-- Verwijderen --}}
                                            <form method="POST"
                                                action="{{ route('backend.collectible-types.destroy', $type) }}"
                                                onsubmit="event.preventDefault(); openDeleteModal(this);">

                                                @csrf
                                                @method('DELETE')

                                                <flux:button type="submit" variant="danger" size="sm"
                                                    icon="trash">
                                                    {{ __('Verwijderen') }}
                                                </flux:button>
                                            </form>

                                        </div>
                                    </td>

                                </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>

            @endif

        </div>

    </div>

    @include('components.delete-confirm-modal')

</x-layouts::app>
