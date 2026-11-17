<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Country;
use App\Models\SeriesCatalogValue;
use App\Models\StampSeries;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class CountryTranslationsSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly CatalogEdition $edition
    ) {}

    public function array(): array
    {
        $rows = [
            [
                'country_slug',
                'country_iso_code',
                'locale',
                'name',
                'description',
            ],
        ];

        // Landen die rechtstreeks aan de catalogus gekoppeld zijn.
        $catalogCountryIds = $this->catalog->countries()
            ->pluck('countries.id');

        // Postzegels binnen de geselecteerde cataloguseditie.
        $stampEntries = $this->edition->stampEntries()
            ->with('stamp')
            ->get();

        // Landen van de postzegels.
        $stampCountryIds = $stampEntries
            ->pluck('stamp.country_id');

        // Series die aan deze postzegels gekoppeld zijn.
        $stampSeriesIds = $stampEntries
            ->pluck('stamp.stamp_series_id');

        // Series met een eigen cataloguswaarde binnen deze editie.
        $valueSeriesIds = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $this->edition->id)
            ->pluck('stamp_series_id');

        // Alle relevante serie-ID's samenvoegen.
        $seriesIds = $stampSeriesIds
            ->merge($valueSeriesIds)
            ->filter()
            ->unique()
            ->values();

        // Landen van de relevante postzegelseries.
        $seriesCountryIds = StampSeries::query()
            ->whereIn('id', $seriesIds)
            ->pluck('country_id');

        // Alle benodigde landen samenvoegen.
        $countryIds = $catalogCountryIds
            ->merge($stampCountryIds)
            ->merge($seriesCountryIds)
            ->filter()
            ->unique()
            ->values();

        // Landen inclusief vertalingen ophalen.
        $countries = Country::query()
            ->with('translations')
            ->whereIn('id', $countryIds)
            ->orderBy('name')
            ->get();

        foreach ($countries as $country) {
            foreach ($country->translations as $translation) {
                $rows[] = [
                    $country->slug,
                    $country->iso_code,
                    $translation->locale,
                    $translation->name,
                    $translation->description,
                ];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Landvertalingen';
    }
}
