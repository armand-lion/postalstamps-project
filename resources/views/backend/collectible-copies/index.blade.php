<x-layouts::app :title="__('Mijn verzamelexemplaren')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Mijn verzamelexemplaren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de verzamelobjecten die je in bezit hebt.') }}
                </flux:text>
            </div>

        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">

                {{ session('success') }}

            </div>
        @endif

        {{-- Overzicht persoonlijke verzamelexemplaren --}}
        <div class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="overflow-x-auto">

                <table class="w-full text-left text-sm">

                    <thead class="border-b border-theme-accent-1 dark:border-neutral-700">
                        <tr>
                            <th class="px-4 py-3">{{ __('Verzamelobject') }}</th>
                            <th class="px-4 py-3">{{ __('Type') }}</th>
                            <th class="px-4 py-3">{{ __('Land') }}</th>
                            <th class="px-4 py-3">{{ __('Uitgiftejaar') }}</th>
                            <th class="px-4 py-3">{{ __('Conditie') }}</th>
                            <th class="px-4 py-3">{{ __('Aantal') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Acties') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($collectibleCopies as $collectibleCopy)
                            <tr
                                class="border-b border-theme-accent-1 hover:bg-brown-5! dark:border-neutral-700 dark:hover:bg-brown-5!">

                                <td class="px-4 py-3">
                                    {{ $collectibleCopy->collectible?->name ?? '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $collectibleCopy->collectible?->collectibleType?->translationWithFallback()?->name ?? '—' }}

                                </td>

                                <td class="px-4 py-3">
                                    {{ $collectibleCopy->collectible?->country?->translationWithFallback()?->name ?? '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $collectibleCopy->collectible?->issue_year ?? '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $collectibleCopy->condition?->translationWithFallback()?->name ?? '—' }}
                                </td>

                                <td class="px-4 py-3">
                                    {{ $collectibleCopy->quantity }}
                                </td>

                                {{-- Acties --}}
                                <td class="px-4 py-3 text-right">

                                    <div class="flex items-center justify-end gap-2">

                                        <flux:button :href="route('backend.collectible-copies.edit', $collectibleCopy)"
                                            variant="ghost" icon="pencil" class="text-zinc-900!">
                                            {{ __('Bewerken') }}
                                        </flux:button>

                                        <form method="POST"
                                            action="{{ route('backend.collectible-copies.destroy', $collectibleCopy) }}"
                                            onsubmit="event.preventDefault(); openDeleteModal(this);">

                                            @csrf
                                            @method('DELETE')

                                            <flux:button type="submit" variant="ghost" icon="trash"
                                                class="text-red-600!">
                                                {{ __('Verwijderen') }}
                                            </flux:button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center">

                                    <flux:text>
                                        {{ __('Je hebt nog geen verzamelobjecten aan je verzameling toegevoegd.') }}
                                    </flux:text>

                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>

            </div>

            {{-- Paginering --}}
            @if ($collectibleCopies->hasPages())
                <div class="border-t border-theme-accent-1 p-4 dark:border-neutral-700">
                    {{ $collectibleCopies->links() }}
                </div>
            @endif

        </div>

    </div>
    @include('components.delete-confirm-modal')
</x-layouts::app>
