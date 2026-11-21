<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use App\Models\SeriesCatalogValue;
use App\Models\StampSeries;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class StampSeriesSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly CatalogEdition $edition
    ) {}

    public function array(): array
    {
        $rows = [
            [
                'exchange_series_id',
                'country_slug',
                'country_iso_code',
                'name',
                'issue_year',
                'description',
            ],
        ];

        // Series die gekoppeld zijn aan postzegels binnen deze editie.
        $stampSeriesIds = $this->edition->stampEntries()
            ->whereHas('stamp', function ($query) {
                $query->whereNotNull('stamp_series_id');
            })
            ->with('stamp')
            ->get()
            ->pluck('stamp.stamp_series_id');

        // Series met een eigen cataloguswaarde binnen deze editie.
        $valueSeriesIds = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $this->edition->id)
            ->pluck('stamp_series_id');

        // Series die gekoppeld zijn aan bovenliggende postzegels,
        // ook wanneer deze zelf niet in de cataloguseditie voorkomen.
        $parentStampSeriesIds = $this->edition->stampEntries()
            ->with('stamp.parentStamp')
            ->get()
            ->pluck('stamp.parentStamp.stamp_series_id');

        // Alle drie de verzamelingen samenvoegen zonder dubbele series.
        $seriesIds = $stampSeriesIds
            ->merge($valueSeriesIds)
            ->merge($parentStampSeriesIds)
            ->filter()
            ->unique()
            ->values();

        $series = StampSeries::query()
            ->with('country')
            ->whereIn('id', $seriesIds)
            ->orderBy('id')
            ->get();

        foreach ($series as $item) {
            $rows[] = [
                $item->id,
                $item->country?->slug,
                $item->country?->iso_code,
                $item->name,
                $item->issue_year,
                $item->description,
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Postzegelseries';
    }
}
