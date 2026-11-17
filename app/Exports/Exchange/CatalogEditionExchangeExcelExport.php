<?php

namespace App\Exports\Exchange;

use App\Models\Catalog;
use App\Models\CatalogEdition;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CatalogEditionExchangeExcelExport implements Export, WithMultipleSheets
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly CatalogEdition $edition
    ) {}

    public function sheets(): array
    {
        return [
            new MetadataSheet,
            new CatalogSheet($this->catalog),
            new EditionSheet($this->edition),
            new CountriesSheet($this->catalog, $this->edition),
            new CountryTranslationsSheet($this->catalog, $this->edition),
            new ConditionRulesSheet($this->catalog),

            new StampSeriesSheet($this->edition),
            new StampSeriesTranslationsSheet($this->edition),

            new StampsSheet($this->edition),
            new ParentStampsSheet($this->edition),
            new StampTranslationsSheet($this->edition),
            new CatalogValuesSheet($this->edition),

            new ThemesSheet($this->edition),
            new ThemeTranslationsSheet($this->edition),
            new StampThemesSheet($this->edition),

            new ConditionsSheet($this->edition),
            new ConditionTranslationsSheet($this->edition),

            new SeriesCatalogValuesSheet($this->edition),
        ];
    }
}
