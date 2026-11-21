<?php

namespace App\Imports\Exchange;

use App\Models\Catalog;
use App\Models\CatalogConditionRule;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\ConditionTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\SeriesCatalogValue;
use App\Models\Stamp;
use App\Models\StampCatalogValue;
use App\Models\StampSeries;
use App\Models\StampSeriesTranslation;
use App\Models\StampTranslation;
use App\Models\Theme;
use App\Models\ThemeTranslation;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;

class CatalogEditionExchangeImporter
{
    /**
     * Controleer of het Excel-bestand bij de geselecteerde catalogus hoort.
     */
    public function validateCatalog(
        string $filePath,
        Catalog $catalog
    ): void {
        $spreadsheet = IOFactory::load($filePath);

        try {
            // Lees het werkblad Catalogus.
            $catalogSheet = $spreadsheet->getSheetByName('Catalogus');

            if ($catalogSheet === null) {
                throw new RuntimeException(
                    'Het tabblad Catalogus ontbreekt.'
                );
            }

            // Lees de catalogusnaam uit cel A2.
            $catalogName = trim(
                (string) $catalogSheet->getCell('A2')->getValue()
            );

            // Controleer of de catalogusnamen overeenkomen.
            if ($catalogName !== trim($catalog->name)) {
                throw new RuntimeException(
                    'De catalogusnaam in het Excel-bestand komt niet overeen met de geselecteerde catalogus.'
                );
            }
        } finally {
            // Maak het spreadsheet vrij uit het geheugen.
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Controleer of de cataloguseditie al bestaat.
     */
    public function validateEditionDoesNotExist(
        string $filePath,
        Catalog $catalog
    ): void {
        $spreadsheet = IOFactory::load($filePath);

        try {
            // Lees het werkblad Editie.
            $editionSheet = $spreadsheet->getSheetByName('Editie');

            if ($editionSheet === null) {
                throw new RuntimeException(
                    'Het tabblad Editie ontbreekt.'
                );
            }

            // Lees de naam van de cataloguseditie.
            $editionName = trim(
                (string) $editionSheet->getCell('A2')->getValue()
            );

            // Lees het editiejaar.
            $editionYear = (int) $editionSheet->getCell('B2')->getValue();

            // Controleer of deze editie al bestaat binnen de catalogus.
            $editionExists = CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', $editionName)
                ->where('edition_year', $editionYear)
                ->exists();

            if ($editionExists) {
                throw new RuntimeException(
                    'Deze cataloguseditie bestaat al binnen de geselecteerde catalogus.'
                );
            }
        } finally {
            // Maak het spreadsheet vrij uit het geheugen.
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * Importeer een nieuwe cataloguseditie vanuit Excel.
     */
    public function importEdition(
        string $filePath,
        Catalog $catalog
    ): CatalogEdition {
        // Valideer eerst het volledige Excel-uitwisselingsbestand.
        app(CatalogEditionExchangeValidator::class)->validate($filePath);

        // Controleer of het bestand bij de geselecteerde catalogus hoort.
        $this->validateCatalog($filePath, $catalog);

        return DB::transaction(function () use ($filePath, $catalog) {
            // Controleer of de cataloguseditie nog niet bestaat.
            $this->validateEditionDoesNotExist($filePath, $catalog);

            // Open het Excel-bestand.
            $spreadsheet = IOFactory::load($filePath);

            try {
                // Lees het werkblad Editie.
                $editionSheet = $spreadsheet->getSheetByName('Editie');

                // Lees de cataloguseditiegegevens.
                $editionName = trim(
                    (string) $editionSheet->getCell('A2')->getValue()
                );

                $editionYear = (int) $editionSheet->getCell('B2')->getValue();

                $publicationDate = trim(
                    (string) $editionSheet->getCell('C2')->getValue()
                );

                $currency = trim(
                    (string) $editionSheet->getCell('D2')->getValue()
                );

                $notes = trim(
                    (string) $editionSheet->getCell('E2')->getValue()
                );

                // Maak de nieuwe cataloguseditie aan.
                $importedEdition = CatalogEdition::create([
                    'catalog_id' => $catalog->id,
                    'edition_name' => $editionName,
                    'edition_year' => $editionYear,
                    'publication_date' => $publicationDate !== '' ? $publicationDate : null,
                    'currency' => $currency !== '' ? $currency : null,
                    'notes' => $notes !== '' ? $notes : null,
                ]);

                // Koppeling tussen Excel-serie-ID's en lokale database-ID's.
                $seriesIdMap = [];

                // Importeer de landen binnen dezelfde databasetransactie.
                $this->importCountries($spreadsheet);
                $this->importCountryTranslations($spreadsheet);
                $this->importConditions($spreadsheet);
                $this->importConditionTranslations($spreadsheet);
                $this->importConditionRules($spreadsheet, $catalog);
                // Importeer de postzegelseries.
                $this->importStampSeries($spreadsheet, $seriesIdMap);
                // Importeer de vertalingen van de postzegelseries.
                $this->importStampSeriesTranslations($spreadsheet, $seriesIdMap);
                // Importeer de thema's.
                $this->importThemes($spreadsheet);
                // Importeer de vertalingen van de thema's.
                $this->importThemeTranslations($spreadsheet);

                // Koppeling tussen Excel-postzegel-ID's en lokale database-ID's.
                $stampIdMap = [];

                // Koppeling tussen Excel-postzegel-ID's en catalogusvermelding-ID's.
                $catalogEntryIdMap = [];

                // Importeer de postzegels en hun catalogusvermeldingen.
                $this->importStamps(
                    $spreadsheet,
                    $importedEdition,
                    $seriesIdMap,
                    $stampIdMap,
                    $catalogEntryIdMap
                );

                // Importeer bovenliggende postzegels die niet in deze
                // cataloguseditie voorkomen en herstel hun ouder-kindrelaties.
                $this->importParentStamps(
                    $spreadsheet,
                    $stampIdMap
                );

                // Importeer de vertalingen van de postzegels.
                $this->importStampTranslations(
                    $spreadsheet,
                    $stampIdMap
                );

                $this->importStampThemes($spreadsheet, $stampIdMap);

                // Importeer de cataloguswaarden van de postzegels.
                $this->importCatalogValues(
                    $spreadsheet,
                    $catalogEntryIdMap
                );

                // Importeer de cataloguswaarden van de postzegelseries.
                $this->importSeriesCatalogValues(
                    $spreadsheet,
                    $importedEdition,
                    $seriesIdMap
                );

                // Geef de geïmporteerde cataloguseditie terug.
                return $importedEdition;
            } finally {
                // Maak het spreadsheet vrij uit het geheugen.
                $spreadsheet->disconnectWorksheets();
            }
        });
    }

    /**
     * Importeer landen uit het werkblad Landen.
     *
     * Deze methode wordt aangeroepen binnen de bestaande
     * databasetransactie van importEdition().
     */
    private function importCountries(
        Spreadsheet $spreadsheet
    ): void {
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        if ($countriesSheet === null) {
            throw new RuntimeException('Het tabblad Landen ontbreekt.');
        }

        for ($row = 2; $row <= $countriesSheet->getHighestDataRow(); $row++) {
            // Lees de landgegevens.
            $name = trim((string) $countriesSheet->getCell('A'.$row)->getValue());
            $isoCode = trim((string) $countriesSheet->getCell('B'.$row)->getValue());
            $entityType = trim((string) $countriesSheet->getCell('C'.$row)->getValue());
            $startYear = trim((string) $countriesSheet->getCell('D'.$row)->getValue());
            $endYear = trim((string) $countriesSheet->getCell('E'.$row)->getValue());
            $isActive = (int) $countriesSheet->getCell('F'.$row)->getValue();
            $slug = trim((string) $countriesSheet->getCell('G'.$row)->getValue());
            $description = $countriesSheet->getCell('H'.$row)->getValue();

            // Hergebruik een bestaand land met dezelfde slug.
            $existingCountry = Country::query()
                ->where('slug', $slug)
                ->first();

            if ($existingCountry !== null) {
                // Controleer of de identificerende gegevens overeenkomen.
                if (
                    $existingCountry->name !== $name ||
                    ($existingCountry->iso_code ?? '') !== $isoCode
                ) {
                    throw new RuntimeException(
                        'Conflicterende landgegevens voor country_slug '.$slug.
                        ' in tabblad Landen, rij '.$row
                    );
                }

                continue;
            }

            // Voorkom conflicten met bestaande landnamen of ISO-codes.
            $conflict = Country::query()
                ->where('name', $name)
                ->when(
                    $isoCode !== '',
                    fn ($query) => $query->orWhere('iso_code', $isoCode)
                )
                ->exists();

            if ($conflict) {
                throw new RuntimeException(
                    'Landnaam of ISO-code bestaat al voor country_slug '.$slug.
                    ' in tabblad Landen, rij '.$row
                );
            }

            // Maak het nieuwe land aan.
            Country::create([
                'name' => $name,
                'iso_code' => $isoCode !== '' ? $isoCode : null,
                'entity_type' => $entityType,
                'start_year' => $startYear !== '' ? (int) $startYear : null,
                'end_year' => $endYear !== '' ? (int) $endYear : null,
                'is_active' => (bool) $isActive,
                'slug' => $slug,
                'description' => $description !== '' ? $description : null,
            ]);
        }
    }

    /**
     * Importeer landvertalingen uit het werkblad Landvertalingen.
     *
     * Wordt aangeroepen nadat de landen zijn geïmporteerd.
     */
    private function importCountryTranslations(
        Spreadsheet $spreadsheet
    ): void {
        $translationsSheet = $spreadsheet->getSheetByName('Landvertalingen');

        if ($translationsSheet === null) {
            throw new RuntimeException('Het tabblad Landvertalingen ontbreekt.');
        }

        for ($row = 2; $row <= $translationsSheet->getHighestDataRow(); $row++) {
            // Lees de vertaalgegevens.
            $countrySlug = trim((string) $translationsSheet->getCell('A'.$row)->getValue());
            $countryIsoCode = trim((string) $translationsSheet->getCell('B'.$row)->getValue());
            $locale = trim((string) $translationsSheet->getCell('C'.$row)->getValue());
            $name = trim((string) $translationsSheet->getCell('D'.$row)->getValue());
            $description = $translationsSheet->getCell('E'.$row)->getValue();

            // Zoek het bijbehorende land.
            $country = Country::query()
                ->where('slug', $countrySlug)
                ->first();

            if ($country === null) {
                throw new RuntimeException(
                    'Onbekende country_slug '.$countrySlug.
                    ' in tabblad Landvertalingen, rij '.$row
                );
            }

            // Controleer of de ISO-code overeenkomt.
            if (($country->iso_code ?? '') !== $countryIsoCode) {
                throw new RuntimeException(
                    'Conflicterende country_iso_code voor country_slug '.$countrySlug.
                    ' in tabblad Landvertalingen, rij '.$row
                );
            }

            // Controleer of de vertaling al bestaat.
            $existingTranslation = CountryTranslation::query()
                ->where('country_id', $country->id)
                ->where('locale', $locale)
                ->first();

            if ($existingTranslation !== null) {
                // Bestaande vertalingen worden niet overschreven.
                continue;
            }

            // Maak de nieuwe vertaling aan.
            CountryTranslation::create([
                'country_id' => $country->id,
                'locale' => $locale,
                'name' => $name,
                'description' => $description === null || $description === ''
                    ? null
                    : $description,
            ]);
        }
    }

    /**
     * Importeer waarderingscategorieën uit het werkblad Waarderingscategorieen.
     *
     * Bestaande waarderingscategorieën worden niet overschreven.
     */
    private function importConditions(
        Spreadsheet $spreadsheet
    ): void {
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        if ($conditionsSheet === null) {
            throw new RuntimeException('Het tabblad Waarderingscategorieen ontbreekt.');
        }

        for ($row = 2; $row <= $conditionsSheet->getHighestDataRow(); $row++) {
            // Lees de gegevens van de waarderingscategorie.
            $conditionSlug = trim((string) $conditionsSheet->getCell('A'.$row)->getValue());
            $sortOrder = (int) $conditionsSheet->getCell('B'.$row)->getValue();
            $isActive = (int) $conditionsSheet->getCell('C'.$row)->getValue();

            // Controleer of de waarderingscategorie al bestaat.
            $existingCondition = Condition::query()
                ->where('slug', $conditionSlug)
                ->first();

            if ($existingCondition !== null) {
                // Bestaande waarderingscategorieën worden niet overschreven.
                continue;
            }

            // Maak de nieuwe waarderingscategorie aan.
            Condition::create([
                'slug' => $conditionSlug,
                'sort_order' => $sortOrder,
                'is_active' => (bool) $isActive,
            ]);
        }
    }

    /**
     * Importeer waarderingsvertalingen uit het werkblad Waarderingsvertalingen.
     *
     * Wordt aangeroepen nadat de waarderingscategorieën zijn geïmporteerd.
     * Bestaande vertalingen worden niet overschreven.
     */
    private function importConditionTranslations(
        Spreadsheet $spreadsheet
    ): void {
        $translationsSheet = $spreadsheet->getSheetByName('Waarderingsvertalingen');

        if ($translationsSheet === null) {
            throw new RuntimeException('Het tabblad Waarderingsvertalingen ontbreekt.');
        }

        for ($row = 2; $row <= $translationsSheet->getHighestDataRow(); $row++) {
            // Lees de vertaalgegevens.
            $conditionSlug = trim((string) $translationsSheet->getCell('A'.$row)->getValue());
            $locale = trim((string) $translationsSheet->getCell('B'.$row)->getValue());
            $name = trim((string) $translationsSheet->getCell('C'.$row)->getValue());
            $description = $translationsSheet->getCell('D'.$row)->getValue();

            // Zoek de bijbehorende waarderingscategorie.
            $condition = Condition::query()
                ->where('slug', $conditionSlug)
                ->first();

            if ($condition === null) {
                throw new RuntimeException(
                    'Onbekende condition_slug '.$conditionSlug.
                    ' in tabblad Waarderingsvertalingen, rij '.$row
                );
            }

            // Controleer of de vertaling al bestaat.
            $existingTranslation = ConditionTranslation::query()
                ->where('condition_id', $condition->id)
                ->where('locale', $locale)
                ->first();

            if ($existingTranslation !== null) {
                // Bestaande vertalingen worden niet overschreven.
                continue;
            }

            // Maak de nieuwe vertaling aan.
            ConditionTranslation::create([
                'condition_id' => $condition->id,
                'locale' => $locale,
                'name' => $name,
                'description' => $description === null || $description === ''
                    ? null
                    : $description,
            ]);
        }
    }

    /**
     * Importeer cataloguswaarderingsregels uit het werkblad Waarderingsregels.
     *
     * Bestaande, identieke regels worden niet opnieuw aangemaakt.
     * Bestaande regels worden niet overschreven.
     */
    private function importConditionRules(
        Spreadsheet $spreadsheet,
        Catalog $catalog
    ): void {
        $rulesSheet = $spreadsheet->getSheetByName('Waarderingsregels');

        if ($rulesSheet === null) {
            throw new RuntimeException('Het tabblad Waarderingsregels ontbreekt.');
        }

        for ($row = 2; $row <= $rulesSheet->getHighestDataRow(); $row++) {
            // Lees de gegevens uit Excel.
            $countrySlug = trim((string) $rulesSheet->getCell('A'.$row)->getValue());
            $conditionSlug = trim((string) $rulesSheet->getCell('C'.$row)->getValue());

            $issueYearFrom = trim((string) $rulesSheet->getCell('D'.$row)->getValue());
            $issueYearTo = trim((string) $rulesSheet->getCell('E'.$row)->getValue());
            $sortOrder = (int) $rulesSheet->getCell('F'.$row)->getValue();

            // Algemene regels hebben geen land.
            $countryId = null;

            if ($countrySlug !== '') {
                $country = Country::query()
                    ->where('slug', $countrySlug)
                    ->first();

                if ($country === null) {
                    throw new RuntimeException(
                        'Onbekende country_slug '.$countrySlug.
                        ' in tabblad Waarderingsregels, rij '.$row
                    );
                }

                $countryId = $country->id;
            }

            // Zoek de waarderingscategorie.
            $condition = Condition::query()
                ->where('slug', $conditionSlug)
                ->first();

            if ($condition === null) {
                throw new RuntimeException(
                    'Onbekende condition_slug '.$conditionSlug.
                    ' in tabblad Waarderingsregels, rij '.$row
                );
            }

            // Zet lege jaartallen om naar NULL.
            $yearFrom = $issueYearFrom === '' ? null : (int) $issueYearFrom;
            $yearTo = $issueYearTo === '' ? null : (int) $issueYearTo;

            // Controleer of een identieke regel al bestaat.
            $existingRule = CatalogConditionRule::query()
                ->where('catalog_id', $catalog->id)
                ->where('country_id', $countryId)
                ->where('condition_id', $condition->id)
                ->where('issue_year_from', $yearFrom)
                ->where('issue_year_to', $yearTo)
                ->where('sort_order', $sortOrder)
                ->first();

            if ($existingRule !== null) {
                // Een identieke regel hoeft niet opnieuw te worden aangemaakt.
                continue;
            }

            // Maak de ontbrekende waarderingsregel aan.
            CatalogConditionRule::create([
                'catalog_id' => $catalog->id,
                'country_id' => $countryId,
                'condition_id' => $condition->id,
                'issue_year_from' => $yearFrom,
                'issue_year_to' => $yearTo,
                'sort_order' => $sortOrder,
            ]);
        }
    }

    /**
     * Importeer postzegelseries uit het werkblad Postzegelseries.
     *
     * De array koppelt Excel-serie-ID's aan lokale database-ID's.
     *
     * @param  array<int, int>  $seriesIdMap
     */
    private function importStampSeries(
        Spreadsheet $spreadsheet,
        array &$seriesIdMap
    ): void {
        // Controleer of het werkblad Postzegelseries aanwezig is.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        if ($seriesSheet === null) {
            throw new RuntimeException(
                'Het tabblad Postzegelseries ontbreekt.'
            );
        }

        // Doorloop alle postzegelseries vanaf rij 2.
        for ($row = 2; $row <= $seriesSheet->getHighestDataRow(); $row++) {

            // Lees de identificatie van de postzegelserie uit Excel.
            $exchangeSeriesId = (int) $seriesSheet->getCell('A'.$row)->getValue();

            // Lees de landidentificatie.
            $countrySlug = trim(
                (string) $seriesSheet->getCell('B'.$row)->getValue()
            );

            // Zoek het bijbehorende land op basis van de slug.
            $country = Country::query()
                ->where('slug', $countrySlug)
                ->first();

            // Controleer of het land bestaat.
            if ($country === null) {
                throw new RuntimeException(
                    'Onbekend land '.$countrySlug.
                    ' bij postzegelserie op rij '.$row
                );
            }

            // Lees de naam van de postzegelserie.
            $name = trim(
                (string) $seriesSheet->getCell('D'.$row)->getValue()
            );

            // Lees het optionele uitgiftejaar.
            $issueYear = trim(
                (string) $seriesSheet->getCell('E'.$row)->getValue()
            );

            // Lees de optionele beschrijving.
            $description = trim(
                (string) $seriesSheet->getCell('F'.$row)->getValue()
            );

            // Zet een leeg uitgiftejaar om naar null.
            $year = $issueYear === '' ? null : (int) $issueYear;

            // Zet een lege beschrijving om naar null.
            $seriesDescription = $description === '' ? null : $description;

            // Zoek bestaande postzegelseries met dezelfde gegevens.
            $existingSeries = StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', $name)
                ->where('issue_year', $year)
                ->get();

            // Voorkom een onduidelijke koppeling bij meerdere overeenkomsten.
            if ($existingSeries->count() > 1) {
                throw new RuntimeException(
                    'Meerdere bestaande postzegelseries gevonden voor '.
                    $name.' op rij '.$row.' in tabblad Postzegelseries.'
                );
            }

            // Hergebruik een bestaande postzegelserie.
            if ($existingSeries->count() === 1) {
                $series = $existingSeries->first();
            } else {
                // Maak een nieuwe postzegelserie aan.
                $series = StampSeries::create([
                    'country_id' => $country->id,
                    'name' => $name,
                    'issue_year' => $year,
                    'description' => $seriesDescription,
                ]);
            }

            // Koppel het Excel-serie-ID aan het lokale database-ID.
            $seriesIdMap[$exchangeSeriesId] = $series->id;
        }
    }

    /**
     * Importeer postzegelserievertalingen uit het werkblad Serievertalingen.
     *
     * De array koppelt Excel-serie-ID's aan lokale database-ID's.
     *
     * @param  array<int, int>  $seriesIdMap
     */
    private function importStampSeriesTranslations(
        Spreadsheet $spreadsheet,
        array $seriesIdMap
    ): void {
        // Controleer of het werkblad Serievertalingen aanwezig is.
        $translationsSheet = $spreadsheet->getSheetByName('Serievertalingen');

        if ($translationsSheet === null) {
            throw new RuntimeException(
                'Het tabblad Serievertalingen ontbreekt.'
            );
        }

        // Doorloop alle vertalingen vanaf rij 2.
        for ($row = 2; $row <= $translationsSheet->getHighestDataRow(); $row++) {
            // Lees de vertaalgegevens uit Excel.
            $exchangeSeriesId = (int) $translationsSheet->getCell('A'.$row)->getValue();
            $locale = trim((string) $translationsSheet->getCell('B'.$row)->getValue());
            $name = trim((string) $translationsSheet->getCell('C'.$row)->getValue());
            $description = $translationsSheet->getCell('D'.$row)->getValue();

            // Zoek het lokale database-ID van de postzegelserie.
            $seriesId = $seriesIdMap[$exchangeSeriesId] ?? null;

            // Controleer of de postzegelserie tijdens de import is gekoppeld.
            if ($seriesId === null) {
                throw new RuntimeException(
                    'Onbekende exchange_series_id '.$exchangeSeriesId.
                    ' in tabblad Serievertalingen, rij '.$row
                );
            }

            // Controleer of de vertaling al bestaat.
            $existingTranslation = StampSeriesTranslation::query()
                ->where('stamp_series_id', $seriesId)
                ->where('locale', $locale)
                ->first();

            // Bestaande vertalingen worden niet overschreven.
            if ($existingTranslation !== null) {
                continue;
            }

            // Maak de nieuwe postzegelserievertaling aan.
            StampSeriesTranslation::create([
                'stamp_series_id' => $seriesId,
                'locale' => $locale,
                'name' => $name,
                'description' => $description === null || $description === ''
                    ? null
                    : $description,
            ]);
        }
    }

    /**
     * Importeer thema's uit het werkblad Themas.
     *
     * Bestaande thema's worden hergebruikt op basis van hun slug.
     * Bestaande gegevens worden niet overschreven.
     */
    private function importThemes(
        Spreadsheet $spreadsheet
    ): void {
        // Controleer of het werkblad Themas aanwezig is.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        if ($themesSheet === null) {
            throw new RuntimeException(
                'Het tabblad Themas ontbreekt.'
            );
        }

        // Doorloop alle thema's vanaf rij 2.
        for ($row = 2; $row <= $themesSheet->getHighestDataRow(); $row++) {
            // Lees de themagegevens uit Excel.
            $themeSlug = trim((string) $themesSheet->getCell('A'.$row)->getValue());
            $name = trim((string) $themesSheet->getCell('B'.$row)->getValue());
            $description = $themesSheet->getCell('C'.$row)->getValue();

            // Controleer of het thema al bestaat.
            $existingTheme = Theme::query()
                ->where('slug', $themeSlug)
                ->first();

            // Bestaande thema's worden niet overschreven.
            if ($existingTheme !== null) {
                continue;
            }

            // Maak het nieuwe thema aan.
            Theme::create([
                'slug' => $themeSlug,
                'name' => $name,
                'description' => $description === null || $description === ''
                    ? null
                    : $description,
            ]);
        }
    }

    /**
     * Importeer themavertalingen uit het werkblad Themavertalingen.
     *
     * Bestaande vertalingen worden niet overschreven.
     */
    private function importThemeTranslations(
        Spreadsheet $spreadsheet
    ): void {
        // Controleer of het werkblad Themavertalingen aanwezig is.
        $translationsSheet = $spreadsheet->getSheetByName('Themavertalingen');

        if ($translationsSheet === null) {
            throw new RuntimeException(
                'Het tabblad Themavertalingen ontbreekt.'
            );
        }

        // Doorloop alle vertalingen vanaf rij 2.
        for ($row = 2; $row <= $translationsSheet->getHighestDataRow(); $row++) {
            // Lees de vertaalgegevens uit Excel.
            $themeSlug = trim((string) $translationsSheet->getCell('A'.$row)->getValue());
            $locale = trim((string) $translationsSheet->getCell('B'.$row)->getValue());
            $name = trim((string) $translationsSheet->getCell('C'.$row)->getValue());
            $description = $translationsSheet->getCell('D'.$row)->getValue();

            // Zoek het bijbehorende thema op basis van de slug.
            $theme = Theme::query()
                ->where('slug', $themeSlug)
                ->first();

            // Controleer of het thema bestaat.
            if ($theme === null) {
                throw new RuntimeException(
                    'Onbekende theme_slug '.$themeSlug.
                    ' in tabblad Themavertalingen, rij '.$row
                );
            }

            // Controleer of de vertaling al bestaat.
            $existingTranslation = ThemeTranslation::query()
                ->where('theme_id', $theme->id)
                ->where('locale', $locale)
                ->first();

            // Bestaande vertalingen worden niet overschreven.
            if ($existingTranslation !== null) {
                continue;
            }

            // Maak de nieuwe themavertaling aan.
            ThemeTranslation::create([
                'theme_id' => $theme->id,
                'locale' => $locale,
                'name' => $name,
                'description' => $description === null || $description === ''
                    ? null
                    : $description,
            ]);
        }
    }

    /**
     * Importeer de koppelingen tussen postzegels en thema's
     * uit het werkblad Postzegelthemas.
     *
     * Deze methode wordt uitgevoerd binnen de bestaande
     * databasetransactie van importEdition().
     *
     * @param  array<string, int>  $stampIdMap
     */
    private function importStampThemes(
        Spreadsheet $spreadsheet,
        array $stampIdMap
    ): void {
        // Controleer of het werkblad Postzegelthemas aanwezig is.
        $stampThemesSheet = $spreadsheet->getSheetByName('Postzegelthemas');

        if ($stampThemesSheet === null) {
            throw new RuntimeException(
                'Het tabblad Postzegelthemas ontbreekt.'
            );
        }

        // Doorloop alle koppelingen vanaf rij 2.
        for ($row = 2; $row <= $stampThemesSheet->getHighestDataRow(); $row++) {

            // Lees de identificaties uit Excel.
            $exchangeStampId = trim(
                (string) $stampThemesSheet->getCell('A'.$row)->getValue()
            );

            $themeSlug = trim(
                (string) $stampThemesSheet->getCell('B'.$row)->getValue()
            );

            // Zoek het lokale database-ID van de geïmporteerde postzegel.
            $stampId = $stampIdMap[$exchangeStampId] ?? null;

            if ($stampId === null) {
                throw new RuntimeException(
                    'Onbekende exchange_stamp_id '.$exchangeStampId.
                    ' in tabblad Postzegelthemas, rij '.$row
                );
            }

            // Zoek het bijbehorende thema.
            $theme = Theme::query()
                ->where('slug', $themeSlug)
                ->first();

            if ($theme === null) {
                throw new RuntimeException(
                    'Onbekende theme_slug '.$themeSlug.
                    ' in tabblad Postzegelthemas, rij '.$row
                );
            }

            // Zoek de geïmporteerde postzegel.
            $stamp = Stamp::query()->findOrFail($stampId);

            // Leg de koppeling vast zonder bestaande koppelingen te verwijderen.
            $stamp->themes()->syncWithoutDetaching([
                $theme->id,
            ]);
        }
    }

    /**
     * Importeer postzegels uit het werkblad Postzegels.
     *
     * De koppelingen met catalogusvermeldingen en bestaande
     * postzegels worden in volgende stappen uitgewerkt.
     *
     * @param  array<int, int>  $seriesIdMap
     * @param  array<string, int>  $stampIdMap
     * @param  array<string, int>  $catalogEntryIdMap
     */
    private function importStamps(
        Spreadsheet $spreadsheet,
        CatalogEdition $importedEdition,
        array $seriesIdMap,
        array &$stampIdMap,
        array &$catalogEntryIdMap
    ): void {

        // Controleer of het werkblad Postzegels aanwezig is.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        if ($stampsSheet === null) {
            throw new RuntimeException(
                'Het tabblad Postzegels ontbreekt.'
            );
        }

        // Houd bij welke Excel-identificaties al zijn verwerkt.
        $processedExchangeStampIds = [];

        // Bewaar tijdelijk de ouder-kindrelaties uit Excel.
        // Deze worden hersteld nadat alle postzegels zijn geïmporteerd.
        $parentStampReferences = [];

        // Doorloop alle postzegels vanaf rij 2.
        for ($row = 2; $row <= $stampsSheet->getHighestDataRow(); $row++) {

            // Identificatie en catalogusgegevens.
            $exchangeStampId = trim((string) $stampsSheet->getCell('A'.$row)->getValue());
            $parentExchangeStampId = trim((string) $stampsSheet->getCell('B'.$row)->getValue());
            $catalogNumber = trim((string) $stampsSheet->getCell('C'.$row)->getValue());
            $exchangeSeriesId = trim((string) $stampsSheet->getCell('D'.$row)->getValue());

            // Landgegevens.
            $countrySlug = trim((string) $stampsSheet->getCell('E'.$row)->getValue());
            $countryIsoCode = trim((string) $stampsSheet->getCell('F'.$row)->getValue());

            // Algemene postzegelgegevens.
            $name = trim((string) $stampsSheet->getCell('G'.$row)->getValue());
            $description = $stampsSheet->getCell('H'.$row)->getValue();
            $issueYear = $stampsSheet->getCell('I'.$row)->getValue();
            $issueDate = $stampsSheet->getCell('J'.$row)->getValue();
            $faceValue = $stampsSheet->getCell('K'.$row)->getValue();
            $currency = trim((string) $stampsSheet->getCell('L'.$row)->getValue());
            $designer = $stampsSheet->getCell('M'.$row)->getValue();
            $printingMethod = $stampsSheet->getCell('N'.$row)->getValue();
            $catalogReference = $stampsSheet->getCell('O'.$row)->getValue();
            $notes = $stampsSheet->getCell('P'.$row)->getValue();

            // Opmerkingen bij de catalogusvermelding.
            $catalogEntryNotes = $stampsSheet->getCell('Q'.$row)->getValue();

            // Controleer en normaliseer de nominale waarde.
            $faceValue = trim((string) $faceValue);

            if ($faceValue === '') {
                $faceValue = null;
            } elseif (mb_strlen($faceValue) > 50) {
                throw new RuntimeException(
                    'Veld face_value overschrijdt de maximale lengte van 50 tekens'.
                    ' in tabblad Postzegels, rij '.$row
                );
            }

            // Controleer en normaliseer de uitgiftedatum.
            $issueDate = trim((string) $issueDate);

            if ($issueDate === '') {
                $issueDate = null;
            } else {
                $date = \DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $issueDate
                );

                if (
                    $date === false
                    || $date->format('Y-m-d') !== $issueDate
                ) {
                    throw new RuntimeException(
                        'Ongeldige issue_date '.$issueDate.
                        ' in tabblad Postzegels, rij '.$row.
                        '. Verwacht formaat: JJJJ-MM-DD.'
                    );
                }
            }

            // Controleer de maximale lengte van de tekstvelden.
            $textFields = [
                'catalog_number' => [$catalogNumber, 100],
                'currency' => [$currency, 3],
                'designer' => [$designer, 150],
                'printing_method' => [$printingMethod, 100],
                'catalog_reference' => [$catalogReference, 100],
            ];

            foreach ($textFields as $field => [$value, $maxLength]) {
                if (
                    $value !== null
                    && mb_strlen((string) $value) > $maxLength
                ) {
                    throw new RuntimeException(
                        'Veld '.$field.
                        ' overschrijdt de maximale lengte van '.$maxLength.
                        ' tekens in tabblad Postzegels, rij '.$row
                    );
                }
            }

            // Controleer de verplichte identificatie.
            if ($exchangeStampId === '') {
                throw new RuntimeException(
                    'exchange_stamp_id ontbreekt in tabblad Postzegels, rij '.$row
                );
            }

            // Controleer of de Excel-identificatie uniek is.
            if (isset($processedExchangeStampIds[$exchangeStampId])) {
                throw new RuntimeException(
                    'Dubbele exchange_stamp_id '.$exchangeStampId.
                    ' in tabblad Postzegels, rij '.$row.
                    '. Deze identificatie komt al voor op rij '.
                    $processedExchangeStampIds[$exchangeStampId].'.'
                );
            }

            // Registreer de identificatie en het bijbehorende rijnummer.
            $processedExchangeStampIds[$exchangeStampId] = $row;

            // Controleer het catalogusnummer.
            if ($catalogNumber === '') {
                throw new RuntimeException(
                    'catalog_number ontbreekt in tabblad Postzegels, rij '.$row
                );
            }

            // Controleer de naam van de postzegel.
            if ($name === '' || mb_strlen($name) > 200) {
                throw new RuntimeException(
                    'Ongeldige name in tabblad Postzegels, rij '.$row
                );
            }

            // Controleer het uitgiftejaar.
            if (
                ! is_numeric($issueYear)
                || filter_var(
                    $issueYear,
                    FILTER_VALIDATE_INT,
                    [
                        'options' => [
                            'min_range' => 1840,
                            'max_range' => 2200,
                        ],
                    ]
                ) === false
            ) {
                throw new RuntimeException(
                    'Ongeldig issue_year in tabblad Postzegels, rij '.$row
                );
            }

            // Zoek het land op basis van de slug.
            $country = Country::query()
                ->where('slug', $countrySlug)
                ->first();

            // Controleer of het land bestaat.
            if ($country === null) {
                throw new RuntimeException(
                    'Onbekende country_slug '.$countrySlug.
                    ' in tabblad Postzegels, rij '.$row
                );
            }

            // Een postzegel hoeft niet bij een serie te horen.
            $stampSeriesId = null;

            // Zoek de geïmporteerde serie op via de Excel-identificatie.
            if ($exchangeSeriesId !== '') {
                if (! array_key_exists($exchangeSeriesId, $seriesIdMap)) {
                    throw new RuntimeException(
                        'Onbekende exchange_series_id '.$exchangeSeriesId.
                        ' in tabblad Postzegels, rij '.$row
                    );
                }

                $stampSeriesId = $seriesIdMap[$exchangeSeriesId];

                // Controleer of de postzegelserie bij het land hoort.
                $stampSeries = StampSeries::query()
                    ->whereKey($stampSeriesId)
                    ->where('country_id', $country->id)
                    ->first();

                if ($stampSeries === null) {
                    throw new RuntimeException(
                        'Postzegelserie '.$exchangeSeriesId.
                        ' hoort niet bij country_slug '.$countrySlug.
                        ' in tabblad Postzegels, rij '.$row
                    );
                }
            }

            // Maak de nieuwe postzegel aan.
            $stamp = Stamp::create([
                'country_id' => $country->id,
                'parent_stamp_id' => null,
                'stamp_series_id' => $stampSeriesId,
                'name' => $name,
                'description' => $description === null || $description === ''
                    ? null
                    : $description,
                'issue_year' => (int) $issueYear,
                'issue_date' => $issueDate,
                'face_value' => $faceValue,
                'currency' => $currency !== '' ? $currency : null,
                'designer' => $designer === null || $designer === ''
                    ? null
                    : $designer,
                'printing_method' => $printingMethod === null || $printingMethod === ''
                    ? null
                    : $printingMethod,
                'catalog_reference' => $catalogReference === null || $catalogReference === ''
                    ? null
                    : $catalogReference,
                'notes' => $notes === null || $notes === ''
                    ? null
                    : $notes,
            ]);

            // Bewaar de koppeling tussen de Excel-identificatie
            // en de database-ID van de nieuwe postzegel.
            $stampIdMap[$exchangeStampId] = $stamp->id;

            // Bewaar de verwijzing naar de bovenliggende postzegel.
            // De database-ID van de ouder is mogelijk nog niet bekend.
            if ($parentExchangeStampId !== '') {
                $parentStampReferences[$exchangeStampId] = $parentExchangeStampId;
            }

            // Maak de catalogusvermelding voor deze postzegel aan.
            $catalogEntry = $stamp->catalogEntries()->create([
                'catalog_edition_id' => $importedEdition->id,
                'catalog_number' => $catalogNumber,
                'notes' => $catalogEntryNotes === null || $catalogEntryNotes === ''
                    ? null
                    : $catalogEntryNotes,
            ]);

            // Bewaar de koppeling tussen de Excel-identificatie
            // en de database-ID van de nieuwe catalogusvermelding.
            $catalogEntryIdMap[$exchangeStampId] = $catalogEntry->id;
        }

        // Herstel de ouder-kindrelaties nadat alle postzegels zijn aangemaakt.
        foreach ($parentStampReferences as $childExchangeId => $parentExchangeId) {

            // Zoek de nieuwe database-ID's van de variant en de hoofdpostzegel.
            $childStampId = $stampIdMap[$childExchangeId] ?? null;
            $parentStampId = $stampIdMap[$parentExchangeId] ?? null;

            // Beide postzegels moeten succesvol zijn geïmporteerd.
            if ($childStampId === null || $parentStampId === null) {
                throw new RuntimeException(
                    'Kan ouder-kindrelatie niet herstellen voor exchange_stamp_id '
                    .$childExchangeId
                );
            }

            // Koppel de variant aan de opnieuw geïmporteerde hoofdpostzegel.
            Stamp::query()
                ->whereKey($childStampId)
                ->update([
                    'parent_stamp_id' => $parentStampId,
                ]);
        }
    }

    /**
     * Importeer bovenliggende postzegels die niet in de cataloguseditie staan.
     *
     * Maak iedere unieke hoofdpostzegel één keer aan en koppel
     * de bijbehorende varianten aan deze hoofdpostzegel.
     *
     * Deze methode wordt uitgevoerd binnen de bestaande databasetransactie.
     *
     * @param  array<string, int>  $stampIdMap
     */
    private function importParentStamps(
        Spreadsheet $spreadsheet,
        array &$stampIdMap
    ): void {
        $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

        if ($parentStampsSheet === null) {
            throw new RuntimeException(
                'Het tabblad BovenliggendePostzegels ontbreekt.'
            );
        }

        // Koppeling tussen Excel-hoofdpostzegel-ID's en nieuwe database-ID's.
        $parentIdMap = [];

        for ($row = 2; $row <= $parentStampsSheet->getHighestDataRow(); $row++) {
            $parentExchangeId = trim((string) $parentStampsSheet->getCell('A'.$row)->getValue());
            $childExchangeId = trim((string) $parentStampsSheet->getCell('B'.$row)->getValue());

            $countrySlug = trim((string) $parentStampsSheet->getCell('C'.$row)->getValue());
            $countryIsoCode = trim((string) $parentStampsSheet->getCell('D'.$row)->getValue());

            $name = trim((string) $parentStampsSheet->getCell('E'.$row)->getValue());
            $description = $parentStampsSheet->getCell('F'.$row)->getValue();
            $issueYear = $parentStampsSheet->getCell('G'.$row)->getValue();
            $issueDate = trim((string) $parentStampsSheet->getCell('H'.$row)->getValue());
            $faceValue = trim((string) $parentStampsSheet->getCell('I'.$row)->getValue());
            $currency = trim((string) $parentStampsSheet->getCell('J'.$row)->getValue());
            $designer = $parentStampsSheet->getCell('K'.$row)->getValue();
            $printingMethod = $parentStampsSheet->getCell('L'.$row)->getValue();
            $catalogReference = $parentStampsSheet->getCell('M'.$row)->getValue();
            $notes = $parentStampsSheet->getCell('N'.$row)->getValue();

            // Controleer of de variant succesvol is geïmporteerd.
            $childStampId = $stampIdMap[$childExchangeId] ?? null;

            if ($childStampId === null) {
                throw new RuntimeException(
                    'Onbekende child_exchange_stamp_id '.$childExchangeId.
                    ' in tabblad BovenliggendePostzegels, rij '.$row
                );
            }

            // Maak dezelfde hoofdpostzegel niet meerdere keren aan.
            if (! isset($parentIdMap[$parentExchangeId])) {
                $country = Country::query()
                    ->where('slug', $countrySlug)
                    ->first();

                if ($country === null) {
                    throw new RuntimeException(
                        'Onbekende country_slug '.$countrySlug.
                        ' in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                if (($country->iso_code ?? '') !== $countryIsoCode) {
                    throw new RuntimeException(
                        'Conflicterende country_iso_code voor country_slug '.$countrySlug.
                        ' in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                if ($name === '' || mb_strlen($name) > 200) {
                    throw new RuntimeException(
                        'Ongeldige name in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                if (
                    filter_var(
                        $issueYear,
                        FILTER_VALIDATE_INT,
                        [
                            'options' => [
                                'min_range' => 1840,
                                'max_range' => 2200,
                            ],
                        ]
                    ) === false
                ) {
                    throw new RuntimeException(
                        'Ongeldig issue_year in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                if ($issueDate !== '') {
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $issueDate);

                    if ($date === false || $date->format('Y-m-d') !== $issueDate) {
                        throw new RuntimeException(
                            'Ongeldige issue_date '.$issueDate.
                            ' in tabblad BovenliggendePostzegels, rij '.$row
                        );
                    }
                }

                if (mb_strlen($faceValue) > 50) {
                    throw new RuntimeException(
                        'Veld face_value overschrijdt de maximale lengte van 50 tekens'.
                        ' in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                $textFields = [
                    'currency' => [$currency, 3],
                    'designer' => [$designer, 150],
                    'printing_method' => [$printingMethod, 100],
                    'catalog_reference' => [$catalogReference, 100],
                ];

                foreach ($textFields as $field => [$value, $maxLength]) {
                    if ($value !== null && mb_strlen((string) $value) > $maxLength) {
                        throw new RuntimeException(
                            'Veld '.$field.
                            ' overschrijdt de maximale lengte van '.$maxLength.
                            ' tekens in tabblad BovenliggendePostzegels, rij '.$row
                        );
                    }
                }

                // Maak de hoofdpostzegel aan zonder catalogusvermelding.
                $parentStamp = Stamp::create([
                    'country_id' => $country->id,
                    'parent_stamp_id' => null,
                    'name' => $name,
                    'description' => $description === null || $description === '' ? null : $description,
                    'issue_year' => (int) $issueYear,
                    'issue_date' => $issueDate !== '' ? $issueDate : null,
                    'face_value' => $faceValue !== '' ? $faceValue : null,
                    'currency' => $currency !== '' ? $currency : null,
                    'designer' => $designer === null || $designer === '' ? null : $designer,
                    'printing_method' => $printingMethod === null || $printingMethod === '' ? null : $printingMethod,
                    'catalog_reference' => $catalogReference === null || $catalogReference === '' ? null : $catalogReference,
                    'notes' => $notes === null || $notes === '' ? null : $notes,
                ]);

                $parentIdMap[$parentExchangeId] = $parentStamp->id;

                // Maak de hoofdpostzegel ook beschikbaar voor de vertaalimport.
                $stampIdMap[$parentExchangeId] = $parentStamp->id;
            }

            // Koppel de variant aan de juiste hoofdpostzegel.
            Stamp::query()
                ->whereKey($childStampId)
                ->update([
                    'parent_stamp_id' => $parentIdMap[$parentExchangeId],
                ]);
        }
    }

    /**
     * Importeer postzegelvertalingen uit het werkblad Postzegelvertalingen.
     *
     * De vertalingen worden gekoppeld aan de nieuw geïmporteerde postzegels
     * via de Excel-identificaties in $stampIdMap.
     *
     * Deze methode wordt uitgevoerd binnen de bestaande databasetransactie.
     *
     * @param  array<string, int>  $stampIdMap
     */
    private function importStampTranslations(
        Spreadsheet $spreadsheet,
        array $stampIdMap
    ): void {
        // Controleer of het werkblad Postzegelvertalingen aanwezig is.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        if ($translationsSheet === null) {
            throw new RuntimeException(
                'Het tabblad Postzegelvertalingen ontbreekt.'
            );
        }

        // Doorloop alle vertalingen vanaf rij 2.
        for ($row = 2; $row <= $translationsSheet->getHighestDataRow(); $row++) {

            // Lees de vertaalgegevens uit Excel.
            $exchangeStampId = trim(
                (string) $translationsSheet->getCell('A'.$row)->getValue()
            );

            $locale = trim(
                (string) $translationsSheet->getCell('C'.$row)->getValue()
            );

            $name = trim(
                (string) $translationsSheet->getCell('D'.$row)->getValue()
            );

            $description = $translationsSheet->getCell('E'.$row)->getValue();

            // Zoek het lokale database-ID van de geïmporteerde postzegel.
            $stampId = $stampIdMap[$exchangeStampId] ?? null;

            // Controleer of de postzegel tijdens de import is gekoppeld.
            if ($stampId === null) {
                throw new RuntimeException(
                    'Onbekende exchange_stamp_id '.$exchangeStampId.
                    ' in tabblad Postzegelvertalingen, rij '.$row
                );
            }

            // Controleer of de vertaling al bestaat.
            $existingTranslation = StampTranslation::query()
                ->where('stamp_id', $stampId)
                ->where('locale', $locale)
                ->first();

            // Bestaande vertalingen worden niet overschreven.
            if ($existingTranslation !== null) {
                continue;
            }

            // Maak de nieuwe postzegelvertaling aan.
            StampTranslation::create([
                'stamp_id' => $stampId,
                'locale' => $locale,
                'name' => $name,
                'description' => $description === null || $description === ''
                    ? null
                    : $description,
            ]);
        }
    }

    /**
     * Importeer cataloguswaarden uit het werkblad Cataloguswaarden.
     *
     * De cataloguswaarden worden gekoppeld aan de nieuw geïmporteerde
     * catalogusvermeldingen via de Excel-identificaties.
     *
     * Deze methode wordt uitgevoerd binnen de bestaande databasetransactie.
     *
     * @param  array<string, int>  $catalogEntryIdMap
     */
    private function importCatalogValues(
        Spreadsheet $spreadsheet,
        array $catalogEntryIdMap
    ): void {
        // Controleer of het werkblad Cataloguswaarden aanwezig is.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        if ($catalogValuesSheet === null) {
            throw new RuntimeException(
                'Het tabblad Cataloguswaarden ontbreekt.'
            );
        }

        // Doorloop alle cataloguswaarden vanaf rij 2.
        for ($row = 2; $row <= $catalogValuesSheet->getHighestDataRow(); $row++) {

            // Lees de identificatie van de catalogusvermelding.
            $exchangeStampId = trim(
                (string) $catalogValuesSheet->getCell('A'.$row)->getValue()
            );

            // Lees de waarderingscategorie.
            $conditionSlug = trim(
                (string) $catalogValuesSheet->getCell('C'.$row)->getValue()
            );

            // Lees de cataloguswaarde.
            $catalogValue = trim(
                (string) $catalogValuesSheet->getCell('D'.$row)->getValue()
            );

            // Lees de valutacode.
            $currency = trim(
                (string) $catalogValuesSheet->getCell('E'.$row)->getValue()
            );

            // Lees de opmerkingen.
            $notes = $catalogValuesSheet->getCell('F'.$row)->getValue();

            // Lees de indicator voor handmatige controle.
            $needsReview = $catalogValuesSheet->getCell('G'.$row)->getValue();

            // Zoek het lokale database-ID van de catalogusvermelding.
            $catalogEntryId = $catalogEntryIdMap[$exchangeStampId] ?? null;

            // Controleer of de catalogusvermelding tijdens de import is gekoppeld.
            if ($catalogEntryId === null) {
                throw new RuntimeException(
                    'Onbekende exchange_stamp_id '.$exchangeStampId.
                    ' in tabblad Cataloguswaarden, rij '.$row
                );
            }

            // Zoek de waarderingscategorie op basis van de slug.
            $condition = Condition::query()
                ->where('slug', $conditionSlug)
                ->first();

            // Controleer of de waarderingscategorie bestaat.
            if ($condition === null) {
                throw new RuntimeException(
                    'Onbekende condition_slug '.$conditionSlug.
                    ' in tabblad Cataloguswaarden, rij '.$row
                );
            }

            // Maak de cataloguswaarde aan.
            StampCatalogValue::create([
                'stamp_catalog_entry_id' => $catalogEntryId,
                'condition_id' => $condition->id,
                'catalog_value' => $catalogValue !== '' ? $catalogValue : null,
                'currency' => $currency !== '' ? $currency : null,
                'notes' => $notes === null || $notes === ''
                    ? null
                    : $notes,
                'needs_review' => (bool) $needsReview,
            ]);
        }
    }

    /**
     * Importeer seriecataloguswaarden uit het werkblad Seriecataloguswaarden.
     *
     * De Excel-serie-ID's worden gekoppeld aan de lokale database-ID's.
     *
     * Deze methode wordt uitgevoerd binnen de bestaande databasetransactie.
     *
     * @param  array<int, int>  $seriesIdMap
     */
    private function importSeriesCatalogValues(
        Spreadsheet $spreadsheet,
        CatalogEdition $edition,
        array $seriesIdMap
    ): void {
        // Controleer of het werkblad Seriecataloguswaarden aanwezig is.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        if ($seriesValuesSheet === null) {
            throw new RuntimeException(
                'Het tabblad Seriecataloguswaarden ontbreekt.'
            );
        }

        // Doorloop alle seriecataloguswaarden vanaf rij 2.
        for ($row = 2; $row <= $seriesValuesSheet->getHighestDataRow(); $row++) {

            // Lees de identificatie van de postzegelserie.
            $exchangeSeriesId = (int) $seriesValuesSheet->getCell('A'.$row)->getValue();

            // Zoek het lokale database-ID van de postzegelserie.
            $seriesId = $seriesIdMap[$exchangeSeriesId] ?? null;

            if ($seriesId === null) {
                throw new RuntimeException(
                    'Onbekende exchange_series_id '.$exchangeSeriesId.
                    ' in tabblad Seriecataloguswaarden, rij '.$row
                );
            }

            // Lees de optionele waarderingscategorie.
            $conditionSlug = trim(
                (string) $seriesValuesSheet->getCell('B'.$row)->getValue()
            );

            // Zoek de waarderingscategorie op wanneer deze is ingevuld.
            $conditionId = null;

            if ($conditionSlug !== '') {
                $condition = Condition::query()
                    ->where('slug', $conditionSlug)
                    ->first();

                if ($condition === null) {
                    throw new RuntimeException(
                        'Onbekende condition_slug '.$conditionSlug.
                        ' in tabblad Seriecataloguswaarden, rij '.$row
                    );
                }

                $conditionId = $condition->id;
            }

            // Lees het optionele catalogusnummer.
            $catalogNumber = trim(
                (string) $seriesValuesSheet->getCell('C'.$row)->getValue()
            );

            // Lees de optionele cataloguswaarde.
            $catalogValue = trim(
                (string) $seriesValuesSheet->getCell('D'.$row)->getValue()
            );

            // Lees de optionele valutacode.
            $currency = trim(
                (string) $seriesValuesSheet->getCell('E'.$row)->getValue()
            );

            // Lees de optionele opmerkingen.
            $notes = $seriesValuesSheet->getCell('F'.$row)->getValue();

            // Maak de seriecataloguswaarde aan.
            SeriesCatalogValue::create([
                'catalog_edition_id' => $edition->id,
                'stamp_series_id' => $seriesId,
                'condition_id' => $conditionId,
                'catalog_number' => $catalogNumber !== '' ? $catalogNumber : null,
                'catalog_value' => $catalogValue !== '' ? $catalogValue : null,
                'currency' => $currency !== '' ? $currency : null,
                'notes' => $notes === null || $notes === ''
                    ? null
                    : $notes,
            ]);
        }
    }
}
