<?php

use App\Exports\Exchange\CatalogEditionExchangeExcelExport;
use App\Imports\Exchange\CatalogEditionExchangeImporter;
use App\Models\Catalog;
use App\Models\CatalogConditionRule;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\ConditionTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\SeriesCatalogValue;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\StampSeries;
use App\Models\StampSeriesTranslation;
use App\Models\StampTranslation;
use App\Models\Theme;
use App\Models\ThemeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

test('een Excel-bestand met een overeenkomende catalogusnaam wordt geaccepteerd', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus import',
        'abbreviation' => 'TCI',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    try {
        file_put_contents($filePath, $excelContent);

        // Controleer of de catalogusnaam overeenkomt.
        app(CatalogEditionExchangeImporter::class)
            ->validateCatalog($filePath, $catalog);

        // Controleer of de methode zonder uitzondering is uitgevoerd.
        expect(true)->toBeTrue();
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        unlink($filePath);
    }
});

test('een Excel-bestand met een afwijkende catalogusnaam wordt afgewezen', function () {
    // Maak de geselecteerde catalogus aan.
    $catalog = Catalog::create([
        'name' => 'NVPH',
        'abbreviation' => 'NVPH',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Wijzig de catalogusnaam naar een andere catalogus.
        $spreadsheet->getSheetByName('Catalogus')
            ->setCellValue('A2', 'Michel');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer of de afwijkende catalogusnaam wordt afgewezen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->validateCatalog($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'De catalogusnaam in het Excel-bestand komt niet overeen met de geselecteerde catalogus.'
        );
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een Excel-bestand met een bestaande cataloguseditie wordt afgewezen', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande editie',
        'abbreviation' => 'TCBE',
    ]);

    // Maak een bestaande cataloguseditie aan.
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    try {
        file_put_contents($filePath, $excelContent);

        // Controleer of de bestaande cataloguseditie wordt afgewezen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->validateEditionDoesNotExist($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Deze cataloguseditie bestaat al binnen de geselecteerde catalogus.'
        );
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        unlink($filePath);
    }
});

test('een Excel-bestand met een nieuwe cataloguseditie wordt geaccepteerd', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus nieuwe editie',
        'abbreviation' => 'TCNE',
    ]);

    // Maak een cataloguseditie aan voor het Excel-bestand.
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    try {
        file_put_contents($filePath, $excelContent);

        // Verwijder de bestaande editie uit de testdatabase.
        // De editiegegevens blijven beschikbaar in het Excel-bestand.
        $edition->delete();

        // Controleer of de editie niet meer bestaat.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

        // Controleer of de nieuwe cataloguseditie wordt geaccepteerd.
        app(CatalogEditionExchangeImporter::class)
            ->validateEditionDoesNotExist($filePath, $catalog);

        // Controleer of de validatie geen editie heeft aangemaakt.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        unlink($filePath);
    }
});

test('een nieuwe cataloguseditie wordt succesvol geimporteerd', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus importeren',
        'abbreviation' => 'TCIM',
    ]);

    // Maak een cataloguseditie aan voor het Excel-bestand.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'publication_date' => '2026-01-15',
        'currency' => 'EUR',
        'notes' => 'Test import cataloguseditie',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    try {
        file_put_contents($filePath, $excelContent);

        // Verwijder de oorspronkelijke editie uit de testdatabase.
        $edition->delete();

        // Controleer dat de editie niet meer bestaat.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

        // Importeer de cataloguseditie vanuit Excel.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de geïmporteerde gegevens.
        expect($importedEdition->catalog_id)->toBe($catalog->id)
            ->and($importedEdition->edition_name)->toBe('Editie 2026')
            ->and($importedEdition->edition_year)->toBe(2026)
            ->and($importedEdition->publication_date->format('Y-m-d'))->toBe('2026-01-15')
            ->and($importedEdition->currency)->toBe('EUR')
            ->and($importedEdition->notes)->toBe('Test import cataloguseditie');

        // Controleer dat precies één editie is opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->count()
        )->toBe(1);
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        unlink($filePath);
    }
});

test('een bestaande cataloguseditie wordt niet overschreven tijdens import', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus dubbele import',
        'abbreviation' => 'TCDI',
    ]);

    // Maak een bestaande cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gegevens',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    try {
        file_put_contents($filePath, $excelContent);

        // Wijzig de bestaande editie na het exporteren.
        $edition->update([
            'notes' => 'Deze gegevens mogen niet worden overschreven',
        ]);

        // De import moet de bestaande editie weigeren.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Deze cataloguseditie bestaat al binnen de geselecteerde catalogus.'
        );

        // Controleer dat de oorspronkelijke editie behouden blijft.
        $edition->refresh();

        expect($edition->notes)
            ->toBe('Deze gegevens mogen niet worden overschreven');

        // Controleer dat er geen tweede editie is aangemaakt.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->count()
        )->toBe(1);
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        unlink($filePath);
    }
});

test('een cataloguseditie met lege optionele velden wordt succesvol geimporteerd', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus lege editievelden',
        'abbreviation' => 'TCLE',
    ]);

    // Maak een cataloguseditie zonder optionele gegevens.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'publication_date' => null,
        'currency' => null,
        'notes' => null,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    try {
        file_put_contents($filePath, $excelContent);

        // Verwijder de oorspronkelijke editie.
        $edition->delete();

        // Importeer de cataloguseditie vanuit Excel.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de verplichte gegevens.
        expect($importedEdition->catalog_id)->toBe($catalog->id)
            ->and($importedEdition->edition_name)->toBe('Editie 2026')
            ->and($importedEdition->edition_year)->toBe(2026);

        // Controleer dat de optionele velden NULL blijven.
        expect($importedEdition->publication_date)->toBeNull()
            ->and($importedEdition->currency)->toBeNull()
            ->and($importedEdition->notes)->toBeNull();

        // Controleer dat precies één editie is opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->count()
        )->toBe(1);
    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        unlink($filePath);
    }
});

test('een nieuw land wordt succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus landenimport',
        'abbreviation' => 'TCLI',
    ]);

    // Maak een cataloguseditie aan voor het Excel-bestand.
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een historisch land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland historisch');
        $countriesSheet->setCellValue('B2', 'TL');
        $countriesSheet->setCellValue('C2', 'historical_state');
        $countriesSheet->setCellValue('D2', 1850);
        $countriesSheet->setCellValue('E2', 1900);
        $countriesSheet->setCellValue('F2', 0);
        $countriesSheet->setCellValue('G2', 'testland-historisch');
        $countriesSheet->setCellValue('H2', 'Historisch testland');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Controleer dat het land nog niet bestaat.
        expect(
            Country::query()
                ->where('slug', 'testland-historisch')
                ->exists()
        )->toBeFalse();

        // Importeer de cataloguseditie en bijbehorende landen.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal het geïmporteerde land op.
        $country = Country::query()
            ->where('slug', 'testland-historisch')
            ->first();

        // Controleer de geïmporteerde landgegevens.
        expect($country)->not->toBeNull()
            ->and($country->name)->toBe('Testland historisch')
            ->and($country->iso_code)->toBe('TL')
            ->and($country->entity_type)->toBe('historical_state')
            ->and($country->start_year)->toBe(1850)
            ->and($country->end_year)->toBe(1900)
            ->and($country->is_active)->toBeFalse()
            ->and($country->description)->toBe('Historisch testland');

        // Controleer dat er geen automatische cataloguskoppeling is gemaakt.
        expect(
            $catalog->countries()
                ->where('countries.id', $country->id)
                ->exists()
        )->toBeFalse();
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een bestaand land wordt hergebruikt op basis van de slug', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaand land',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Dit land bestaat al in de database.
    $country = Country::create([
        'name' => 'Bestaand testland',
        'iso_code' => 'BT',
        'entity_type' => 'country',
        'slug' => 'bestaand-testland',
        'description' => 'Oorspronkelijke beschrijving',
        'is_active' => true,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        // De slug, naam en ISO-code komen overeen met het bestaande land.
        $countriesSheet->setCellValue('A2', 'Bestaand testland');
        $countriesSheet->setCellValue('B2', 'BT');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'bestaand-testland');

        // Deze beschrijving mag de bestaande waarde niet overschrijven.
        $countriesSheet->setCellValue('H2', 'Gewijzigde beschrijving uit Excel');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        $edition->delete();

        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer dat de bestaande landrecord behouden blijft.
        $country->refresh();

        expect($country->description)
            ->toBe('Oorspronkelijke beschrijving');

        // Controleer dat er geen duplicaat is aangemaakt.
        expect(
            Country::query()
                ->where('slug', 'bestaand-testland')
                ->count()
        )->toBe(1);

        // Controleer dat de cataloguseditie wel is geïmporteerd.
        expect($importedEdition->edition_name)
            ->toBe('Editie 2026');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('conflicterende landgegevens met dezelfde slug worden afgewezen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus landconflict',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    Country::create([
        'name' => 'Testland origineel',
        'iso_code' => 'TC',
        'entity_type' => 'country',
        'slug' => 'testland-conflict',
        'is_active' => true,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland gewijzigd');
        $countriesSheet->setCellValue('B2', 'TC');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-conflict');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke editie.
        $edition->delete();

        // De import moet worden geweigerd.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Conflicterende landgegevens'
        );

        // De transactie moet de nieuwe editie hebben teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

        // Het oorspronkelijke land moet ongewijzigd blijven.
        expect(
            Country::query()
                ->where('slug', 'testland-conflict')
                ->value('name')
        )->toBe('Testland origineel');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuw land met een bestaande ISO-code wordt afgewezen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ISO-conflict',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Bestaand land met ISO-code LA.
    Country::create([
        'name' => 'Land Alpha',
        'iso_code' => 'LA',
        'entity_type' => 'country',
        'slug' => 'land-alpha',
        'is_active' => true,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        // Nieuw land met een bestaande ISO-code.
        $countriesSheet->setCellValue('A2', 'Land Beta');
        $countriesSheet->setCellValue('B2', 'LA');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'land-beta');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        $edition->delete();

        // De import moet worden geweigerd.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Landnaam of ISO-code bestaat al'
        );

        // Het nieuwe land mag niet zijn aangemaakt.
        expect(
            Country::query()
                ->where('slug', 'land-beta')
                ->exists()
        )->toBeFalse();

        // Het bestaande land moet behouden blijven.
        expect(
            Country::query()
                ->where('slug', 'land-alpha')
                ->value('iso_code')
        )->toBe('LA');

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuw land met een bestaande landnaam wordt afgewezen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus naamconflict',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Bestaand land.
    Country::create([
        'name' => 'Testland Nederland',
        'iso_code' => 'TN',
        'entity_type' => 'country',
        'slug' => 'testland-nederland',
        'is_active' => true,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        // De naam bestaat al, maar de slug is anders.
        $countriesSheet->setCellValue('A2', 'Testland Nederland');
        $countriesSheet->setCellValue('B2', 'TB');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-nederland-nieuw');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        $edition->delete();

        // De import moet worden geweigerd.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Landnaam of ISO-code bestaat al'
        );

        // Het nieuwe land mag niet worden aangemaakt.
        expect(
            Country::query()
                ->where('slug', 'testland-nederland-nieuw')
                ->exists()
        )->toBeFalse();

        // Het oorspronkelijke land moet behouden blijven.
        expect(
            Country::query()
                ->where('slug', 'testland-nederland')
                ->value('iso_code')
        )->toBe('TN');

        // De cataloguseditie moet zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('landen en cataloguseditie worden teruggedraaid bij een conflict tijdens import', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback landen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Bestaand land waarmee rij 3 een ISO-conflict veroorzaakt.
    Country::create([
        'name' => 'Bestaand conflictland',
        'iso_code' => 'CL',
        'entity_type' => 'country',
        'slug' => 'bestaand-conflictland',
        'is_active' => true,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        // Rij 2: geldig nieuw land.
        $countriesSheet->setCellValue('A2', 'Testland Nieuw');
        $countriesSheet->setCellValue('B2', 'NL');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-nieuw');

        // Rij 3: nieuwe slug, maar bestaande ISO-code.
        $countriesSheet->setCellValue('A3', 'Testland Conflict');
        $countriesSheet->setCellValue('B3', 'CL');
        $countriesSheet->setCellValue('C3', 'country');
        $countriesSheet->setCellValue('F3', 1);
        $countriesSheet->setCellValue('G3', 'testland-conflict-nieuw');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken door het ISO-conflict op rij 3.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Landnaam of ISO-code bestaat al'
        );

        // Ook het geldige land uit rij 2 moet zijn teruggedraaid.
        expect(
            Country::query()
                ->where('slug', 'testland-nieuw')
                ->exists()
        )->toBeFalse();

        // Het conflicterende nieuwe land mag niet bestaan.
        expect(
            Country::query()
                ->where('slug', 'testland-conflict-nieuw')
                ->exists()
        )->toBeFalse();

        // Het oorspronkelijke land moet behouden blijven.
        expect(
            Country::query()
                ->where('slug', 'bestaand-conflictland')
                ->value('iso_code')
        )->toBe('CL');

        // De nieuwe cataloguseditie moet zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe landvertaling wordt succesvol geimporteerd vanuit Excel', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus landvertalingen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuw land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland vertalingen');
        $countriesSheet->setCellValue('B2', 'TV');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-vertalingen');

        // Voeg de Nederlandse vertaling toe.
        $translationsSheet = $spreadsheet->getSheetByName('Landvertalingen');

        $translationsSheet->setCellValue('A2', 'testland-vertalingen');
        $translationsSheet->setCellValue('B2', 'TV');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Testland Nederlands');
        $translationsSheet->setCellValue('E2', 'Nederlandse beschrijving');

        // Sla het Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer of het land is geïmporteerd.
        $country = Country::query()
            ->where('slug', 'testland-vertalingen')
            ->first();

        expect($country)->not->toBeNull();

        // Zoek de Nederlandse vertaling.
        $translation = CountryTranslation::query()
            ->where('country_id', $country->id)
            ->where('locale', 'nl')
            ->first();

        // Controleer de vertaling.
        expect($translation)->not->toBeNull()
            ->and($translation->name)->toBe('Testland Nederlands')
            ->and($translation->description)->toBe('Nederlandse beschrijving');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een bestaande landvertaling wordt niet overschreven tijdens import', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande landvertaling',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'name' => 'Testland vertalingen',
        'iso_code' => 'TV',
        'entity_type' => 'country',
        'slug' => 'testland-vertalingen',
        'is_active' => true,
    ]);

    // Maak een bestaande Nederlandse vertaling aan.
    $translation = CountryTranslation::create([
        'country_id' => $country->id,
        'locale' => 'nl',
        'name' => 'Oorspronkelijke naam',
        'description' => 'Oorspronkelijke beschrijving',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het bestaande land toe aan het Excel-bestand.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland vertalingen');
        $countriesSheet->setCellValue('B2', 'TV');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-vertalingen');

        // Voeg gewijzigde vertaalgegevens toe.
        $translationsSheet = $spreadsheet->getSheetByName('Landvertalingen');

        $translationsSheet->setCellValue('A2', 'testland-vertalingen');
        $translationsSheet->setCellValue('B2', 'TV');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Gewijzigde naam');
        $translationsSheet->setCellValue('E2', 'Gewijzigde beschrijving');

        // Sla het Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer het Excel-bestand.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de oorspronkelijke vertaling opnieuw op.
        $translation->refresh();

        // Controleer dat de gegevens niet zijn overschreven.
        expect($translation->name)
            ->toBe('Oorspronkelijke naam');

        expect($translation->description)
            ->toBe('Oorspronkelijke beschrijving');

        // Controleer dat er geen dubbele vertaling is aangemaakt.
        expect(
            CountryTranslation::query()
                ->where('country_id', $country->id)
                ->where('locale', 'nl')
                ->count()
        )->toBe(1);

        // Controleer dat de cataloguseditie wel is geïmporteerd.
        expect($importedEdition->edition_name)
            ->toBe('Editie 2026');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere landvertalingen worden succesvol geimporteerd vanuit Excel', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meertalige landen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuw land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland meertalig');
        $countriesSheet->setCellValue('B2', 'TM');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-meertalig');

        // Voeg vijf vertalingen toe.
        $translationsSheet = $spreadsheet->getSheetByName('Landvertalingen');

        $translations = [
            ['nl', 'Testland Nederlands'],
            ['en', 'Test Country'],
            ['de', 'Testland Deutsch'],
            ['fr', 'Pays de test'],
            ['es', 'País de prueba'],
        ];

        foreach ($translations as $index => [$locale, $name]) {
            $row = $index + 2;

            $translationsSheet->setCellValue('A'.$row, 'testland-meertalig');
            $translationsSheet->setCellValue('B'.$row, 'TM');
            $translationsSheet->setCellValue('C'.$row, $locale);
            $translationsSheet->setCellValue('D'.$row, $name);
        }

        // Sla het aangepaste Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer het Excel-bestand.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Zoek het geïmporteerde land.
        $country = Country::query()
            ->where('slug', 'testland-meertalig')
            ->first();

        expect($country)->not->toBeNull();

        // Controleer alle vijf vertalingen.
        foreach ($translations as [$locale, $name]) {
            $translation = CountryTranslation::query()
                ->where('country_id', $country->id)
                ->where('locale', $locale)
                ->first();

            expect($translation)->not->toBeNull();
            expect($translation->name)->toBe($name);
        }

        // Controleer het totale aantal vertalingen.
        expect(
            CountryTranslation::query()
                ->where('country_id', $country->id)
                ->count()
        )->toBe(5);

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('landen vertalingen en cataloguseditie worden teruggedraaid bij een vertaalconflict', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback landvertalingen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuw land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland rollback');
        $countriesSheet->setCellValue('B2', 'TR');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-rollback');

        // Voeg twee landvertalingen toe.
        $translationsSheet = $spreadsheet->getSheetByName('Landvertalingen');

        // Rij 2: geldige Nederlandse vertaling.
        $translationsSheet->setCellValue('A2', 'testland-rollback');
        $translationsSheet->setCellValue('B2', 'TR');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Testland Nederlands');

        // Rij 3: ongeldige ISO-code.
        $translationsSheet->setCellValue('A3', 'testland-rollback');
        $translationsSheet->setCellValue('B3', 'XX');
        $translationsSheet->setCellValue('C3', 'en');
        $translationsSheet->setCellValue('D3', 'Test Country');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken door de ongeldige ISO-code.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(RuntimeException::class);

        // Het nieuwe land mag niet bestaan.
        expect(
            Country::query()
                ->where('slug', 'testland-rollback')
                ->exists()
        )->toBeFalse();

        // De Nederlandse vertaling mag niet bestaan.
        expect(
            CountryTranslation::query()
                ->where('locale', 'nl')
                ->where('name', 'Testland Nederlands')
                ->exists()
        )->toBeFalse();

        // De Engelse vertaling mag niet bestaan.
        expect(
            CountryTranslation::query()
                ->where('locale', 'en')
                ->where('name', 'Test Country')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('landen vertalingen en cataloguseditie worden teruggedraaid bij een fout tijdens het opslaan', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus database rollback landvertalingen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuw land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland database rollback');
        $countriesSheet->setCellValue('B2', 'DR');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-database-rollback');

        // Voeg twee geldige landvertalingen toe.
        $translationsSheet = $spreadsheet->getSheetByName('Landvertalingen');

        // Nederlandse vertaling.
        $translationsSheet->setCellValue('A2', 'testland-database-rollback');
        $translationsSheet->setCellValue('B2', 'DR');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Testland Nederlands rollback');

        // Engelse vertaling.
        $translationsSheet->setCellValue('A3', 'testland-database-rollback');
        $translationsSheet->setCellValue('B3', 'DR');
        $translationsSheet->setCellValue('C3', 'en');
        $translationsSheet->setCellValue('D3', 'Test Country rollback');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Bewaar de oorspronkelijke Eloquent-event-dispatcher.
        $originalDispatcher = CountryTranslation::getEventDispatcher();

        // Gebruik een afzonderlijke dispatcher voor deze test.
        $testDispatcher = clone $originalDispatcher;

        CountryTranslation::setEventDispatcher($testDispatcher);

        // Simuleer een fout bij het opslaan van de Engelse vertaling.
        CountryTranslation::creating(function (CountryTranslation $translation) {
            if ($translation->locale === 'en') {
                throw new RuntimeException(
                    'Gesimuleerde fout tijdens opslaan Engelse landvertaling.'
                );
            }
        });

        // De import moet mislukken tijdens de database-transactie.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens opslaan Engelse landvertaling.'
        );

        // Het nieuwe land moet zijn teruggedraaid.
        expect(
            Country::query()
                ->where('slug', 'testland-database-rollback')
                ->exists()
        )->toBeFalse();

        // De Nederlandse vertaling moet zijn teruggedraaid.
        expect(
            CountryTranslation::query()
                ->where('locale', 'nl')
                ->where('name', 'Testland Nederlands rollback')
                ->exists()
        )->toBeFalse();

        // De Engelse vertaling mag niet bestaan.
        expect(
            CountryTranslation::query()
                ->where('locale', 'en')
                ->where('name', 'Test Country rollback')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie moet zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        // Herstel de oorspronkelijke Eloquent-event-dispatcher.
        if (isset($originalDispatcher)) {
            CountryTranslation::setEventDispatcher($originalDispatcher);
        }

        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe waarderingscategorie wordt succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus waarderingscategorieenimport',
        'abbreviation' => 'TCWI',
    ]);

    // Maak een cataloguseditie aan voor het Excel-bestand.
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuwe waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', 'test-bijzondere-conditie');
        $conditionsSheet->setCellValue('B2', 25);
        $conditionsSheet->setCellValue('C2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Controleer dat de waarderingscategorie nog niet bestaat.
        expect(
            Condition::query()
                ->where('slug', 'test-bijzondere-conditie')
                ->exists()
        )->toBeFalse();

        // Importeer de cataloguseditie en waarderingscategorieën.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de geïmporteerde waarderingscategorie op.
        $condition = Condition::query()
            ->where('slug', 'test-bijzondere-conditie')
            ->first();

        // Controleer de geïmporteerde gegevens.
        expect($condition)->not->toBeNull()
            ->and($condition->sort_order)->toBe(25)
            ->and($condition->is_active)->toBeFalse();
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een bestaande waarderingscategorie wordt hergebruikt op basis van de slug', function () {
    // Maak een bestaande waarderingscategorie aan.
    $existingCondition = Condition::create([
        'slug' => 'test-bestaande-conditie',
        'sort_order' => 5,
        'is_active' => true,
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande waarderingscategorie',
    ]);

    // Maak een cataloguseditie aan voor het Excel-bestand.
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg de bestaande waarderingscategorie toe aan Excel,
        // maar met afwijkende gegevens.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', 'test-bestaande-conditie');
        $conditionsSheet->setCellValue('B2', 25);
        $conditionsSheet->setCellValue('C2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de bestaande waarderingscategorie opnieuw op.
        $condition = Condition::query()
            ->where('slug', 'test-bestaande-conditie')
            ->first();

        // Controleer dat de bestaande gegevens behouden zijn.
        expect($condition)->not->toBeNull()
            ->and($condition->id)->toBe($existingCondition->id)
            ->and($condition->sort_order)->toBe(5)
            ->and($condition->is_active)->toBeTrue();

        // Controleer dat er geen dubbele categorie is aangemaakt.
        expect(
            Condition::query()
                ->where('slug', 'test-bestaande-conditie')
                ->count()
        )->toBe(1);
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe waarderingsvertaling wordt succesvol geimporteerd vanuit Excel', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus waarderingsvertalingen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuwe waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', 'test-waarderingsvertaling');
        $conditionsSheet->setCellValue('B2', 25);
        $conditionsSheet->setCellValue('C2', 1);

        // Voeg een Nederlandse vertaling toe.
        $translationsSheet = $spreadsheet->getSheetByName('Waarderingsvertalingen');

        $translationsSheet->setCellValue('A2', 'test-waarderingsvertaling');
        $translationsSheet->setCellValue('B2', 'nl');
        $translationsSheet->setCellValue('C2', 'Testwaardering Nederlands');
        $translationsSheet->setCellValue('D2', 'Nederlandse beschrijving');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Zoek de geïmporteerde waarderingscategorie.
        $condition = Condition::query()
            ->where('slug', 'test-waarderingsvertaling')
            ->first();

        expect($condition)->not->toBeNull();

        // Zoek de Nederlandse vertaling.
        $translation = ConditionTranslation::query()
            ->where('condition_id', $condition->id)
            ->where('locale', 'nl')
            ->first();

        // Controleer de geïmporteerde vertaling.
        expect($translation)->not->toBeNull()
            ->and($translation->name)->toBe('Testwaardering Nederlands')
            ->and($translation->description)->toBe('Nederlandse beschrijving');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een bestaande waarderingsvertaling wordt niet overschreven tijdens import', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande waarderingsvertaling',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaande waarderingscategorie aan.
    $condition = Condition::create([
        'slug' => 'test-bestaande-waarderingsvertaling',
        'sort_order' => 25,
        'is_active' => true,
    ]);

    // Maak een bestaande Nederlandse vertaling aan.
    $translation = ConditionTranslation::create([
        'condition_id' => $condition->id,
        'locale' => 'nl',
        'name' => 'Oorspronkelijke naam',
        'description' => 'Oorspronkelijke beschrijving',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de bestaande waarderingscategorie toe aan Excel.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', 'test-bestaande-waarderingsvertaling');
        $conditionsSheet->setCellValue('B2', 25);
        $conditionsSheet->setCellValue('C2', 1);

        // Voeg gewijzigde vertaalgegevens toe.
        $translationsSheet = $spreadsheet->getSheetByName('Waarderingsvertalingen');

        $translationsSheet->setCellValue('A2', 'test-bestaande-waarderingsvertaling');
        $translationsSheet->setCellValue('B2', 'nl');
        $translationsSheet->setCellValue('C2', 'Gewijzigde naam');
        $translationsSheet->setCellValue('D2', 'Gewijzigde beschrijving');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer het Excel-bestand.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de oorspronkelijke vertaling opnieuw op.
        $translation->refresh();

        // Controleer dat de naam niet is overschreven.
        expect($translation->name)
            ->toBe('Oorspronkelijke naam');

        // Controleer dat de beschrijving niet is overschreven.
        expect($translation->description)
            ->toBe('Oorspronkelijke beschrijving');

        // Controleer dat er geen dubbele vertaling is aangemaakt.
        expect(
            ConditionTranslation::query()
                ->where('condition_id', $condition->id)
                ->where('locale', 'nl')
                ->count()
        )->toBe(1);

        // Controleer dat de cataloguseditie wel is geïmporteerd.
        expect($importedEdition->edition_name)
            ->toBe('Editie 2026');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe algemene waarderingsregel wordt succesvol geimporteerd vanuit Excel', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus algemene waarderingsregel',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een waarderingscategorie aan.
    $condition = Condition::create([
        'slug' => 'test-algemene-waarderingsregel',
        'sort_order' => 25,
        'is_active' => true,
    ]);

    // Exporteer de cataloguseditie naar Excel.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe aan Excel.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', 'test-algemene-waarderingsregel');
        $conditionsSheet->setCellValue('B2', 25);
        $conditionsSheet->setCellValue('C2', 1);

        // Voeg een algemene waarderingsregel toe.
        $rulesSheet = $spreadsheet->getSheetByName('Waarderingsregels');

        // Geen land: country_slug en country_iso_code blijven leeg.
        $rulesSheet->setCellValue('A2', null);
        $rulesSheet->setCellValue('B2', null);

        // Waarderingscategorie.
        $rulesSheet->setCellValue('C2', 'test-algemene-waarderingsregel');

        // Beginjaar, eindjaar en sorteervolgorde.
        $rulesSheet->setCellValue('D2', null);
        $rulesSheet->setCellValue('E2', null);
        $rulesSheet->setCellValue('F2', 1);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie inclusief waarderingsregel.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Zoek de geïmporteerde waarderingsregel.
        $rule = CatalogConditionRule::query()
            ->where('catalog_id', $catalog->id)
            ->where('condition_id', $condition->id)
            ->first();

        // Controleer de geïmporteerde gegevens.
        expect($rule)->not->toBeNull()
            ->and($rule->catalog_id)->toBe($catalog->id)
            ->and($rule->country_id)->toBeNull()
            ->and($rule->condition_id)->toBe($condition->id)
            ->and($rule->issue_year_from)->toBeNull()
            ->and($rule->issue_year_to)->toBeNull()
            ->and($rule->sort_order)->toBe(1);

        // Controleer dat de cataloguseditie is geïmporteerd.
        expect($importedEdition->edition_name)
            ->toBe('Editie 2026');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een bestaande algemene waarderingsregel wordt niet dubbel aangemaakt tijdens import', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande algemene waarderingsregel',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaande waarderingscategorie aan.
    $condition = Condition::create([
        'slug' => 'test-bestaande-algemene-waarderingsregel',
        'sort_order' => 25,
        'is_active' => true,
    ]);

    // Maak een bestaande algemene waarderingsregel aan.
    $existingRule = CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => null,
        'condition_id' => $condition->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2025,
        'sort_order' => 1,
    ]);

    // Exporteer de cataloguseditie naar Excel.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Controleer of de bestaande waarderingsregel is geëxporteerd.
        $rulesSheet = $spreadsheet->getSheetByName('Waarderingsregels');

        expect($rulesSheet->getCell('C2')->getValue())
            ->toBe('test-bestaande-algemene-waarderingsregel');

        // Sluit het Excel-bestand.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer het Excel-bestand.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer dat de bestaande regel behouden blijft.
        $existingRule->refresh();

        expect($existingRule->catalog_id)->toBe($catalog->id)
            ->and($existingRule->country_id)->toBeNull()
            ->and($existingRule->condition_id)->toBe($condition->id)
            ->and($existingRule->issue_year_from)->toBe(2020)
            ->and($existingRule->issue_year_to)->toBe(2025)
            ->and($existingRule->sort_order)->toBe(1);

        // Controleer dat er geen dubbele regel is aangemaakt.
        expect(
            CatalogConditionRule::query()
                ->where('catalog_id', $catalog->id)
                ->where('condition_id', $condition->id)
                ->count()
        )->toBe(1);

        // Controleer dat de cataloguseditie is geïmporteerd.
        expect($importedEdition->edition_name)
            ->toBe('Editie 2026');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe landgebonden waarderingsregel wordt succesvol geimporteerd vanuit Excel', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus landgebonden waarderingsregel',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een waarderingscategorie aan.
    $condition = Condition::create([
        'slug' => 'test-landgebonden-waarderingsregel',
        'sort_order' => 25,
        'is_active' => true,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuw land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland historisch');
        $countriesSheet->setCellValue('B2', 'TL');
        $countriesSheet->setCellValue('C2', 'historical_state');
        $countriesSheet->setCellValue('D2', 1850);
        $countriesSheet->setCellValue('E2', 1900);
        $countriesSheet->setCellValue('F2', 0);
        $countriesSheet->setCellValue('G2', 'testland-historisch');
        $countriesSheet->setCellValue('H2', 'Historisch testland');

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', 'test-landgebonden-waarderingsregel');
        $conditionsSheet->setCellValue('B2', 25);
        $conditionsSheet->setCellValue('C2', 1);

        // Voeg een landgebonden waarderingsregel toe.
        $rulesSheet = $spreadsheet->getSheetByName('Waarderingsregels');

        $rulesSheet->setCellValue('A2', 'testland-historisch');
        $rulesSheet->setCellValue('B2', 'TL');
        $rulesSheet->setCellValue('C2', 'test-landgebonden-waarderingsregel');
        $rulesSheet->setCellValue('D2', 1850);
        $rulesSheet->setCellValue('E2', 1900);
        $rulesSheet->setCellValue('F2', 1);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer het Excel-bestand.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Zoek het geïmporteerde land.
        $country = Country::query()
            ->where('slug', 'testland-historisch')
            ->first();

        expect($country)->not->toBeNull()
            ->and($country->iso_code)->toBe('TL');

        // Zoek de geïmporteerde waarderingsregel.
        $rule = CatalogConditionRule::query()
            ->where('catalog_id', $catalog->id)
            ->where('condition_id', $condition->id)
            ->first();

        // Controleer de geïmporteerde waarderingsregel.
        expect($rule)->not->toBeNull()
            ->and($rule->catalog_id)->toBe($catalog->id)
            ->and($rule->country_id)->toBe($country->id)
            ->and($rule->condition_id)->toBe($condition->id)
            ->and($rule->issue_year_from)->toBe(1850)
            ->and($rule->issue_year_to)->toBe(1900)
            ->and($rule->sort_order)->toBe(1);

        // Controleer de geïmporteerde cataloguseditie.
        expect($importedEdition->edition_name)
            ->toBe('Editie 2026');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere algemene waarderingsregels worden succesvol geimporteerd vanuit Excel', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere waarderingsregels',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak twee waarderingscategorieën aan.
    $conditionPostfris = Condition::create([
        'slug' => 'test-postfris-meerdere-regels',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $conditionGestempeld = Condition::create([
        'slug' => 'test-gestempeld-meerdere-regels',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Exporteer de cataloguseditie naar Excel.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg beide waarderingscategorieën toe aan Excel.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', 'test-postfris-meerdere-regels');
        $conditionsSheet->setCellValue('B2', 1);
        $conditionsSheet->setCellValue('C2', 1);

        $conditionsSheet->setCellValue('A3', 'test-gestempeld-meerdere-regels');
        $conditionsSheet->setCellValue('B3', 2);
        $conditionsSheet->setCellValue('C3', 1);

        // Voeg twee algemene waarderingsregels toe.
        $rulesSheet = $spreadsheet->getSheetByName('Waarderingsregels');

        // Eerste regel: postfris.
        $rulesSheet->setCellValue('C2', 'test-postfris-meerdere-regels');
        $rulesSheet->setCellValue('D2', 2020);
        $rulesSheet->setCellValue('E2', 2025);
        $rulesSheet->setCellValue('F2', 1);

        // Tweede regel: gestempeld.
        $rulesSheet->setCellValue('C3', 'test-gestempeld-meerdere-regels');
        $rulesSheet->setCellValue('D3', 2020);
        $rulesSheet->setCellValue('E3', 2025);
        $rulesSheet->setCellValue('F3', 2);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer het Excel-bestand.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de geïmporteerde waarderingsregels op.
        $rules = CatalogConditionRule::query()
            ->where('catalog_id', $catalog->id)
            ->orderBy('sort_order')
            ->get();

        // Controleer het aantal regels.
        expect($rules)->toHaveCount(2);

        // Controleer de eerste waarderingsregel.
        expect($rules[0]->condition_id)->toBe($conditionPostfris->id)
            ->and($rules[0]->country_id)->toBeNull()
            ->and($rules[0]->issue_year_from)->toBe(2020)
            ->and($rules[0]->issue_year_to)->toBe(2025)
            ->and($rules[0]->sort_order)->toBe(1);

        // Controleer de tweede waarderingsregel.
        expect($rules[1]->condition_id)->toBe($conditionGestempeld->id)
            ->and($rules[1]->country_id)->toBeNull()
            ->and($rules[1]->issue_year_from)->toBe(2020)
            ->and($rules[1]->issue_year_to)->toBe(2025)
            ->and($rules[1]->sort_order)->toBe(2);

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('waarderingsregels en cataloguseditie worden teruggedraaid bij een fout tijdens het opslaan', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback waarderingsregels',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak twee waarderingscategorieën aan.
    $conditionOne = Condition::create([
        'slug' => 'test-rollback-waarderingsregel-een',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $conditionTwo = Condition::create([
        'slug' => 'test-rollback-waarderingsregel-twee',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg beide waarderingscategorieën toe aan Excel.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', 'test-rollback-waarderingsregel-een');
        $conditionsSheet->setCellValue('B2', 1);
        $conditionsSheet->setCellValue('C2', 1);

        $conditionsSheet->setCellValue('A3', 'test-rollback-waarderingsregel-twee');
        $conditionsSheet->setCellValue('B3', 2);
        $conditionsSheet->setCellValue('C3', 1);

        // Voeg twee geldige waarderingsregels toe.
        $rulesSheet = $spreadsheet->getSheetByName('Waarderingsregels');

        // Eerste waarderingsregel.
        $rulesSheet->setCellValue('C2', 'test-rollback-waarderingsregel-een');
        $rulesSheet->setCellValue('D2', 2020);
        $rulesSheet->setCellValue('E2', 2025);
        $rulesSheet->setCellValue('F2', 1);

        // Tweede waarderingsregel.
        $rulesSheet->setCellValue('C3', 'test-rollback-waarderingsregel-twee');
        $rulesSheet->setCellValue('D3', 2020);
        $rulesSheet->setCellValue('E3', 2025);
        $rulesSheet->setCellValue('F3', 2);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Bewaar de oorspronkelijke Eloquent-event-dispatcher.
        $originalDispatcher = CatalogConditionRule::getEventDispatcher();

        // Gebruik een afzonderlijke dispatcher voor deze test.
        $testDispatcher = clone $originalDispatcher;

        CatalogConditionRule::setEventDispatcher($testDispatcher);

        // Simuleer een fout bij het opslaan van de tweede waarderingsregel.
        CatalogConditionRule::creating(function (CatalogConditionRule $rule) use ($conditionTwo) {
            if ($rule->condition_id === $conditionTwo->id) {
                throw new RuntimeException(
                    'Gesimuleerde fout tijdens opslaan tweede waarderingsregel.'
                );
            }
        });

        // De import moet mislukken tijdens de database-transactie.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens opslaan tweede waarderingsregel.'
        );

        // De eerste waarderingsregel moet zijn teruggedraaid.
        expect(
            CatalogConditionRule::query()
                ->where('catalog_id', $catalog->id)
                ->where('condition_id', $conditionOne->id)
                ->exists()
        )->toBeFalse();

        // De tweede waarderingsregel mag niet bestaan.
        expect(
            CatalogConditionRule::query()
                ->where('catalog_id', $catalog->id)
                ->where('condition_id', $conditionTwo->id)
                ->exists()
        )->toBeFalse();

        // Er mogen geen waarderingsregels voor deze catalogus bestaan.
        expect(
            CatalogConditionRule::query()
                ->where('catalog_id', $catalog->id)
                ->count()
        )->toBe(0);

        // De cataloguseditie moet eveneens zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        // Herstel de oorspronkelijke Eloquent-event-dispatcher.
        if (isset($originalDispatcher)) {
            CatalogConditionRule::setEventDispatcher($originalDispatcher);
        }

        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe postzegelserie wordt succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus postzegelseriesimport',
        'abbreviation' => 'TPSI',
    ]);

    // Maak een cataloguseditie aan voor het Excel-bestand.
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een testland toe aan het werkblad Landen.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland Postzegelseries');
        $countriesSheet->setCellValue('B2', 'TS');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-postzegelseries');

        // Voeg een nieuwe postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 1001);
        $seriesSheet->setCellValue('B2', 'testland-postzegelseries');
        $seriesSheet->setCellValue('C2', 'TS');
        $seriesSheet->setCellValue('D2', 'Testserie Bloemen');
        $seriesSheet->setCellValue('E2', 2025);
        $seriesSheet->setCellValue('F2', 'Een serie met bloemen.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Controleer dat de postzegelserie nog niet bestaat.
        expect(
            StampSeries::query()
                ->where('name', 'Testserie Bloemen')
                ->exists()
        )->toBeFalse();

        // Importeer de cataloguseditie en postzegelserie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de geïmporteerde postzegelserie op.
        $series = StampSeries::query()
            ->where('name', 'Testserie Bloemen')
            ->first();

        // Controleer de geïmporteerde gegevens.
        expect($series)->not->toBeNull()
            ->and($series->country->slug)->toBe('testland-postzegelseries')
            ->and($series->issue_year)->toBe(2025)
            ->and($series->description)->toBe('Een serie met bloemen.');

        // Controleer of de cataloguseditie is geïmporteerd.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_year', 2026)
                ->exists()
        )->toBeTrue();
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een bestaande postzegelserie wordt hergebruikt tijdens import', function () {
    // Maak een bestaand land aan.
    $country = Country::create([
        'name' => 'Testland Bestaande Series',
        'iso_code' => 'BS',
        'entity_type' => 'country',
        'slug' => 'testland-bestaande-series',
        'is_active' => true,
    ]);

    // Maak een bestaande postzegelserie aan.
    $existingSeries = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie Vogels',
        'issue_year' => 2025,
        'description' => 'Oorspronkelijke beschrijving.',
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande postzegelseries',
    ]);

    // Koppel het land aan de catalogus.
    $catalog->countries()->attach($country->id);

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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg de bestaande postzegelserie toe aan Excel.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 1002);
        $seriesSheet->setCellValue('B2', 'testland-bestaande-series');
        $seriesSheet->setCellValue('C2', 'BS');
        $seriesSheet->setCellValue('D2', 'Testserie Vogels');
        $seriesSheet->setCellValue('E2', 2025);

        // Gebruik bewust een afwijkende beschrijving.
        $seriesSheet->setCellValue(
            'F2',
            'Nieuwe beschrijving vanuit Excel.'
        );

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de bestaande postzegelserie opnieuw op.
        $series = StampSeries::query()
            ->where('country_id', $country->id)
            ->where('name', 'Testserie Vogels')
            ->where('issue_year', 2025)
            ->first();

        // Controleer dat de bestaande serie is hergebruikt.
        expect($series)->not->toBeNull()
            ->and($series->id)->toBe($existingSeries->id)
            ->and($series->description)->toBe('Oorspronkelijke beschrijving.');

        // Controleer dat er geen dubbele serie is aangemaakt.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie Vogels')
                ->where('issue_year', 2025)
                ->count()
        )->toBe(1);

        // Controleer dat de cataloguseditie is geïmporteerd.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_year', 2026)
                ->exists()
        )->toBeTrue();
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere bestaande postzegelseries met dezelfde gegevens worden geweigerd', function () {
    // Maak een testland aan.
    $country = Country::create([
        'name' => 'Testland Dubbele Series',
        'iso_code' => 'DS',
        'entity_type' => 'country',
        'slug' => 'testland-dubbele-series',
        'is_active' => true,
    ]);

    // Maak twee postzegelseries met dezelfde identificerende gegevens.
    for ($i = 1; $i <= 2; $i++) {
        StampSeries::create([
            'country_id' => $country->id,
            'name' => 'Testserie Dieren',
            'issue_year' => 2025,
            'description' => 'Beschrijving '.$i,
        ]);
    }

    // Maak een catalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus dubbele postzegelseries',
    ]);

    $catalog->countries()->attach($country->id);

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

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg de postzegelserie toe aan Excel.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 1003);
        $seriesSheet->setCellValue('B2', 'testland-dubbele-series');
        $seriesSheet->setCellValue('C2', 'DS');
        $seriesSheet->setCellValue('D2', 'Testserie Dieren');
        $seriesSheet->setCellValue('E2', 2025);
        $seriesSheet->setCellValue('F2', 'Beschrijving vanuit Excel.');

        // Sla het Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De importer moet meerdere overeenkomende series weigeren.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Meerdere bestaande postzegelseries gevonden'
        );

        // Controleer dat de mislukte import geen editie heeft aangemaakt.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

        // De twee oorspronkelijke series moeten behouden blijven.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie Dieren')
                ->where('issue_year', 2025)
                ->count()
        )->toBe(2);
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe postzegelserievertaling wordt succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus serievertalingen',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een testland toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland Serievertalingen');
        $countriesSheet->setCellValue('B2', 'SV');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-serievertalingen');

        // Voeg een nieuwe postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 2001);
        $seriesSheet->setCellValue('B2', 'testland-serievertalingen');
        $seriesSheet->setCellValue('C2', 'SV');
        $seriesSheet->setCellValue('D2', 'Testserie Bloemen');
        $seriesSheet->setCellValue('E2', 2025);
        $seriesSheet->setCellValue('F2', 'Nederlandse beschrijving.');

        // Voeg een Engelse serievertaling toe.
        $translationsSheet = $spreadsheet->getSheetByName('Serievertalingen');

        $translationsSheet->setCellValue('A2', 2001);
        $translationsSheet->setCellValue('B2', 'en');
        $translationsSheet->setCellValue('C2', 'Flower Series');
        $translationsSheet->setCellValue('D2', 'A series featuring flowers.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de geïmporteerde postzegelserie op.
        $series = StampSeries::query()
            ->where('name', 'Testserie Bloemen')
            ->first();

        expect($series)->not->toBeNull();

        // Haal de Engelse vertaling op.
        $translation = StampSeriesTranslation::query()
            ->where('stamp_series_id', $series->id)
            ->where('locale', 'en')
            ->first();

        // Controleer de geïmporteerde vertaling.
        expect($translation)->not->toBeNull()
            ->and($translation->stamp_series_id)->toBe($series->id)
            ->and($translation->locale)->toBe('en')
            ->and($translation->name)->toBe('Flower Series')
            ->and($translation->description)->toBe('A series featuring flowers.');

        // Controleer of de cataloguseditie is geïmporteerd.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_year', 2026)
                ->exists()
        )->toBeTrue();
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een bestaande postzegelserievertaling wordt niet overschreven tijdens import', function () {
    // Maak een bestaand land aan.
    $country = Country::create([
        'name' => 'Testland Bestaande Serievertalingen',
        'iso_code' => 'BV',
        'entity_type' => 'country',
        'slug' => 'testland-bestaande-serievertalingen',
        'is_active' => true,
    ]);

    // Maak een bestaande postzegelserie aan.
    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie Vogels',
        'issue_year' => 2025,
        'description' => 'Nederlandse seriebeschrijving.',
    ]);

    // Maak een bestaande Engelse vertaling aan.
    $translation = StampSeriesTranslation::create([
        'stamp_series_id' => $series->id,
        'locale' => 'en',
        'name' => 'Original Bird Series',
        'description' => 'Original English description.',
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande serievertalingen',
    ]);

    // Koppel het land aan de catalogus.
    $catalog->countries()->attach($country->id);

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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg de bestaande postzegelserie toe aan Excel.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 2002);
        $seriesSheet->setCellValue('B2', 'testland-bestaande-serievertalingen');
        $seriesSheet->setCellValue('C2', 'BV');
        $seriesSheet->setCellValue('D2', 'Testserie Vogels');
        $seriesSheet->setCellValue('E2', 2025);
        $seriesSheet->setCellValue('F2', 'Nederlandse seriebeschrijving.');

        // Voeg afwijkende Engelse vertaalgegevens toe.
        $translationsSheet = $spreadsheet->getSheetByName('Serievertalingen');

        $translationsSheet->setCellValue('A2', 2002);
        $translationsSheet->setCellValue('B2', 'en');
        $translationsSheet->setCellValue('C2', 'Modified Bird Series');
        $translationsSheet->setCellValue('D2', 'Modified English description.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de bestaande vertaling opnieuw op.
        $translation->refresh();

        // Controleer dat het oorspronkelijke ID behouden blijft.
        expect($translation->stamp_series_id)
            ->toBe($series->id);

        // Controleer dat de oorspronkelijke naam behouden blijft.
        expect($translation->name)
            ->toBe('Original Bird Series');

        // Controleer dat de beschrijving niet is overschreven.
        expect($translation->description)
            ->toBe('Original English description.');

        // Controleer dat er geen dubbele vertaling is aangemaakt.
        expect(
            StampSeriesTranslation::query()
                ->where('stamp_series_id', $series->id)
                ->where('locale', 'en')
                ->count()
        )->toBe(1);

        // Controleer dat de cataloguseditie is geïmporteerd.
        expect($importedEdition->edition_name)
            ->toBe('Editie 2026');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere postzegelserievertalingen worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meertalige postzegelseries',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een testland toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland Meertalige Series');
        $countriesSheet->setCellValue('B2', 'MS');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-meertalige-series');

        // Voeg een nieuwe postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 2003);
        $seriesSheet->setCellValue('B2', 'testland-meertalige-series');
        $seriesSheet->setCellValue('C2', 'MS');
        $seriesSheet->setCellValue('D2', 'Testserie Natuur');
        $seriesSheet->setCellValue('E2', 2025);
        $seriesSheet->setCellValue('F2', 'Een serie over de natuur.');

        // Definieer vijf vertalingen.
        $translations = [
            ['nl', 'Natuurserie', 'Een serie over de natuur.'],
            ['en', 'Nature Series', 'A series about nature.'],
            ['de', 'Naturserie', 'Eine Serie über die Natur.'],
            ['fr', 'Série Nature', 'Une série sur la nature.'],
            ['es', 'Serie Naturaleza', 'Una serie sobre la naturaleza.'],
        ];

        // Voeg de vijf vertalingen toe aan Excel.
        $translationsSheet = $spreadsheet->getSheetByName('Serievertalingen');

        foreach ($translations as $index => [$locale, $name, $description]) {
            $row = $index + 2;

            $translationsSheet->setCellValue('A'.$row, 2003);
            $translationsSheet->setCellValue('B'.$row, $locale);
            $translationsSheet->setCellValue('C'.$row, $name);
            $translationsSheet->setCellValue('D'.$row, $description);
        }

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Zoek de geïmporteerde postzegelserie.
        $series = StampSeries::query()
            ->where('name', 'Testserie Natuur')
            ->first();

        expect($series)->not->toBeNull();

        // Controleer alle vijf vertalingen.
        foreach ($translations as [$locale, $name, $description]) {
            $translation = StampSeriesTranslation::query()
                ->where('stamp_series_id', $series->id)
                ->where('locale', $locale)
                ->first();

            expect($translation)->not->toBeNull()
                ->and($translation->name)->toBe($name)
                ->and($translation->description)->toBe($description);
        }

        // Controleer het totale aantal vertalingen.
        expect(
            StampSeriesTranslation::query()
                ->where('stamp_series_id', $series->id)
                ->count()
        )->toBe(5);

        // Controleer of de cataloguseditie is geïmporteerd.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_year', 2026)
                ->exists()
        )->toBeTrue();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelseries vertalingen en cataloguseditie worden teruggedraaid bij een onbekende serie', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback serievertalingen',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuw land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', 'Testland Rollback Series');
        $countriesSheet->setCellValue('B2', 'RS');
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', 'testland-rollback-series');

        // Voeg een nieuwe postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 3001);
        $seriesSheet->setCellValue('B2', 'testland-rollback-series');
        $seriesSheet->setCellValue('C2', 'RS');
        $seriesSheet->setCellValue('D2', 'Testserie Rollback');
        $seriesSheet->setCellValue('E2', 2025);
        $seriesSheet->setCellValue('F2', 'Beschrijving rollbacktest.');

        // Voeg twee serievertalingen toe.
        $translationsSheet = $spreadsheet->getSheetByName('Serievertalingen');

        // Rij 2: geldige Engelse vertaling.
        $translationsSheet->setCellValue('A2', 3001);
        $translationsSheet->setCellValue('B2', 'en');
        $translationsSheet->setCellValue('C2', 'Rollback Series');
        $translationsSheet->setCellValue('D2', 'Valid English description.');

        // Rij 3: onbekende postzegelserie.
        $translationsSheet->setCellValue('A3', 9999);
        $translationsSheet->setCellValue('B3', 'de');
        $translationsSheet->setCellValue('C3', 'Unbekannte Serie');
        $translationsSheet->setCellValue('D3', 'Ungültige Serienreferenz.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken door het onbekende serie-ID.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende exchange_series_id 9999 in tabblad Serievertalingen, rij 3'
        );

        // Het nieuwe land mag niet bestaan.
        expect(
            Country::query()
                ->where('slug', 'testland-rollback-series')
                ->exists()
        )->toBeFalse();

        // De nieuwe postzegelserie mag niet bestaan.
        expect(
            StampSeries::query()
                ->where('name', 'Testserie Rollback')
                ->exists()
        )->toBeFalse();

        // De geldige Engelse vertaling moet zijn teruggedraaid.
        expect(
            StampSeriesTranslation::query()
                ->where('locale', 'en')
                ->where('name', 'Rollback Series')
                ->exists()
        )->toBeFalse();

        // De ongeldige Duitse vertaling mag niet bestaan.
        expect(
            StampSeriesTranslation::query()
                ->where('locale', 'de')
                ->where('name', 'Unbekannte Serie')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuw thema wordt succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus themaimport',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuw thema toe aan Excel.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        $themesSheet->setCellValue('A2', 'test-thema-natuur');
        $themesSheet->setCellValue('B2', 'Natuur');
        $themesSheet->setCellValue('C2', 'Postzegels met natuurafbeeldingen.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Controleer dat het thema nog niet bestaat.
        expect(
            Theme::query()
                ->where('slug', 'test-thema-natuur')
                ->exists()
        )->toBeFalse();

        // Importeer de cataloguseditie en het thema.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal het geïmporteerde thema op.
        $theme = Theme::query()
            ->where('slug', 'test-thema-natuur')
            ->first();

        // Controleer de geïmporteerde gegevens.
        expect($theme)->not->toBeNull()
            ->and($theme->slug)->toBe('test-thema-natuur')
            ->and($theme->name)->toBe('Natuur')
            ->and($theme->description)->toBe('Postzegels met natuurafbeeldingen.');

        // Controleer of de cataloguseditie is geïmporteerd.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_year', 2026)
                ->exists()
        )->toBeTrue();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een bestaand thema wordt hergebruikt op basis van de slug', function () {
    // Maak een bestaand thema aan.
    $existingTheme = Theme::create([
        'slug' => 'test-bestaand-thema',
        'name' => 'Oorspronkelijk thema',
        'description' => 'Oorspronkelijke beschrijving.',
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaand thema',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg het bestaande thema toe aan Excel,
        // maar met afwijkende gegevens.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        $themesSheet->setCellValue('A2', 'test-bestaand-thema');
        $themesSheet->setCellValue('B2', 'Gewijzigd thema');
        $themesSheet->setCellValue('C2', 'Gewijzigde beschrijving.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal het bestaande thema opnieuw op.
        $theme = Theme::query()
            ->where('slug', 'test-bestaand-thema')
            ->first();

        // Controleer dat het bestaande thema is hergebruikt.
        expect($theme)->not->toBeNull()
            ->and($theme->id)->toBe($existingTheme->id)
            ->and($theme->name)->toBe('Oorspronkelijk thema')
            ->and($theme->description)->toBe('Oorspronkelijke beschrijving.');

        // Controleer dat er geen dubbel thema is aangemaakt.
        expect(
            Theme::query()
                ->where('slug', 'test-bestaand-thema')
                ->count()
        )->toBe(1);

        // Controleer dat de cataloguseditie is geïmporteerd.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_year', 2026)
                ->exists()
        )->toBeTrue();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere themas worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere themas',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Definieer drie nieuwe thema's.
        $themes = [
            [
                'test-thema-dieren',
                'Dieren',
                'Postzegels met afbeeldingen van dieren.',
            ],
            [
                'test-thema-bloemen',
                'Bloemen',
                'Postzegels met afbeeldingen van bloemen.',
            ],
            [
                'test-thema-sport',
                'Sport',
                'Postzegels met afbeeldingen van sportactiviteiten.',
            ],
        ];

        // Voeg de thema's toe aan Excel.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        foreach ($themes as $index => [$slug, $name, $description]) {
            $row = $index + 2;

            $themesSheet->setCellValue('A'.$row, $slug);
            $themesSheet->setCellValue('B'.$row, $name);
            $themesSheet->setCellValue('C'.$row, $description);
        }

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Controleer dat de thema's nog niet bestaan.
        foreach ($themes as [$slug]) {
            expect(
                Theme::query()
                    ->where('slug', $slug)
                    ->exists()
            )->toBeFalse();
        }

        // Importeer de cataloguseditie en thema's.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer alle drie de geïmporteerde thema's.
        foreach ($themes as [$slug, $name, $description]) {
            $theme = Theme::query()
                ->where('slug', $slug)
                ->first();

            expect($theme)->not->toBeNull()
                ->and($theme->slug)->toBe($slug)
                ->and($theme->name)->toBe($name)
                ->and($theme->description)->toBe($description);
        }

        // Controleer het totale aantal geïmporteerde thema's.
        expect(
            Theme::query()
                ->whereIn('slug', array_column($themes, 0))
                ->count()
        )->toBe(3);

        // Controleer of de cataloguseditie is geïmporteerd.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_year', 2026)
                ->exists()
        )->toBeTrue();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('themas en cataloguseditie worden teruggedraaid bij een fout tijdens het opslaan', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback themas',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;
    $originalDispatcher = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg twee nieuwe thema's toe aan Excel.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        // Eerste thema.
        $themesSheet->setCellValue('A2', 'test-rollback-thema-een');
        $themesSheet->setCellValue('B2', 'Rollback thema een');
        $themesSheet->setCellValue('C2', 'Eerste thema voor rollbacktest.');

        // Tweede thema.
        $themesSheet->setCellValue('A3', 'test-rollback-thema-twee');
        $themesSheet->setCellValue('B3', 'Rollback thema twee');
        $themesSheet->setCellValue('C3', 'Tweede thema voor rollbacktest.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Controleer dat beide thema's nog niet bestaan.
        expect(
            Theme::query()
                ->whereIn('slug', [
                    'test-rollback-thema-een',
                    'test-rollback-thema-twee',
                ])
                ->count()
        )->toBe(0);

        // Bewaar de oorspronkelijke Eloquent-event-dispatcher.
        $originalDispatcher = Theme::getEventDispatcher();

        // Gebruik een afzonderlijke dispatcher voor deze test.
        $testDispatcher = clone $originalDispatcher;

        Theme::setEventDispatcher($testDispatcher);

        // Simuleer een fout bij het opslaan van het tweede thema.
        Theme::creating(function (Theme $theme) {
            if ($theme->slug === 'test-rollback-thema-twee') {
                throw new RuntimeException(
                    'Gesimuleerde fout tijdens opslaan tweede thema.'
                );
            }
        });

        // De import moet mislukken tijdens de database-transactie.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens opslaan tweede thema.'
        );

        // Het eerste thema moet zijn teruggedraaid.
        expect(
            Theme::query()
                ->where('slug', 'test-rollback-thema-een')
                ->exists()
        )->toBeFalse();

        // Het tweede thema mag niet bestaan.
        expect(
            Theme::query()
                ->where('slug', 'test-rollback-thema-twee')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie moet eveneens zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        // Herstel de oorspronkelijke Eloquent-event-dispatcher.
        if ($originalDispatcher !== null) {
            Theme::setEventDispatcher($originalDispatcher);
        }

        // Sluit het spreadsheet indien nodig.
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        // Verwijder het tijdelijke Excel-bestand.
        unlink($filePath);
    }
});

test('een nieuwe themavertaling wordt succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus themavertalingen',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuw thema toe.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        $themesSheet->setCellValue('A2', 'test-thema-vertaling-bloemen');
        $themesSheet->setCellValue('B2', 'Bloemen');
        $themesSheet->setCellValue('C2', 'Postzegels met bloemen.');

        // Voeg een Engelse themavertaling toe.
        $translationsSheet = $spreadsheet->getSheetByName('Themavertalingen');

        $translationsSheet->setCellValue('A2', 'test-thema-vertaling-bloemen');
        $translationsSheet->setCellValue('B2', 'en');
        $translationsSheet->setCellValue('C2', 'Flowers');
        $translationsSheet->setCellValue('D2', 'Stamps featuring flowers.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Controleer dat het thema nog niet bestaat.
        expect(
            Theme::query()
                ->where('slug', 'test-thema-vertaling-bloemen')
                ->exists()
        )->toBeFalse();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal het geïmporteerde thema op.
        $theme = Theme::query()
            ->where('slug', 'test-thema-vertaling-bloemen')
            ->first();

        expect($theme)->not->toBeNull();

        // Haal de Engelse vertaling op.
        $translation = ThemeTranslation::query()
            ->where('theme_id', $theme->id)
            ->where('locale', 'en')
            ->first();

        // Controleer de geïmporteerde vertaling.
        expect($translation)->not->toBeNull()
            ->and($translation->theme_id)->toBe($theme->id)
            ->and($translation->locale)->toBe('en')
            ->and($translation->name)->toBe('Flowers')
            ->and($translation->description)->toBe('Stamps featuring flowers.');

        // Controleer of de cataloguseditie is geïmporteerd.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_year', 2026)
                ->exists()
        )->toBeTrue();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een bestaande themavertaling wordt niet overschreven tijdens import', function () {
    // Maak een bestaand thema aan.
    $theme = Theme::create([
        'slug' => 'test-bestaande-thema-vertaling',
        'name' => 'Bloemen',
        'description' => 'Postzegels met bloemen.',
    ]);

    // Maak een bestaande Engelse vertaling aan.
    $translation = ThemeTranslation::create([
        'theme_id' => $theme->id,
        'locale' => 'en',
        'name' => 'Original Flowers',
        'description' => 'Original English description.',
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande themavertalingen',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg het bestaande thema toe aan Excel.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        $themesSheet->setCellValue('A2', 'test-bestaande-thema-vertaling');
        $themesSheet->setCellValue('B2', 'Bloemen');
        $themesSheet->setCellValue('C2', 'Postzegels met bloemen.');

        // Voeg afwijkende Engelse vertaalgegevens toe.
        $translationsSheet = $spreadsheet->getSheetByName('Themavertalingen');

        $translationsSheet->setCellValue('A2', 'test-bestaande-thema-vertaling');
        $translationsSheet->setCellValue('B2', 'en');
        $translationsSheet->setCellValue('C2', 'Modified Flowers');
        $translationsSheet->setCellValue('D2', 'Modified English description.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de bestaande vertaling opnieuw op.
        $translation->refresh();

        // Controleer dat de koppeling behouden blijft.
        expect($translation->theme_id)
            ->toBe($theme->id);

        // Controleer dat de oorspronkelijke naam behouden blijft.
        expect($translation->name)
            ->toBe('Original Flowers');

        // Controleer dat de beschrijving niet is overschreven.
        expect($translation->description)
            ->toBe('Original English description.');

        // Controleer dat er geen dubbele vertaling is aangemaakt.
        expect(
            ThemeTranslation::query()
                ->where('theme_id', $theme->id)
                ->where('locale', 'en')
                ->count()
        )->toBe(1);

        // Controleer dat de cataloguseditie is geïmporteerd.
        expect($importedEdition->edition_name)
            ->toBe('Editie 2026');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('themas en themavertalingen worden teruggedraaid bij een onbekende theme_slug', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback themavertalingen',
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
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een nieuw thema toe.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        $themesSheet->setCellValue('A2', 'test-rollback-thema-vertaling');
        $themesSheet->setCellValue('B2', 'Bloemen rollback');
        $themesSheet->setCellValue('C2', 'Testthema voor rollback.');

        // Voeg twee themavertalingen toe.
        $translationsSheet = $spreadsheet->getSheetByName('Themavertalingen');

        // Rij 2: geldige Engelse vertaling.
        $translationsSheet->setCellValue('A2', 'test-rollback-thema-vertaling');
        $translationsSheet->setCellValue('B2', 'en');
        $translationsSheet->setCellValue('C2', 'Rollback Flowers');
        $translationsSheet->setCellValue('D2', 'Valid English description.');

        // Rij 3: onbekend thema.
        $translationsSheet->setCellValue('A3', 'test-onbekend-thema');
        $translationsSheet->setCellValue('B3', 'de');
        $translationsSheet->setCellValue('C3', 'Unbekanntes Thema');
        $translationsSheet->setCellValue('D3', 'Ungültige Themenreferenz.');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken door de onbekende theme_slug.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende theme_slug test-onbekend-thema in tabblad Themavertalingen, rij 3'
        );

        // Het nieuwe thema moet zijn teruggedraaid.
        expect(
            Theme::query()
                ->where('slug', 'test-rollback-thema-vertaling')
                ->exists()
        )->toBeFalse();

        // De geldige Engelse vertaling moet zijn teruggedraaid.
        expect(
            ThemeTranslation::query()
                ->where('locale', 'en')
                ->where('name', 'Rollback Flowers')
                ->exists()
        )->toBeFalse();

        // De ongeldige Duitse vertaling mag niet bestaan.
        expect(
            ThemeTranslation::query()
                ->where('locale', 'de')
                ->where('name', 'Unbekanntes Thema')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een postzegel met catalogusvermelding wordt succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus import postzegels',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-import-postzegels',
        'iso_code' => 'TST',
        'name' => 'Testland',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'test-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Testpostzegel');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');
        $stampsSheet->setCellValue('L2', 'EUR');
        $stampsSheet->setCellValue('Q2', 'Test catalogusopmerking');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de geïmporteerde postzegel.
        $stamp = Stamp::query()
            ->where('name', 'Testpostzegel')
            ->first();

        expect($stamp)->not->toBeNull();

        expect($stamp->country_id)->toBe($country->id);
        expect($stamp->issue_year)->toBe(2026);
        expect($stamp->face_value)->toBe('1/2');
        expect($stamp->currency)->toBe('EUR');

        // Controleer de catalogusvermelding.
        $entry = StampCatalogEntry::query()
            ->where('stamp_id', $stamp->id)
            ->where('catalog_edition_id', $importedEdition->id)
            ->first();

        expect($entry)->not->toBeNull();
        expect($entry->catalog_number)->toBe('100');
        expect($entry->notes)->toBe('Test catalogusopmerking');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere postzegels met catalogusvermeldingen worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere postzegels',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-meerdere-postzegels',
        'iso_code' => 'TMP',
        'name' => 'Testland meerdere postzegels',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Werkblad Postzegels.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel.
        $stampsSheet->setCellValue('A2', 'test-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Testpostzegel 1');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');
        $stampsSheet->setCellValue('L2', 'EUR');

        // Tweede postzegel.
        $stampsSheet->setCellValue('A3', 'test-postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Testpostzegel 2');
        $stampsSheet->setCellValue('I3', 2026);
        $stampsSheet->setCellValue('K3', '50+10');
        $stampsSheet->setCellValue('L3', 'EUR');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de eerste postzegel.
        $stamp1 = Stamp::query()
            ->where('name', 'Testpostzegel 1')
            ->first();

        expect($stamp1)->not->toBeNull();
        expect($stamp1->country_id)->toBe($country->id);
        expect($stamp1->face_value)->toBe('1/2');

        // Controleer de tweede postzegel.
        $stamp2 = Stamp::query()
            ->where('name', 'Testpostzegel 2')
            ->first();

        expect($stamp2)->not->toBeNull();
        expect($stamp2->country_id)->toBe($country->id);
        expect($stamp2->face_value)->toBe('50+10');

        // Controleer de catalogusvermeldingen.
        $entries = StampCatalogEntry::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->orderBy('catalog_number')
            ->get();

        expect($entries)->toHaveCount(2);

        expect($entries[0]->catalog_number)->toBe('100');
        expect($entries[0]->stamp_id)->toBe($stamp1->id);

        expect($entries[1]->catalog_number)->toBe('101');
        expect($entries[1]->stamp_id)->toBe($stamp2->id);

        // Controleer het totale aantal geïmporteerde postzegels.
        expect(
            Stamp::query()
                ->where('country_id', $country->id)
                ->count()
        )->toBe(2);

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden teruggedraaid bij een onbekend land', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback postzegels',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-rollback-postzegels',
        'iso_code' => 'TRP',
        'name' => 'Testland rollback postzegels',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldig.
        $stampsSheet->setCellValue('A2', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige rollback postzegel');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');

        // Tweede postzegel: onbekend land.
        $stampsSheet->setCellValue('A3', 'rollback-postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', 'onbekend-rollback-land');
        $stampsSheet->setCellValue('F3', 'XXX');
        $stampsSheet->setCellValue('G3', 'Ongeldige rollback postzegel');
        $stampsSheet->setCellValue('I3', 2026);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken bij de tweede postzegel.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende country_slug onbekend-rollback-land in tabblad Postzegels, rij 3'
        );

        // De eerste postzegel moet zijn teruggedraaid.
        expect(
            Stamp::query()
                ->where('name', 'Geldige rollback postzegel')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet bestaan.
        expect(
            Stamp::query()
                ->where('name', 'Ongeldige rollback postzegel')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen zijn achtergebleven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie moet zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden teruggedraaid bij een dubbele exchange_stamp_id', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus dubbele postzegelidentificatie',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-dubbele-postzegel-id',
        'iso_code' => 'TDP',
        'name' => 'Testland dubbele postzegel ID',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldig.
        $stampsSheet->setCellValue('A2', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Eerste rollback postzegel');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');

        // Tweede postzegel: dubbele exchange_stamp_id.
        $stampsSheet->setCellValue('A3', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Tweede rollback postzegel');
        $stampsSheet->setCellValue('I3', 2026);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken bij de dubbele identificatie.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Dubbele exchange_stamp_id in tabblad Postzegels, rij 3'
        );

        // De eerste postzegel moet zijn teruggedraaid.
        expect(
            Stamp::query()
                ->where('name', 'Eerste rollback postzegel')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet bestaan.
        expect(
            Stamp::query()
                ->where('name', 'Tweede rollback postzegel')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen zijn achtergebleven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie moet zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden teruggedraaid bij een ontbrekend catalog_number', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekend catalogusnummer',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-ontbrekend-catalogusnummer',
        'iso_code' => 'TOC',
        'name' => 'Testland ontbrekend catalogusnummer',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldig.
        $stampsSheet->setCellValue('A2', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige postzegel met catalogusnummer');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');

        // Tweede postzegel: ontbrekend catalogusnummer.
        $stampsSheet->setCellValue('A3', 'rollback-postzegel-2');
        $stampsSheet->setCellValue('C3', '');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Postzegel zonder catalogusnummer');
        $stampsSheet->setCellValue('I3', 2026);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken bij het ontbrekende catalogusnummer.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'catalog_number ontbreekt in tabblad Postzegels, rij 3'
        );

        // De eerste postzegel moet zijn teruggedraaid.
        expect(
            Stamp::query()
                ->where('name', 'Geldige postzegel met catalogusnummer')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet bestaan.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel zonder catalogusnummer')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding moet zijn teruggedraaid.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie moet zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ontbrekende name', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende postzegelnaam',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-ontbrekende-postzegelnaam',
        'iso_code' => 'TOP',
        'name' => 'Testland ontbrekende postzegelnaam',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldig.
        $stampsSheet->setCellValue('A2', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige postzegel met naam');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');

        // Tweede postzegel: ontbrekende naam.
        $stampsSheet->setCellValue('A3', 'rollback-postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', '');
        $stampsSheet->setCellValue('I3', 2026);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ontbrekende naam afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende name in tabblad Postzegels, rij 3'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Geldige postzegel met naam')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen zijn achtergebleven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ontbrekende issue_year', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekend uitgiftejaar',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-ontbrekend-uitgiftejaar',
        'iso_code' => 'TOU',
        'name' => 'Testland ontbrekend uitgiftejaar',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldig.
        $stampsSheet->setCellValue('A2', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige postzegel met uitgiftejaar');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');

        // Tweede postzegel: ontbrekend uitgiftejaar.
        $stampsSheet->setCellValue('A3', 'rollback-postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Postzegel zonder uitgiftejaar');
        $stampsSheet->setCellValue('I3', '');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet het ontbrekende uitgiftejaar afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende issue_year in tabblad Postzegels, rij 3'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Geldige postzegel met uitgiftejaar')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel zonder uitgiftejaar')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ongeldige issue_year', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldig uitgiftejaar',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-ongeldig-uitgiftejaar',
        'iso_code' => 'TOI',
        'name' => 'Testland ongeldig uitgiftejaar',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldig.
        $stampsSheet->setCellValue('A2', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige postzegel met uitgiftejaar');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');

        // Tweede postzegel: ongeldig uitgiftejaar.
        $stampsSheet->setCellValue('A3', 'rollback-postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Postzegel met ongeldig uitgiftejaar');
        $stampsSheet->setCellValue('I3', 'abc');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet het ongeldige uitgiftejaar afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige issue_year in tabblad Postzegels, rij 3'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Geldige postzegel met uitgiftejaar')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel met ongeldig uitgiftejaar')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een issue_year hoger dan 65535', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te hoog uitgiftejaar',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-te-hoog-uitgiftejaar',
        'iso_code' => 'THU',
        'name' => 'Testland te hoog uitgiftejaar',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldig.
        $stampsSheet->setCellValue('A2', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige postzegel met uitgiftejaar');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');

        // Tweede postzegel: uitgiftejaar hoger dan 65535.
        $stampsSheet->setCellValue('A3', 'rollback-postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Postzegel met te hoog uitgiftejaar');
        $stampsSheet->setCellValue('I3', 65536);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet het te hoge uitgiftejaar afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'issue_year is hoger dan 65535 in tabblad Postzegels, rij 3'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Geldige postzegel met uitgiftejaar')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel met te hoog uitgiftejaar')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ongeldige issue_date', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige uitgiftedatum',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-ongeldige-uitgiftedatum',
        'iso_code' => 'TOD',
        'name' => 'Testland ongeldige uitgiftedatum',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldige uitgiftedatum.
        $stampsSheet->setCellValue('A2', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige postzegel met uitgiftedatum');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('J2', '2026-01-15');
        $stampsSheet->setCellValue('K2', '1/2');

        // Tweede postzegel: ongeldige uitgiftedatum.
        $stampsSheet->setCellValue('A3', 'rollback-postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Postzegel met ongeldige uitgiftedatum');
        $stampsSheet->setCellValue('I3', 2026);
        $stampsSheet->setCellValue('J3', '2026-02-30');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ongeldige uitgiftedatum afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige issue_date in tabblad Postzegels, rij 3'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Geldige postzegel met uitgiftedatum')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel met ongeldige uitgiftedatum')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een te lange face_value', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange nominale waarde',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-te-lange-nominale-waarde',
        'iso_code' => 'TLN',
        'name' => 'Testland te lange nominale waarde',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldige nominale waarde.
        $stampsSheet->setCellValue('A2', 'rollback-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige postzegel met nominale waarde');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');

        // Tweede postzegel: nominale waarde van 51 tekens.
        $stampsSheet->setCellValue('A3', 'rollback-postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Postzegel met te lange nominale waarde');
        $stampsSheet->setCellValue('I3', 2026);
        $stampsSheet->setCellValue('K3', str_repeat('1', 51));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de te lange nominale waarde afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'face_value is langer dan 50 tekens in tabblad Postzegels, rij 3'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Geldige postzegel met nominale waarde')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel met te lange nominale waarde')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een onbekende parent_exchange_stamp_id', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onbekende bovenliggende postzegel',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-onbekende-bovenliggende-postzegel',
        'iso_code' => 'OBP',
        'name' => 'Testland onbekende bovenliggende postzegel',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldige postzegel zonder bovenliggende postzegel.
        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige postzegel zonder ouder');
        $stampsSheet->setCellValue('I2', 2026);

        // Tweede postzegel: verwijst naar een onbekende bovenliggende postzegel.
        $stampsSheet->setCellValue('A3', 'postzegel-2');
        $stampsSheet->setCellValue('B3', 'onbekende-postzegel');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Postzegel met onbekende ouder');
        $stampsSheet->setCellValue('I3', 2026);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de onbekende ouderverwijzing afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende parent_exchange_stamp_id voor exchange_stamp_id postzegel-2 in tabblad Postzegels'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Geldige postzegel zonder ouder')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel met onbekende ouder')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen wanneer een postzegel zichzelf als ouder heeft', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus postzegel eigen ouder',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-postzegel-eigen-ouder',
        'iso_code' => 'PEO',
        'name' => 'Testland postzegel eigen ouder',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: geldig, zonder bovenliggende postzegel.
        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Geldige postzegel zonder eigen ouder');
        $stampsSheet->setCellValue('I2', 2026);

        // Tweede postzegel: verwijst naar zichzelf als ouder.
        $stampsSheet->setCellValue('A3', 'postzegel-2');
        $stampsSheet->setCellValue('B3', 'postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Postzegel met zichzelf als ouder');
        $stampsSheet->setCellValue('I3', 2026);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de verwijzing naar zichzelf afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Postzegel postzegel-2 kan niet zijn eigen bovenliggende postzegel zijn.'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Geldige postzegel zonder eigen ouder')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel met zichzelf als ouder')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een circulaire ouder-kindrelatie', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus circulaire ouder-kindrelatie',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-circulaire-ouder-kindrelatie',
        'iso_code' => 'COK',
        'name' => 'Testland circulaire ouder-kindrelatie',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel: verwijst naar de tweede postzegel.
        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('B2', 'postzegel-2');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Eerste postzegel circulaire relatie');
        $stampsSheet->setCellValue('I2', 2026);

        // Tweede postzegel: verwijst terug naar de eerste postzegel.
        $stampsSheet->setCellValue('A3', 'postzegel-2');
        $stampsSheet->setCellValue('B3', 'postzegel-1');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Tweede postzegel circulaire relatie');
        $stampsSheet->setCellValue('I3', 2026);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de circulaire relatie afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Circulaire ouder-kindrelatie gevonden voor exchange_stamp_id postzegel-1 in tabblad Postzegels'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Eerste postzegel circulaire relatie')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Tweede postzegel circulaire relatie')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ontbrekende parent_exchange_id', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende parent exchange id',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-ontbrekende-parent-exchange-id',
        'iso_code' => 'OPE',
        'name' => 'Testland ontbrekende parent exchange id',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak een geldige onderliggende postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ontbrekende parent exchange id');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een ongeldige ouder-kindkoppeling aan.
        $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

        // Kolom A: parent_exchange_id ontbreekt.
        $parentStampsSheet->setCellValue('A2', '');

        // Kolom B: bestaande onderliggende postzegel.
        $parentStampsSheet->setCellValue('B2', 'postzegel-1');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ontbrekende identificatie afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende parent_exchange_id in tabblad BovenliggendePostzegels, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ontbrekende parent exchange id')
                ->exists()
        )->toBeFalse();

        // Er mag geen catalogusvermelding achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ontbrekende child_exchange_stamp_id', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende child exchange stamp id',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-ontbrekende-child-exchange-id',
        'iso_code' => 'OCE',
        'name' => 'Testland ontbrekende child exchange stamp id',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak een geldige postzegel aan in het Excel-bestand.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ontbrekende child exchange id');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een ongeldige ouder-kindkoppeling aan.
        $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

        // Kolom A: parent_exchange_id is ingevuld.
        $parentStampsSheet->setCellValue('A2', 'ouder-1');

        // Kolom B: child_exchange_stamp_id ontbreekt.
        $parentStampsSheet->setCellValue('B2', '');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ontbrekende identificatie afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende child_exchange_stamp_id in tabblad BovenliggendePostzegels, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ontbrekende child exchange id')
                ->exists()
        )->toBeFalse();

        // Er mag geen catalogusvermelding achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een onbekende child_exchange_stamp_id', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onbekende child exchange stamp id',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-onbekende-child-exchange-id',
        'iso_code' => 'UCE',
        'name' => 'Testland onbekende child exchange stamp id',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak een geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel onbekende child exchange id');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een ouder-kindkoppeling met een onbekende postzegel.
        $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

        // Kolom A: geldige parent_exchange_id.
        $parentStampsSheet->setCellValue('A2', 'ouder-1');

        // Kolom B: verwijst naar een niet-bestaande postzegel.
        $parentStampsSheet->setCellValue('B2', 'postzegel-999');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de onbekende postzegel afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende child_exchange_stamp_id in tabblad BovenliggendePostzegels, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel onbekende child exchange id')
                ->exists()
        )->toBeFalse();

        // Er mag geen catalogusvermelding achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een tegenstrijdige ouderverwijzing', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus tegenstrijdige ouderverwijzing',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een bestaand land aan.
    $country = Country::create([
        'slug' => 'testland-tegenstrijdige-ouderverwijzing',
        'iso_code' => 'TOV',
        'name' => 'Testland tegenstrijdige ouderverwijzing',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak twee geldige postzegels aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        // Eerste postzegel verwijst naar de tweede postzegel.
        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('B2', 'postzegel-2');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Eerste postzegel tegenstrijdige ouderverwijzing');
        $stampsSheet->setCellValue('I2', 2026);

        // Tweede postzegel heeft geen bovenliggende postzegel.
        $stampsSheet->setCellValue('A3', 'postzegel-2');
        $stampsSheet->setCellValue('C3', '101');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Tweede postzegel tegenstrijdige ouderverwijzing');
        $stampsSheet->setCellValue('I3', 2026);

        // Maak een tegenstrijdige ouder-kindkoppeling aan.
        $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

        // Wijs dezelfde onderliggende postzegel opnieuw aan een ouder toe.
        $parentStampsSheet->setCellValue('A2', 'ouder-1');
        $parentStampsSheet->setCellValue('B2', 'postzegel-1');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de tegenstrijdige ouderverwijzing afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Tegenstrijdige ouderverwijzing voor child_exchange_stamp_id in tabblad BovenliggendePostzegels, rij 2'
        );

        // De eerste postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Eerste postzegel tegenstrijdige ouderverwijzing')
                ->exists()
        )->toBeFalse();

        // De tweede postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Tweede postzegel tegenstrijdige ouderverwijzing')
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen achterblijven.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De geïmporteerde cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een dubbele ouder-kindkoppeling', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus dubbele ouder-kindkoppeling',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-dubbele-ouder-kindkoppeling',
        'iso_code' => 'DOK',
        'name' => 'Testland dubbele ouder-kindkoppeling',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel dubbele ouder-kindkoppeling');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak tweemaal dezelfde ouder-kindkoppeling aan.
        $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

        $parentStampsSheet->setCellValue('A2', 'ouder-1');
        $parentStampsSheet->setCellValue('B2', 'postzegel-1');

        $parentStampsSheet->setCellValue('A3', 'ouder-1');
        $parentStampsSheet->setCellValue('B3', 'postzegel-1');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de dubbele koppeling afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Dubbele ouder-kindkoppeling in tabblad BovenliggendePostzegels, rij 3'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel dubbele ouder-kindkoppeling')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij meerdere bovenliggende postzegels', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere bovenliggende postzegels',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-meerdere-bovenliggende-postzegels',
        'iso_code' => 'MBP',
        'name' => 'Testland meerdere bovenliggende postzegels',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel meerdere bovenliggende postzegels');
        $stampsSheet->setCellValue('I2', 2026);

        // Koppel dezelfde postzegel aan twee verschillende ouders.
        $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

        $parentStampsSheet->setCellValue('A2', 'ouder-1');
        $parentStampsSheet->setCellValue('B2', 'postzegel-1');

        $parentStampsSheet->setCellValue('A3', 'ouder-2');
        $parentStampsSheet->setCellValue('B3', 'postzegel-1');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de tweede ouderverwijzing afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Meerdere bovenliggende postzegels voor dezelfde child_exchange_stamp_id in tabblad BovenliggendePostzegels, rij 3'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel meerdere bovenliggende postzegels')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een te lange face_value in BovenliggendePostzegels', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus te lange face value bovenliggende postzegels',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-face-value-bovenliggende-postzegels',
        'iso_code' => 'FVB',
        'name' => 'Testland face value bovenliggende postzegels',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel te lange face value bovenliggende postzegels');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een ouder-kindkoppeling met een te lange face_value.
        $parentStampsSheet = $spreadsheet->getSheetByName('BovenliggendePostzegels');

        $parentStampsSheet->setCellValue('A2', 'ouder-1');
        $parentStampsSheet->setCellValue('B2', 'postzegel-1');

        // Kolom I: 51 tekens, terwijl maximaal 50 zijn toegestaan.
        $parentStampsSheet->setCellValue('I2', str_repeat('A', 51));

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de te lange face_value afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'face_value is langer dan 50 tekens in tabblad BovenliggendePostzegels, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel te lange face value bovenliggende postzegels')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een onbekende exchange_stamp_id in Postzegelvertalingen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onbekende postzegelvertaling',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-onbekende-postzegelvertaling',
        'iso_code' => 'OPV',
        'name' => 'Testland onbekende postzegelvertaling',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel onbekende postzegelvertaling');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een vertaling met een onbekende exchange_stamp_id.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        $translationsSheet->setCellValue('A2', 'onbekende-postzegel');
        $translationsSheet->setCellValue('B2', '100');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Onbekende postzegelvertaling');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de onbekende postzegel afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende exchange_stamp_id onbekende-postzegel in tabblad Postzegelvertalingen, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel onbekende postzegelvertaling')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een afwijkend catalog_number in Postzegelvertalingen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus afwijkend catalogusnummer postzegelvertaling',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-afwijkend-catalogusnummer-vertaling',
        'iso_code' => 'ACV',
        'name' => 'Testland afwijkend catalogusnummer vertaling',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel afwijkend catalogusnummer vertaling');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een vertaling met een afwijkend catalogusnummer.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        $translationsSheet->setCellValue('A2', 'postzegel-1');
        $translationsSheet->setCellValue('B2', '999');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Postzegel met afwijkend catalogusnummer');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet het afwijkende catalogusnummer afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Afwijkend catalog_number voor exchange_stamp_id postzegel-1 in tabblad Postzegelvertalingen, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel afwijkend catalogusnummer vertaling')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ongeldige locale in Postzegelvertalingen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige locale postzegelvertaling',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-locale-postzegelvertaling',
        'iso_code' => 'OLV',
        'name' => 'Testland ongeldige locale postzegelvertaling',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ongeldige locale vertaling');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een vertaling met een ongeldige taalcode.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        $translationsSheet->setCellValue('A2', 'postzegel-1');
        $translationsSheet->setCellValue('B2', '100');
        $translationsSheet->setCellValue('C2', 'it');
        $translationsSheet->setCellValue('D2', 'Postzegel ongeldige taalcode');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ongeldige taalcode afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige locale it in tabblad Postzegelvertalingen, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ongeldige locale vertaling')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij dubbele postzegelvertalingen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus dubbele postzegelvertalingen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-dubbele-postzegelvertalingen',
        'iso_code' => 'DPV',
        'name' => 'Testland dubbele postzegelvertalingen',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel dubbele postzegelvertalingen');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak twee vertalingen voor dezelfde postzegel en taal.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        // Eerste Nederlandse vertaling.
        $translationsSheet->setCellValue('A2', 'postzegel-1');
        $translationsSheet->setCellValue('B2', '100');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Nederlandse vertaling 1');

        // Tweede Nederlandse vertaling voor dezelfde postzegel.
        $translationsSheet->setCellValue('A3', 'postzegel-1');
        $translationsSheet->setCellValue('B3', '100');
        $translationsSheet->setCellValue('C3', 'nl');
        $translationsSheet->setCellValue('D3', 'Nederlandse vertaling 2');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de dubbele vertaling afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Dubbele postzegelvertaling voor exchange_stamp_id postzegel-1 en locale nl in tabblad Postzegelvertalingen, rij 3'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel dubbele postzegelvertalingen')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ontbrekende name in Postzegelvertalingen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende naam postzegelvertaling',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ontbrekende-naam-postzegelvertaling',
        'iso_code' => 'ONV',
        'name' => 'Testland ontbrekende naam postzegelvertaling',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ontbrekende naam vertaling');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een postzegelvertaling zonder naam.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        $translationsSheet->setCellValue('A2', 'postzegel-1');
        $translationsSheet->setCellValue('B2', '100');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', '');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ontbrekende naam afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende name in tabblad Postzegelvertalingen, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ontbrekende naam vertaling')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ongeldige description in Postzegelvertalingen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige beschrijving postzegelvertaling',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-beschrijving-postzegelvertaling',
        'iso_code' => 'OBV',
        'name' => 'Testland ongeldige beschrijving postzegelvertaling',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ongeldige beschrijving vertaling');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een postzegelvertaling met een numerieke description.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        $translationsSheet->setCellValue('A2', 'postzegel-1');
        $translationsSheet->setCellValue('B2', '100');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Nederlandse vertaling');
        $translationsSheet->setCellValue('E2', 123);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de numerieke description afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige description in tabblad Postzegelvertalingen, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ongeldige beschrijving vertaling')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ontbrekende exchange_stamp_id in Postzegelvertalingen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende exchange stamp id postzegelvertaling',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ontbrekende-exchange-id-postzegelvertaling',
        'iso_code' => 'OEV',
        'name' => 'Testland ontbrekende exchange id postzegelvertaling',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException('Kan tijdelijk Excel-bestand niet aanmaken.');
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ontbrekende exchange id vertaling');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een vertaling zonder exchange_stamp_id.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        $translationsSheet->setCellValue('A2', '');
        $translationsSheet->setCellValue('B2', '100');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Nederlandse vertaling');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ontbrekende exchange_stamp_id afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende exchange_stamp_id  in tabblad Postzegelvertalingen, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ontbrekende exchange id vertaling')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere postzegelvertalingen worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus import postzegelvertalingen',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-import-postzegelvertalingen',
        'iso_code' => 'TPV',
        'name' => 'Testland postzegelvertalingen',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Testpostzegel vertalingen');
        $stampsSheet->setCellValue('I2', 2026);

        // Voeg drie vertalingen toe.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        // Nederlandse vertaling.
        $translationsSheet->setCellValue('A2', 'postzegel-1');
        $translationsSheet->setCellValue('B2', '100');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Nederlandse postzegel');
        $translationsSheet->setCellValue('E2', 'Nederlandse beschrijving');

        // Engelse vertaling.
        $translationsSheet->setCellValue('A3', 'postzegel-1');
        $translationsSheet->setCellValue('B3', '100');
        $translationsSheet->setCellValue('C3', 'en');
        $translationsSheet->setCellValue('D3', 'English stamp');
        $translationsSheet->setCellValue('E3', 'English description');

        // Duitse vertaling zonder beschrijving.
        $translationsSheet->setCellValue('A4', 'postzegel-1');
        $translationsSheet->setCellValue('B4', '100');
        $translationsSheet->setCellValue('C4', 'de');
        $translationsSheet->setCellValue('D4', 'Deutsche Briefmarke');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer of de cataloguseditie is geïmporteerd.
        expect($importedEdition)->not->toBeNull();

        // Zoek de geïmporteerde postzegel.
        $stamp = Stamp::query()
            ->where('name', 'Testpostzegel vertalingen')
            ->first();

        expect($stamp)->not->toBeNull();

        // Haal de vertalingen van deze postzegel op.
        $translations = $stamp->translations()
            ->get()
            ->keyBy('locale');

        // Controleer het aantal vertalingen.
        expect($translations)->toHaveCount(3);

        // Controleer de Nederlandse vertaling.
        expect($translations->get('nl'))->not->toBeNull();
        expect($translations->get('nl')->name)->toBe('Nederlandse postzegel');
        expect($translations->get('nl')->description)->toBe('Nederlandse beschrijving');

        // Controleer de Engelse vertaling.
        expect($translations->get('en'))->not->toBeNull();
        expect($translations->get('en')->name)->toBe('English stamp');
        expect($translations->get('en')->description)->toBe('English description');

        // Controleer de Duitse vertaling.
        expect($translations->get('de'))->not->toBeNull();
        expect($translations->get('de')->name)->toBe('Deutsche Briefmarke');
        expect($translations->get('de')->description)->toBeNull();

        // Controleer of de catalogusvermelding bestaat.
        expect(
            $stamp->catalogEntries()
                ->where('catalog_edition_id', $importedEdition->id)
                ->where('catalog_number', '100')
                ->exists()
        )->toBeTrue();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels vertalingen en cataloguseditie worden teruggedraaid bij een fout tijdens het opslaan', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback postzegelvertalingen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-rollback-postzegelvertalingen',
        'iso_code' => 'RPV',
        'name' => 'Testland rollback postzegelvertalingen',
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg een postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-rollback-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel rollback vertalingen');
        $stampsSheet->setCellValue('I2', 2026);

        // Voeg twee geldige postzegelvertalingen toe.
        $translationsSheet = $spreadsheet->getSheetByName('Postzegelvertalingen');

        // Nederlandse vertaling.
        $translationsSheet->setCellValue('A2', 'postzegel-rollback-1');
        $translationsSheet->setCellValue('B2', '100');
        $translationsSheet->setCellValue('C2', 'nl');
        $translationsSheet->setCellValue('D2', 'Nederlandse postzegel rollback');

        // Engelse vertaling.
        $translationsSheet->setCellValue('A3', 'postzegel-rollback-1');
        $translationsSheet->setCellValue('B3', '100');
        $translationsSheet->setCellValue('C3', 'en');
        $translationsSheet->setCellValue('D3', 'English stamp rollback');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Bewaar de oorspronkelijke Eloquent-event-dispatcher.
        $originalDispatcher = StampTranslation::getEventDispatcher();

        // Gebruik een afzonderlijke dispatcher voor deze test.
        $testDispatcher = clone $originalDispatcher;

        StampTranslation::setEventDispatcher($testDispatcher);

        // Simuleer een fout bij het opslaan van de Engelse vertaling.
        StampTranslation::creating(function (StampTranslation $translation) {
            if ($translation->locale === 'en') {
                throw new RuntimeException(
                    'Gesimuleerde fout tijdens opslaan Engelse postzegelvertaling.'
                );
            }
        });

        // De import moet mislukken tijdens de databasetransactie.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens opslaan Engelse postzegelvertaling.'
        );

        // De geïmporteerde postzegel moet zijn teruggedraaid.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel rollback vertalingen')
                ->exists()
        )->toBeFalse();

        // De Nederlandse vertaling moet zijn teruggedraaid.
        expect(
            StampTranslation::query()
                ->where('locale', 'nl')
                ->where('name', 'Nederlandse postzegel rollback')
                ->exists()
        )->toBeFalse();

        // De Engelse vertaling mag niet bestaan.
        expect(
            StampTranslation::query()
                ->where('locale', 'en')
                ->where('name', 'English stamp rollback')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding moet zijn teruggedraaid.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie moet zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        // Herstel de oorspronkelijke Eloquent-event-dispatcher.
        if (isset($originalDispatcher)) {
            StampTranslation::setEventDispatcher($originalDispatcher);
        }

        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een afwijkend catalog_number in Cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus afwijkend catalogusnummer cataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-afwijkend-catalogusnummer-cataloguswaarden',
        'iso_code' => 'ACC',
        'name' => 'Testland afwijkend catalogusnummer cataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-cataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel afwijkend catalogusnummer cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een cataloguswaarde met een afwijkend catalogusnummer.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '999');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.00');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet het afwijkende catalogusnummer afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Afwijkend catalog_number voor exchange_stamp_id postzegel-1 in tabblad Cataloguswaarden, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel afwijkend catalogusnummer cataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ongeldige currency in Cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige currency',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-currency',
        'iso_code' => 'OCU',
        'name' => 'Testland ongeldige currency',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-ongeldige-currency',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe aan het Excel-bestand.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ongeldige currency');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een cataloguswaarde met drie decimalen.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.00');
        $catalogValuesSheet->setCellValue('E2', 'eur');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ongeldige cataloguswaarde afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige currency in tabblad Cataloguswaarden, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ongeldige currency')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij ongeldige notes in Cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige notes',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-notes',
        'iso_code' => 'ONT',
        'name' => 'Testland ongeldige notes',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-ongeldige-notes',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe aan het Excel-bestand.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ongeldige notes');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een cataloguswaarde met een ongeldige numerieke notes-waarde.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.00');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('F2', 12345);
        $catalogValuesSheet->setCellValue('G2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ongeldige notes afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige notes in tabblad Cataloguswaarden, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ongeldige notes')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ongeldige needs_review in Cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige needs_review',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-needs-review',
        'iso_code' => 'ONR',
        'name' => 'Testland ongeldige needs_review',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-ongeldige-needs-review',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe aan het Excel-bestand.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ongeldige needs_review');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een cataloguswaarde met een ongeldige needs_review-waarde.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.00');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('F2', 'Testopmerking');
        $catalogValuesSheet->setCellValue('G2', 2);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ongeldige needs_review afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige needs_review in tabblad Cataloguswaarden, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ongeldige needs_review')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('cataloguswaarden worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus import cataloguswaarden',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-import-cataloguswaarden',
        'iso_code' => 'ICW',
        'name' => 'Testland import cataloguswaarden',
    ]);

    // Maak een waarderingscategorie aan.
    $condition = Condition::create([
        'slug' => 'testconditie-import-cataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe aan Excel.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Voeg één postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel import cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Voeg een geldige cataloguswaarde toe.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.50');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('F2', 'Testopmerking cataloguswaarde');
        $catalogValuesSheet->setCellValue('G2', 1);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie met cataloguswaarden.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de geïmporteerde cataloguseditie.
        expect($importedEdition->catalog_id)->toBe($catalog->id);
        expect($importedEdition->edition_name)->toBe('Editie 2026');

        // Controleer de geïmporteerde postzegel.
        $stamp = Stamp::query()
            ->where('name', 'Postzegel import cataloguswaarden')
            ->first();

        expect($stamp)->not->toBeNull();

        // Controleer de catalogusvermelding.
        $catalogEntry = StampCatalogEntry::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('stamp_id', $stamp->id)
            ->where('catalog_number', '100')
            ->first();

        expect($catalogEntry)->not->toBeNull();

        // Controleer de geïmporteerde cataloguswaarde.
        $catalogValue = StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $catalogEntry->id)
            ->where('condition_id', $condition->id)
            ->first();

        expect($catalogValue)->not->toBeNull();

        // Controleer alle geïmporteerde velden.
        expect($catalogValue->catalog_value)->toBe('25.50');
        expect($catalogValue->currency)->toBe('EUR');
        expect($catalogValue->notes)->toBe('Testopmerking cataloguswaarde');
        expect($catalogValue->needs_review)->toBeTrue();

        // Controleer dat precies één cataloguswaarde is aangemaakt.
        expect(
            StampCatalogValue::query()
                ->where('stamp_catalog_entry_id', $catalogEntry->id)
                ->count()
        )->toBe(1);

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere cataloguswaarden worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere cataloguswaarden',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-meerdere-cataloguswaarden',
        'iso_code' => 'MCW',
        'name' => 'Testland meerdere cataloguswaarden',
    ]);

    // Maak twee waarderingscategorieën aan.
    $postfris = Condition::create([
        'slug' => 'test-postfris-cataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'test-gestempeld-cataloguswaarden',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg beide waarderingscategorieën toe aan Excel.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $postfris->slug);
        $conditionsSheet->setCellValue('A3', $gestempeld->slug);

        // Voeg één postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel meerdere cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Voeg de cataloguswaarde voor postfris toe.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $postfris->slug);
        $catalogValuesSheet->setCellValue('D2', '25.50');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('F2', 'Cataloguswaarde postfris');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Voeg de cataloguswaarde voor gestempeld toe.
        $catalogValuesSheet->setCellValue('A3', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B3', '100');
        $catalogValuesSheet->setCellValue('C3', $gestempeld->slug);
        $catalogValuesSheet->setCellValue('D3', '12.75');
        $catalogValuesSheet->setCellValue('E3', 'EUR');
        $catalogValuesSheet->setCellValue('F3', 'Cataloguswaarde gestempeld');
        $catalogValuesSheet->setCellValue('G3', 1);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Zoek de geïmporteerde postzegel.
        $stamp = Stamp::query()
            ->where('name', 'Postzegel meerdere cataloguswaarden')
            ->first();

        expect($stamp)->not->toBeNull();

        // Zoek de catalogusvermelding.
        $catalogEntry = StampCatalogEntry::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('stamp_id', $stamp->id)
            ->where('catalog_number', '100')
            ->first();

        expect($catalogEntry)->not->toBeNull();

        // Controleer dat precies twee cataloguswaarden zijn aangemaakt.
        expect(
            StampCatalogValue::query()
                ->where('stamp_catalog_entry_id', $catalogEntry->id)
                ->count()
        )->toBe(2);

        // Controleer de cataloguswaarde voor postfris.
        $postfrisValue = StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $catalogEntry->id)
            ->where('condition_id', $postfris->id)
            ->first();

        expect($postfrisValue)->not->toBeNull();
        expect($postfrisValue->catalog_value)->toBe('25.50');
        expect($postfrisValue->currency)->toBe('EUR');
        expect($postfrisValue->notes)->toBe('Cataloguswaarde postfris');
        expect($postfrisValue->needs_review)->toBeFalse();

        // Controleer de cataloguswaarde voor gestempeld.
        $gestempeldValue = StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $catalogEntry->id)
            ->where('condition_id', $gestempeld->id)
            ->first();

        expect($gestempeldValue)->not->toBeNull();
        expect($gestempeldValue->catalog_value)->toBe('12.75');
        expect($gestempeldValue->currency)->toBe('EUR');
        expect($gestempeldValue->notes)->toBe('Cataloguswaarde gestempeld');
        expect($gestempeldValue->needs_review)->toBeTrue();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('cataloguswaarden met lege optionele velden worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus lege optionele cataloguswaarden',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-lege-cataloguswaarden',
        'iso_code' => 'LCW',
        'name' => 'Testland lege cataloguswaarden',
    ]);

    // Maak een waarderingscategorie aan.
    $condition = Condition::create([
        'slug' => 'testconditie-lege-cataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe aan Excel.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Voeg één geldige postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel lege cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Voeg een cataloguswaarde met lege optionele velden toe.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', null);
        $catalogValuesSheet->setCellValue('E2', null);
        $catalogValuesSheet->setCellValue('F2', null);
        $catalogValuesSheet->setCellValue('G2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de geïmporteerde postzegel.
        $stamp = Stamp::query()
            ->where('name', 'Postzegel lege cataloguswaarden')
            ->first();

        expect($stamp)->not->toBeNull();

        // Controleer de catalogusvermelding.
        $catalogEntry = StampCatalogEntry::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('stamp_id', $stamp->id)
            ->where('catalog_number', '100')
            ->first();

        expect($catalogEntry)->not->toBeNull();

        // Zoek de geïmporteerde cataloguswaarde.
        $catalogValue = StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $catalogEntry->id)
            ->where('condition_id', $condition->id)
            ->first();

        expect($catalogValue)->not->toBeNull();

        // Controleer de lege optionele velden.
        expect($catalogValue->catalog_value)->toBeNull();
        expect($catalogValue->currency)->toBeNull();
        expect($catalogValue->notes)->toBeNull();

        // Controleer de indicator voor handmatige controle.
        expect($catalogValue->needs_review)->toBeFalse();

        // Controleer dat precies één cataloguswaarde is aangemaakt.
        expect(
            StampCatalogValue::query()
                ->where('stamp_catalog_entry_id', $catalogEntry->id)
                ->count()
        )->toBe(1);

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('cataloguswaarden en cataloguseditie worden teruggedraaid bij een fout tijdens het opslaan', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback cataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-rollback-cataloguswaarden',
        'iso_code' => 'RCW',
        'name' => 'Testland rollback cataloguswaarden',
    ]);

    $postfris = Condition::create([
        'slug' => 'test-postfris-rollback-cataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'test-gestempeld-rollback-cataloguswaarden',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg beide waarderingscategorieën toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $postfris->slug);
        $conditionsSheet->setCellValue('A3', $gestempeld->slug);

        // Voeg één postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-rollback-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel rollback cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Voeg twee geldige cataloguswaarden toe.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        // Eerste cataloguswaarde: postfris.
        $catalogValuesSheet->setCellValue('A2', 'postzegel-rollback-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $postfris->slug);
        $catalogValuesSheet->setCellValue('D2', '25.50');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('F2', 'Rollbacktest postfris');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Tweede cataloguswaarde: gestempeld.
        $catalogValuesSheet->setCellValue('A3', 'postzegel-rollback-1');
        $catalogValuesSheet->setCellValue('B3', '100');
        $catalogValuesSheet->setCellValue('C3', $gestempeld->slug);
        $catalogValuesSheet->setCellValue('D3', '12.75');
        $catalogValuesSheet->setCellValue('E3', 'EUR');
        $catalogValuesSheet->setCellValue('F3', 'Rollbacktest gestempeld');
        $catalogValuesSheet->setCellValue('G3', 1);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Bewaar de oorspronkelijke Eloquent-event-dispatcher.
        $originalDispatcher = StampCatalogValue::getEventDispatcher();

        // Gebruik een afzonderlijke dispatcher voor deze test.
        $testDispatcher = clone $originalDispatcher;

        StampCatalogValue::setEventDispatcher($testDispatcher);

        // Simuleer een fout bij het opslaan van de tweede cataloguswaarde.
        StampCatalogValue::creating(function (StampCatalogValue $value) use ($gestempeld) {
            if ($value->condition_id === $gestempeld->id) {
                throw new RuntimeException(
                    'Gesimuleerde fout tijdens opslaan cataloguswaarde gestempeld.'
                );
            }
        });

        // De import moet mislukken tijdens de databasetransactie.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens opslaan cataloguswaarde gestempeld.'
        );

        // De geïmporteerde postzegel moet zijn teruggedraaid.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel rollback cataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding moet zijn teruggedraaid.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // De eerste cataloguswaarde moet zijn teruggedraaid.
        expect(
            StampCatalogValue::query()
                ->where('condition_id', $postfris->id)
                ->where('notes', 'Rollbacktest postfris')
                ->exists()
        )->toBeFalse();

        // De tweede cataloguswaarde mag niet bestaan.
        expect(
            StampCatalogValue::query()
                ->where('condition_id', $gestempeld->id)
                ->where('notes', 'Rollbacktest gestempeld')
                ->exists()
        )->toBeFalse();

        // De nieuwe cataloguseditie moet zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        // Herstel de oorspronkelijke Eloquent-event-dispatcher.
        if (isset($originalDispatcher)) {
            StampCatalogValue::setEventDispatcher($originalDispatcher);
        }

        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij dubbele cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus dubbele cataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-dubbele-cataloguswaarden',
        'iso_code' => 'DCW',
        'name' => 'Testland dubbele cataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'test-postfris-dubbele-cataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Voeg één geldige postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel dubbele cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Voeg twee cataloguswaarden toe met dezelfde combinatie.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        // Eerste cataloguswaarde.
        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.50');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Tweede cataloguswaarde: dezelfde postzegel en waarderingscategorie.
        $catalogValuesSheet->setCellValue('A3', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B3', '100');
        $catalogValuesSheet->setCellValue('C3', $condition->slug);
        $catalogValuesSheet->setCellValue('D3', '30.00');
        $catalogValuesSheet->setCellValue('E3', 'EUR');
        $catalogValuesSheet->setCellValue('G3', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de dubbele combinatie afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Dubbele cataloguswaarde voor exchange_stamp_id postzegel-1 en condition_slug '.$condition->slug.' in tabblad Cataloguswaarden, rij 3'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel dubbele cataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // Er mogen geen cataloguswaarden zijn opgeslagen.
        expect(
            StampCatalogValue::query()
                ->where('condition_id', $condition->id)
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ontbrekende exchange_stamp_id in Cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende exchange id cataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ontbrekende-exchange-id-cataloguswaarden',
        'iso_code' => 'OEC',
        'name' => 'Testland ontbrekende exchange id cataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-ontbrekende-exchange-id-cataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Voeg één geldige postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ontbrekende exchange id cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Voeg een cataloguswaarde zonder exchange_stamp_id toe.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', '');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.50');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ontbrekende exchange_stamp_id afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende exchange_stamp_id in tabblad Cataloguswaarden, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ontbrekende exchange id cataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // Er mogen geen cataloguswaarden zijn opgeslagen.
        expect(
            StampCatalogValue::query()
                ->where('condition_id', $condition->id)
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een onbekende exchange_stamp_id in Cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onbekende exchange id cataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-onbekende-exchange-id-cataloguswaarden',
        'iso_code' => 'OIC',
        'name' => 'Testland onbekende exchange id cataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-onbekende-exchange-id-cataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel onbekende exchange id cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een cataloguswaarde met een onbekende exchange_stamp_id.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-999');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.50');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de onbekende exchange_stamp_id afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende exchange_stamp_id in tabblad Cataloguswaarden, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel onbekende exchange id cataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // Er mogen geen cataloguswaarden zijn opgeslagen.
        expect(
            StampCatalogValue::query()
                ->where('condition_id', $condition->id)
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ontbrekende condition_slug in Cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende condition slug cataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ontbrekende-condition-slug-cataloguswaarden',
        'iso_code' => 'OCS',
        'name' => 'Testland ontbrekende condition slug cataloguswaarden',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ontbrekende condition slug cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een cataloguswaarde zonder condition_slug.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', '');
        $catalogValuesSheet->setCellValue('D2', '25.50');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ontbrekende condition_slug afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ontbrekende condition_slug in tabblad Cataloguswaarden, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ontbrekende condition slug cataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // Er mogen geen cataloguswaarden zijn opgeslagen.
        expect(
            StampCatalogValue::query()
                ->where('catalog_value', '25.50')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een onbekende condition_slug in Cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onbekende condition slug cataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-onbekende-condition-slug-cataloguswaarden',
        'iso_code' => 'UCS',
        'name' => 'Testland onbekende condition slug cataloguswaarden',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel onbekende condition slug cataloguswaarden');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een cataloguswaarde met een onbekende condition_slug.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', 'onbekende-conditie');
        $catalogValuesSheet->setCellValue('D2', '25.50');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de onbekende condition_slug afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende condition_slug onbekende-conditie in tabblad Cataloguswaarden, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel onbekende condition slug cataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // Er mogen geen cataloguswaarden zijn opgeslagen.
        expect(
            StampCatalogValue::query()
                ->where('catalog_value', '25.50')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegels en cataloguseditie worden niet opgeslagen bij een ongeldige catalog_value in Cataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige catalog value',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-catalog-value',
        'iso_code' => 'OCV',
        'name' => 'Testland ongeldige catalog value',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-ongeldige-catalog-value',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegel aan.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel ongeldige catalog value');
        $stampsSheet->setCellValue('I2', 2026);

        // Maak een cataloguswaarde met drie decimalen.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.555');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('G2', 0);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ongeldige cataloguswaarde afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige catalog_value in tabblad Cataloguswaarden, rij 2'
        );

        // De postzegel mag niet zijn opgeslagen.
        expect(
            Stamp::query()
                ->where('name', 'Postzegel ongeldige catalog value')
                ->exists()
        )->toBeFalse();

        // De catalogusvermelding mag niet zijn opgeslagen.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_number', '100')
                ->exists()
        )->toBeFalse();

        // Er mogen geen cataloguswaarden zijn opgeslagen.
        expect(
            StampCatalogValue::query()
                ->where('condition_id', $condition->id)
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('seriecataloguswaarden worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus import seriecataloguswaarden',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-import-seriecataloguswaarden',
        'iso_code' => 'ISW',
        'name' => 'Testland import seriecataloguswaarden',
    ]);

    // Maak een waarderingscategorie aan.
    $condition = Condition::create([
        'slug' => 'testconditie-import-seriecataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe aan het Excel-tabblad Landen.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg de waarderingscategorie toe aan Excel.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Voeg een postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 1001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie import seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving postzegelserie');

        // Voeg een seriecataloguswaarde toe.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 1001);
        $seriesValuesSheet->setCellValue('B2', $condition->slug);
        $seriesValuesSheet->setCellValue('C2', 'SER-100');
        $seriesValuesSheet->setCellValue('D2', '125.50');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Testopmerking seriecataloguswaarde');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie met seriecataloguswaarden.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de geïmporteerde cataloguseditie.
        expect($importedEdition->catalog_id)->toBe($catalog->id);
        expect($importedEdition->edition_name)->toBe('Editie 2026');

        // Controleer de geïmporteerde postzegelserie.
        $series = StampSeries::query()
            ->where('country_id', $country->id)
            ->where('name', 'Testserie import seriecataloguswaarden')
            ->first();

        expect($series)->not->toBeNull();

        // Controleer de geïmporteerde seriecataloguswaarde.
        $seriesCatalogValue = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('stamp_series_id', $series->id)
            ->where('condition_id', $condition->id)
            ->first();

        expect($seriesCatalogValue)->not->toBeNull();

        // Controleer alle geïmporteerde velden.
        expect($seriesCatalogValue->catalog_number)->toBe('SER-100');
        expect($seriesCatalogValue->catalog_value)->toBe('125.50');
        expect($seriesCatalogValue->currency)->toBe('EUR');
        expect($seriesCatalogValue->notes)->toBe('Testopmerking seriecataloguswaarde');

        // Controleer dat precies één seriecataloguswaarde is aangemaakt.
        expect(
            SeriesCatalogValue::query()
                ->where('catalog_edition_id', $importedEdition->id)
                ->where('stamp_series_id', $series->id)
                ->count()
        )->toBe(1);

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere seriecataloguswaarden worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere seriecataloguswaarden',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-meerdere-seriecataloguswaarden',
        'iso_code' => 'MSW',
        'name' => 'Testland meerdere seriecataloguswaarden',
    ]);

    // Maak twee waarderingscategorieën aan.
    $postfris = Condition::create([
        'slug' => 'test-postfris-seriecataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'test-gestempeld-seriecataloguswaarden',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe aan Excel.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg beide waarderingscategorieën toe aan Excel.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $postfris->slug);
        $conditionsSheet->setCellValue('A3', $gestempeld->slug);

        // Voeg één postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 2001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie meerdere seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving postzegelserie');

        // Voeg de seriecataloguswaarde voor postfris toe.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 2001);
        $seriesValuesSheet->setCellValue('B2', $postfris->slug);
        $seriesValuesSheet->setCellValue('C2', 'SER-200');
        $seriesValuesSheet->setCellValue('D2', '125.50');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Seriecataloguswaarde postfris');

        // Voeg de seriecataloguswaarde voor gestempeld toe.
        $seriesValuesSheet->setCellValue('A3', 2001);
        $seriesValuesSheet->setCellValue('B3', $gestempeld->slug);
        $seriesValuesSheet->setCellValue('C3', 'SER-200');
        $seriesValuesSheet->setCellValue('D3', '75.00');
        $seriesValuesSheet->setCellValue('E3', 'EUR');
        $seriesValuesSheet->setCellValue('F3', 'Seriecataloguswaarde gestempeld');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Zoek de geïmporteerde postzegelserie.
        $series = StampSeries::query()
            ->where('country_id', $country->id)
            ->where('name', 'Testserie meerdere seriecataloguswaarden')
            ->first();

        expect($series)->not->toBeNull();

        // Controleer dat precies twee seriecataloguswaarden zijn aangemaakt.
        expect(
            SeriesCatalogValue::query()
                ->where('catalog_edition_id', $importedEdition->id)
                ->where('stamp_series_id', $series->id)
                ->count()
        )->toBe(2);

        // Controleer de seriecataloguswaarde voor postfris.
        $postfrisValue = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('stamp_series_id', $series->id)
            ->where('condition_id', $postfris->id)
            ->first();

        expect($postfrisValue)->not->toBeNull();
        expect($postfrisValue->catalog_number)->toBe('SER-200');
        expect($postfrisValue->catalog_value)->toBe('125.50');
        expect($postfrisValue->currency)->toBe('EUR');
        expect($postfrisValue->notes)->toBe('Seriecataloguswaarde postfris');

        // Controleer de seriecataloguswaarde voor gestempeld.
        $gestempeldValue = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('stamp_series_id', $series->id)
            ->where('condition_id', $gestempeld->id)
            ->first();

        expect($gestempeldValue)->not->toBeNull();
        expect($gestempeldValue->catalog_number)->toBe('SER-200');
        expect($gestempeldValue->catalog_value)->toBe('75.00');
        expect($gestempeldValue->currency)->toBe('EUR');
        expect($gestempeldValue->notes)->toBe('Seriecataloguswaarde gestempeld');

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('seriecataloguswaarden met lege optionele velden worden succesvol geimporteerd vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus lege optionele seriecataloguswaarden',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-lege-seriecataloguswaarden',
        'iso_code' => 'LSW',
        'name' => 'Testland lege seriecataloguswaarden',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe aan Excel.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg één postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 3001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie lege optionele seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving postzegelserie');

        // Voeg een seriecataloguswaarde toe met lege optionele velden.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 3001);
        $seriesValuesSheet->setCellValue('B2', null);
        $seriesValuesSheet->setCellValue('C2', null);
        $seriesValuesSheet->setCellValue('D2', null);
        $seriesValuesSheet->setCellValue('E2', null);
        $seriesValuesSheet->setCellValue('F2', null);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Zoek de geïmporteerde postzegelserie.
        $series = StampSeries::query()
            ->where('country_id', $country->id)
            ->where('name', 'Testserie lege optionele seriecataloguswaarden')
            ->first();

        expect($series)->not->toBeNull();

        // Zoek de geïmporteerde seriecataloguswaarde.
        $seriesCatalogValue = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('stamp_series_id', $series->id)
            ->first();

        expect($seriesCatalogValue)->not->toBeNull();

        // Controleer alle lege optionele velden.
        expect($seriesCatalogValue->condition_id)->toBeNull();
        expect($seriesCatalogValue->catalog_number)->toBeNull();
        expect($seriesCatalogValue->catalog_value)->toBeNull();
        expect($seriesCatalogValue->currency)->toBeNull();
        expect($seriesCatalogValue->notes)->toBeNull();

        // Controleer dat precies één seriecataloguswaarde is aangemaakt.
        expect(
            SeriesCatalogValue::query()
                ->where('catalog_edition_id', $importedEdition->id)
                ->where('stamp_series_id', $series->id)
                ->count()
        )->toBe(1);

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('seriecataloguswaarden en cataloguseditie worden teruggedraaid bij een fout tijdens het opslaan', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback seriecataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-rollback-seriecataloguswaarden',
        'iso_code' => 'RSW',
        'name' => 'Testland rollback seriecataloguswaarden',
    ]);

    $postfris = Condition::create([
        'slug' => 'test-postfris-rollback-seriecataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'test-gestempeld-rollback-seriecataloguswaarden',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg beide waarderingscategorieën toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $postfris->slug);
        $conditionsSheet->setCellValue('A3', $gestempeld->slug);

        // Voeg één postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 4001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie rollback seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving rollback postzegelserie');

        // Voeg twee geldige seriecataloguswaarden toe.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        // Eerste seriecataloguswaarde: postfris.
        $seriesValuesSheet->setCellValue('A2', 4001);
        $seriesValuesSheet->setCellValue('B2', $postfris->slug);
        $seriesValuesSheet->setCellValue('C2', 'SER-400');
        $seriesValuesSheet->setCellValue('D2', '125.50');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Rollbacktest serie postfris');

        // Tweede seriecataloguswaarde: gestempeld.
        $seriesValuesSheet->setCellValue('A3', 4001);
        $seriesValuesSheet->setCellValue('B3', $gestempeld->slug);
        $seriesValuesSheet->setCellValue('C3', 'SER-400');
        $seriesValuesSheet->setCellValue('D3', '75.00');
        $seriesValuesSheet->setCellValue('E3', 'EUR');
        $seriesValuesSheet->setCellValue('F3', 'Rollbacktest serie gestempeld');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Bewaar de oorspronkelijke Eloquent-event-dispatcher.
        $originalDispatcher = SeriesCatalogValue::getEventDispatcher();

        // Gebruik een afzonderlijke dispatcher voor deze test.
        $testDispatcher = clone $originalDispatcher;

        SeriesCatalogValue::setEventDispatcher($testDispatcher);

        // Simuleer een fout bij de tweede seriecataloguswaarde.
        SeriesCatalogValue::creating(
            function (SeriesCatalogValue $value) use ($gestempeld) {
                if ($value->condition_id === $gestempeld->id) {
                    throw new RuntimeException(
                        'Gesimuleerde fout tijdens opslaan seriecataloguswaarde gestempeld.'
                    );
                }
            }
        );

        // De import moet mislukken tijdens de databasetransactie.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens opslaan seriecataloguswaarde gestempeld.'
        );

        // De geïmporteerde postzegelserie moet zijn teruggedraaid.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie rollback seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De eerste seriecataloguswaarde moet zijn teruggedraaid.
        expect(
            SeriesCatalogValue::query()
                ->where('condition_id', $postfris->id)
                ->where('notes', 'Rollbacktest serie postfris')
                ->exists()
        )->toBeFalse();

        // De tweede seriecataloguswaarde mag niet bestaan.
        expect(
            SeriesCatalogValue::query()
                ->where('condition_id', $gestempeld->id)
                ->where('notes', 'Rollbacktest serie gestempeld')
                ->exists()
        )->toBeFalse();

        // De nieuwe cataloguseditie moet zijn teruggedraaid.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        // Herstel de oorspronkelijke Eloquent-event-dispatcher.
        if (isset($originalDispatcher)) {
            SeriesCatalogValue::setEventDispatcher($originalDispatcher);
        }

        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelseries en cataloguseditie worden niet opgeslagen bij een onbekende exchange_series_id in Seriecataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onbekende exchange id seriecataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-onbekende-exchange-id-seriecataloguswaarden',
        'iso_code' => 'OIS',
        'name' => 'Testland onbekende exchange id seriecataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-onbekende-exchange-id-seriecataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegelserie aan.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 5001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie onbekende exchange id seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving onbekende exchange id');

        // Maak een seriecataloguswaarde met een onbekende exchange_series_id.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 9999);
        $seriesValuesSheet->setCellValue('B2', $condition->slug);
        $seriesValuesSheet->setCellValue('C2', 'SER-500');
        $seriesValuesSheet->setCellValue('D2', '125.50');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Onbekende exchange series id');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de onbekende exchange_series_id afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende exchange_series_id 9999 in tabblad Seriecataloguswaarden, rij 2'
        );

        // De postzegelserie mag niet zijn opgeslagen.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie onbekende exchange id seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De seriecataloguswaarde mag niet zijn opgeslagen.
        expect(
            SeriesCatalogValue::query()
                ->where('condition_id', $condition->id)
                ->where('notes', 'Onbekende exchange series id')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelseries en cataloguseditie worden niet opgeslagen bij een onbekende condition_slug in Seriecataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onbekende condition slug seriecataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-onbekende-condition-slug-seriecataloguswaarden',
        'iso_code' => 'UCS',
        'name' => 'Testland onbekende condition slug seriecataloguswaarden',
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Maak één geldige postzegelserie aan.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 6001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie onbekende condition slug seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving onbekende condition slug');

        // Maak een seriecataloguswaarde met een onbekende condition_slug.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 6001);
        $seriesValuesSheet->setCellValue('B2', 'onbekende-conditie');
        $seriesValuesSheet->setCellValue('C2', 'SER-600');
        $seriesValuesSheet->setCellValue('D2', '125.50');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Onbekende condition slug seriecataloguswaarden');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de onbekende condition_slug afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende condition_slug onbekende-conditie in tabblad Seriecataloguswaarden, rij 2'
        );

        // De postzegelserie mag niet zijn opgeslagen.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie onbekende condition slug seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De seriecataloguswaarde mag niet zijn opgeslagen.
        expect(
            SeriesCatalogValue::query()
                ->where('catalog_number', 'SER-600')
                ->where('notes', 'Onbekende condition slug seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelseries en cataloguseditie worden niet opgeslagen bij dubbele seriecataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus dubbele seriecataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-dubbele-seriecataloguswaarden',
        'iso_code' => 'DSW',
        'name' => 'Testland dubbele seriecataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'test-postfris-dubbele-seriecataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegelserie aan.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 7001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie dubbele seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving dubbele seriecataloguswaarden');

        // Voeg twee seriecataloguswaarden toe met dezelfde combinatie.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        // Eerste seriecataloguswaarde.
        $seriesValuesSheet->setCellValue('A2', 7001);
        $seriesValuesSheet->setCellValue('B2', $condition->slug);
        $seriesValuesSheet->setCellValue('C2', 'SER-700');
        $seriesValuesSheet->setCellValue('D2', '125.50');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Eerste dubbele seriecataloguswaarde');

        // Tweede seriecataloguswaarde: dezelfde serie en waarderingscategorie.
        $seriesValuesSheet->setCellValue('A3', 7001);
        $seriesValuesSheet->setCellValue('B3', $condition->slug);
        $seriesValuesSheet->setCellValue('C3', 'SER-700');
        $seriesValuesSheet->setCellValue('D3', '150.00');
        $seriesValuesSheet->setCellValue('E3', 'EUR');
        $seriesValuesSheet->setCellValue('F3', 'Tweede dubbele seriecataloguswaarde');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de dubbele combinatie afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Dubbele seriecataloguswaarde voor exchange_series_id 7001 en condition_slug '.$condition->slug.' in tabblad Seriecataloguswaarden, rij 3'
        );

        // De postzegelserie mag niet zijn opgeslagen.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie dubbele seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De eerste seriecataloguswaarde mag niet zijn opgeslagen.
        expect(
            SeriesCatalogValue::query()
                ->where('condition_id', $condition->id)
                ->where('notes', 'Eerste dubbele seriecataloguswaarde')
                ->exists()
        )->toBeFalse();

        // De tweede seriecataloguswaarde mag niet zijn opgeslagen.
        expect(
            SeriesCatalogValue::query()
                ->where('condition_id', $condition->id)
                ->where('notes', 'Tweede dubbele seriecataloguswaarde')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelseries en cataloguseditie worden niet opgeslagen bij een ongeldige catalog_value in Seriecataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige catalog value seriecataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-catalog-value-seriecataloguswaarden',
        'iso_code' => 'OCS',
        'name' => 'Testland ongeldige catalog value seriecataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-ongeldige-catalog-value-seriecataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegelserie aan.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 8001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie ongeldige catalog value seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving ongeldige catalog value');

        // Maak een seriecataloguswaarde met drie decimalen.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 8001);
        $seriesValuesSheet->setCellValue('B2', $condition->slug);
        $seriesValuesSheet->setCellValue('C2', 'SER-800');
        $seriesValuesSheet->setCellValue('D2', '125.123');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Ongeldige catalog value seriecataloguswaarden');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ongeldige cataloguswaarde afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige catalog_value in tabblad Seriecataloguswaarden, rij 2'
        );

        // De postzegelserie mag niet zijn opgeslagen.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie ongeldige catalog value seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De seriecataloguswaarde mag niet zijn opgeslagen.
        expect(
            SeriesCatalogValue::query()
                ->where('catalog_number', 'SER-800')
                ->where('notes', 'Ongeldige catalog value seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelseries en cataloguseditie worden niet opgeslagen bij een ongeldige currency in Seriecataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige currency seriecataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-currency-seriecataloguswaarden',
        'iso_code' => 'OCS',
        'name' => 'Testland ongeldige currency seriecataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-ongeldige-currency-seriecataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegelserie aan.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 9001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie ongeldige currency seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving ongeldige currency');

        // Maak een seriecataloguswaarde met een ongeldige valutacode.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 9001);
        $seriesValuesSheet->setCellValue('B2', $condition->slug);
        $seriesValuesSheet->setCellValue('C2', 'SER-900');
        $seriesValuesSheet->setCellValue('D2', '125.50');
        $seriesValuesSheet->setCellValue('E2', 'EURO');
        $seriesValuesSheet->setCellValue('F2', 'Ongeldige currency seriecataloguswaarden');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de ongeldige valutacode afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige currency in tabblad Seriecataloguswaarden, rij 2'
        );

        // De postzegelserie mag niet zijn opgeslagen.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie ongeldige currency seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De seriecataloguswaarde mag niet zijn opgeslagen.
        expect(
            SeriesCatalogValue::query()
                ->where('catalog_number', 'SER-900')
                ->where('notes', 'Ongeldige currency seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelseries en cataloguseditie worden niet opgeslagen bij een ongeldige catalog_number in Seriecataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige catalog number seriecataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-catalog-number-seriecataloguswaarden',
        'iso_code' => 'OCN',
        'name' => 'Testland ongeldige catalog number seriecataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-ongeldige-catalog-number-seriecataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegelserie aan.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 10001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie ongeldige catalog number seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving ongeldige catalog number');

        // Maak een catalogusnummer van 101 tekens.
        $invalidCatalogNumber = str_repeat('A', 101);

        // Voeg de seriecataloguswaarde toe.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 10001);
        $seriesValuesSheet->setCellValue('B2', $condition->slug);
        $seriesValuesSheet->setCellValue('C2', $invalidCatalogNumber);
        $seriesValuesSheet->setCellValue('D2', '125.50');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Ongeldige catalog number seriecataloguswaarden');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet het te lange catalogusnummer afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige catalog_number in tabblad Seriecataloguswaarden, rij 2'
        );

        // De postzegelserie mag niet zijn opgeslagen.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie ongeldige catalog number seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De seriecataloguswaarde mag niet zijn opgeslagen.
        expect(
            SeriesCatalogValue::query()
                ->where('catalog_number', $invalidCatalogNumber)
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelseries en cataloguseditie worden niet opgeslagen bij ongeldige notes in Seriecataloguswaarden', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige notes seriecataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-ongeldige-notes-seriecataloguswaarden',
        'iso_code' => 'ONS',
        'name' => 'Testland ongeldige notes seriecataloguswaarden',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-ongeldige-notes-seriecataloguswaarden',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Genereer het Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg het land toe.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Maak één geldige postzegelserie aan.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 11001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie ongeldige notes seriecataloguswaarden');
        $seriesSheet->setCellValue('E2', 2026);
        $seriesSheet->setCellValue('F2', 'Testbeschrijving ongeldige notes');

        // Voeg een seriecataloguswaarde met numerieke notes toe.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 11001);
        $seriesValuesSheet->setCellValue('B2', $condition->slug);
        $seriesValuesSheet->setCellValue('C2', 'SER-1100');
        $seriesValuesSheet->setCellValue('D2', '125.50');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 12345);

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De validator moet de numerieke notes afwijzen.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Ongeldige notes in tabblad Seriecataloguswaarden, rij 2'
        );

        // De postzegelserie mag niet zijn opgeslagen.
        expect(
            StampSeries::query()
                ->where('country_id', $country->id)
                ->where('name', 'Testserie ongeldige notes seriecataloguswaarden')
                ->exists()
        )->toBeFalse();

        // De seriecataloguswaarde mag niet zijn opgeslagen.
        expect(
            SeriesCatalogValue::query()
                ->where('catalog_number', 'SER-1100')
                ->exists()
        )->toBeFalse();

        // De cataloguseditie mag niet zijn opgeslagen.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een postzegel wordt succesvol gekoppeld aan een thema vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus postzegelthemas',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-postzegelthemas',
        'iso_code' => 'TST',
        'name' => 'Testland',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'test-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Testpostzegel met thema');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');
        $stampsSheet->setCellValue('L2', 'EUR');

        // Voeg een thema toe.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        $themesSheet->setCellValue('A2', 'test-thema-dieren');
        $themesSheet->setCellValue('B2', 'Dieren');
        $themesSheet->setCellValue('C2', 'Postzegels met dieren.');

        // Koppel de postzegel aan het thema.
        $stampThemesSheet = $spreadsheet->getSheetByName('Postzegelthemas');

        $stampThemesSheet->setCellValue('A2', 'test-postzegel-1');
        $stampThemesSheet->setCellValue('B2', 'test-thema-dieren');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de geïmporteerde postzegel.
        $stamp = Stamp::query()
            ->where('name', 'Testpostzegel met thema')
            ->first();

        expect($stamp)->not->toBeNull();

        // Controleer het geïmporteerde thema.
        $theme = Theme::query()
            ->where('slug', 'test-thema-dieren')
            ->first();

        expect($theme)->not->toBeNull();

        // Controleer de koppeling tussen postzegel en thema.
        expect(
            $stamp->themes()
                ->where('themes.id', $theme->id)
                ->exists()
        )->toBeTrue();

        // Controleer dat de postzegel precies één thema heeft.
        expect($stamp->themes()->count())->toBe(1);

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('meerdere themas worden succesvol gekoppeld aan een postzegel vanuit Excel', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere postzegelthemas',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-meerdere-postzegelthemas',
        'iso_code' => 'TST',
        'name' => 'Testland',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'test-postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Testpostzegel met meerdere themas');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');
        $stampsSheet->setCellValue('L2', 'EUR');

        // Definieer drie thema's.
        $themes = [
            ['test-thema-dieren', 'Dieren', 'Postzegels met dieren.'],
            ['test-thema-bloemen', 'Bloemen', 'Postzegels met bloemen.'],
            ['test-thema-sport', 'Sport', 'Postzegels met sport.'],
        ];

        // Voeg de thema's toe aan Excel.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        // Koppel alle drie de thema's aan dezelfde postzegel.
        $stampThemesSheet = $spreadsheet->getSheetByName('Postzegelthemas');

        foreach ($themes as $index => [$slug, $name, $description]) {
            $row = $index + 2;

            $themesSheet->setCellValue('A'.$row, $slug);
            $themesSheet->setCellValue('B'.$row, $name);
            $themesSheet->setCellValue('C'.$row, $description);

            $stampThemesSheet->setCellValue('A'.$row, 'test-postzegel-1');
            $stampThemesSheet->setCellValue('B'.$row, $slug);
        }

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer de cataloguseditie.
        app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de geïmporteerde postzegel.
        $stamp = Stamp::query()
            ->where('name', 'Testpostzegel met meerdere themas')
            ->first();

        expect($stamp)->not->toBeNull();

        // Controleer of alle drie de thema's bestaan.
        foreach ($themes as [$slug]) {
            $theme = Theme::query()
                ->where('slug', $slug)
                ->first();

            expect($theme)->not->toBeNull();

            // Controleer de koppeling met de postzegel.
            expect(
                $stamp->themes()
                    ->where('themes.id', $theme->id)
                    ->exists()
            )->toBeTrue();
        }

        // Controleer dat de postzegel precies drie thema's heeft.
        expect($stamp->themes()->count())->toBe(3);

        // Controleer de daadwerkelijke koppelingen in de database.
        expect(
            DB::table('stamp_themes')
                ->where('stamp_id', $stamp->id)
                ->count()
        )->toBe(3);

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelthemas worden niet geimporteerd bij een onbekende theme_slug', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback postzegelthemas',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-rollback-postzegelthemas',
        'iso_code' => 'TST',
        'name' => 'Testland',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'test-postzegel-rollback');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Testpostzegel rollback themas');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');
        $stampsSheet->setCellValue('L2', 'EUR');

        // Voeg een geldig thema toe.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        $themesSheet->setCellValue('A2', 'test-thema-rollback');
        $themesSheet->setCellValue('B2', 'Rollback thema');
        $themesSheet->setCellValue('C2', 'Testthema voor foutafhandeling.');

        // Voeg twee postzegel-themakoppelingen toe.
        $stampThemesSheet = $spreadsheet->getSheetByName('Postzegelthemas');

        // Rij 2: geldige koppeling.
        $stampThemesSheet->setCellValue('A2', 'test-postzegel-rollback');
        $stampThemesSheet->setCellValue('B2', 'test-thema-rollback');

        // Rij 3: onbekend thema.
        $stampThemesSheet->setCellValue('A3', 'test-postzegel-rollback');
        $stampThemesSheet->setCellValue('B3', 'test-onbekend-thema');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken door de onbekende theme_slug.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende theme_slug test-onbekend-thema in tabblad Postzegelthemas, rij 3'
        );

        // De nieuwe postzegel mag niet bestaan.
        expect(
            Stamp::query()
                ->where('name', 'Testpostzegel rollback themas')
                ->exists()
        )->toBeFalse();

        // Het nieuwe thema mag niet bestaan.
        expect(
            Theme::query()
                ->where('slug', 'test-thema-rollback')
                ->exists()
        )->toBeFalse();

        // Er mogen geen nieuwe postzegel-themakoppelingen bestaan.
        expect(
            DB::table('stamp_themes')->count()
        )->toBe(0);

        // De cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('postzegelthemas worden niet geimporteerd bij een onbekende exchange_stamp_id', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus onbekende postzegelthema koppeling',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-onbekende-postzegelthema-koppeling',
        'iso_code' => 'TST',
        'name' => 'Testland',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een geldige postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'test-postzegel-bestaand');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Testpostzegel onbekende koppeling');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');
        $stampsSheet->setCellValue('L2', 'EUR');

        // Voeg een geldig thema toe.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        $themesSheet->setCellValue('A2', 'test-thema-onbekende-koppeling');
        $themesSheet->setCellValue('B2', 'Testthema');
        $themesSheet->setCellValue('C2', 'Thema voor foutafhandeling.');

        // Voeg twee postzegel-themakoppelingen toe.
        $stampThemesSheet = $spreadsheet->getSheetByName('Postzegelthemas');

        // Rij 2: geldige koppeling.
        $stampThemesSheet->setCellValue('A2', 'test-postzegel-bestaand');
        $stampThemesSheet->setCellValue('B2', 'test-thema-onbekende-koppeling');

        // Rij 3: onbekende postzegel.
        $stampThemesSheet->setCellValue('A3', 'test-postzegel-onbekend');
        $stampThemesSheet->setCellValue('B3', 'test-thema-onbekende-koppeling');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken door de onbekende exchange_stamp_id.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Onbekende exchange_stamp_id test-postzegel-onbekend in tabblad Postzegelthemas, rij 3'
        );

        // De geldige postzegel mag niet geïmporteerd zijn.
        expect(
            Stamp::query()
                ->where('name', 'Testpostzegel onbekende koppeling')
                ->exists()
        )->toBeFalse();

        // Het nieuwe thema mag niet geïmporteerd zijn.
        expect(
            Theme::query()
                ->where('slug', 'test-thema-onbekende-koppeling')
                ->exists()
        )->toBeFalse();

        // Er mogen geen nieuwe postzegel-themakoppelingen bestaan.
        expect(
            DB::table('stamp_themes')->count()
        )->toBe(0);

        // De cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('dubbele postzegel-themakoppelingen worden afgewezen tijdens Excel-import', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus dubbele postzegelthemas',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-dubbele-postzegelthemas',
        'iso_code' => 'TST',
        'name' => 'Testland',
    ]);

    // Genereer een geldig Excel-uitwisselingsbestand.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    $spreadsheet = null;

    try {
        file_put_contents($filePath, $excelContent);

        // Open het Excel-bestand.
        $spreadsheet = IOFactory::load($filePath);

        // Voeg een postzegel toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'test-postzegel-dubbel');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Testpostzegel dubbele themakoppeling');
        $stampsSheet->setCellValue('I2', 2026);
        $stampsSheet->setCellValue('K2', '1/2');
        $stampsSheet->setCellValue('L2', 'EUR');

        // Voeg een thema toe.
        $themesSheet = $spreadsheet->getSheetByName('Themas');

        $themesSheet->setCellValue('A2', 'test-thema-dubbel');
        $themesSheet->setCellValue('B2', 'Dubbel thema');
        $themesSheet->setCellValue('C2', 'Thema voor controle op dubbele koppelingen.');

        // Voeg twee identieke postzegel-themakoppelingen toe.
        $stampThemesSheet = $spreadsheet->getSheetByName('Postzegelthemas');

        // Rij 2: eerste koppeling.
        $stampThemesSheet->setCellValue('A2', 'test-postzegel-dubbel');
        $stampThemesSheet->setCellValue('B2', 'test-thema-dubbel');

        // Rij 3: dezelfde koppeling.
        $stampThemesSheet->setCellValue('A3', 'test-postzegel-dubbel');
        $stampThemesSheet->setCellValue('B3', 'test-thema-dubbel');

        // Sla het gewijzigde Excel-bestand op.
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        // Sluit het spreadsheet.
        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // De import moet mislukken door de dubbele koppeling.
        expect(
            fn () => app(CatalogEditionExchangeImporter::class)
                ->importEdition($filePath, $catalog)
        )->toThrow(
            RuntimeException::class,
            'Dubbele postzegel-themakoppeling voor exchange_stamp_id '.
            'test-postzegel-dubbel en theme_slug test-thema-dubbel '.
            'in tabblad Postzegelthemas, rij 3'
        );

        // De postzegel mag niet geïmporteerd zijn.
        expect(
            Stamp::query()
                ->where('name', 'Testpostzegel dubbele themakoppeling')
                ->exists()
        )->toBeFalse();

        // Het thema mag niet geïmporteerd zijn.
        expect(
            Theme::query()
                ->where('slug', 'test-thema-dubbel')
                ->exists()
        )->toBeFalse();

        // Er mogen geen postzegel-themakoppelingen zijn opgeslagen.
        expect(
            DB::table('stamp_themes')->count()
        )->toBe(0);

        // De cataloguseditie mag niet bestaan.
        expect(
            CatalogEdition::query()
                ->where('catalog_id', $catalog->id)
                ->where('edition_name', 'Editie 2026')
                ->where('edition_year', 2026)
                ->exists()
        )->toBeFalse();

    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een postzegel met serie en catalogusvermelding blijft behouden na Excel round trip', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus Excel round trip',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-round-trip',
        'iso_code' => 'TRT',
        'name' => 'Testland round trip',
    ]);

    // Maak een postzegelserie aan.
    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie round trip',
        'issue_year' => 2026,
        'description' => 'Serie voor Excel round-triptest',
    ]);

    // Maak een postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'stamp_series_id' => $series->id,
        'name' => 'Testpostzegel round trip',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    // Koppel de postzegel aan de cataloguseditie.
    StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
        'notes' => 'Catalogusvermelding round trip',
    ]);

    // Exporteer de oorspronkelijke gegevens naar Excel.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_round_trip_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    try {
        file_put_contents($filePath, $excelContent);

        // Verwijder de oorspronkelijke catalogusvermelding.
        StampCatalogEntry::query()
            ->where('catalog_edition_id', $edition->id)
            ->delete();

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Verwijder de oorspronkelijke postzegel.
        $stamp->delete();

        // Verwijder de oorspronkelijke serie.
        $series->delete();

        // Importeer het ongewijzigde Excel-bestand.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Controleer de geïmporteerde postzegel.
        $importedStamp = Stamp::query()
            ->where('name', 'Testpostzegel round trip')
            ->first();

        expect($importedStamp)->not->toBeNull();

        expect($importedStamp->issue_year)->toBe(2026);
        expect($importedStamp->face_value)->toBe('1/2');
        expect($importedStamp->currency)->toBe('EUR');
        expect($importedStamp->country_id)->toBe($country->id);

        // Controleer de geïmporteerde serie.
        $importedSeries = StampSeries::query()
            ->where('name', 'Testserie round trip')
            ->first();

        expect($importedSeries)->not->toBeNull();

        expect($importedSeries->issue_year)->toBe(2026);
        expect($importedSeries->country_id)->toBe($country->id);

        // Controleer de koppeling tussen postzegel en serie.
        expect($importedStamp->stamp_series_id)->toBe($importedSeries->id);

        // Controleer de catalogusvermelding.
        $importedEntry = StampCatalogEntry::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('stamp_id', $importedStamp->id)
            ->first();

        expect($importedEntry)->not->toBeNull();

        expect($importedEntry->catalog_number)->toBe('100');
        expect($importedEntry->notes)->toBe('Catalogusvermelding round trip');

        // Controleer dat de cataloguseditie precies één vermelding bevat.
        expect(
            StampCatalogEntry::query()
                ->where('catalog_edition_id', $importedEdition->id)
                ->count()
        )->toBe(1);

    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        unlink($filePath);
    }
});

test('cataloguswaarden blijven behouden na Excel round trip', function () {
    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus cataloguswaarden round trip',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-cataloguswaarden-round-trip',
        'iso_code' => 'CRT',
        'name' => 'Testland cataloguswaarden round trip',
    ]);

    // Maak een waarderingscategorie aan.
    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak een postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Testpostzegel cataloguswaarden round trip',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    // Maak de catalogusvermelding aan.
    $entry = StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    // Maak een cataloguswaarde aan.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Testopmerking cataloguswaarde round trip',
        'needs_review' => true,
    ]);

    // Exporteer de gegevens naar Excel.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een tijdelijk Excel-bestand aan.
    $filePath = tempnam(sys_get_temp_dir(), 'catalog_values_round_trip_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    try {
        file_put_contents($filePath, $excelContent);

        // Verwijder de oorspronkelijke cataloguswaarde.
        StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $entry->id)
            ->delete();

        // Verwijder de oorspronkelijke catalogusvermelding.
        $entry->delete();

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Verwijder de oorspronkelijke postzegel.
        $stamp->delete();

        // Importeer het ongewijzigde Excel-bestand.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Zoek de geïmporteerde postzegel.
        $importedStamp = Stamp::query()
            ->where('name', 'Testpostzegel cataloguswaarden round trip')
            ->first();

        expect($importedStamp)->not->toBeNull();

        // Zoek de geïmporteerde catalogusvermelding.
        $importedEntry = StampCatalogEntry::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('stamp_id', $importedStamp->id)
            ->first();

        expect($importedEntry)->not->toBeNull();

        expect($importedEntry->catalog_number)->toBe('100');

        // Zoek de geïmporteerde cataloguswaarde.
        $importedValue = StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $importedEntry->id)
            ->first();

        expect($importedValue)->not->toBeNull();

        // Controleer alle oorspronkelijke cataloguswaardegegevens.
        expect($importedValue->condition_id)->toBe($condition->id);
        expect($importedValue->catalog_value)->toBe('25.50');
        expect($importedValue->currency)->toBe('EUR');
        expect($importedValue->notes)->toBe(
            'Testopmerking cataloguswaarde round trip'
        );
        expect($importedValue->needs_review)->toBeTrue();

        // Controleer dat precies één cataloguswaarde is geïmporteerd.
        expect(
            StampCatalogValue::query()
                ->where('stamp_catalog_entry_id', $importedEntry->id)
                ->count()
        )->toBe(1);

    } finally {
        // Verwijder het tijdelijke Excel-bestand.
        unlink($filePath);
    }
});
