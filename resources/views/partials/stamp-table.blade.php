@if ($stamps->isEmpty())
    <div class="p-8 text-center">
        <flux:heading>
            {{ $emptyHeading ?? __('Geen postzegels gevonden') }}
        </flux:heading>

        <flux:text class="mt-2">
            {{ $emptyDescription ?? __('Pas de filters aan.') }}
        </flux:text>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">

            <thead class="bg-brown-5 text-brown-2 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                    <th class="px-4 py-3 font-medium">
                        @php
                            $pageStampIds = $stamps->pluck('id')->all();

                            $allPageSelected = count(array_diff($pageStampIds, $selectedIds)) === 0;
                        @endphp

                        <form method="POST" action="{{ route($selectionRoute) }}">

                            @csrf

                            @foreach ($pageStampIds as $stampId)
                                <input type="hidden" name="stamp_ids[]" value="{{ $stampId }}">
                            @endforeach

                            <input type="hidden" name="action" value="{{ $allPageSelected ? 'remove' : 'add' }}">

                            <flux:checkbox :checked="$allPageSelected"
                                :aria-label="__('Selecteer of deselecteer alle postzegels op deze pagina')"
                                onchange="this.closest('form').requestSubmit()"
                                class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                        </form>
                    </th>
                    <th class="px-4 py-3 font-medium">{{ __('Afbeelding') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Naam') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Land') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Jaar') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Uitgiftedatum') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Catalogusnummer') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Serie') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Nominale waarde') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Ontwerper') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Druktechniek') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __("Thema's") }}</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">

                @foreach ($stamps as $stamp)
                    <tr class="transition-colors hover:bg-brown-4!">

                        {{-- Selectie --}}
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route($selectionRoute) }}">

                                @csrf

                                <input type="hidden" name="stamp_ids[]" value="{{ $stamp->id }}">

                                <input type="hidden" name="action"
                                    value="{{ in_array($stamp->id, $selectedIds, true) ? 'remove' : 'add' }}">

                                <flux:checkbox :checked="in_array($stamp->id, $selectedIds, true)"
                                    onchange="this.closest('form').requestSubmit()"
                                    class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                            </form>
                        </td>

                        {{-- Catalogusafbeelding --}}
                        <td class="px-4 py-3">
                            @php
                                $thumbnail = $stamp->images
                                    ->where('image_type', 'front')
                                    ->sortByDesc('is_primary')
                                    ->first();
                            @endphp

                            @if ($thumbnail)
                                <a href="{{ route($detailRoute, $stamp) }}">
                                    <img src="{{ asset('storage/' . $thumbnail->path) }}"
                                        alt="{{ $thumbnail->alt_text ?? $stamp->name }}"
                                        class="h-15 w-15 rounded object-contain" loading="lazy">
                                </a>
                            @else
                                <span class="text-neutral-400">—</span>
                            @endif
                        </td>

                        {{-- Naam --}}
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route($detailRoute, $stamp) }}"
                                class="underline decoration-neutral-300 underline-offset-4 hover:decoration-current">
                                {{ $stamp->translationWithFallback()?->name ?? $stamp->name }}
                            </a>
                        </td>

                        {{-- Land --}}
                        <td class="px-4 py-3">
                            {{ $stamp->country?->translationWithFallback()?->name ?? ($stamp->country?->name ?? '—') }}
                        </td>

                        {{-- Uitgiftejaar --}}
                        <td class="px-4 py-3">
                            {{ $stamp->issue_year }}
                        </td>

                        {{-- Uitgiftedatum --}}
                        <td class="whitespace-nowrap px-4 py-3">
                            {{ $stamp->issue_date?->format('d-m-Y') ?? '—' }}
                        </td>

                        {{-- Catalogusnummer --}}
                        <td class="px-4 py-3">
                            @php
                                $selectedEditionId = request('catalog_edition_id');

                                $catalogEntry = $stamp->catalogEntries
                                    ->when(
                                        $selectedEditionId,
                                        fn($entries) => $entries->where('catalog_edition_id', $selectedEditionId),
                                    )
                                    ->first();
                            @endphp

                            {{ $catalogEntry?->catalog_number ?? '—' }}
                        </td>

                        {{-- Postzegelserie --}}
                        <td class="px-4 py-3">
                            {{ $stamp->series?->translationWithFallback()?->name ?? ($stamp->series?->name ?? '—') }}
                        </td>

                        {{-- Nominale waarde --}}
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($stamp->face_value !== null && $stamp->face_value !== '')
                                {{ $stamp->face_value }}
                                {{ $stamp->currency }}
                            @else
                                —
                            @endif
                        </td>

                        {{-- Ontwerper --}}
                        <td class="px-4 py-3">
                            {{ $stamp->designer ?: '—' }}
                        </td>

                        {{-- Druktechniek --}}
                        <td class="px-4 py-3">
                            {{ $stamp->printing_method ?: '—' }}
                        </td>

                        {{-- Thema's --}}
                        <td class="px-4 py-3">
                            @forelse ($stamp->themes as $theme)
                                <div>
                                    {{ $theme->translationWithFallback()?->name ?? $theme->name }}
                                </div>
                            @empty
                                —
                            @endforelse
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
