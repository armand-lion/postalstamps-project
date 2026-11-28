<x-layouts::app :title="__('Postzegels beheren')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="rounded-xl flex flex-col justify-between gap-4 sm:flex-row sm:items-center bg-brown-6 rounded-lg border border-theme-accent-1 p-5">
            <div>
                <flux:heading size="xl">
                    {{ __('Postzegels beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de postzegels in je catalogus.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2">

                {{-- Wijzigingsgeschiedenis --}}
                <flux:button :href="route('backend.stamps.history.index')" icon="arrow-path"
                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                    {{ __('Wijzigingsgeschiedenis') }}

                </flux:button>

                @if (auth()->user()?->is_admin === true)
                    <flux:button :href="route('backend.stamps.bulk-edit.index')" icon="pencil-square"
                        class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                        {{ __('Bulkbewerking') }}

                    </flux:button>
                @endif

                <flux:button :href="route('backend.stamps.create')" variant="primary" icon="plus"
                    class="bg-brown-2 hover:bg-brown-1">
                    {{ __('Postzegel toevoegen') }}
                </flux:button>

            </div>
        </div>

        {{-- Succes of fout melding --}}
        @include('partials.flash-messages')

        {{-- Filters --}}
        @include('partials.stamp-filters', [
            'filterRoute' => 'backend.stamps.index',
        ])
        {{-- Resultaten --}}
        <div class="overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            <div class="border-b border-brown-2 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Catalogus') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ trans_choice(__(':count stamp found|:count stamps found'), $stamps->total()) }}
                </flux:text>
            </div>

            @if ($stamps->isEmpty())
                <div class="p-8 text-center">
                    <flux:heading>{{ __('Geen postzegels gevonden') }}</flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een postzegel toe of pas de filters aan.') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button :href="route('backend.stamps.create')" variant="primary">
                            {{ __('Eerste postzegel toevoegen') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-brown-5 text-brown-2 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Afbeelding') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Naam') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Land') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Jaar') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Catalogusnummer') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Cataloguswaarde') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Nominale waarde') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Acties') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">
                            @foreach ($stamps as $stamp)
                                <tr
                                    class="transition-colors hover:bg-brown-4! {{ $stamp->is_owned ? '' : 'opacity-50 hover:opacity-75' }}">
                                    {{-- Catalogusafbeelding als thumbnail --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $thumbnail = $stamp->images
                                                ->where('image_type', 'front')
                                                ->sortByDesc('is_primary')
                                                ->first();
                                        @endphp

                                        @if ($thumbnail)
                                            <a href="{{ route('backend.stamps.show', $stamp) }}">
                                                <img src="{{ asset('storage/' . $thumbnail->path) }}"
                                                    alt="{{ $thumbnail->alt_text ?? $stamp->name }}"
                                                    class="h-15 w-15 rounded object-contain" loading="lazy">
                                            </a>
                                        @else
                                            <span class="text-neutral-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('backend.stamps.show', $stamp) }}"
                                            class="underline decoration-neutral-300 underline-offset-4 hover:decoration-current">
                                            {{ $stamp->translationWithFallback()?->name ?? $stamp->name }}
                                        </a>
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $stamp->country?->translationWithFallback()?->name ?? ($stamp->country?->name ?? '—') }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $stamp->issue_year }}
                                    </td>

                                    {{-- Officieel catalogusnummer --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $selectedEditionId = request('catalog_edition_id');

                                            $catalogEntry = $stamp->catalogEntries
                                                ->when(
                                                    $selectedEditionId,
                                                    fn($entries) => $entries->where(
                                                        'catalog_edition_id',
                                                        $selectedEditionId,
                                                    ),
                                                )
                                                ->first();
                                        @endphp

                                        {{ $catalogEntry?->catalog_number ?? '—' }}
                                    </td>

                                    {{-- Cataloguswaarden van de geselecteerde cataloguseditie --}}
                                    <td class="px-4 py-3">
                                        @php
                                            $selectedEditionId = request('catalog_edition_id');

                                            $catalogEntry = $selectedEditionId
                                                ? $stamp->catalogEntries->firstWhere(
                                                    'catalog_edition_id',
                                                    (int) $selectedEditionId,
                                                )
                                                : null;
                                        @endphp

                                        @if ($catalogEntry && $catalogEntry->values->isNotEmpty())
                                            <div class="space-y-1">
                                                @foreach ($catalogEntry->values->sortBy(fn($value) => $value->condition?->sort_order ?? 999) as $value)
                                                    @php
                                                        $condition = $value->condition;

                                                        $conditionTranslation = $condition
                                                            ? $condition->translations->firstWhere(
                                                                    'locale',
                                                                    app()->getLocale(),
                                                                ) ??
                                                                $condition->translations->firstWhere(
                                                                    'locale',
                                                                    config('app.fallback_locale', 'nl'),
                                                                )
                                                            : null;
                                                    @endphp

                                                    <div class="text-sm">
                                                        <span class="font-medium">
                                                            {{ $conditionTranslation?->name ?? ($condition?->slug ?? '—') }}:
                                                        </span>

                                                        {{ $value->currency }}
                                                        {{ number_format((float) $value->catalog_value, 2, ',', '.') }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-neutral-400">—</span>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($stamp->face_value !== null && $stamp->face_value !== '')
                                            {{ $stamp->face_value }}
                                            {{ $stamp->currency }}
                                        @else
                                            —
                                        @endif
                                    </td>


                                    {{-- Acties --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">

                                            {{-- Toevoegen aan persoonlijke verzameling --}}
                                            @if (!$stamp->is_owned)
                                                <flux:button
                                                    :href="route('backend.stamp-copies.create', ['stamp_id' => $stamp->id])"
                                                    size="sm" variant="ghost" icon="plus">
                                                    {{ __('Toevoegen aan mijn verzameling') }}
                                                </flux:button>
                                            @endif

                                            {{-- Bekijken --}}
                                            <flux:button :href="route('backend.stamps.show', $stamp)" size="sm"
                                                variant="ghost" icon="eye">
                                                {{ __('Bekijken') }}
                                            </flux:button>

                                            {{-- Bewerken --}}
                                            <flux:button :href="route('backend.stamps.edit', $stamp)" size="sm"
                                                variant="ghost" icon="pencil">
                                                {{ __('Bewerken') }}
                                            </flux:button>

                                            {{-- Verwijderen --}}
                                            <form method="POST" action="{{ route('backend.stamps.destroy', $stamp) }}"
                                                class="delete-confirm-form">
                                                @csrf
                                                @method('DELETE')

                                                <flux:button type="button" size="sm" variant="ghost"
                                                    icon="trash" class="text-red-600 dark:text-red-400"
                                                    onclick="openDeleteModal(this.closest('form'))">
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

                {{-- Paginering --}}
                <div class="border-t border-brown-4 p-4 dark:border-neutral-700">
                    {{ $stamps->links() }}
                </div>
            @endif
        </div>
    </div>

    @include('components.delete-confirm-modal')

    <script>
        const catalogSelect = document.querySelector('select[name="catalog_id"]');
        const catalogEditionSelect = document.querySelector(
            'select[name="catalog_edition_id"]'
        );

        function filterCatalogEditions() {
            const selectedCatalogId = catalogSelect.value;
            const selectedEditionId = catalogEditionSelect.value;

            Array.from(catalogEditionSelect.options).forEach(option => {
                if (option.value === '') {
                    option.hidden = false;
                    return;
                }

                option.hidden = selectedCatalogId !== '' &&
                    option.dataset.catalogId !== selectedCatalogId;
            });

            const selectedOption = Array.from(catalogEditionSelect.options).find(
                option => option.value === selectedEditionId
            );

            if (selectedOption && selectedOption.hidden) {
                catalogEditionSelect.value = '';
            }
        }

        catalogSelect.addEventListener('change', filterCatalogEditions);

        filterCatalogEditions();
    </script>
</x-layouts::app>
