<x-layouts::app :title="__('Catalogusedities vergelijken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div
            class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Catalogusedities vergelijken') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Vergelijk cataloguswaarden tussen twee edities van dezelfde catalogus.') }}
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $catalog->name }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-3">

                <flux:button :href="route('backend.catalogs.editions.index', $catalog)" variant="ghost" icon="arrow-left">

                    {{ __('Terug naar catalogusedities') }}

                </flux:button>

            </div>

        </div>

        {{-- Selectie van catalogusedities --}}
        <section
            class="flex flex-col gap-4 overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

            {{-- Sectiekop --}}
            <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">

                <flux:heading size="lg">
                    {{ __('Catalogusedities selecteren') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Selecteer twee verschillende edities om de cataloguswaarden te vergelijken.') }}
                </flux:text>

            </div>

            {{-- Inhoud --}}
            <div class="p-4">

                @if ($editions->count() < 2)
                    {{-- Onvoldoende catalogusedities --}}
                    <div class="py-8 text-center">

                        <flux:icon.book-open class="mx-auto mb-4 size-10 text-neutral-400" />

                        <flux:heading size="lg">
                            {{ __('Onvoldoende catalogusedities beschikbaar') }}
                        </flux:heading>

                        <flux:text class="mt-2">
                            {{ __('Er zijn minimaal twee catalogusedities nodig om een vergelijking uit te voeren.') }}
                        </flux:text>

                    </div>
                @else
                    {{-- Selectieformulier catalogusedities --}}
                    <form method="GET" action="{{ route('backend.catalogs.editions.compare', $catalog) }}">

                        <div class="grid grid-cols-1 items-end gap-6 lg:grid-cols-[320px_320px_auto]">

                            {{-- Vorige cataloguseditie --}}
                            <flux:field>
                                <flux:label>
                                    {{ __('Vorige cataloguseditie') }}
                                    <span class="text-red-600">*</span>
                                </flux:label>

                                <div x-data="{
                                    selectedId: @js((string) request('previous_edition_id', '')),
                                    selectedName: @js($editions->firstWhere('id', request('previous_edition_id'))?->edition_name ?? __('Selecteer een cataloguseditie'))
                                }">

                                    <input type="hidden" name="previous_edition_id" x-model="selectedId">

                                    <flux:dropdown position="bottom" align="start">

                                        <flux:button type="button" icon-trailing="chevron-down"
                                            class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                            <span x-text="selectedName" class="truncate"></span>

                                        </flux:button>

                                        <flux:menu
                                            class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                            @foreach ($editions as $edition)
                                                <flux:menu.item as="button" type="button"
                                                    x-on:click="
                                                    selectedId = @js((string) $edition->id);
                                                    selectedName = @js($edition->edition_name);
                                                "
                                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                    {{ $edition->edition_name }}

                                                </flux:menu.item>
                                            @endforeach

                                        </flux:menu>

                                    </flux:dropdown>

                                </div>

                            </flux:field>

                            {{-- Huidige cataloguseditie --}}
                            <flux:field>
                                <flux:label>
                                    {{ __('Huidige cataloguseditie') }}
                                    <span class="text-red-600">*</span>
                                </flux:label>

                                <div x-data="{
                                    selectedId: @js((string) request('current_edition_id', '')),
                                    selectedName: @js($editions->firstWhere('id', request('current_edition_id'))?->edition_name ?? __('Selecteer een cataloguseditie'))
                                }">

                                    <input type="hidden" name="current_edition_id" x-model="selectedId">

                                    <flux:dropdown position="bottom" align="start">

                                        <flux:button type="button" icon-trailing="chevron-down"
                                            class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                            <span x-text="selectedName" class="truncate"></span>

                                        </flux:button>

                                        <flux:menu
                                            class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                            @foreach ($editions as $edition)
                                                <flux:menu.item as="button" type="button"
                                                    x-on:click="
                                                    selectedId = @js((string) $edition->id);
                                                    selectedName = @js($edition->edition_name);
                                                "
                                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                    {{ $edition->edition_name }}

                                                </flux:menu.item>
                                            @endforeach

                                        </flux:menu>

                                    </flux:dropdown>

                                </div>

                            </flux:field>

                            {{-- Vergelijkingsknop --}}
                            <div class="flex items-end lg:justify-end">

                                <flux:button type="submit" icon="arrows-right-left"
                                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                                    {{ __('Catalogusedities vergelijken') }}

                                </flux:button>

                            </div>

                        </div>

                        {{-- Filters en sortering --}}
                        <div class="mt-6 flex flex-wrap items-end gap-6">

                            <flux:field>
                                <flux:label>
                                    {{ __('Waarderingscategorie') }}
                                </flux:label>

                                <div x-data="{
                                    selectedId: @js((string) request('condition_id', '')),
                                    selectedName: @js($conditions->firstWhere('id', request('condition_id'))?->translationWithFallback()?->name ?? __('Alle waarderingscategorieën'))
                                }">

                                    <input type="hidden" name="condition_id" x-model="selectedId">

                                    <flux:dropdown position="bottom" align="start">

                                        <flux:button type="button" icon-trailing="chevron-down"
                                            class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                            <span x-text="selectedName" class="truncate"></span>

                                        </flux:button>

                                        <flux:menu
                                            class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                            {{-- Alle waarderingscategorieën --}}
                                            <flux:menu.item as="button" type="button"
                                                x-on:click="
                                                    selectedId = '';
                                                    selectedName = @js(__('Alle waarderingscategorieën'));
                                                "
                                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                {{ __('Alle waarderingscategorieën') }}

                                            </flux:menu.item>

                                            {{-- Beschikbare waarderingscategorieën --}}
                                            @foreach ($conditions as $condition)
                                                <flux:menu.item as="button" type="button"
                                                    x-on:click="
                                                        selectedId = @js((string) $condition->id);
                                                        selectedName = @js($condition->translationWithFallback()?->name ?? $condition->slug);
                                                    "
                                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                    {{ $condition->translationWithFallback()?->name ?? $condition->slug }}

                                                </flux:menu.item>
                                            @endforeach

                                        </flux:menu>

                                    </flux:dropdown>

                                </div>

                            </flux:field>

                            {{-- Sorteren op waardeverschillen --}}
                            <flux:field>
                                <flux:label>
                                    {{ __('Sorteren op') }}
                                </flux:label>

                                <div x-data="{
                                    selectedId: @js((string) request('sort', 'default')),
                                    selectedName: @js(
    match (request('sort', 'default')) {
        'absolute_increase' => __('Grootste waardestijging'),
        'absolute_decrease' => __('Grootste waardedaling'),
        'percentage_increase' => __('Grootste procentuele stijging'),
        'percentage_decrease' => __('Grootste procentuele daling'),
        default => __('Standaardvolgorde'),
    },
)
                                }">

                                    <input type="hidden" name="sort" x-model="selectedId">

                                    <flux:dropdown position="bottom" align="start">

                                        <flux:button type="button" icon-trailing="chevron-down"
                                            class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                            <span x-text="selectedName" class="truncate"></span>

                                        </flux:button>

                                        <flux:menu
                                            class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                            @foreach ([
        'default' => __('Standaardvolgorde'),
        'absolute_increase' => __('Grootste waardestijging'),
        'absolute_decrease' => __('Grootste waardedaling'),
        'percentage_increase' => __('Grootste procentuele stijging'),
        'percentage_decrease' => __('Grootste procentuele daling'),
    ] as $sortValue => $sortLabel)
                                                <flux:menu.item as="button" type="button"
                                                    x-on:click="
                            selectedId = @js($sortValue);
                            selectedName = @js($sortLabel);
                        "
                                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                    {{ $sortLabel }}

                                                </flux:menu.item>
                                            @endforeach

                                        </flux:menu>

                                    </flux:dropdown>

                                </div>
                            </flux:field>

                        </div>

                    </form>
                @endif

            </div>

        </section>

        {{-- Resultaten van de cataloguseditievergelijking --}}
        @if (request()->filled('previous_edition_id') && request()->filled('current_edition_id'))
            <section
                class="flex flex-col gap-4 overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6 dark:border-neutral-700">

                {{-- Sectiekop --}}
                <div class="border-b border-theme-accent-1 bg-brown-6 px-4 py-3 dark:border-neutral-700">

                    <flux:heading size="lg">
                        {{ __('Vergelijkingsresultaten') }}
                    </flux:heading>

                    <flux:text class="mt-1">
                        {{ __('Overzicht van de cataloguswaarden en waardeveranderingen tussen beide edities.') }}
                    </flux:text>

                </div>

                {{-- Inhoud --}}
                <div class="p-4">

                    {{-- Controleer of er vergelijkingsresultaten beschikbaar zijn --}}
                    @if (empty($comparisonResults))
                        <div class="py-8 text-center">

                            <flux:icon.information-circle class="mx-auto mb-4 size-10 text-neutral-400" />

                            <flux:heading size="lg">
                                {{ __('Geen vergelijkingsresultaten beschikbaar') }}
                            </flux:heading>

                            <flux:text class="mt-2">
                                {{ __('Er zijn geen postzegels gevonden in de geselecteerde catalogusedities.') }}
                            </flux:text>

                        </div>
                    @else
                        {{-- Vergelijkingstabel --}}
                        <div class="overflow-x-auto">

                            <table class="min-w-full divide-y divide-theme-accent-1">

                                {{-- Tabelkop --}}
                                <thead class="bg-brown-5">

                                    <tr class="text-left text-sm font-semibold text-zinc-900">

                                        <th class="px-4 py-3">
                                            {{ __('Postzegel') }}
                                        </th>

                                        <th class="px-4 py-3">
                                            {{ __('Vorig catalogusnummer') }}
                                        </th>

                                        <th class="px-4 py-3">
                                            {{ __('Huidig catalogusnummer') }}
                                        </th>

                                        <th class="px-4 py-3">
                                            {{ __('Waarderingscategorie') }}
                                        </th>

                                        <th class="px-4 py-3 text-right">
                                            {{ __('Vorige waarde') }}
                                        </th>

                                        <th class="px-4 py-3 text-right">
                                            {{ __('Huidige waarde') }}
                                        </th>

                                        <th class="px-4 py-3 text-right">
                                            {{ __('Verschil') }}
                                        </th>

                                        <th class="px-4 py-3 text-right">
                                            {{ __('Verschil (%)') }}
                                        </th>

                                        <th class="px-4 py-3">
                                            {{ __('Status') }}
                                        </th>

                                    </tr>

                                </thead>

                                {{-- Tabelgegevens --}}
                                <tbody class="divide-y divide-theme-accent-1">

                                    @foreach ($comparisonResults as $result)
                                        {{-- Postzegel zonder cataloguswaarden --}}
                                        @if (empty($result['conditions']))
                                            <tr class="text-sm text-zinc-900 hover:bg-brown-5">

                                                {{-- Postzegel --}}
                                                <td class="px-4 py-3">
                                                    {{ $result['stamp']?->name ?? '—' }}
                                                </td>

                                                {{-- Vorig catalogusnummer --}}
                                                <td class="px-4 py-3">
                                                    {{ $result['previous_catalog_number'] ?? '—' }}
                                                </td>

                                                {{-- Huidig catalogusnummer --}}
                                                <td class="px-4 py-3">
                                                    {{ $result['current_catalog_number'] ?? '—' }}
                                                </td>

                                                {{-- Geen waarderingscategorie of cataloguswaarden --}}
                                                <td class="px-4 py-3 text-zinc-500">
                                                    {{ __('Geen cataloguswaarden') }}
                                                </td>

                                                <td class="px-4 py-3 text-right">—</td>
                                                <td class="px-4 py-3 text-right">—</td>
                                                <td class="px-4 py-3 text-right">—</td>
                                                <td class="px-4 py-3 text-right">—</td>

                                                {{-- Status van de postzegel --}}
                                                <td class="px-4 py-3 whitespace-nowrap">

                                                    @switch($result['status'])
                                                        @case('added')
                                                            <span class="font-medium text-blue-700">
                                                                {{ __('Toegevoegd') }}
                                                            </span>
                                                        @break

                                                        @case('removed')
                                                            <span class="font-medium text-red-700">
                                                                {{ __('Verwijderd') }}
                                                            </span>
                                                        @break

                                                        @default
                                                            <span class="font-medium text-zinc-700">
                                                                {{ __('Bestaand') }}
                                                            </span>
                                                    @endswitch

                                                </td>

                                            </tr>
                                        @endif
                                        {{-- Doorloop de waarderingscategorieën van deze postzegel --}}
                                        @foreach ($result['conditions'] as $conditionResult)
                                            <tr class="text-sm text-zinc-900 hover:bg-brown-5">

                                                {{-- Postzegel --}}
                                                <td class="px-4 py-3">

                                                    {{ $result['stamp']?->name ?? '—' }}

                                                </td>

                                                {{-- Vorig catalogusnummer --}}
                                                <td class="px-4 py-3">

                                                    {{ $result['previous_catalog_number'] ?? '—' }}

                                                </td>

                                                {{-- Huidig catalogusnummer --}}
                                                <td class="px-4 py-3">

                                                    {{ $result['current_catalog_number'] ?? '—' }}

                                                </td>

                                                {{-- Waarderingscategorie --}}
                                                <td class="px-4 py-3">

                                                    {{ $conditionResult['condition']?->translationWithFallback()?->name ??
                                                        ($conditionResult['condition']?->slug ?? '—') }}

                                                </td>

                                                {{-- Vorige cataloguswaarde --}}
                                                <td class="px-4 py-3 text-right whitespace-nowrap">

                                                    @if ($conditionResult['previous_value'] !== null)
                                                        {{ number_format($conditionResult['previous_value'], 2, ',', '.') }}
                                                        {{ $conditionResult['previous_currency'] ?? '' }}
                                                    @else
                                                        —
                                                    @endif

                                                </td>

                                                {{-- Huidige cataloguswaarde --}}
                                                <td class="px-4 py-3 text-right whitespace-nowrap">

                                                    @if ($conditionResult['current_value'] !== null)
                                                        {{ number_format($conditionResult['current_value'], 2, ',', '.') }}
                                                        {{ $conditionResult['current_currency'] ?? '' }}
                                                    @else
                                                        —
                                                    @endif

                                                </td>

                                                {{-- Absolute waardeverandering --}}
                                                <td class="px-4 py-3 text-right whitespace-nowrap">

                                                    @if ($conditionResult['absolute_change'] !== null)
                                                        @if ($conditionResult['absolute_change'] > 0)
                                                            +
                                                        @endif

                                                        {{ number_format($conditionResult['absolute_change'], 2, ',', '.') }}
                                                        {{ $conditionResult['current_currency'] ?? '' }}
                                                    @else
                                                        —
                                                    @endif

                                                </td>

                                                {{-- Procentuele waardeverandering --}}
                                                <td class="px-4 py-3 text-right whitespace-nowrap">

                                                    @if ($conditionResult['percentage_change'] !== null)
                                                        @if ($conditionResult['percentage_change'] > 0)
                                                            +
                                                        @endif

                                                        {{ number_format($conditionResult['percentage_change'], 2, ',', '.') }}%
                                                    @else
                                                        —
                                                    @endif

                                                </td>

                                                {{-- Status van de waardeverandering --}}
                                                <td class="px-4 py-3 whitespace-nowrap">

                                                    @switch($conditionResult['status'])
                                                        @case('added')
                                                            <span class="font-medium text-blue-700">
                                                                {{ __('Toegevoegd') }}
                                                            </span>
                                                        @break

                                                        @case('removed')
                                                            <span class="font-medium text-red-700">
                                                                {{ __('Verwijderd') }}
                                                            </span>
                                                        @break

                                                        @case('increased')
                                                            <span class="font-medium text-green-700">
                                                                {{ __('Gestegen') }}
                                                            </span>
                                                        @break

                                                        @case('decreased')
                                                            <span class="font-medium text-red-700">
                                                                {{ __('Gedaald') }}
                                                            </span>
                                                        @break

                                                        @case('unchanged')
                                                            <span class="font-medium text-zinc-700">
                                                                {{ __('Ongewijzigd') }}
                                                            </span>
                                                        @break

                                                        @case('not_comparable')
                                                            <span class="font-medium text-amber-700">
                                                                {{ __('Niet vergelijkbaar') }}
                                                            </span>
                                                        @break
                                                    @endswitch

                                                </td>

                                            </tr>
                                        @endforeach
                                    @endforeach

                                </tbody>

                            </table>

                        </div>
                    @endif

                </div>

            </section>
        @endif

    </div>

</x-layouts::app>
