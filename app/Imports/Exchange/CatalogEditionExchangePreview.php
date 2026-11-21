<?php

namespace App\Imports\Exchange;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class CatalogEditionExchangePreview
{
    /**
     * Verzamel gegevens voor de voorbeeldweergave
     * zonder wijzigingen in de database aan te brengen.
     *
     * @return array<string, mixed>
     */
    public function getPreview(string $filePath): array
    {
        // Controleer het Excel-bestand met de bestaande validator.
        app(CatalogEditionExchangeValidator::class)->validate($filePath);

        // Open het gevalideerde Excel-bestand.
        try {
            $reader = IOFactory::createReaderForFile($filePath);

            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($filePath);
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Het Excel-bestand kan niet worden geopend.',
                previous: $exception
            );
        }

        try {
            // Haal de werkbladen met catalogus- en editiegegevens op.
            $catalogSheet = $spreadsheet->getSheetByName('Catalogus');
            $editionSheet = $spreadsheet->getSheetByName('Editie');

            // Lees de catalogusnaam.
            $catalogName = trim(
                (string) $catalogSheet->getCell('A2')->getValue()
            );

            // Lees de naam van de cataloguseditie.
            $editionName = trim(
                (string) $editionSheet->getCell('A2')->getValue()
            );

            // Lees het editiejaar.
            $editionYear = (int) $editionSheet->getCell('B2')->getValue();

            // Haal het werkblad met landen en gebieden op.
            $countriesSheet = $spreadsheet->getSheetByName('Landen');

            // Tel de gegevensrijen, zonder de kolomkoppen mee te tellen.
            $countryCount = max(0, $countriesSheet->getHighestDataRow() - 1);

            // Haal het werkblad met postzegelseries op.
            $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

            // Tel de postzegelseries, zonder de kolomkoppen mee te tellen.
            $seriesCount = max(0, $seriesSheet->getHighestDataRow() - 1);

            // Haal het werkblad met postzegels op.
            $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

            // Tel de postzegels, zonder de kolomkoppen mee te tellen.
            $stampCount = max(0, $stampsSheet->getHighestDataRow() - 1);

            // Haal het werkblad met thema's op.
            $themesSheet = $spreadsheet->getSheetByName('Themas');

            // Tel de thema's, zonder de kolomkoppen mee te tellen.
            $themeCount = max(0, $themesSheet->getHighestDataRow() - 1);

            // Geef de gegevens terug voor de voorbeeldweergave.
            return [
                'catalog_name' => $catalogName,
                'edition_name' => $editionName,
                'edition_year' => $editionYear,
                'country_count' => $countryCount,
                'series_count' => $seriesCount,
                'stamp_count' => $stampCount,
                'theme_count' => $themeCount,
            ];
        } finally {
            // Maak het spreadsheet vrij uit het geheugen.
            $spreadsheet->disconnectWorksheets();
        }
    }
}
