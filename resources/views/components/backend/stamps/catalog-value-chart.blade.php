@props(['entries'])

@php
    // De drie waarderingscategorieën die we in de grafiek tonen.
    $conditions = [
        'postfris' => __('Postfris'),
        'ongebruikt-met-plakker' => __('Ongebruikt met plakker'),
        'gestempeld' => __('Gestempeld'),
    ];

    // Sorteer de catalogusedities chronologisch.
    $sortedEntries = $entries
        ->sortBy([
            fn($a, $b) => ($a->catalogEdition?->edition_year ?? 0) <=> ($b->catalogEdition?->edition_year ?? 0),
            fn($a, $b) => $a->id <=> $b->id,
        ])
        ->values();

    // Labels voor de horizontale as.
    $labels = $sortedEntries
        ->map(
            fn($entry) => trim(
                ($entry->catalogEdition?->edition_year ?? '') . ' ' . ($entry->catalogEdition?->edition_name ?? ''),
            ),
        )
        ->all();

    // Verzamel de beschikbare valuta.
    $currencies = $sortedEntries
        ->flatMap(fn($entry) => $entry->values)
        ->filter(
            fn($value) => in_array($value->condition?->slug, array_keys($conditions), true) &&
                $value->catalog_value !== null &&
                filled($value->currency),
        )
        ->pluck('currency')
        ->unique()
        ->values();

    // We vergelijken alleen waarden met dezelfde valuta.
    $canShowChart = $currencies->count() === 1;

    $currency = $canShowChart ? $currencies->first() : null;

    // Bouw de gegevensreeksen voor Chart.js.
    $datasets = [];

    if ($canShowChart) {
        foreach ($conditions as $slug => $label) {
            $values = $sortedEntries
                ->map(function ($entry) use ($slug, $currency) {
                    $value = $entry->values->first(fn($value) => $value->condition?->slug === $slug);

                    if ($value === null || $value->catalog_value === null || $value->currency !== $currency) {
                        return null;
                    }

                    return (float) $value->catalog_value;
                })
                ->all();

            $datasets[] = [
                'label' => $label,
                'data' => $values,
            ];
        }
    }
@endphp

<div class="mt-4 rounded-lg border border-theme-accent-3 bg-brown-6 p-4">

    <flux:heading size="sm" class="text-zinc-900!">
        {{ __('Ontwikkeling cataloguswaarden') }}
    </flux:heading>

    @if ($canShowChart)
        <div class="relative mt-4 h-72 w-full md:h-96">
            <canvas data-catalog-value-chart data-chart-labels="{{ json_encode($labels) }}"
                data-chart-datasets="{{ json_encode($datasets) }}" data-chart-currency="{{ $currency }}" role="img"
                aria-label="{{ __('Grafiek met historische cataloguswaarden') }}"></canvas>
        </div>
    @else
        <flux:text class="mt-3 text-sm text-zinc-700!">
            {{ __('Er zijn onvoldoende vergelijkbare cataloguswaarden beschikbaar om een grafiek te tonen.') }}
        </flux:text>
    @endif

</div>
