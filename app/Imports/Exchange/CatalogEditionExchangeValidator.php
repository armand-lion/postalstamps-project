<?php

namespace App\Imports\Exchange;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class CatalogEditionExchangeValidator
{
    private const REQUIRED_SHEETS = [
        'Metadata',
        'Catalogus',
        'Editie',
        'Landen',
        'Landvertalingen',
        'Waarderingsregels',
        'Postzegelseries',
        'Serievertalingen',
        'Postzegels',
        'BovenliggendePostzegels',
        'Postzegelvertalingen',
        'Cataloguswaarden',
        'Themas',
        'Themavertalingen',
        'Postzegelthemas',
        'Waarderingscategorieen',
        'Waarderingsvertalingen',
        'Seriecataloguswaarden',
    ];

    public function validate(string $filePath): void
    {
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
            $sheetNames = $spreadsheet->getSheetNames();

            $missingSheets = array_diff(
                self::REQUIRED_SHEETS,
                $sheetNames
            );

            if ($missingSheets !== []) {
                throw new RuntimeException(
                    'De volgende verplichte tabbladen ontbreken: '
                    .implode(', ', $missingSheets)
                );
            }

            // Controleer het uitwisselingsformaat en de formaatversie.
            $metadata = $spreadsheet->getSheetByName('Metadata');

            $format = trim((string) $metadata->getCell('B2')->getValue());
            $version = trim((string) $metadata->getCell('B3')->getValue());

            if ($format !== 'postalstamps-catalog-exchange') {
                throw new RuntimeException(
                    'Ongeldig Excel-uitwisselingsformaat.'
                );
            }

            if ($version !== '1.1') {
                throw new RuntimeException(
                    'Niet-ondersteunde Excel-formaatversie: '.$version
                );
            }

            // Controleer de kolomnamen en hun volgorde.
            foreach (CatalogEditionExchangeSchema::HEADERS as $sheetName => $expectedHeaders) {
                $sheet = $spreadsheet->getSheetByName($sheetName);

                $columnCount = count($expectedHeaders);

                $actualHeaders = $sheet->rangeToArray(
                    'A1:'.Coordinate::stringFromColumnIndex($columnCount).'1',
                    null,
                    false,
                    false
                )[0];

                if ($actualHeaders !== $expectedHeaders) {
                    throw new RuntimeException(
                        'Ongeldige kolomstructuur in tabblad: '.$sheetName
                    );
                }

                if ($sheet->getHighestDataColumn() !==
                    Coordinate::stringFromColumnIndex($columnCount)) {
                    throw new RuntimeException(
                        'Onverwacht aantal kolommen in tabblad: '.$sheetName
                    );
                }
            }

            // Controleer de catalogusgegevens.
            $catalogSheet = $spreadsheet->getSheetByName('Catalogus');

            // Het werkblad Catalogus mag maximaal één gegevensrij bevatten.
            if ($catalogSheet->getHighestDataRow() > 2) {
                throw new RuntimeException(
                    'Meerdere gegevensrijen in tabblad Catalogus zijn niet toegestaan'
                );
            }

            // Lees de catalogusnaam uit cel A2.
            $catalogName = trim(
                (string) $catalogSheet->getCell('A2')->getValue()
            );

            // De catalogusnaam is verplicht.
            if ($catalogName === '') {
                throw new RuntimeException(
                    'Ontbrekende name in tabblad Catalogus, rij 2'
                );
            }

            // De catalogusnaam mag maximaal 150 tekens bevatten.
            if (mb_strlen($catalogName) > 150) {
                throw new RuntimeException(
                    'name is langer dan 150 tekens in tabblad Catalogus, rij 2'
                );
            }

            // Lees de optionele catalogusafkorting uit cel B2.
            $catalogAbbreviation = trim(
                (string) $catalogSheet->getCell('B2')->getValue()
            );

            // De catalogusafkorting mag maximaal 30 tekens bevatten.
            if (mb_strlen($catalogAbbreviation) > 30) {
                throw new RuntimeException(
                    'abbreviation is langer dan 30 tekens in tabblad Catalogus, rij 2'
                );
            }

            // Lees de optionele uitgever uit cel C2.
            $catalogPublisher = trim(
                (string) $catalogSheet->getCell('C2')->getValue()
            );

            // De uitgever mag maximaal 150 tekens bevatten.
            if (mb_strlen($catalogPublisher) > 150) {
                throw new RuntimeException(
                    'publisher is langer dan 150 tekens in tabblad Catalogus, rij 2'
                );
            }

            // Lees de optionele website uit cel D2.
            $catalogWebsite = trim(
                (string) $catalogSheet->getCell('D2')->getValue()
            );

            // De website mag maximaal 255 tekens bevatten.
            if (mb_strlen($catalogWebsite) > 255) {
                throw new RuntimeException(
                    'website is langer dan 255 tekens in tabblad Catalogus, rij 2'
                );
            }

            // Een ingevulde website moet een geldige URL zijn.
            if (
                $catalogWebsite !== ''
                && filter_var($catalogWebsite, FILTER_VALIDATE_URL) === false
            ) {
                throw new RuntimeException(
                    'Ongeldige website in tabblad Catalogus, rij 2'
                );
            }

            // Lees de optionele catalogusbeschrijving uit cel E2.
            $catalogDescription = $catalogSheet->getCell('E2')->getValue();

            // De catalogusbeschrijving moet tekst zijn wanneer deze is ingevuld.
            if (
                $catalogDescription !== null
                && $catalogDescription !== ''
                && ! is_string($catalogDescription)
            ) {
                throw new RuntimeException(
                    'Ongeldige description in tabblad Catalogus, rij 2'
                );
            }

            // Controleer de verplichte naam van de cataloguseditie.
            $editionSheet = $spreadsheet->getSheetByName('Editie');

            // Het werkblad Editie mag maximaal één gegevensrij bevatten.
            if ($editionSheet->getHighestDataRow() > 2) {
                throw new RuntimeException(
                    'Meerdere gegevensrijen in tabblad Editie zijn niet toegestaan'
                );
            }

            // Lees de naam van de cataloguseditie uit rij 2.
            $editionName = trim(
                (string) $editionSheet->getCell('A2')->getValue()
            );

            // De naam van de cataloguseditie is verplicht.
            if ($editionName === '') {
                throw new RuntimeException(
                    'Ontbrekende edition_name in tabblad Editie, rij 2'
                );
            }

            // De naam van de cataloguseditie mag maximaal 150 tekens bevatten.
            if (mb_strlen($editionName) > 150) {
                throw new RuntimeException(
                    'edition_name is langer dan 150 tekens in tabblad Editie, rij 2'
                );
            }

            // Lees het editiejaar uit cel B2.
            $editionYear = trim(
                (string) $editionSheet->getCell('B2')->getValue()
            );

            // Het editiejaar is verplicht.
            if ($editionYear === '') {
                throw new RuntimeException(
                    'Ontbrekende edition_year in tabblad Editie, rij 2'
                );
            }

            // Het editiejaar moet een geheel getal zijn.
            if (! ctype_digit($editionYear)) {
                throw new RuntimeException(
                    'Ongeldige edition_year in tabblad Editie, rij 2'
                );
            }

            // Het editiejaar mag maximaal 65535 zijn.
            if (strlen($editionYear) > 5 || (int) $editionYear > 65535) {
                throw new RuntimeException(
                    'edition_year is hoger dan 65535 in tabblad Editie, rij 2'
                );
            }

            // Lees de publicatiedatum uit cel C2.
            $publicationDate = trim(
                (string) $editionSheet->getCell('C2')->getValue()
            );

            // De publicatiedatum is optioneel.
            // Als deze is ingevuld, moet het een geldige datum zijn in YYYY-MM-DD.
            if ($publicationDate !== '') {
                $date = \DateTimeImmutable::createFromFormat(
                    '!Y-m-d',
                    $publicationDate
                );

                $dateErrors = \DateTimeImmutable::getLastErrors();

                if (
                    ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $publicationDate)
                    || $date === false
                    || (
                        $dateErrors !== false
                        && (
                            $dateErrors['warning_count'] > 0
                            || $dateErrors['error_count'] > 0
                        )
                    )
                ) {
                    throw new RuntimeException(
                        'Ongeldige publication_date in tabblad Editie, rij 2'
                    );
                }
            }

            // Lees de optionele valutacode uit cel D2.
            $editionCurrency = trim(
                (string) $editionSheet->getCell('D2')->getValue()
            );

            // Een ingevulde valutacode moet uit precies drie hoofdletters bestaan.
            if (
                $editionCurrency !== ''
                && ! preg_match('/^[A-Z]{3}$/', $editionCurrency)
            ) {
                throw new RuntimeException(
                    'Ongeldige currency in tabblad Editie, rij 2'
                );
            }

            // Lees de optionele opmerkingen uit cel E2.
            $editionNotes = $editionSheet->getCell('E2')->getValue();

            // Ingevulde opmerkingen moeten tekst zijn.
            if (
                $editionNotes !== null
                && ! is_string($editionNotes)
            ) {
                throw new RuntimeException(
                    'Ongeldige notes in tabblad Editie, rij 2'
                );
            }

            // Controleer ontbrekende en dubbele postzegelidentificaties.
            $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

            $exchangeStampIds = [];
            $stampCatalogNumbers = [];
            $stampParentReferences = [];

            for ($row = 2; $row <= $stampsSheet->getHighestDataRow(); $row++) {
                $exchangeStampId = trim(
                    (string) $stampsSheet->getCell('A'.$row)->getValue()
                );

                if ($exchangeStampId === '') {
                    throw new RuntimeException(
                        'Ontbrekende exchange_stamp_id in tabblad Postzegels, rij '.$row
                    );
                }

                if (isset($exchangeStampIds[$exchangeStampId])) {
                    throw new RuntimeException(
                        'Dubbele exchange_stamp_id in tabblad Postzegels, rij '.$row
                    );
                }

                $exchangeStampIds[$exchangeStampId] = true;

                // Controleer de verplichte naam van de postzegel.
                $stampName = trim(
                    (string) $stampsSheet->getCell('G'.$row)->getValue()
                );

                if ($stampName === '') {
                    throw new RuntimeException(
                        'Ontbrekende name in tabblad Postzegels, rij '.$row
                    );
                }

                // De postzegelnaam mag maximaal 200 tekens bevatten.
                if (mb_strlen($stampName) > 200) {
                    throw new RuntimeException(
                        'name is langer dan 200 tekens in tabblad Postzegels, rij '.$row
                    );
                }

                // Controleer het verplichte uitgiftejaar van de postzegel.
                $stampIssueYear = trim(
                    (string) $stampsSheet->getCell('I'.$row)->getValue()
                );

                if ($stampIssueYear === '') {
                    throw new RuntimeException(
                        'Ontbrekende issue_year in tabblad Postzegels, rij '.$row
                    );
                }

                // Controleer of het uitgiftejaar een geldig geheel getal is.
                if (! ctype_digit($stampIssueYear)) {
                    throw new RuntimeException(
                        'Ongeldige issue_year in tabblad Postzegels, rij '.$row
                    );
                }

                // Het uitgiftejaar mag niet hoger zijn dan 65535.
                if (
                    strlen($stampIssueYear) > 5 ||
                    (int) $stampIssueYear > 65535
                ) {
                    throw new RuntimeException(
                        'issue_year is hoger dan 65535 in tabblad Postzegels, rij '.$row
                    );
                }

                // Lees de optionele uitgiftedatum van de postzegel.
                $stampIssueDate = trim(
                    (string) $stampsSheet->getCell('J'.$row)->getValue()
                );

                // Een ingevulde uitgiftedatum moet geldig zijn in YYYY-MM-DD.
                if ($stampIssueDate !== '') {
                    $date = \DateTimeImmutable::createFromFormat(
                        '!Y-m-d',
                        $stampIssueDate
                    );

                    $dateErrors = \DateTimeImmutable::getLastErrors();

                    if (
                        ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $stampIssueDate)
                        || $date === false
                        || (
                            $dateErrors !== false
                            && (
                                $dateErrors['warning_count'] > 0
                                || $dateErrors['error_count'] > 0
                            )
                        )
                    ) {
                        throw new RuntimeException(
                            'Ongeldige issue_date in tabblad Postzegels, rij '.$row
                        );
                    }
                }

                // Lees de optionele nominale waarde als tekst.
                // Historische waarden zoals 7½, 12½ en 1½ zijn toegestaan.
                $stampFaceValue = trim(
                    (string) $stampsSheet->getCell('K'.$row)->getValue()
                );

                // De nominale waarde mag maximaal 50 tekens bevatten.
                if (mb_strlen($stampFaceValue) > 50) {
                    throw new RuntimeException(
                        'face_value is langer dan 50 tekens in tabblad Postzegels, rij '.$row
                    );
                }

                // Bewaar het catalogusnummer van deze postzegel.
                $stampCatalogNumbers[$exchangeStampId] = trim(
                    (string) $stampsSheet->getCell('C'.$row)->getValue()
                );

                // Bewaar eventuele verwijzingen naar bovenliggende postzegels.
                $parentExchangeStampId = trim(
                    (string) $stampsSheet->getCell('B'.$row)->getValue()
                );

                if ($parentExchangeStampId !== '') {
                    $stampParentReferences[$exchangeStampId] = $parentExchangeStampId;
                }
            }

            // Controleer interne verwijzingen naar bovenliggende postzegels.
            foreach ($stampParentReferences as $exchangeStampId => $parentExchangeStampId) {

                // Controleer of de bovenliggende postzegel bestaat.
                if (! isset($exchangeStampIds[$parentExchangeStampId])) {
                    throw new RuntimeException(
                        'Onbekende parent_exchange_stamp_id voor exchange_stamp_id '.$exchangeStampId.' in tabblad Postzegels'
                    );
                }

                // Een postzegel mag niet zijn eigen bovenliggende postzegel zijn.
                if ((string) $exchangeStampId === (string) $parentExchangeStampId) {
                    throw new RuntimeException(
                        'Postzegel '.$exchangeStampId.' kan niet zijn eigen bovenliggende postzegel zijn.'
                    );
                }
            }

            // Controleer op circulaire ouder-kindrelaties binnen Postzegels.
            foreach ($stampParentReferences as $exchangeStampId => $parentExchangeStampId) {

                $visitedStampIds = [];
                $currentStampId = (string) $exchangeStampId;

                // Volg de ouderverwijzingen totdat er geen bovenliggende postzegel meer is.
                while (isset($stampParentReferences[$currentStampId])) {

                    // Een eerder bezochte postzegel betekent dat er een cirkel bestaat.
                    if (isset($visitedStampIds[$currentStampId])) {
                        throw new RuntimeException(
                            'Circulaire ouder-kindrelatie gevonden voor exchange_stamp_id '.$exchangeStampId.' in tabblad Postzegels'
                        );
                    }

                    // Registreer de huidige postzegel.
                    $visitedStampIds[$currentStampId] = true;

                    // Ga naar de bovenliggende postzegel.
                    $currentStampId = (string) $stampParentReferences[$currentStampId];
                }
            }

            // Controleer verwijzingen naar bovenliggende en onderliggende postzegels.
            $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

            $parentChildRelations = [];
            $childParentRelations = [];

            // Houd bij welke externe bovenliggende postzegels in Excel voorkomen.
            $externalParentStampIds = [];

            for ($row = 2; $row <= $parentStampsSheet->getHighestDataRow(); $row++) {

                // Controleer de identificatie van de bovenliggende postzegel.
                $parentExchangeId = trim(
                    (string) $parentStampsSheet->getCell('A'.$row)->getValue()
                );

                if ($parentExchangeId === '') {
                    throw new RuntimeException(
                        'Ontbrekende parent_exchange_id in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                // Lees de optionele nominale waarde als tekst.
                // Historische waarden zoals 7½, 12½ en 1½ zijn toegestaan.
                $parentFaceValue = trim(
                    (string) $parentStampsSheet->getCell('I'.$row)->getValue()
                );

                // De nominale waarde mag maximaal 50 tekens bevatten.
                if (mb_strlen($parentFaceValue) > 50) {
                    throw new RuntimeException(
                        'face_value is langer dan 50 tekens in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                // Controleer de identificatie van de onderliggende postzegel.
                $childExchangeStampId = trim(
                    (string) $parentStampsSheet->getCell('B'.$row)->getValue()
                );

                if ($childExchangeStampId === '') {
                    throw new RuntimeException(
                        'Ontbrekende child_exchange_stamp_id in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                // Controleer of de onderliggende postzegel bestaat.
                if (! isset($exchangeStampIds[$childExchangeStampId])) {
                    throw new RuntimeException(
                        'Onbekende child_exchange_stamp_id in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                // Voorkom tegenstrijdige ouderverwijzingen tussen beide tabbladen.
                if (isset($stampParentReferences[$childExchangeStampId])) {
                    throw new RuntimeException(
                        'Tegenstrijdige ouderverwijzing voor child_exchange_stamp_id in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                // Controleer of dezelfde ouder-kindkoppeling al bestaat.
                if (isset($parentChildRelations[$parentExchangeId][$childExchangeStampId])) {
                    throw new RuntimeException(
                        'Dubbele ouder-kindkoppeling in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                // Controleer of de onderliggende postzegel al een andere ouder heeft.
                if (isset($childParentRelations[$childExchangeStampId])) {
                    throw new RuntimeException(
                        'Meerdere bovenliggende postzegels voor dezelfde child_exchange_stamp_id in tabblad BovenliggendePostzegels, rij '.$row
                    );
                }

                // Registreer de geldige koppelingen.
                $parentChildRelations[$parentExchangeId][$childExchangeStampId] = true;
                $childParentRelations[$childExchangeStampId] = $parentExchangeId;

                // Registreer de identificatie voor de validatie van postzegelvertalingen.
                $externalParentStampIds[$parentExchangeId] = true;
            }

            // Controleer de vertalingen van de postzegels.
            $stampTranslationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

            $seenStampTranslations = [];

            for ($row = 2; $row <= $stampTranslationsSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van de postzegel.
                $translationStampId = trim(
                    (string) $stampTranslationsSheet->getCell('A'.$row)->getValue()
                );

                // De identificatie moet verwijzen naar een postzegel uit de editie
                // of naar een externe bovenliggende postzegel.
                if (
                    $translationStampId === '' ||
                    (
                        ! isset($exchangeStampIds[$translationStampId]) &&
                        ! isset($externalParentStampIds[$translationStampId])
                    )
                ) {
                    throw new RuntimeException(
                        'Onbekende exchange_stamp_id '.$translationStampId.
                        ' in tabblad Postzegelvertalingen, rij '.$row
                    );
                }

                // Lees het catalogusnummer van de postzegelvertaling.
                $translationCatalogNumber = trim(
                    (string) $stampTranslationsSheet->getCell('B'.$row)->getValue()
                );

                // Controleer of het om een externe bovenliggende postzegel gaat.
                $isExternalParentStamp = isset(
                    $externalParentStampIds[$translationStampId]
                );

                // Externe bovenliggende postzegels hebben geen catalogusnummer.
                // Gewone postzegels moeten hun oorspronkelijke catalogusnummer behouden.
                $expectedCatalogNumber = $isExternalParentStamp
                    ? ''
                    : $stampCatalogNumbers[$translationStampId];

                if ($translationCatalogNumber !== $expectedCatalogNumber) {
                    throw new RuntimeException(
                        'Afwijkend catalog_number voor exchange_stamp_id '.
                        $translationStampId.
                        ' in tabblad Postzegelvertalingen, rij '.$row
                    );
                }

                // Controleer de taalcode van de postzegelvertaling.
                $stampTranslationLocale = trim(
                    (string) $stampTranslationsSheet->getCell('C'.$row)->getValue()
                );

                // Alleen de vijf ondersteunde talen zijn toegestaan.
                if (
                    ! in_array(
                        $stampTranslationLocale,
                        ['nl', 'en', 'de', 'fr', 'es'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Ongeldige locale '.$stampTranslationLocale.
                        ' in tabblad Postzegelvertalingen, rij '.$row
                    );
                }

                // Controleer of deze postzegelvertaling al voorkomt.
                $stampTranslationKey = $translationStampId.'|'.$stampTranslationLocale;

                if (isset($seenStampTranslations[$stampTranslationKey])) {
                    throw new RuntimeException(
                        'Dubbele postzegelvertaling voor exchange_stamp_id '.
                        $translationStampId.' en locale '.$stampTranslationLocale.
                        ' in tabblad Postzegelvertalingen, rij '.$row
                    );
                }

                $seenStampTranslations[$stampTranslationKey] = true;

                // Controleer de vertaalde naam van de postzegel.
                $stampTranslationName = trim(
                    (string) $stampTranslationsSheet->getCell('D'.$row)->getValue()
                );

                // De vertaalde naam is verplicht.
                if ($stampTranslationName === '') {
                    throw new RuntimeException(
                        'Ontbrekende name in tabblad Postzegelvertalingen, rij '.$row
                    );
                }

                // Controleer de optionele vertaalde beschrijving.
                $stampTranslationDescription = $stampTranslationsSheet
                    ->getCell('E'.$row)
                    ->getValue();

                // Alleen tekst of een lege waarde is toegestaan.
                if (
                    $stampTranslationDescription !== null &&
                    ! is_string($stampTranslationDescription)
                ) {
                    throw new RuntimeException(
                        'Ongeldige description in tabblad Postzegelvertalingen, rij '.$row
                    );
                }
            }

            // Controleer de thema's.
            $themesSheet = $spreadsheet->getSheetByName('Themas');

            $availableThemeSlugs = [];

            for ($row = 2; $row <= $themesSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van het thema.
                $themeSlug = trim(
                    (string) $themesSheet->getCell('A'.$row)->getValue()
                );

                // Een thema moet een identificatie hebben.
                if ($themeSlug === '') {
                    throw new RuntimeException(
                        'Ontbrekende theme_slug in tabblad Themas, rij '.$row
                    );
                }

                // Een theme_slug mag maar één keer voorkomen.
                if (isset($availableThemeSlugs[$themeSlug])) {
                    throw new RuntimeException(
                        'Dubbele theme_slug '.$themeSlug.
                        ' in tabblad Themas, rij '.$row
                    );
                }

                // Registreer het thema voor latere controles.
                $availableThemeSlugs[$themeSlug] = true;

                // Controleer de naam van het thema.
                $themeName = trim(
                    (string) $themesSheet->getCell('B'.$row)->getValue()
                );

                // De naam van het thema is verplicht.
                if ($themeName === '') {
                    throw new RuntimeException(
                        'Ontbrekende name in tabblad Themas, rij '.$row
                    );
                }

                // Controleer de optionele beschrijving van het thema.
                $themeDescription = $themesSheet
                    ->getCell('C'.$row)
                    ->getValue();

                // Alleen tekst of een lege waarde is toegestaan.
                if (
                    $themeDescription !== null &&
                    ! is_string($themeDescription)
                ) {
                    throw new RuntimeException(
                        'Ongeldige description in tabblad Themas, rij '.$row
                    );
                }
            }

            // Controleer de vertalingen van de thema's.
            $themeTranslationsSheet = $spreadsheet->getSheetByName('Themavertalingen');

            $seenThemeTranslations = [];

            for ($row = 2; $row <= $themeTranslationsSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van het thema.
                $translationThemeSlug = trim(
                    (string) $themeTranslationsSheet->getCell('A'.$row)->getValue()
                );

                // De identificatie moet verwijzen naar een bestaand thema.
                if (
                    $translationThemeSlug === '' ||
                    ! isset($availableThemeSlugs[$translationThemeSlug])
                ) {
                    throw new RuntimeException(
                        'Onbekende theme_slug '.$translationThemeSlug.
                        ' in tabblad Themavertalingen, rij '.$row
                    );
                }

                // Controleer de taalcode van de themavertaling.
                $themeTranslationLocale = trim(
                    (string) $themeTranslationsSheet->getCell('B'.$row)->getValue()
                );

                // Alleen de vijf ondersteunde talen zijn toegestaan.
                if (
                    ! in_array(
                        $themeTranslationLocale,
                        ['nl', 'en', 'de', 'fr', 'es'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Ongeldige locale '.$themeTranslationLocale.
                        ' in tabblad Themavertalingen, rij '.$row
                    );
                }

                // Controleer of deze themavertaling al voorkomt.
                $themeTranslationKey = $translationThemeSlug.'|'.$themeTranslationLocale;

                if (isset($seenThemeTranslations[$themeTranslationKey])) {
                    throw new RuntimeException(
                        'Dubbele themavertaling voor theme_slug '.
                        $translationThemeSlug.' en locale '.$themeTranslationLocale.
                        ' in tabblad Themavertalingen, rij '.$row
                    );
                }

                // Registreer de combinatie van thema en taal.
                $seenThemeTranslations[$themeTranslationKey] = true;

                // Controleer de vertaalde naam van het thema.
                $themeTranslationName = trim(
                    (string) $themeTranslationsSheet->getCell('C'.$row)->getValue()
                );

                // De vertaalde naam is verplicht.
                if ($themeTranslationName === '') {
                    throw new RuntimeException(
                        'Ontbrekende name in tabblad Themavertalingen, rij '.$row
                    );
                }

                // Controleer de optionele beschrijving van de themavertaling.
                $themeTranslationDescription = $themeTranslationsSheet
                    ->getCell('D'.$row)
                    ->getValue();

                // Alleen tekst of een lege waarde is toegestaan.
                if (
                    $themeTranslationDescription !== null &&
                    ! is_string($themeTranslationDescription)
                ) {
                    throw new RuntimeException(
                        'Ongeldige description in tabblad Themavertalingen, rij '.$row
                    );
                }
            }

            // Controleer de koppelingen tussen postzegels en thema's.
            $stampThemesSheet = $spreadsheet->getSheetByName('Postzegelthemas');

            $seenStampThemes = [];

            for ($row = 2; $row <= $stampThemesSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van de postzegel.
                $stampThemeExchangeId = trim(
                    (string) $stampThemesSheet->getCell('A'.$row)->getValue()
                );

                // De identificatie moet verwijzen naar een postzegel uit de editie
                // of naar een externe bovenliggende postzegel.
                if (
                    $stampThemeExchangeId === '' ||
                    (
                        ! isset($exchangeStampIds[$stampThemeExchangeId]) &&
                        ! isset($externalParentStampIds[$stampThemeExchangeId])
                    )
                ) {
                    throw new RuntimeException(
                        'Onbekende exchange_stamp_id '.$stampThemeExchangeId.
                        ' in tabblad Postzegelthemas, rij '.$row
                    );
                }
                // Lees de identificatie van het thema.
                $stampThemeSlug = trim(
                    (string) $stampThemesSheet->getCell('B'.$row)->getValue()
                );

                // De identificatie moet verwijzen naar een bestaand thema.
                if (
                    $stampThemeSlug === '' ||
                    ! isset($availableThemeSlugs[$stampThemeSlug])
                ) {
                    throw new RuntimeException(
                        'Onbekende theme_slug '.$stampThemeSlug.
                        ' in tabblad Postzegelthemas, rij '.$row
                    );
                }

                // Controleer of deze postzegel-themakoppeling al voorkomt.
                $stampThemeKey = $stampThemeExchangeId.'|'.$stampThemeSlug;

                if (isset($seenStampThemes[$stampThemeKey])) {
                    throw new RuntimeException(
                        'Dubbele postzegel-themakoppeling voor exchange_stamp_id '.
                        $stampThemeExchangeId.' en theme_slug '.$stampThemeSlug.
                        ' in tabblad Postzegelthemas, rij '.$row
                    );
                }

                // Registreer de combinatie van postzegel en thema.
                $seenStampThemes[$stampThemeKey] = true;
            }

            // Verzamel de beschikbare waarderingscategorieën.
            $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

            $availableConditionSlugs = [];

            for ($row = 2; $row <= $conditionsSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van de waarderingscategorie.
                $conditionSlug = trim(
                    (string) $conditionsSheet->getCell('A'.$row)->getValue()
                );

                // Een waarderingscategorie moet een identificatie hebben.
                if ($conditionSlug === '') {
                    throw new RuntimeException(
                        'Ontbrekende condition_slug in tabblad Waarderingscategorieen, rij '.$row
                    );
                }

                // Dezelfde waarderingscategorie mag niet meerdere keren voorkomen.
                if (isset($availableConditionSlugs[$conditionSlug])) {
                    throw new RuntimeException(
                        'Dubbele condition_slug in tabblad Waarderingscategorieen, rij '.$row
                    );
                }

                // Registreer de beschikbare waarderingscategorie.
                $availableConditionSlugs[$conditionSlug] = true;
            }

            // Controleer de vertalingen van de waarderingscategorieën.
            $conditionTranslationsSheet = $spreadsheet->getSheetByName('Waarderingsvertalingen');

            $seenConditionTranslations = [];

            for ($row = 2; $row <= $conditionTranslationsSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van de waarderingscategorie.
                $translationConditionSlug = trim(
                    (string) $conditionTranslationsSheet->getCell('A'.$row)->getValue()
                );

                // De identificatie moet verwijzen naar een bestaande waarderingscategorie.
                if (
                    $translationConditionSlug === '' ||
                    ! isset($availableConditionSlugs[$translationConditionSlug])
                ) {
                    throw new RuntimeException(
                        'Onbekende condition_slug '.$translationConditionSlug.
                        ' in tabblad Waarderingsvertalingen, rij '.$row
                    );
                }

                // Controleer de taalcode van de waarderingsvertaling.
                $conditionTranslationLocale = trim(
                    (string) $conditionTranslationsSheet->getCell('B'.$row)->getValue()
                );

                // Alleen de vijf ondersteunde talen zijn toegestaan.
                if (
                    ! in_array(
                        $conditionTranslationLocale,
                        ['nl', 'en', 'de', 'fr', 'es'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Ongeldige locale '.$conditionTranslationLocale.
                        ' in tabblad Waarderingsvertalingen, rij '.$row
                    );
                }

                // Controleer of deze waarderingsvertaling al voorkomt.
                $conditionTranslationKey = $translationConditionSlug.'|'.$conditionTranslationLocale;

                if (isset($seenConditionTranslations[$conditionTranslationKey])) {
                    throw new RuntimeException(
                        'Dubbele waarderingsvertaling voor condition_slug '.
                        $translationConditionSlug.' en locale '.$conditionTranslationLocale.
                        ' in tabblad Waarderingsvertalingen, rij '.$row
                    );
                }

                // Registreer de combinatie van waarderingscategorie en taal.
                $seenConditionTranslations[$conditionTranslationKey] = true;

                // Controleer de vertaalde naam van de waarderingscategorie.
                $conditionTranslationName = trim(
                    (string) $conditionTranslationsSheet->getCell('C'.$row)->getValue()
                );

                // De vertaalde naam is verplicht.
                if ($conditionTranslationName === '') {
                    throw new RuntimeException(
                        'Ontbrekende name in tabblad Waarderingsvertalingen, rij '.$row
                    );
                }

                // Controleer de optionele beschrijving van de waarderingscategorie.
                $conditionTranslationDescription = $conditionTranslationsSheet
                    ->getCell('D'.$row)
                    ->getValue();

                // Een ingevulde beschrijving moet tekst zijn.
                if (
                    $conditionTranslationDescription !== null &&
                    ! is_string($conditionTranslationDescription)
                ) {
                    throw new RuntimeException(
                        'Ongeldige description in tabblad Waarderingsvertalingen, rij '.$row
                    );
                }
            }

            // Verzamel de beschikbare landen en bijbehorende ISO-codes.
            $countriesSheet = $spreadsheet->getSheetByName('Landen');

            $availableCountrySlugs = [];

            for ($row = 2; $row <= $countriesSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van het land.
                $countrySlug = trim(
                    (string) $countriesSheet->getCell('G'.$row)->getValue()
                );

                // Lees de naam van het land.
                $countryName = trim(
                    (string) $countriesSheet->getCell('A'.$row)->getValue()
                );

                // Een land moet een naam hebben.
                if ($countryName === '') {
                    throw new RuntimeException(
                        'Ontbrekende name in tabblad Landen, rij '.$row
                    );
                }

                // Een landnaam mag maximaal 100 tekens bevatten.
                if (mb_strlen($countryName) > 100) {
                    throw new RuntimeException(
                        'Te lange name in tabblad Landen, rij '.$row
                    );
                }

                // Lees het type land of gebied.
                $entityType = trim(
                    (string) $countriesSheet->getCell('C'.$row)->getValue()
                );

                // Toegestane typen landen en gebieden.
                $allowedEntityTypes = [
                    'country',
                    'historical_state',
                    'territory',
                    'colonial_territory',
                    'other_issuer',
                ];

                // Controleer of het type is ingevuld en toegestaan.
                if (! in_array($entityType, $allowedEntityTypes, true)) {
                    throw new RuntimeException(
                        'Ongeldige entity_type in tabblad Landen, rij '.$row
                    );
                }

                // Een land moet een unieke identificatie hebben.
                if ($countrySlug === '') {
                    throw new RuntimeException(
                        'Ontbrekende country_slug in tabblad Landen, rij '.$row
                    );
                }

                // Een landidentificatie mag maximaal 120 tekens bevatten.
                if (mb_strlen($countrySlug) > 120) {
                    throw new RuntimeException(
                        'Te lange country_slug in tabblad Landen, rij '.$row
                    );
                }

                // Controleer of de landidentificatie al voorkomt.
                if (isset($availableCountrySlugs[$countrySlug])) {
                    throw new RuntimeException(
                        'Dubbele country_slug in tabblad Landen, rij '.$row
                    );
                }

                // Lees de ISO-code van het land.
                $countryIsoCode = trim(
                    (string) $countriesSheet->getCell('B'.$row)->getValue()
                );

                // Lees het beginjaar en eindjaar van het land.
                $startYear = trim(
                    (string) $countriesSheet->getCell('D'.$row)->getValue()
                );

                $endYear = trim(
                    (string) $countriesSheet->getCell('E'.$row)->getValue()
                );

                // Controleer het beginjaar wanneer dit is ingevuld.
                if ($startYear !== '' && (
                    ! ctype_digit($startYear) ||
                    (int) $startYear > 9999
                )) {
                    throw new RuntimeException(
                        'Ongeldig start_year in tabblad Landen, rij '.$row
                    );
                }

                // Controleer het eindjaar wanneer dit is ingevuld.
                if ($endYear !== '' && (
                    ! ctype_digit($endYear) ||
                    (int) $endYear > 9999
                )) {
                    throw new RuntimeException(
                        'Ongeldig end_year in tabblad Landen, rij '.$row
                    );
                }

                // Het eindjaar mag niet vóór het beginjaar liggen.
                if (
                    $startYear !== '' &&
                    $endYear !== '' &&
                    (int) $endYear < (int) $startYear
                ) {
                    throw new RuntimeException(
                        'end_year ligt vóór start_year in tabblad Landen, rij '.$row
                    );
                }

                // Lees de actieve status van het land.
                $isActive = $countriesSheet->getCell('F'.$row)->getValue();

                // Alleen de numerieke waarden 0 en 1 zijn toegestaan.
                if (! in_array($isActive, [0, 1], true)) {
                    throw new RuntimeException(
                        'Ongeldige is_active in tabblad Landen, rij '.$row
                    );
                }

                // Lees de beschrijving van het land.
                $description = $countriesSheet->getCell('H'.$row)->getValue();

                // Een beschrijving is optioneel, maar moet tekst bevatten wanneer ingevuld.
                if ($description !== null && ! is_string($description)) {
                    throw new RuntimeException(
                        'Ongeldige description in tabblad Landen, rij '.$row
                    );
                }

                // Een ISO-code is optioneel, maar mag maximaal 3 tekens bevatten.
                if ($countryIsoCode !== '' && mb_strlen($countryIsoCode) > 3) {
                    throw new RuntimeException(
                        'Ongeldige country_iso_code in tabblad Landen, rij '.$row
                    );
                }

                // Registreer het land met de bijbehorende ISO-code.
                $availableCountrySlugs[$countrySlug] = $countryIsoCode;
            }

            // Controleer de landidentificaties in het tabblad Landvertalingen.
            $countryTranslationsSheet = $spreadsheet->getSheetByName('Landvertalingen');

            $seenCountryTranslations = [];

            for ($row = 2; $row <= $countryTranslationsSheet->getHighestDataRow(); $row++) {

                // Lees de landidentificatie van de vertaling.
                $countrySlug = trim(
                    (string) $countryTranslationsSheet->getCell('A'.$row)->getValue()
                );

                // Iedere landvertaling moet een landidentificatie hebben.
                if ($countrySlug === '') {
                    throw new RuntimeException(
                        'Ontbrekende country_slug in tabblad Landvertalingen, rij '.$row
                    );
                }

                // Controleer of het land voorkomt in het tabblad Landen.
                if (! isset($availableCountrySlugs[$countrySlug])) {
                    throw new RuntimeException(
                        'Onbekende country_slug '.$countrySlug.
                        ' in tabblad Landvertalingen, rij '.$row
                    );
                }

                // Lees de ISO-code van de landvertaling.
                $countryIsoCode = trim(
                    (string) $countryTranslationsSheet->getCell('B'.$row)->getValue()
                );

                // Haal de verwachte ISO-code op uit het tabblad Landen.
                $expectedIsoCode = $availableCountrySlugs[$countrySlug];

                // De ISO-code moet overeenkomen met die van het bijbehorende land.
                if ($countryIsoCode !== $expectedIsoCode) {
                    throw new RuntimeException(
                        'Ongeldige country_iso_code '.$countryIsoCode.
                        ' voor country_slug '.$countrySlug.
                        ' in tabblad Landvertalingen, rij '.$row
                    );
                }

                // Lees de taalcode van de landvertaling.
                $locale = trim(
                    (string) $countryTranslationsSheet->getCell('C'.$row)->getValue()
                );

                // Toegestane talen voor landvertalingen.
                $allowedLocales = ['nl', 'en', 'de', 'fr', 'es'];

                // Iedere landvertaling moet een geldige taalcode hebben.
                if (! in_array($locale, $allowedLocales, true)) {
                    throw new RuntimeException(
                        'Ongeldige locale '.$locale.
                        ' in tabblad Landvertalingen, rij '.$row
                    );
                }

                // Maak een unieke sleutel voor de combinatie van land en taal.
                $translationKey = $countrySlug.'|'.$locale;

                // Controleer of deze landvertaling al voorkomt.
                if (isset($seenCountryTranslations[$translationKey])) {
                    throw new RuntimeException(
                        'Dubbele combinatie country_slug '.$countrySlug.
                        ' en locale '.$locale.
                        ' in tabblad Landvertalingen, rij '.$row
                    );
                }

                // Registreer de combinatie van land en taal.
                $seenCountryTranslations[$translationKey] = true;

                // Lees de vertaalde naam van het land.
                $translatedName = trim(
                    (string) $countryTranslationsSheet->getCell('D'.$row)->getValue()
                );

                // Iedere landvertaling moet een naam bevatten.
                if ($translatedName === '') {
                    throw new RuntimeException(
                        'Ontbrekende name in tabblad Landvertalingen, rij '.$row
                    );
                }

                // Een vertaalde landnaam mag maximaal 100 tekens bevatten.
                if (mb_strlen($translatedName) > 100) {
                    throw new RuntimeException(
                        'Te lange name in tabblad Landvertalingen, rij '.$row
                    );
                }

                // Lees de beschrijving van de landvertaling.
                $translatedDescription = $countryTranslationsSheet
                    ->getCell('E'.$row)
                    ->getValue();

                // Een beschrijving is optioneel, maar moet tekst zijn wanneer ingevuld.
                if ($translatedDescription !== null && ! is_string($translatedDescription)) {
                    throw new RuntimeException(
                        'Ongeldige description in tabblad Landvertalingen, rij '.$row
                    );
                }
            }

            // Controleer de identificaties van de postzegelseries.
            $stampSeriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

            $availableSeriesIds = [];

            for ($row = 2; $row <= $stampSeriesSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van de postzegelserie.
                $exchangeSeriesId = trim(
                    (string) $stampSeriesSheet->getCell('A'.$row)->getValue()
                );

                // Een serie moet een positief geheel getal als identificatie hebben.
                if (
                    $exchangeSeriesId === '' ||
                    ! ctype_digit($exchangeSeriesId) ||
                    (int) $exchangeSeriesId < 1
                ) {
                    throw new RuntimeException(
                        'Ongeldige exchange_series_id in tabblad Postzegelseries, rij '.$row
                    );
                }

                // Controleer of de serie-identificatie al voorkomt.
                if (isset($availableSeriesIds[$exchangeSeriesId])) {
                    throw new RuntimeException(
                        'Dubbele exchange_series_id '.$exchangeSeriesId.
                        ' in tabblad Postzegelseries, rij '.$row
                    );
                }

                // Registreer de beschikbare postzegelserie.
                $availableSeriesIds[$exchangeSeriesId] = true;

                // Lees de landidentificatie van de postzegelserie.
                $seriesCountrySlug = trim(
                    (string) $stampSeriesSheet->getCell('B'.$row)->getValue()
                );

                // Iedere postzegelserie moet aan een land gekoppeld zijn.
                if ($seriesCountrySlug === '') {
                    throw new RuntimeException(
                        'Ontbrekende country_slug in tabblad Postzegelseries, rij '.$row
                    );
                }

                // Controleer of het land voorkomt in het tabblad Landen.
                if (! isset($availableCountrySlugs[$seriesCountrySlug])) {
                    throw new RuntimeException(
                        'Onbekende country_slug '.$seriesCountrySlug.
                        ' in tabblad Postzegelseries, rij '.$row
                    );
                }

                // Lees de ISO-code van het land van de postzegelserie.
                $seriesCountryIsoCode = trim(
                    (string) $stampSeriesSheet->getCell('C'.$row)->getValue()
                );

                // Haal de verwachte ISO-code op uit het tabblad Landen.
                $expectedSeriesCountryIsoCode = $availableCountrySlugs[$seriesCountrySlug];

                // De ISO-code moet overeenkomen met die van het gekoppelde land.
                if ($seriesCountryIsoCode !== $expectedSeriesCountryIsoCode) {
                    throw new RuntimeException(
                        'Ongeldige country_iso_code '.$seriesCountryIsoCode.
                        ' voor country_slug '.$seriesCountrySlug.
                        ' in tabblad Postzegelseries, rij '.$row
                    );
                }

                // Lees de naam van de postzegelserie.
                $seriesName = trim(
                    (string) $stampSeriesSheet->getCell('D'.$row)->getValue()
                );

                // Iedere postzegelserie moet een naam bevatten.
                if ($seriesName === '') {
                    throw new RuntimeException(
                        'Ontbrekende name in tabblad Postzegelseries, rij '.$row
                    );
                }

                // Lees het uitgiftejaar van de postzegelserie.
                $seriesIssueYear = trim(
                    (string) $stampSeriesSheet->getCell('E'.$row)->getValue()
                );

                // Het uitgiftejaar is optioneel, maar moet een geheel getal
                // tussen 0 en 9999 zijn wanneer het is ingevuld.
                if (
                    $seriesIssueYear !== '' &&
                    (
                        ! ctype_digit($seriesIssueYear) ||
                        (int) $seriesIssueYear > 9999
                    )
                ) {
                    throw new RuntimeException(
                        'Ongeldige issue_year in tabblad Postzegelseries, rij '.$row
                    );
                }

                // Controleer de optionele beschrijving van de postzegelserie.
                $seriesDescription = $stampSeriesSheet->getCell('F'.$row)->getValue();

                if (
                    $seriesDescription !== null &&
                    ! is_string($seriesDescription)
                ) {
                    throw new RuntimeException(
                        'Ongeldige description in tabblad Postzegelseries, rij '.$row
                    );
                }
            }

            // Controleer de vertalingen van de postzegelseries.
            $seriesTranslationsSheet = $spreadsheet->getSheetByName('Serievertalingen');

            $seenSeriesTranslations = [];

            for ($row = 2; $row <= $seriesTranslationsSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van de postzegelserie.
                $translationSeriesId = trim(
                    (string) $seriesTranslationsSheet->getCell('A'.$row)->getValue()
                );

                // De identificatie moet verwijzen naar een bestaande serie.
                if (
                    $translationSeriesId === '' ||
                    ! isset($availableSeriesIds[$translationSeriesId])
                ) {
                    throw new RuntimeException(
                        'Onbekende exchange_series_id '.$translationSeriesId.
                        ' in tabblad Serievertalingen, rij '.$row
                    );
                }

                // Controleer de taalcode van de serievertaling.
                $seriesTranslationLocale = trim(
                    (string) $seriesTranslationsSheet->getCell('B'.$row)->getValue()
                );

                // Alleen de vijf ondersteunde talen zijn toegestaan.
                if (
                    ! in_array(
                        $seriesTranslationLocale,
                        ['nl', 'en', 'de', 'fr', 'es'],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Ongeldige locale '.$seriesTranslationLocale.
                        ' in tabblad Serievertalingen, rij '.$row
                    );
                }

                // Controleer of deze serievertaling al voorkomt.
                $seriesTranslationKey = $translationSeriesId.'|'.$seriesTranslationLocale;

                if (isset($seenSeriesTranslations[$seriesTranslationKey])) {
                    throw new RuntimeException(
                        'Dubbele serievertaling voor exchange_series_id '.
                        $translationSeriesId.' en locale '.$seriesTranslationLocale.
                        ' in tabblad Serievertalingen, rij '.$row
                    );
                }

                $seenSeriesTranslations[$seriesTranslationKey] = true;

                // Controleer de vertaalde naam van de postzegelserie.
                $seriesTranslationName = trim(
                    (string) $seriesTranslationsSheet->getCell('C'.$row)->getValue()
                );

                // De vertaalde naam is verplicht.
                if ($seriesTranslationName === '') {
                    throw new RuntimeException(
                        'Ontbrekende name in tabblad Serievertalingen, rij '.$row
                    );
                }

                // Controleer de optionele vertaalde beschrijving.
                $seriesTranslationDescription = $seriesTranslationsSheet
                    ->getCell('D'.$row)
                    ->getValue();

                if (
                    $seriesTranslationDescription !== null &&
                    ! is_string($seriesTranslationDescription)
                ) {
                    throw new RuntimeException(
                        'Ongeldige description in tabblad Serievertalingen, rij '.$row
                    );
                }
            }

            // Controleer de waarderingscategorieën in de waarderingsregels.
            $conditionRulesSheet = $spreadsheet->getSheetByName('Waarderingsregels');

            for ($row = 2; $row <= $conditionRulesSheet->getHighestDataRow(); $row++) {

                // Lees de landidentificatie van de waarderingsregel.
                $countrySlug = trim(
                    (string) $conditionRulesSheet->getCell('A'.$row)->getValue()
                );

                // Controleer of een ingevuld land bestaat.
                if ($countrySlug !== '' && ! isset($availableCountrySlugs[$countrySlug])) {
                    throw new RuntimeException(
                        'Onbekende country_slug '.$countrySlug.
                        ' in tabblad Waarderingsregels, rij '.$row
                    );
                }

                // Lees de ISO-code van de waarderingsregel.
                $countryIsoCode = trim(
                    (string) $conditionRulesSheet->getCell('B'.$row)->getValue()
                );

                // Een algemene waarderingsregel mag geen landgebonden ISO-code hebben.
                if ($countrySlug === '' && $countryIsoCode !== '') {
                    throw new RuntimeException(
                        'country_iso_code zonder country_slug in tabblad Waarderingsregels, rij '.$row
                    );
                }

                // Controleer of de ISO-code overeenkomt met het geselecteerde land.
                if ($countrySlug !== '') {

                    $expectedIsoCode = $availableCountrySlugs[$countrySlug];

                    if ($countryIsoCode !== $expectedIsoCode) {
                        throw new RuntimeException(
                            'Ongeldige country_iso_code '.$countryIsoCode.
                            ' voor country_slug '.$countrySlug.
                            ' in tabblad Waarderingsregels, rij '.$row
                        );
                    }
                }

                // Lees de waarderingscategorie van de regel.
                $conditionSlug = trim(
                    (string) $conditionRulesSheet->getCell('C'.$row)->getValue()
                );

                // Een waarderingsregel moet een waarderingscategorie hebben.
                if ($conditionSlug === '') {
                    throw new RuntimeException(
                        'Ontbrekende condition_slug in tabblad Waarderingsregels, rij '.$row
                    );
                }

                // Controleer of de waarderingscategorie bestaat.
                if (! isset($availableConditionSlugs[$conditionSlug])) {
                    throw new RuntimeException(
                        'Onbekende condition_slug '.$conditionSlug.
                        ' in tabblad Waarderingsregels, rij '.$row
                    );
                }

                // Lees het beginjaar en eindjaar van de waarderingsregel.
                $issueYearFrom = trim(
                    (string) $conditionRulesSheet->getCell('D'.$row)->getValue()
                );

                $issueYearTo = trim(
                    (string) $conditionRulesSheet->getCell('E'.$row)->getValue()
                );

                // Controleer het beginjaar wanneer dit is ingevuld.
                if ($issueYearFrom !== '' && (
                    ! ctype_digit($issueYearFrom) ||
                    (int) $issueYearFrom > 9999
                )) {
                    throw new RuntimeException(
                        'Ongeldig issue_year_from in tabblad Waarderingsregels, rij '.$row
                    );
                }

                // Controleer het eindjaar wanneer dit is ingevuld.
                if ($issueYearTo !== '' && (
                    ! ctype_digit($issueYearTo) ||
                    (int) $issueYearTo > 9999
                )) {
                    throw new RuntimeException(
                        'Ongeldig issue_year_to in tabblad Waarderingsregels, rij '.$row
                    );
                }

                // Het eindjaar mag niet vóór het beginjaar liggen.
                if (
                    $issueYearFrom !== '' &&
                    $issueYearTo !== '' &&
                    (int) $issueYearTo < (int) $issueYearFrom
                ) {
                    throw new RuntimeException(
                        'issue_year_to ligt vóór issue_year_from in tabblad Waarderingsregels, rij '.$row
                    );
                }

                // Lees de sorteervolgorde van de waarderingsregel.
                $sortOrder = trim(
                    (string) $conditionRulesSheet->getCell('F'.$row)->getValue()
                );

                // De sorteervolgorde is verplicht en moet tussen 1 en 9999 liggen.
                if (
                    $sortOrder === '' ||
                    ! ctype_digit($sortOrder) ||
                    (int) $sortOrder < 1 ||
                    (int) $sortOrder > 9999
                ) {
                    throw new RuntimeException(
                        'Ongeldige sort_order in tabblad Waarderingsregels, rij '.$row
                    );
                }
            }

            // Controleer ontbrekende en dubbele cataloguswaarden.
            $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

            $catalogValueCombinations = [];

            for ($row = 2; $row <= $catalogValuesSheet->getHighestDataRow(); $row++) {

                // Identificatie van de postzegel.
                $exchangeStampId = trim(
                    (string) $catalogValuesSheet->getCell('A'.$row)->getValue()
                );

                if ($exchangeStampId === '') {
                    throw new RuntimeException(
                        'Ontbrekende exchange_stamp_id in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Controleer of de postzegel bestaat.
                if (! isset($exchangeStampIds[$exchangeStampId])) {
                    throw new RuntimeException(
                        'Onbekende exchange_stamp_id in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Lees het catalogusnummer van de cataloguswaarde.
                $catalogValueCatalogNumber = trim(
                    (string) $catalogValuesSheet->getCell('B'.$row)->getValue()
                );

                // Het catalogusnummer moet overeenkomen met de oorspronkelijke postzegel.
                if (
                    $catalogValueCatalogNumber !==
                    $stampCatalogNumbers[$exchangeStampId]
                ) {
                    throw new RuntimeException(
                        'Afwijkend catalog_number voor exchange_stamp_id '.
                        $exchangeStampId.
                        ' in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Waarderingscategorie.
                $conditionSlug = trim(
                    (string) $catalogValuesSheet->getCell('C'.$row)->getValue()
                );

                if ($conditionSlug === '') {
                    throw new RuntimeException(
                        'Ontbrekende condition_slug in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Controleer of de waarderingscategorie bestaat.
                if (! isset($availableConditionSlugs[$conditionSlug])) {
                    throw new RuntimeException(
                        'Onbekende condition_slug '.$conditionSlug.
                        ' in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Controleer of deze combinatie al voorkomt.
                if (isset($catalogValueCombinations[$exchangeStampId][$conditionSlug])) {
                    throw new RuntimeException(
                        'Dubbele cataloguswaarde voor exchange_stamp_id '.$exchangeStampId.
                        ' en condition_slug '.$conditionSlug.
                        ' in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Lees de optionele cataloguswaarde van de postzegel.
                $catalogValue = trim(
                    (string) $catalogValuesSheet->getCell('D'.$row)->getValue()
                );

                // Een ingevulde cataloguswaarde mag maximaal tien cijfers vóór
                // de decimale punt en maximaal twee cijfers erachter bevatten.
                if (
                    $catalogValue !== '' &&
                    ! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $catalogValue)
                ) {
                    throw new RuntimeException(
                        'Ongeldige catalog_value in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Lees de optionele valutacode van de cataloguswaarde.
                $catalogCurrency = $catalogValuesSheet
                    ->getCell('E'.$row)
                    ->getValue();

                // Een ingevulde valutacode moet uit drie hoofdletters bestaan.
                if (
                    $catalogCurrency !== null &&
                    ! preg_match('/^[A-Z]{3}$/', (string) $catalogCurrency)
                ) {
                    throw new RuntimeException(
                        'Ongeldige currency in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Lees de optionele opmerkingen bij de cataloguswaarde.
                $catalogNotes = $catalogValuesSheet
                    ->getCell('F'.$row)
                    ->getValue();

                // Ingevulde opmerkingen moeten tekst zijn.
                if (
                    $catalogNotes !== null &&
                    ! is_string($catalogNotes)
                ) {
                    throw new RuntimeException(
                        'Ongeldige notes in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Lees de indicator voor handmatige controle.
                $needsReview = $catalogValuesSheet
                    ->getCell('G'.$row)
                    ->getValue();

                // Alleen 0 en 1 zijn toegestaan.
                if (! in_array($needsReview, [0, 1, '0', '1'], true)) {
                    throw new RuntimeException(
                        'Ongeldige needs_review in tabblad Cataloguswaarden, rij '.$row
                    );
                }

                // Registreer de geldige combinatie.
                $catalogValueCombinations[$exchangeStampId][$conditionSlug] = true;

            }

            // Controleer de cataloguswaarden van postzegelseries.
            $seriesCatalogValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

            // Houd bestaande combinaties van serie en waarderingscategorie bij.
            $seriesCatalogValueCombinations = [];

            for ($row = 2; $row <= $seriesCatalogValuesSheet->getHighestDataRow(); $row++) {

                // Lees de identificatie van de postzegelserie.
                $seriesCatalogExchangeId = trim(
                    (string) $seriesCatalogValuesSheet->getCell('A'.$row)->getValue()
                );

                // De identificatie moet verwijzen naar een bestaande postzegelserie.
                if (
                    $seriesCatalogExchangeId === '' ||
                    ! isset($availableSeriesIds[$seriesCatalogExchangeId])
                ) {
                    throw new RuntimeException(
                        'Onbekende exchange_series_id '.$seriesCatalogExchangeId.
                        ' in tabblad Seriecataloguswaarden, rij '.$row
                    );
                }
                // Lees de waarderingscategorie van de seriecataloguswaarde.
                $seriesCatalogConditionSlug = trim(
                    (string) $seriesCatalogValuesSheet->getCell('B'.$row)->getValue()
                );

                // Een ingevulde waarderingscategorie moet bestaan.
                if (
                    $seriesCatalogConditionSlug !== '' &&
                    ! isset($availableConditionSlugs[$seriesCatalogConditionSlug])
                ) {
                    throw new RuntimeException(
                        'Onbekende condition_slug '.$seriesCatalogConditionSlug.
                        ' in tabblad Seriecataloguswaarden, rij '.$row
                    );
                }

                // Controleer of deze combinatie al voorkomt.
                if (
                    isset(
                        $seriesCatalogValueCombinations[$seriesCatalogExchangeId][$seriesCatalogConditionSlug]
                    )
                ) {
                    throw new RuntimeException(
                        'Dubbele seriecataloguswaarde voor exchange_series_id '.
                        $seriesCatalogExchangeId.
                        ' en condition_slug '.$seriesCatalogConditionSlug.
                        ' in tabblad Seriecataloguswaarden, rij '.$row
                    );
                }

                // Registreer de combinatie van serie en waarderingscategorie.
                $seriesCatalogValueCombinations[$seriesCatalogExchangeId][$seriesCatalogConditionSlug] = true;

                // Lees het optionele catalogusnummer van de postzegelserie.
                $seriesCatalogNumber = $seriesCatalogValuesSheet
                    ->getCell('C'.$row)
                    ->getValue();

                // Een ingevuld catalogusnummer moet tekst zijn en mag maximaal 100 tekens bevatten.
                if (
                    $seriesCatalogNumber !== null &&
                    (
                        ! is_string($seriesCatalogNumber) ||
                        mb_strlen($seriesCatalogNumber) > 100
                    )
                ) {
                    throw new RuntimeException(
                        'Ongeldige catalog_number in tabblad Seriecataloguswaarden, rij '.$row
                    );
                }

                // Lees de optionele cataloguswaarde van de postzegelserie.
                $seriesCatalogValue = trim(
                    (string) $seriesCatalogValuesSheet->getCell('D'.$row)->getValue()
                );

                // Een ingevulde cataloguswaarde mag maximaal tien cijfers vóór
                // de decimale punt en maximaal twee cijfers erachter bevatten.
                if (
                    $seriesCatalogValue !== '' &&
                    ! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $seriesCatalogValue)
                ) {
                    throw new RuntimeException(
                        'Ongeldige catalog_value in tabblad Seriecataloguswaarden, rij '.$row
                    );
                }

                // Lees de optionele valutacode van de seriecataloguswaarde.
                $seriesCatalogCurrency = $seriesCatalogValuesSheet
                    ->getCell('E'.$row)
                    ->getValue();

                // Een ingevulde valutacode moet uit drie hoofdletters bestaan.
                if (
                    $seriesCatalogCurrency !== null &&
                    ! preg_match('/^[A-Z]{3}$/', (string) $seriesCatalogCurrency)
                ) {
                    throw new RuntimeException(
                        'Ongeldige currency in tabblad Seriecataloguswaarden, rij '.$row
                    );
                }

                // Lees de optionele opmerkingen bij de seriecataloguswaarde.
                $seriesCatalogNotes = $seriesCatalogValuesSheet
                    ->getCell('F'.$row)
                    ->getValue();

                // Ingevulde opmerkingen moeten tekst zijn.
                if (
                    $seriesCatalogNotes !== null &&
                    ! is_string($seriesCatalogNotes)
                ) {
                    throw new RuntimeException(
                        'Ongeldige notes in tabblad Seriecataloguswaarden, rij '.$row
                    );
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }
}
