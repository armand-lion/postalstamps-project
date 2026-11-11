@props(['catalogEntries'])

@php
    // Groepeer de catalogusvermeldingen per catalogus.
    $groupedCatalogEntries = $catalogEntries
        ->filter(fn($entry) => $entry->catalogEdition?->catalog !== null)
        ->groupBy(fn($entry) => $entry->catalogEdition->catalog->id)
        ->map(
            fn($entries) => $entries
                ->sortBy([
                    fn($a, $b) => ($a->catalogEdition?->edition_year ?? 0) <=> ($b->catalogEdition?->edition_year ?? 0),
                    fn($a, $b) => $a->id <=> $b->id,
                ])
                ->values(),
        );
@endphp

<div class="rounded-xl border border-theme-accent-1 bg-brown-5 p-4 md:p-6">

    <flux:heading size="lg" class="text-zinc-900!">
        {{ __('Historische cataloguswaarden') }}
    </flux:heading>

    <flux:text class="mt-2 text-zinc-700!">
        {{ __('Vergelijk de cataloguswaarden van deze postzegel tussen verschillende catalogusedities.') }}
    </flux:text>

    @forelse ($groupedCatalogEntries as $entries)

        @php
            $catalog = $entries->first()->catalogEdition->catalog;
        @endphp

        <div class="mt-6">

            <flux:heading size="md" class="text-zinc-900!">
                {{ $catalog->name }}
            </flux:heading>

            <div class="mt-3 space-y-2">

                @php
                    // Bewaar de waarden van de vorige cataloguseditie.
                    $previousValues = [];
                @endphp

                @foreach ($entries as $entry)
                    @php
                        // De drie standaardcataloguscondities.
                        $conditionSlugs = ['postfris', 'ongebruikt-met-plakker', 'gestempeld'];

                        // Cataloguswaarden van de huidige editie.
                        $currentValues = [];

                        // Berekende verschillen per conditie.
                        $valueChanges = [];

                        foreach ($conditionSlugs as $slug) {
                            $currentValue = $entry->values->first(fn($value) => $value->condition?->slug === $slug);

                            $currentValues[$slug] = $currentValue;

                            $previousValue = $previousValues[$slug] ?? null;

                            $absoluteChange = null;
                            $percentageChange = null;

                            if (
                                $currentValue !== null &&
                                $previousValue !== null &&
                                $currentValue->catalog_value !== null &&
                                $previousValue->catalog_value !== null &&
                                $currentValue->currency !== null &&
                                $previousValue->currency !== null &&
                                $currentValue->currency === $previousValue->currency
                            ) {
                                $currentAmount = (float) $currentValue->catalog_value;
                                $previousAmount = (float) $previousValue->catalog_value;

                                $absoluteChange = round($currentAmount - $previousAmount, 2);

                                if ($previousAmount > 0) {
                                    $percentageChange = round(($absoluteChange / $previousAmount) * 100, 2);
                                }
                            }

                            $valueChanges[$slug] = [
                                'absolute' => $absoluteChange,
                                'percentage' => $percentageChange,
                            ];
                        }

                        // Bestaande variabelen voor de weergave behouden.
                        $postfris = $currentValues['postfris'];
                        $ongebruiktMetPlakker = $currentValues['ongebruikt-met-plakker'];
                        $gestempeld = $currentValues['gestempeld'];

                        // De huidige editie wordt de vorige editie
                        // voor de volgende iteratie.
                        $previousValues = $currentValues;
                    @endphp

                    <div class="rounded-lg border border-theme-accent-3 bg-brown-6 p-4 text-sm text-zinc-900">

                        <div class="font-semibold">
                            {{ $entry->catalogEdition->edition_year }}
                            —
                            {{ $entry->catalogEdition->edition_name }}
                        </div>

                        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">

                            {{-- Postfris --}}
                            <div>
                                <div class="text-zinc-700">
                                    {{ __('Postfris') }}
                                </div>

                                <div class="mt-1 font-medium">
                                    @if ($postfris !== null && $postfris->catalog_value !== null)
                                        {{ $postfris->currency }}
                                        {{ number_format((float) $postfris->catalog_value, 2, ',', '.') }}
                                    @else
                                        —
                                    @endif
                            </div>

                            @php
                                $postfrisChange = $valueChanges['postfris'];
                            @endphp

                            @if ($postfrisChange['absolute'] !== null)
                                <div class="mt-1 text-xs font-medium text-zinc-700">

                                    @if ($postfrisChange['absolute'] > 0)
                                        +
                                    @elseif ($postfrisChange['absolute'] < 0)
                                        −
                                    @endif

                                    {{ $postfris->currency }}
                                    {{ number_format(abs($postfrisChange['absolute']), 2, ',', '.') }}

                                    @if ($postfrisChange['percentage'] !== null)
                                        (@if ($postfrisChange['percentage'] > 0)
                                            +
                                        @elseif ($postfrisChange['percentage'] < 0)
                                            −
                                        @endif
                                        {{ number_format(abs($postfrisChange['percentage']), 2, ',', '.') }}%)
                                    @endif

                                </div>
                            @endif
                        </div>

                        {{-- Ongebruikt met plakker --}}
                        <div>
                            <div class="text-zinc-700">
                                {{ __('Ongebruikt met plakker') }}
                            </div>

                            <div class="mt-1 font-medium">
                                @if ($ongebruiktMetPlakker !== null && $ongebruiktMetPlakker->catalog_value !== null)
                                    {{ $ongebruiktMetPlakker->currency }}
                                    {{ number_format((float) $ongebruiktMetPlakker->catalog_value, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </div>

                            @php
                                $ongebruiktMetPlakkerChange = $valueChanges['ongebruikt-met-plakker'];
                            @endphp

                            @if ($ongebruiktMetPlakkerChange['absolute'] !== null)
                                <div class="mt-1 text-xs font-medium text-zinc-700">

                                    @if ($ongebruiktMetPlakkerChange['absolute'] > 0)
                                        +
                                    @elseif ($ongebruiktMetPlakkerChange['absolute'] < 0)
                                        −
                                    @endif

                                    {{ $ongebruiktMetPlakker->currency }}
                                    {{ number_format(abs($ongebruiktMetPlakkerChange['absolute']), 2, ',', '.') }}

                                    @if ($ongebruiktMetPlakkerChange['percentage'] !== null)
                                        (
                                        @if ($ongebruiktMetPlakkerChange['percentage'] > 0)
                                            +
                                        @elseif ($ongebruiktMetPlakkerChange['percentage'] < 0)
                                            −
                                        @endif
                                        {{ number_format(abs($ongebruiktMetPlakkerChange['percentage']), 2, ',', '.') }}%
                                        )
                                    @endif

                                </div>
                            @endif
                        </div>

                        {{-- Gestempeld --}}
                        <div>
                            <div class="text-zinc-700">
                                {{ __('Gestempeld') }}
                            </div>

                            <div class="mt-1 font-medium">
                                @if ($gestempeld !== null && $gestempeld->catalog_value !== null)
                                    {{ $gestempeld->currency }}
                                    {{ number_format((float) $gestempeld->catalog_value, 2, ',', '.') }}
                                @else
                                    —
                                @endif
                            </div>

                            @php
                                $gestempeldChange = $valueChanges['gestempeld'];
                            @endphp

                            @if ($gestempeldChange['absolute'] !== null)
                                <div class="mt-1 text-xs font-medium text-zinc-700">

                                    @if ($gestempeldChange['absolute'] > 0)
                                        +
                                    @elseif ($gestempeldChange['absolute'] < 0)
                                        −
                                    @endif

                                    {{ $gestempeld->currency }}
                                    {{ number_format(abs($gestempeldChange['absolute']), 2, ',', '.') }}

                                    @if ($gestempeldChange['percentage'] !== null)
                                        (
                                        @if ($gestempeldChange['percentage'] > 0)
                                            +
                                        @elseif ($gestempeldChange['percentage'] < 0)
                                            −
                                        @endif
                                        {{ number_format(abs($gestempeldChange['percentage']), 2, ',', '.') }}%
                                        )
                                    @endif

                                </div>
                            @endif
                        </div>

                    </div>

            </div>
    @endforeach

</div>

</div>

@empty

<flux:text class="mt-4 text-zinc-700!">
    {{ __('Er zijn nog geen historische cataloguswaarden beschikbaar.') }}
</flux:text>
@endforelse

</div>
