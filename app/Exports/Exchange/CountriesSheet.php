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
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

class CountriesSheet implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
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
                'name',
                'iso_code',
                'entity_type',
                'start_year',
                'end_year',
                'is_active',
                'slug',
                'description',
            ],
        ];

        // Landen die rechtstreeks aan de catalogus gekoppeld zijn.
        $catalogCountryIds = $this->catalog->countries()
            ->pluck('countries.id');

        // Landen van postzegels binnen de geselecteerde cataloguseditie.
        $stampCountryIds = $this->edition->stampEntries()
            ->with('stamp')
            ->get()
            ->pluck('stamp.country_id');

        // Landen van bovenliggende postzegels die buiten de cataloguseditie vallen.
        $parentStampCountryIds = $this->edition->stampEntries()
            ->with('stamp.parentStamp')
            ->get()
            ->pluck('stamp.parentStamp.country_id')
            ->filter();

        // Series die aan postzegels binnen deze editie gekoppeld zijn.
        $stampSeriesIds = $this->edition->stampEntries()
            ->with('stamp')
            ->get()
            ->pluck('stamp.stamp_series_id');

        // Series die via een seriecataloguswaarde aan deze editie gekoppeld zijn.
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
            ->merge($parentStampCountryIds)
            ->merge($seriesCountryIds)
            ->filter()
            ->unique()
            ->values();

        // Landen ophalen zonder duplicaten.
        $countries = Country::query()
            ->whereIn('id', $countryIds)
            ->orderBy('name')
            ->get();

        foreach ($countries as $country) {
            $rows[] = [
                $country->name,
                $country->iso_code,
                $country->entity_type,
                $country->start_year,
                $country->end_year,
                (int) $country->is_active,
                $country->slug,
                $country->description,
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Landen';
    }
}
