<?php

use App\Exports\Exchange\CatalogEditionExchangeExcelExport;
use App\Imports\Exchange\CatalogEditionExchangeValidator;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

test('een geldig Excel-uitwisselingsbestand v1.1 wordt geaccepteerd', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus Excel import',
        'abbreviation' => 'TCEI',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand v1.1.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    try {
        // Schrijf de Excel-inhoud naar het tijdelijke bestand.
        file_put_contents($filePath, $excelContent);

        // Valideer het gegenereerde Excel-bestand.
        $validator = app(CatalogEditionExchangeValidator::class);

        $validator->validate($filePath);

        // De validator heeft geen uitzondering veroorzaakt.
        expect(true)->toBeTrue();
    } finally {
        // Verwijder het tijdelijke bestand.
        unlink($filePath);
    }
});

test('een Excel-bestand zonder edition_name wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende editienaam',
        'abbreviation' => 'TCOE',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het gegenereerde Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Maak de verplichte editienaam leeg.
        $spreadsheet->getSheetByName('Editie')
            ->setCellValue('A2', null);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet voordat de validator het bestand opent.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de ontbrekende naam afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende edition_name in tabblad Editie, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te lange edition_name wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange editienaam',
        'abbreviation' => 'TCLE',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een editienaam van 151 tekens in.
        $spreadsheet->getSheetByName('Editie')
            ->setCellValue('A2', str_repeat('A', 151));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange naam afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'edition_name is langer dan 150 tekens in tabblad Editie, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand zonder edition_year wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekend editiejaar',
        'abbreviation' => 'TCOJ',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Maak het verplichte editiejaar leeg.
        $spreadsheet->getSheetByName('Editie')
            ->setCellValue('B2', null);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator het ontbrekende editiejaar afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende edition_year in tabblad Editie, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een ongeldig edition_year wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldig editiejaar',
        'abbreviation' => 'TCOE',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vervang het editiejaar door een ongeldige tekstwaarde.
        $spreadsheet->getSheetByName('Editie')
            ->setCellValue('B2', 'abc');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator het ongeldige editiejaar afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige edition_year in tabblad Editie, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te hoog edition_year wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te hoog editiejaar',
        'abbreviation' => 'TCHJ',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Stel een editiejaar in dat buiten het databasebereik valt.
        $spreadsheet->getSheetByName('Editie')
            ->setCellValue('B2', 65536);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator het te hoge editiejaar afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'edition_year is hoger dan 65535 in tabblad Editie, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een ongeldige publication_date wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige publicatiedatum',
        'abbreviation' => 'TCOP',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een datum met een ongeldige notatie in.
        $spreadsheet->getSheetByName('Editie')
            ->setCellValueExplicit(
                'C2',
                '17-11-2026',
                DataType::TYPE_STRING
            );

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de ongeldige datum afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige publication_date in tabblad Editie, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een onmogelijke publication_date wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onmogelijke publicatiedatum',
        'abbreviation' => 'TCOD',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een kalendertechnisch onmogelijke datum in.
        $spreadsheet->getSheetByName('Editie')
            ->setCellValueExplicit(
                'C2',
                '2026-02-30',
                DataType::TYPE_STRING
            );

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de onmogelijke datum afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige publication_date in tabblad Editie, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een ongeldige currency wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige valutacode',
        'abbreviation' => 'TCOV',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een ongeldige valutacode in.
        $spreadsheet->getSheetByName('Editie')
            ->setCellValueExplicit(
                'D2',
                'EURO',
                DataType::TYPE_STRING
            );

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de ongeldige valutacode afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige currency in tabblad Editie, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met ongeldige notes wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige opmerkingen',
        'abbreviation' => 'TCOO',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een numerieke waarde in bij notes.
        $spreadsheet->getSheetByName('Editie')
            ->setCellValue('E2', 12345);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator numerieke opmerkingen afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige notes in tabblad Editie, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met meerdere edities wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere edities',
        'abbreviation' => 'TCME',
    ]);

    // Maak een geldige cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een tweede cataloguseditie toe op rij 3.
        $editionSheet = $spreadsheet->getSheetByName('Editie');

        $editionSheet->setCellValue('A3', 'Editie 2027');
        $editionSheet->setCellValue('B3', 2027);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator meerdere edities afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Meerdere gegevensrijen in tabblad Editie zijn niet toegestaan'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met geldige optionele editiegegevens wordt geaccepteerd', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geldige editiegegevens',
        'abbreviation' => 'TCGE',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul de optionele velden van het werkblad Editie in.
        $editionSheet = $spreadsheet->getSheetByName('Editie');

        $editionSheet->setCellValueExplicit(
            'C2',
            '2026-11-17',
            DataType::TYPE_STRING
        );

        $editionSheet->setCellValue('D2', 'EUR');

        $editionSheet->setCellValue(
            'E2',
            'Cataloguseditie met aanvullende opmerkingen'
        );

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator geldige gegevens accepteert.
        app(CatalogEditionExchangeValidator::class)
            ->validate($filePath);

        expect(true)->toBeTrue();
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een postzegel zonder name wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende postzegelnaam',
        'abbreviation' => 'TCOP',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een geldige postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel 2026',
        'issue_year' => 2026,
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Verwijder de postzegelnaam uit kolom G.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('G2', null);

        // Sla het gewijzigde bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de ontbrekende naam afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende name in tabblad Postzegels, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te lange postzegelnaam wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange postzegelnaam',
        'abbreviation' => 'TCLP',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een geldige postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel 2026',
        'issue_year' => 2026,
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een postzegelnaam van 201 tekens in.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('G2', str_repeat('A', 201));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange naam afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'name is langer dan 200 tekens in tabblad Postzegels, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een postzegel zonder issue_year wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekend uitgiftejaar',
        'abbreviation' => 'TCOU',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een geldige postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel 2026',
        'issue_year' => 2026,
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Verwijder het uitgiftejaar uit kolom I.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('I2', null);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator het ontbrekende uitgiftejaar afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende issue_year in tabblad Postzegels, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een ongeldig issue_year bij een postzegel wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldig uitgiftejaar',
        'abbreviation' => 'TCOJ',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een geldige postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel 2026',
        'issue_year' => 2026,
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vervang het uitgiftejaar door een ongeldige tekstwaarde.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('I2', 'abc');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator het ongeldige uitgiftejaar afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige issue_year in tabblad Postzegels, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te hoog issue_year bij een postzegel wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te hoog uitgiftejaar',
        'abbreviation' => 'TCHJ',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een geldige postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel 2026',
        'issue_year' => 2026,
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vervang het uitgiftejaar door een waarde boven 65535.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('I2', 65536);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator het te hoge uitgiftejaar afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'issue_year is hoger dan 65535 in tabblad Postzegels, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een ongeldige issue_date bij een postzegel wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige uitgiftedatum',
        'abbreviation' => 'TCOD',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een geldige postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel 2026',
        'issue_year' => 2026,
        'issue_date' => '2026-11-15',
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vervang de uitgiftedatum door een ongeldige notatie.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('J2', '15-11-2026');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de ongeldige datum afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige issue_date in tabblad Postzegels, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een onmogelijke issue_date bij een postzegel wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onmogelijke uitgiftedatum',
        'abbreviation' => 'TCOD',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een geldige postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel 2026',
        'issue_year' => 2026,
        'issue_date' => '2026-02-28',
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vervang de uitgiftedatum door een onmogelijke datum.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('J2', '2026-02-30');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de onmogelijke datum afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige issue_date in tabblad Postzegels, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een historische nominale waarde wordt geaccepteerd', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus historische nominale waarde',
        'abbreviation' => 'TCHN',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een geldige postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel historische nominale waarde',
        'issue_year' => 2026,
        'face_value' => '7½',
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Controleer of de historische waarde correct is geëxporteerd.
        expect($stampsSheet->getCell('K2')->getValue())->toBe('7½');

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de Excel-validator de waarde accepteert.
        app(CatalogEditionExchangeValidator::class)
            ->validate($filePath);
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te lange nominale waarde wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange nominale waarde',
        'abbreviation' => 'TCLN',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een geldige postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel nominale waarde',
        'issue_year' => 2026,
        'face_value' => '7½',
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Vervang de nominale waarde door een tekst van 51 tekens.
        $stampsSheet->setCellValue('K2', str_repeat('A', 51));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange waarde afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'face_value is langer dan 50 tekens in tabblad Postzegels, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te lange nominale waarde bij een bovenliggende postzegel wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bovenliggende nominale waarde',
        'abbreviation' => 'TCBN',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Nederland',
        'slug' => 'nederland',
        'iso_code' => 'NL',
    ]);

    // Maak een bovenliggende postzegel aan.
    $parentStamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bovenliggende testpostzegel',
        'issue_year' => 2026,
        'face_value' => '7½',
    ]);

    // Maak een onderliggende postzegel aan.
    $childStamp = Stamp::create([
        'country_id' => $country->id,
        'parent_stamp_id' => $parentStamp->id,
        'name' => 'Onderliggende testpostzegel',
        'issue_year' => 2026,
        'face_value' => '1½',
    ]);

    // Koppel alleen de onderliggende postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $childStamp->id,
        'catalog_number' => '100',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

        // Controleer of de historische nominale waarde correct is geëxporteerd.
        expect($parentStampsSheet->getCell('I2')->getValue())->toBe('7½');

        // Vervang de nominale waarde door een tekst van 51 tekens.
        $parentStampsSheet->setCellValue('I2', str_repeat('A', 51));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange waarde afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'face_value is langer dan 50 tekens in tabblad BovenliggendePostzegels, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand zonder catalogusnaam wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende catalogusnaam',
        'abbreviation' => 'TCOC',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Maak de verplichte catalogusnaam leeg.
        $spreadsheet->getSheetByName('Catalogus')
            ->setCellValue('A2', null);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de ontbrekende naam afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende name in tabblad Catalogus, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te lange catalogusnaam wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange catalogusnaam',
        'abbreviation' => 'TCLC',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een catalogusnaam van 151 tekens in.
        $spreadsheet->getSheetByName('Catalogus')
            ->setCellValue('A2', str_repeat('A', 151));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange naam afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'name is langer dan 150 tekens in tabblad Catalogus, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te lange catalogusafkorting wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange afkorting',
        'abbreviation' => 'TCLA',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een catalogusafkorting van 31 tekens in.
        $spreadsheet->getSheetByName('Catalogus')
            ->setCellValue('B2', str_repeat('A', 31));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange afkorting afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'abbreviation is langer dan 30 tekens in tabblad Catalogus, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te lange uitgever wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange uitgever',
        'abbreviation' => 'TCLU',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een uitgever van 151 tekens in.
        $spreadsheet->getSheetByName('Catalogus')
            ->setCellValue('C2', str_repeat('A', 151));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange uitgever afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'publisher is langer dan 150 tekens in tabblad Catalogus, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een te lange website wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange website',
        'abbreviation' => 'TCLW',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een website in die langer is dan 255 tekens.
        $spreadsheet->getSheetByName('Catalogus')
            ->setCellValue(
                'D2',
                'https://example.com/'.str_repeat('a', 256)
            );

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange website afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'website is langer dan 255 tekens in tabblad Catalogus, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een ongeldige website wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige website',
        'abbreviation' => 'TCOW',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een ongeldige website in.
        $spreadsheet->getSheetByName('Catalogus')
            ->setCellValue('D2', 'geen-geldige-url');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de ongeldige website afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige website in tabblad Catalogus, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een ongeldige catalogusbeschrijving wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige beschrijving',
        'abbreviation' => 'TCOB',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een numerieke waarde in als catalogusbeschrijving.
        $spreadsheet->getSheetByName('Catalogus')
            ->setCellValueExplicit('E2', 12345, DataType::TYPE_NUMERIC);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de numerieke beschrijving afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige description in tabblad Catalogus, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een landnaam langer dan 100 tekens wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange landnaam',
        'abbreviation' => 'TCLN',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een landnaam van 101 tekens in.
        $spreadsheet->getSheetByName('Landen')
            ->setCellValue('A2', str_repeat('A', 101));

        // Vul een unieke landidentificatie in.
        $spreadsheet->getSheetByName('Landen')
            ->setCellValue('G2', 'test-land-te-lange-naam');

        // Vul een geldig landtype in.
        $spreadsheet->getSheetByName('Landen')
            ->setCellValue('C2', 'country');

        // Vul een geldige actieve status in.
        $spreadsheet->getSheetByName('Landen')
            ->setCellValue('F2', 1);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange landnaam afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Te lange name in tabblad Landen, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een country_slug langer dan 120 tekens wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange landidentificatie',
        'abbreviation' => 'TCLI',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Vul een geldige landnaam in.
        $spreadsheet->getSheetByName('Landen')
            ->setCellValue('A2', 'Testland lange slug');

        // Vul een geldig landtype in.
        $spreadsheet->getSheetByName('Landen')
            ->setCellValue('C2', 'country');

        // Vul een geldige actieve status in.
        $spreadsheet->getSheetByName('Landen')
            ->setCellValue('F2', 1);

        // Vul een landidentificatie van 121 tekens in.
        $spreadsheet->getSheetByName('Landen')
            ->setCellValue('G2', str_repeat('a', 121));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange slug afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Te lange country_slug in tabblad Landen, rij 2'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een vertaalde landnaam langer dan 100 tekens wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange landvertaling',
        'abbreviation' => 'TCLV',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een geldig land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland vertaling');
        $countriesSheet->setCellValue('B2', 'TV');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-vertaling');

        // Voeg een landvertaling toe.
        $translationsSheet = $spreadsheet->getSheetByName('Landvertalingen');

        $translationsSheet->setCellValue('A2', 'testland-vertaling');
        $translationsSheet->setCellValue('B2', 'TV');
        $translationsSheet->setCellValue('C2', 'nl');

        // Gebruik een vertaalde landnaam van 101 tekens.
        $translationsSheet->setCellValue('D2', str_repeat('A', 101));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de validator de te lange naam afwijst.
        expect(
            fn () => app(CatalogEditionExchangeValidator::class)
                ->validate($filePath)
        )->toThrow(
            RuntimeException::class,
            'Te lange name in tabblad Landvertalingen, rij 2'
        );

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});
