<?php

use App\Exports\Exchange\CatalogEditionExchangeExcelExport;
use App\Imports\Exchange\CatalogEditionExchangeImporter;
use App\Models\Catalog;
use App\Models\CatalogChangeBatch;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\SeriesCatalogValue;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\StampSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

test('een nieuwe cataloguseditie registreert de aangemaakte catalogusvermelding in de geschiedenis', function () {
    // Maak een testgebruiker aan.
    $user = User::factory()->create([
        'name' => 'Testgebruiker importgeschiedenis',
    ]);

    // Maak een testcatalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus importgeschiedenis',
    ]);

    // Maak een cataloguseditie aan.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak een land aan.
    $country = Country::create([
        'slug' => 'testland-importgeschiedenis',
        'iso_code' => 'TST',
        'name' => 'Testland',
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

        // Voeg een postzegel met catalogusvermelding toe.
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

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Verwijder de oorspronkelijke cataloguseditie.
        $edition->delete();

        // Importeer met een expliciet opgegeven gebruiker.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'create', $user);

        // Haal de geïmporteerde catalogusvermelding op.
        $entry = StampCatalogEntry::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->sole();

        // Controleer dat precies één geschiedenisbatch bestaat.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)->toBe($importedEdition->id)
            ->and($batch->operation)->toBe('import_create')
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name)
            ->and($batch->catalog_name)->toBe($catalog->name)
            ->and($batch->catalog_edition_name)->toBe('Editie 2026');

        // Controleer dat precies één wijzigingslog bestaat.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        // Controleer de geregistreerde wijziging.
        expect($log->action)->toBe('created')
            ->and($log->record_type)->toBe('stamp_catalog_entry')
            ->and($log->record_id)->toBe($entry->id)
            ->and($log->stamp_id)->toBe($entry->stamp_id)
            ->and($log->catalog_edition_id)->toBe($importedEdition->id)
            ->and($log->old_values)->toBeNull()
            ->and($log->new_values['catalog_number'])->toBe('100')
            ->and($log->new_values['notes'])->toBe('Test catalogusopmerking');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe cataloguswaarde wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis cataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-importgeschiedenis-waarden',
        'iso_code' => 'ICW',
        'name' => 'Testland importgeschiedenis',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-importgeschiedenis',
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

        // Voeg de waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A2', $condition->slug);

        // Voeg een postzegel met catalogusvermelding toe.
        $stampsSheet = $spreadsheet->getSheetByName('Postzegels');

        $stampsSheet->setCellValue('A2', 'postzegel-1');
        $stampsSheet->setCellValue('C2', '100');
        $stampsSheet->setCellValue('E2', $country->slug);
        $stampsSheet->setCellValue('F2', $country->iso_code);
        $stampsSheet->setCellValue('G2', 'Postzegel importgeschiedenis');
        $stampsSheet->setCellValue('I2', 2026);

        // Voeg een cataloguswaarde toe.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('A2', 'postzegel-1');
        $catalogValuesSheet->setCellValue('B2', '100');
        $catalogValuesSheet->setCellValue('C2', $condition->slug);
        $catalogValuesSheet->setCellValue('D2', '25.50');
        $catalogValuesSheet->setCellValue('E2', 'EUR');
        $catalogValuesSheet->setCellValue('F2', 'Testopmerking cataloguswaarde');
        $catalogValuesSheet->setCellValue('G2', 1);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        $edition->delete();

        // Importeer zonder expliciet opgegeven gebruiker.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        $entry = StampCatalogEntry::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->sole();

        $value = StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $entry->id)
            ->where('condition_id', $condition->id)
            ->sole();

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_create')
            ->and($batch->catalog_edition_id)->toBe($importedEdition->id)
            ->and($batch->user_id)->toBeNull()
            ->and($batch->user_name)->toBeNull();

        // De catalogusvermelding en cataloguswaarde delen één batch.
        expect($batch->logs()->count())->toBe(2);

        $log = $batch->logs()
            ->where('record_id', $value->id)
            ->where('condition_id', $condition->id)
            ->sole();

        // Controleer de geregistreerde cataloguswaarde.
        expect($log->action)->toBe('created')
            ->and($log->record_type)->toBe('stamp_catalog_value')
            ->and($log->record_id)->toBe($value->id)
            ->and($log->stamp_id)->toBe($entry->stamp_id)
            ->and($log->condition_id)->toBe($condition->id)
            ->and($log->catalog_edition_id)->toBe($importedEdition->id)
            ->and($log->old_values)->toBeNull()
            ->and((float) $log->new_values['catalog_value'])->toBe(25.50)
            ->and($log->new_values['currency'])->toBe('EUR')
            ->and($log->new_values['notes'])->toBe('Testopmerking cataloguswaarde')
            ->and((bool) $log->new_values['needs_review'])->toBeTrue();
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe seriecataloguswaarde wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis seriecataloguswaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-seriecataloguswaarden',
        'iso_code' => 'ISW',
        'name' => 'Testland importgeschiedenis series',
    ]);

    $condition = Condition::create([
        'slug' => 'testconditie-geschiedenis-seriecataloguswaarden',
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

        // Voeg een postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 1001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', 'Testserie importgeschiedenis');
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

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        $edition->delete();

        // Importeer de cataloguseditie.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog);

        // Haal de geïmporteerde seriecataloguswaarde op.
        $value = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $importedEdition->id)
            ->where('condition_id', $condition->id)
            ->sole();

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_create')
            ->and($batch->catalog_edition_id)->toBe($importedEdition->id)
            ->and($batch->user_id)->toBeNull()
            ->and($batch->user_name)->toBeNull();

        // Controleer dat precies één wijzigingslog bestaat.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        // Controleer de geregistreerde seriecataloguswaarde.
        expect($log->action)->toBe('created')
            ->and($log->record_type)->toBe('series_catalog_value')
            ->and($log->record_id)->toBe($value->id)
            ->and($log->stamp_id)->toBeNull()
            ->and($log->stamp_series_id)->toBe($value->stamp_series_id)
            ->and($log->condition_id)->toBe($condition->id)
            ->and($log->catalog_edition_id)->toBe($importedEdition->id)
            ->and($log->old_values)->toBeNull()
            ->and($log->new_values['catalog_number'])->toBe('SER-100')
            ->and((float) $log->new_values['catalog_value'])->toBe(125.50)
            ->and($log->new_values['currency'])->toBe('EUR')
            ->and($log->new_values['notes'])->toBe('Testopmerking seriecataloguswaarde');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een gewijzigde cataloguswaarde wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis gewijzigde cataloguswaarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-gewijzigde-waarde',
        'iso_code' => 'TVC',
        'name' => 'Testland gewijzigde cataloguswaarde',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker vervangen',
    ]);

    // Maak een bestaande postzegel aan.
    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Postzegel gewijzigde cataloguswaarde',
        'issue_year' => 2026,
    ]);

    // Maak een bestaande catalogusvermelding aan.
    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    // Maak de oorspronkelijke cataloguswaarde aan.
    $existingValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande waarde',
        'needs_review' => false,
    ]);

    $originalValueId = $existingValue->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Wijzig de cataloguswaarde in Excel.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('D2', '50.00');
        $catalogValuesSheet->setCellValue('E2', 'USD');
        $catalogValuesSheet->setCellValue('F2', 'Gewijzigde waarde');
        $catalogValuesSheet->setCellValue('G2', 1);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $existingValue->refresh();

        // Controleer dat de bestaande records behouden blijven.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingValue->id)->toBe($originalValueId)
            ->and($existingValue->catalog_value)->toBe('50.00')
            ->and($existingValue->currency)->toBe('USD')
            ->and($existingValue->notes)->toBe('Gewijzigde waarde')
            ->and($existingValue->needs_review)->toBeTrue();

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Controleer dat uitsluitend de wijziging is geregistreerd.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('updated')
            ->and($log->record_type)->toBe('stamp_catalog_value')
            ->and($log->record_id)->toBe($originalValueId)
            ->and($log->stamp_id)->toBe($stamp->id)
            ->and($log->condition_id)->toBe($condition->id)
            ->and($log->catalog_edition_id)->toBe($edition->id);

        // Controleer de oorspronkelijke waarden.
        expect((float) $log->old_values['catalog_value'])->toBe(25.50)
            ->and($log->old_values['currency'])->toBe('EUR')
            ->and($log->old_values['notes'])->toBe('Bestaande waarde')
            ->and((bool) $log->old_values['needs_review'])->toBeFalse();

        // Controleer de nieuwe waarden.
        expect((float) $log->new_values['catalog_value'])->toBe(50.00)
            ->and($log->new_values['currency'])->toBe('USD')
            ->and($log->new_values['notes'])->toBe('Gewijzigde waarde')
            ->and((bool) $log->new_values['needs_review'])->toBeTrue();

        // Ongewijzigde velden mogen niet worden geregistreerd.
        expect($log->old_values)->not->toHaveKey('updated_at')
            ->and($log->new_values)->not->toHaveKey('updated_at')
            ->and($log->old_values)->not->toHaveKey('condition_id')
            ->and($log->new_values)->not->toHaveKey('condition_id');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een gewijzigde seriecataloguswaarde wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis gewijzigde seriecataloguswaarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-gewijzigde-seriewaarde',
        'iso_code' => 'TVS',
        'name' => 'Testland gewijzigde seriewaarde',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker vervangen seriewaarde',
    ]);

    // Maak een bestaande postzegelserie aan.
    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegelserie',
        'issue_year' => 2026,
    ]);

    // Maak de oorspronkelijke seriecataloguswaarde aan.
    $existingValue = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => $condition->id,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande seriecataloguswaarde',
    ]);

    $originalValueId = $existingValue->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Wijzig de seriecataloguswaarde in Excel.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('C2', 'SER-200');
        $seriesValuesSheet->setCellValue('D2', '250.00');
        $seriesValuesSheet->setCellValue('E2', 'USD');
        $seriesValuesSheet->setCellValue('F2', 'Gewijzigde seriecataloguswaarde');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $existingValue->refresh();

        // Controleer dat de bestaande records behouden blijven.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingValue->id)->toBe($originalValueId)
            ->and($existingValue->catalog_number)->toBe('SER-200')
            ->and($existingValue->catalog_value)->toBe('250.00')
            ->and($existingValue->currency)->toBe('USD')
            ->and($existingValue->notes)->toBe('Gewijzigde seriecataloguswaarde');

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Controleer dat uitsluitend de wijziging is geregistreerd.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('updated')
            ->and($log->record_type)->toBe('series_catalog_value')
            ->and($log->record_id)->toBe($originalValueId)
            ->and($log->stamp_id)->toBeNull()
            ->and($log->stamp_series_id)->toBe($series->id)
            ->and($log->condition_id)->toBe($condition->id)
            ->and($log->catalog_edition_id)->toBe($edition->id);

        // Controleer de oorspronkelijke waarden.
        expect($log->old_values['catalog_number'])->toBe('SER-100')
            ->and((float) $log->old_values['catalog_value'])->toBe(125.50)
            ->and($log->old_values['currency'])->toBe('EUR')
            ->and($log->old_values['notes'])->toBe('Bestaande seriecataloguswaarde');

        // Controleer de nieuwe waarden.
        expect($log->new_values['catalog_number'])->toBe('SER-200')
            ->and((float) $log->new_values['catalog_value'])->toBe(250.00)
            ->and($log->new_values['currency'])->toBe('USD')
            ->and($log->new_values['notes'])->toBe('Gewijzigde seriecataloguswaarde');

        // Ongewijzigde velden mogen niet worden geregistreerd.
        expect($log->old_values)->not->toHaveKey('updated_at')
            ->and($log->new_values)->not->toHaveKey('updated_at')
            ->and($log->old_values)->not->toHaveKey('condition_id')
            ->and($log->new_values)->not->toHaveKey('condition_id')
            ->and($log->old_values)->not->toHaveKey('stamp_series_id')
            ->and($log->new_values)->not->toHaveKey('stamp_series_id');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een verwijderde cataloguswaarde wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis verwijderde cataloguswaarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-verwijderde-waarde',
        'iso_code' => 'TOW',
        'name' => 'Testland verwijderde cataloguswaarde',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker verwijderen cataloguswaarde',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Postzegel met twee waarderingen',
        'issue_year' => 2026,
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    // Maak de cataloguswaarde aan die behouden moet blijven.
    $retainedValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Exporteer uitsluitend de waarde die behouden moet blijven.
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

    try {
        file_put_contents($filePath, $excelContent);

        // Voeg een cataloguswaarde toe die niet in Excel voorkomt.
        $missingValue = StampCatalogValue::create([
            'stamp_catalog_entry_id' => $entry->id,
            'condition_id' => $gestempeld->id,
            'catalog_value' => '10.00',
            'currency' => 'EUR',
            'notes' => 'Te verwijderen cataloguswaarde',
            'needs_review' => true,
        ]);

        $originalValueId = $missingValue->id;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        // Controleer de bestaande cataloguseditie.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer dat uitsluitend de ontbrekende waarde is verwijderd.
        expect(StampCatalogValue::query()
            ->whereKey($retainedValue->id)
            ->exists())->toBeTrue();

        expect(StampCatalogValue::query()
            ->whereKey($originalValueId)
            ->exists())->toBeFalse();

        expect(StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $entry->id)
            ->count())->toBe(1);

        // Controleer dat de catalogusvermelding en postzegel behouden blijven.
        expect(StampCatalogEntry::query()
            ->whereKey($entry->id)
            ->exists())->toBeTrue();

        expect(Stamp::query()
            ->whereKey($stamp->id)
            ->exists())->toBeTrue();

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Controleer dat uitsluitend de verwijdering is geregistreerd.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('deleted')
            ->and($log->record_type)->toBe('stamp_catalog_value')
            ->and($log->record_id)->toBe($originalValueId)
            ->and($log->stamp_id)->toBe($stamp->id)
            ->and($log->stamp_series_id)->toBeNull()
            ->and($log->condition_id)->toBe($gestempeld->id)
            ->and($log->catalog_edition_id)->toBe($edition->id)
            ->and($log->new_values)->toBeNull();

        // Controleer de oorspronkelijke gegevens van de verwijderde waarde.
        expect($log->old_values['id'])->toBe($originalValueId)
            ->and($log->old_values['stamp_catalog_entry_id'])->toBe($entry->id)
            ->and($log->old_values['condition_id'])->toBe($gestempeld->id)
            ->and((float) $log->old_values['catalog_value'])->toBe(10.00)
            ->and($log->old_values['currency'])->toBe('EUR')
            ->and($log->old_values['notes'])->toBe('Te verwijderen cataloguswaarde')
            ->and((bool) $log->old_values['needs_review'])->toBeTrue();
    } finally {
        unlink($filePath);
    }
});

test('een verwijderde seriecataloguswaarde wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis verwijderde seriecataloguswaarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-verwijderde-seriewaarde',
        'iso_code' => 'TOS',
        'name' => 'Testland verwijderde seriewaarde',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker verwijderen seriewaarde',
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Postzegelserie met twee waarderingen',
        'issue_year' => 2026,
    ]);

    // Maak de seriecataloguswaarde aan die behouden moet blijven.
    $retainedValue = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => $postfris->id,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande seriecataloguswaarde',
    ]);

    // Exporteer uitsluitend de waarde die behouden moet blijven.
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

    try {
        file_put_contents($filePath, $excelContent);

        // Voeg een seriecataloguswaarde toe die niet in Excel voorkomt.
        $missingValue = SeriesCatalogValue::create([
            'catalog_edition_id' => $edition->id,
            'stamp_series_id' => $series->id,
            'condition_id' => $gestempeld->id,
            'catalog_number' => 'SER-200',
            'catalog_value' => '50.00',
            'currency' => 'EUR',
            'notes' => 'Te verwijderen seriecataloguswaarde',
        ]);

        $originalValueId = $missingValue->id;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        // Controleer de bestaande cataloguseditie.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer dat uitsluitend de ontbrekende waarde is verwijderd.
        expect(SeriesCatalogValue::query()
            ->whereKey($retainedValue->id)
            ->exists())->toBeTrue();

        expect(SeriesCatalogValue::query()
            ->whereKey($originalValueId)
            ->exists())->toBeFalse();

        expect(SeriesCatalogValue::query()
            ->where('catalog_edition_id', $edition->id)
            ->count())->toBe(1);

        // Controleer dat de postzegelserie behouden blijft.
        expect(StampSeries::query()
            ->whereKey($series->id)
            ->exists())->toBeTrue();

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Controleer dat uitsluitend de verwijdering is geregistreerd.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('deleted')
            ->and($log->record_type)->toBe('series_catalog_value')
            ->and($log->record_id)->toBe($originalValueId)
            ->and($log->stamp_id)->toBeNull()
            ->and($log->stamp_series_id)->toBe($series->id)
            ->and($log->condition_id)->toBe($gestempeld->id)
            ->and($log->catalog_edition_id)->toBe($edition->id)
            ->and($log->new_values)->toBeNull();

        // Controleer de oorspronkelijke gegevens van de verwijderde waarde.
        expect($log->old_values['id'])->toBe($originalValueId)
            ->and($log->old_values['catalog_edition_id'])->toBe($edition->id)
            ->and($log->old_values['stamp_series_id'])->toBe($series->id)
            ->and($log->old_values['condition_id'])->toBe($gestempeld->id)
            ->and($log->old_values['catalog_number'])->toBe('SER-200')
            ->and((float) $log->old_values['catalog_value'])->toBe(50.00)
            ->and($log->old_values['currency'])->toBe('EUR')
            ->and($log->old_values['notes'])->toBe('Te verwijderen seriecataloguswaarde');
    } finally {
        unlink($filePath);
    }
});

test('een verwijderde catalogusvermelding en bijbehorende waarden worden correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis verwijderde catalogusvermelding',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-verwijderde-vermelding',
        'iso_code' => 'TOV',
        'name' => 'Testland verwijderde vermelding',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker verwijderen catalogusvermelding',
    ]);

    // Maak de postzegel aan die behouden moet blijven.
    $retainedStamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Postzegel behouden',
        'issue_year' => 2026,
    ]);

    $retainedEntry = StampCatalogEntry::create([
        'stamp_id' => $retainedStamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    // Exporteer uitsluitend de catalogusvermelding die behouden moet blijven.
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

    try {
        file_put_contents($filePath, $excelContent);

        // Maak een postzegel aan die niet in Excel voorkomt.
        $missingStamp = Stamp::create([
            'country_id' => $country->id,
            'name' => 'Postzegel ontbreekt in Excel',
            'issue_year' => 2026,
        ]);

        // Maak de ontbrekende catalogusvermelding aan.
        $missingEntry = StampCatalogEntry::create([
            'stamp_id' => $missingStamp->id,
            'catalog_edition_id' => $edition->id,
            'catalog_number' => '200',
            'notes' => 'Te verwijderen catalogusvermelding',
        ]);

        // Maak de bijbehorende cataloguswaarde aan.
        $missingValue = StampCatalogValue::create([
            'stamp_catalog_entry_id' => $missingEntry->id,
            'condition_id' => $condition->id,
            'catalog_value' => '25.50',
            'currency' => 'EUR',
            'notes' => 'Bijbehorende cataloguswaarde',
            'needs_review' => false,
        ]);

        $originalEntryId = $missingEntry->id;
        $originalValueId = $missingValue->id;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        // Controleer dat dezelfde cataloguseditie behouden blijft.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer de verwijderde en behouden catalogusvermeldingen.
        expect(StampCatalogEntry::query()
            ->whereKey($retainedEntry->id)
            ->exists())->toBeTrue();

        expect(StampCatalogEntry::query()
            ->whereKey($originalEntryId)
            ->exists())->toBeFalse();

        expect(StampCatalogValue::query()
            ->whereKey($originalValueId)
            ->exists())->toBeFalse();

        // Controleer dat beide postzegels behouden blijven.
        expect(Stamp::query()
            ->whereKey($retainedStamp->id)
            ->exists())->toBeTrue();

        expect(Stamp::query()
            ->whereKey($missingStamp->id)
            ->exists())->toBeTrue();

        expect(StampCatalogEntry::query()
            ->where('catalog_edition_id', $edition->id)
            ->count())->toBe(1);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Beide verwijderingen moeten in dezelfde batch staan.
        expect($batch->logs()->count())->toBe(2);

        $valueLog = $batch->logs()
            ->where('record_id', $originalValueId)
            ->where('condition_id', $condition->id)
            ->sole();

        $entryLog = $batch->logs()
            ->where('record_id', $originalEntryId)
            ->whereNull('condition_id')
            ->sole();

        // Controleer de verwijderde cataloguswaarde.
        expect($valueLog->action)->toBe('deleted')
            ->and($valueLog->record_type)->toBe('stamp_catalog_value')
            ->and($valueLog->record_id)->toBe($originalValueId)
            ->and($valueLog->stamp_id)->toBe($missingStamp->id)
            ->and($valueLog->stamp_series_id)->toBeNull()
            ->and($valueLog->condition_id)->toBe($condition->id)
            ->and($valueLog->catalog_edition_id)->toBe($edition->id)
            ->and($valueLog->new_values)->toBeNull();

        expect($valueLog->old_values['id'])->toBe($originalValueId)
            ->and($valueLog->old_values['stamp_catalog_entry_id'])->toBe($originalEntryId)
            ->and($valueLog->old_values['condition_id'])->toBe($condition->id)
            ->and((float) $valueLog->old_values['catalog_value'])->toBe(25.50)
            ->and($valueLog->old_values['currency'])->toBe('EUR')
            ->and($valueLog->old_values['notes'])->toBe('Bijbehorende cataloguswaarde')
            ->and((bool) $valueLog->old_values['needs_review'])->toBeFalse();

        // Controleer de verwijderde catalogusvermelding.
        expect($entryLog->action)->toBe('deleted')
            ->and($entryLog->record_type)->toBe('stamp_catalog_entry')
            ->and($entryLog->record_id)->toBe($originalEntryId)
            ->and($entryLog->stamp_id)->toBe($missingStamp->id)
            ->and($entryLog->stamp_series_id)->toBeNull()
            ->and($entryLog->condition_id)->toBeNull()
            ->and($entryLog->catalog_edition_id)->toBe($edition->id)
            ->and($entryLog->new_values)->toBeNull();

        expect($entryLog->old_values['id'])->toBe($originalEntryId)
            ->and($entryLog->old_values['stamp_id'])->toBe($missingStamp->id)
            ->and($entryLog->old_values['catalog_edition_id'])->toBe($edition->id)
            ->and($entryLog->old_values['catalog_number'])->toBe('200')
            ->and($entryLog->old_values['notes'])->toBe('Te verwijderen catalogusvermelding');

        // De cataloguswaarde moet vóór de catalogusvermelding zijn verwijderd.
        expect($valueLog->id)->toBeLessThan($entryLog->id);
    } finally {
        unlink($filePath);
    }
});

test('importmethode aanvullen registreert geen geschiedenis voor een bestaande cataloguswaarde', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis aanvullen bestaande waarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-aanvullen-waarde',
        'iso_code' => 'TAC',
        'name' => 'Testland aanvullen cataloguswaarde',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker aanvullen bestaande waarde',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Postzegel bestaande cataloguswaarde',
        'issue_year' => 2026,
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    // Maak de oorspronkelijke cataloguswaarde aan.
    $existingValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande waarde',
        'needs_review' => false,
    ]);

    $originalValueId = $existingValue->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Wijzig bewust alle bewerkbare velden in Excel.
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('D2', '50.00');
        $catalogValuesSheet->setCellValue('E2', 'USD');
        $catalogValuesSheet->setCellValue('F2', 'Gewijzigde waarde');
        $catalogValuesSheet->setCellValue('G2', 1);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Aanvullen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'merge', $user);

        $existingValue->refresh();

        // Controleer dat alle oorspronkelijke gegevens behouden blijven.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingValue->id)->toBe($originalValueId)
            ->and($existingValue->catalog_value)->toBe('25.50')
            ->and($existingValue->currency)->toBe('EUR')
            ->and($existingValue->notes)->toBe('Bestaande waarde')
            ->and($existingValue->needs_review)->toBeFalse();

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(StampCatalogValue::query()->count())->toBe(1)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(Stamp::query()->count())->toBe(1);

        // Zonder cataloguswijzigingen mag geen geschiedenisbatch bestaan.
        expect(CatalogChangeBatch::query()->count())->toBe(0);

        // Controleer dat ook geen wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('importmethode aanvullen registreert uitsluitend de nieuwe cataloguswaarde in de geschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis aanvullen ontbrekende waarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-aanvullen-ontbrekende-waarde',
        'iso_code' => 'TAO',
        'name' => 'Testland aanvullen ontbrekende waarde',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker aanvullen ontbrekende waarde',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Postzegel ontbrekende cataloguswaarde',
        'issue_year' => 2026,
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    // Maak uitsluitend de bestaande postfriswaarde aan.
    $existingValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande waarde postfris',
        'needs_review' => false,
    ]);

    $originalValueId = $existingValue->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Voeg de ontbrekende waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A3', $gestempeld->slug);

        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        // Wijzig bewust de bestaande postfriswaarde in Excel.
        $catalogValuesSheet->setCellValue('D2', '75.00');
        $catalogValuesSheet->setCellValue('E2', 'USD');
        $catalogValuesSheet->setCellValue('F2', 'Gewijzigde waarde postfris');
        $catalogValuesSheet->setCellValue('G2', 1);

        // Voeg de ontbrekende cataloguswaarde voor gestempeld toe.
        $exchangeStampId = $catalogValuesSheet->getCell('A2')->getValue();

        $catalogValuesSheet->setCellValue('A3', $exchangeStampId);
        $catalogValuesSheet->setCellValue('B3', '100');
        $catalogValuesSheet->setCellValue('C3', $gestempeld->slug);
        $catalogValuesSheet->setCellValue('D3', '12.50');
        $catalogValuesSheet->setCellValue('E3', 'EUR');
        $catalogValuesSheet->setCellValue('F3', 'Nieuwe waarde gestempeld');
        $catalogValuesSheet->setCellValue('G3', 1);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Aanvullen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'merge', $user);

        $existingValue->refresh();

        // Controleer dat de bestaande gegevens ongewijzigd blijven.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingValue->id)->toBe($originalValueId)
            ->and($existingValue->catalog_value)->toBe('25.50')
            ->and($existingValue->currency)->toBe('EUR')
            ->and($existingValue->notes)->toBe('Bestaande waarde postfris')
            ->and($existingValue->needs_review)->toBeFalse();

        // Haal de nieuwe cataloguswaarde op.
        $newValue = StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $entry->id)
            ->where('condition_id', $gestempeld->id)
            ->sole();

        expect($newValue->catalog_value)->toBe('12.50')
            ->and($newValue->currency)->toBe('EUR')
            ->and($newValue->notes)->toBe('Nieuwe waarde gestempeld')
            ->and($newValue->needs_review)->toBeTrue();

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(StampCatalogValue::query()->count())->toBe(2)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(Stamp::query()->count())->toBe(1);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_merge')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Uitsluitend de nieuwe cataloguswaarde mag geregistreerd zijn.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('created')
            ->and($log->record_type)->toBe('stamp_catalog_value')
            ->and($log->record_id)->toBe($newValue->id)
            ->and($log->stamp_id)->toBe($stamp->id)
            ->and($log->stamp_series_id)->toBeNull()
            ->and($log->condition_id)->toBe($gestempeld->id)
            ->and($log->catalog_edition_id)->toBe($edition->id)
            ->and($log->old_values)->toBeNull();

        // Controleer de geregistreerde nieuwe gegevens.
        expect($log->new_values['stamp_catalog_entry_id'])->toBe($entry->id)
            ->and($log->new_values['condition_id'])->toBe($gestempeld->id)
            ->and((float) $log->new_values['catalog_value'])->toBe(12.50)
            ->and($log->new_values['currency'])->toBe('EUR')
            ->and($log->new_values['notes'])->toBe('Nieuwe waarde gestempeld')
            ->and((bool) $log->new_values['needs_review'])->toBeTrue();
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('importmethode aanvullen registreert geen geschiedenis voor een bestaande seriecataloguswaarde', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis aanvullen bestaande seriewaarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-aanvullen-seriewaarde',
        'iso_code' => 'TAS',
        'name' => 'Testland aanvullen seriewaarde',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker aanvullen bestaande seriewaarde',
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegelserie',
        'issue_year' => 2026,
    ]);

    // Maak de oorspronkelijke seriecataloguswaarde aan.
    $existingValue = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => $condition->id,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande seriecataloguswaarde',
    ]);

    $originalValueId = $existingValue->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Wijzig bewust alle bewerkbare velden in Excel.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('C2', 'SER-200');
        $seriesValuesSheet->setCellValue('D2', '250.00');
        $seriesValuesSheet->setCellValue('E2', 'USD');
        $seriesValuesSheet->setCellValue('F2', 'Gewijzigde seriecataloguswaarde');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Aanvullen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'merge', $user);

        $existingValue->refresh();

        // Controleer dat alle oorspronkelijke gegevens behouden blijven.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingValue->id)->toBe($originalValueId)
            ->and($existingValue->catalog_edition_id)->toBe($edition->id)
            ->and($existingValue->stamp_series_id)->toBe($series->id)
            ->and($existingValue->condition_id)->toBe($condition->id)
            ->and($existingValue->catalog_number)->toBe('SER-100')
            ->and($existingValue->catalog_value)->toBe('125.50')
            ->and($existingValue->currency)->toBe('EUR')
            ->and($existingValue->notes)->toBe('Bestaande seriecataloguswaarde');

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(SeriesCatalogValue::query()->count())->toBe(1)
            ->and(StampSeries::query()->count())->toBe(1);

        // Zonder cataloguswijzigingen mag geen geschiedenisbatch bestaan.
        expect(CatalogChangeBatch::query()->count())->toBe(0);

        // Controleer dat ook geen wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('importmethode aanvullen registreert uitsluitend de nieuwe seriecataloguswaarde in de geschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis aanvullen ontbrekende seriewaarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-aanvullen-ontbrekende-seriewaarde',
        'iso_code' => 'TOS',
        'name' => 'Testland aanvullen ontbrekende seriewaarde',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker aanvullen ontbrekende seriewaarde',
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegelserie',
        'issue_year' => 2026,
    ]);

    // Maak uitsluitend de bestaande postfriswaarde aan.
    $existingValue = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => $postfris->id,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande waarde postfris',
    ]);

    $originalValueId = $existingValue->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Voeg de ontbrekende waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A3', $gestempeld->slug);

        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        // Wijzig bewust de bestaande postfriswaarde in Excel.
        $seriesValuesSheet->setCellValue('C2', 'SER-200');
        $seriesValuesSheet->setCellValue('D2', '250.00');
        $seriesValuesSheet->setCellValue('E2', 'USD');
        $seriesValuesSheet->setCellValue('F2', 'Gewijzigde waarde postfris');

        // Voeg de ontbrekende seriecataloguswaarde toe.
        $exchangeSeriesId = $seriesValuesSheet->getCell('A2')->getValue();

        $seriesValuesSheet->setCellValue('A3', $exchangeSeriesId);
        $seriesValuesSheet->setCellValue('B3', $gestempeld->slug);
        $seriesValuesSheet->setCellValue('C3', 'SER-100');
        $seriesValuesSheet->setCellValue('D3', '75.00');
        $seriesValuesSheet->setCellValue('E3', 'EUR');
        $seriesValuesSheet->setCellValue('F3', 'Nieuwe waarde gestempeld');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Aanvullen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'merge', $user);

        $existingValue->refresh();

        // Controleer dat de oorspronkelijke gegevens behouden blijven.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingValue->id)->toBe($originalValueId)
            ->and($existingValue->catalog_edition_id)->toBe($edition->id)
            ->and($existingValue->stamp_series_id)->toBe($series->id)
            ->and($existingValue->condition_id)->toBe($postfris->id)
            ->and($existingValue->catalog_number)->toBe('SER-100')
            ->and($existingValue->catalog_value)->toBe('125.50')
            ->and($existingValue->currency)->toBe('EUR')
            ->and($existingValue->notes)->toBe('Bestaande waarde postfris');

        // Haal de nieuwe seriecataloguswaarde op.
        $newValue = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $edition->id)
            ->where('stamp_series_id', $series->id)
            ->where('condition_id', $gestempeld->id)
            ->sole();

        expect($newValue->catalog_number)->toBe('SER-100')
            ->and($newValue->catalog_value)->toBe('75.00')
            ->and($newValue->currency)->toBe('EUR')
            ->and($newValue->notes)->toBe('Nieuwe waarde gestempeld');

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(SeriesCatalogValue::query()->count())->toBe(2)
            ->and(StampSeries::query()->count())->toBe(1);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_merge')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Uitsluitend de nieuwe seriecataloguswaarde mag geregistreerd zijn.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('created')
            ->and($log->record_type)->toBe('series_catalog_value')
            ->and($log->record_id)->toBe($newValue->id)
            ->and($log->stamp_id)->toBeNull()
            ->and($log->stamp_series_id)->toBe($series->id)
            ->and($log->condition_id)->toBe($gestempeld->id)
            ->and($log->catalog_edition_id)->toBe($edition->id)
            ->and($log->old_values)->toBeNull();

        // Controleer de geregistreerde nieuwe gegevens.
        expect($log->new_values['catalog_edition_id'])->toBe($edition->id)
            ->and($log->new_values['stamp_series_id'])->toBe($series->id)
            ->and($log->new_values['condition_id'])->toBe($gestempeld->id)
            ->and($log->new_values['catalog_number'])->toBe('SER-100')
            ->and((float) $log->new_values['catalog_value'])->toBe(75.00)
            ->and($log->new_values['currency'])->toBe('EUR')
            ->and($log->new_values['notes'])->toBe('Nieuwe waarde gestempeld');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('importmethode vervangen registreert een ontbrekende cataloguswaarde als aangemaakt', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis vervangen ontbrekende waarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-vervangen-ontbrekende-waarde',
        'iso_code' => 'TVO',
        'name' => 'Testland vervangen ontbrekende waarde',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker vervangen ontbrekende waarde',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Postzegel vervangen ontbrekende cataloguswaarde',
        'issue_year' => 2026,
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    // Maak uitsluitend de bestaande postfriswaarde aan.
    $existingValue = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande waarde postfris',
        'needs_review' => false,
    ]);

    $originalValueId = $existingValue->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Voeg de ontbrekende waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A3', $gestempeld->slug);

        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $exchangeStampId = $catalogValuesSheet->getCell('A2')->getValue();

        // Voeg de ontbrekende cataloguswaarde toe.
        $catalogValuesSheet->setCellValue('A3', $exchangeStampId);
        $catalogValuesSheet->setCellValue('B3', '100');
        $catalogValuesSheet->setCellValue('C3', $gestempeld->slug);
        $catalogValuesSheet->setCellValue('D3', '12.50');
        $catalogValuesSheet->setCellValue('E3', 'EUR');
        $catalogValuesSheet->setCellValue('F3', 'Nieuwe waarde gestempeld');
        $catalogValuesSheet->setCellValue('G3', 1);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $existingValue->refresh();

        // Controleer dat de oorspronkelijke gegevens behouden blijven.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingValue->id)->toBe($originalValueId)
            ->and($existingValue->catalog_value)->toBe('25.50')
            ->and($existingValue->currency)->toBe('EUR')
            ->and($existingValue->notes)->toBe('Bestaande waarde postfris')
            ->and($existingValue->needs_review)->toBeFalse();

        // Haal de nieuwe cataloguswaarde op.
        $newValue = StampCatalogValue::query()
            ->where('stamp_catalog_entry_id', $entry->id)
            ->where('condition_id', $gestempeld->id)
            ->sole();

        expect($newValue->catalog_value)->toBe('12.50')
            ->and($newValue->currency)->toBe('EUR')
            ->and($newValue->notes)->toBe('Nieuwe waarde gestempeld')
            ->and($newValue->needs_review)->toBeTrue();

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(StampCatalogValue::query()->count())->toBe(2)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(Stamp::query()->count())->toBe(1);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Uitsluitend de nieuwe cataloguswaarde mag geregistreerd zijn.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('created')
            ->and($log->record_type)->toBe('stamp_catalog_value')
            ->and($log->record_id)->toBe($newValue->id)
            ->and($log->stamp_id)->toBe($stamp->id)
            ->and($log->stamp_series_id)->toBeNull()
            ->and($log->condition_id)->toBe($gestempeld->id)
            ->and($log->catalog_edition_id)->toBe($edition->id)
            ->and($log->old_values)->toBeNull();

        // Controleer de geregistreerde nieuwe gegevens.
        expect($log->new_values['stamp_catalog_entry_id'])->toBe($entry->id)
            ->and($log->new_values['condition_id'])->toBe($gestempeld->id)
            ->and((float) $log->new_values['catalog_value'])->toBe(12.50)
            ->and($log->new_values['currency'])->toBe('EUR')
            ->and($log->new_values['notes'])->toBe('Nieuwe waarde gestempeld')
            ->and((bool) $log->new_values['needs_review'])->toBeTrue();
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('importmethode vervangen registreert een gewijzigde en een nieuwe seriecataloguswaarde in dezelfde geschiedenisbatch', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis vervangen ontbrekende seriewaarde',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-vervangen-ontbrekende-seriewaarde',
        'iso_code' => 'TOV',
        'name' => 'Testland vervangen ontbrekende seriewaarde',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker vervangen ontbrekende seriewaarde',
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegelserie',
        'issue_year' => 2026,
    ]);

    // Maak uitsluitend de bestaande postfriswaarde aan.
    $existingValue = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => $postfris->id,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande waarde postfris',
    ]);

    $originalValueId = $existingValue->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Voeg de ontbrekende waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName('Waarderingscategorieen');

        $conditionsSheet->setCellValue('A3', $gestempeld->slug);

        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $exchangeSeriesId = $seriesValuesSheet->getCell('A2')->getValue();

        // Wijzig de bestaande postfriswaarde.
        $seriesValuesSheet->setCellValue('C2', 'SER-200');
        $seriesValuesSheet->setCellValue('D2', '150.00');
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Gewijzigde waarde postfris');

        // Voeg de ontbrekende seriecataloguswaarde toe.
        $seriesValuesSheet->setCellValue('A3', $exchangeSeriesId);
        $seriesValuesSheet->setCellValue('B3', $gestempeld->slug);
        $seriesValuesSheet->setCellValue('C3', 'SER-200');
        $seriesValuesSheet->setCellValue('D3', '75.00');
        $seriesValuesSheet->setCellValue('E3', 'EUR');
        $seriesValuesSheet->setCellValue('F3', 'Nieuwe waarde gestempeld');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $existingValue->refresh();

        // Controleer de bijgewerkte seriecataloguswaarde.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingValue->id)->toBe($originalValueId)
            ->and($existingValue->catalog_edition_id)->toBe($edition->id)
            ->and($existingValue->stamp_series_id)->toBe($series->id)
            ->and($existingValue->condition_id)->toBe($postfris->id)
            ->and($existingValue->catalog_number)->toBe('SER-200')
            ->and($existingValue->catalog_value)->toBe('150.00')
            ->and($existingValue->currency)->toBe('EUR')
            ->and($existingValue->notes)->toBe('Gewijzigde waarde postfris');

        // Haal de nieuwe seriecataloguswaarde op.
        $newValue = SeriesCatalogValue::query()
            ->where('catalog_edition_id', $edition->id)
            ->where('stamp_series_id', $series->id)
            ->where('condition_id', $gestempeld->id)
            ->sole();

        expect($newValue->catalog_number)->toBe('SER-200')
            ->and($newValue->catalog_value)->toBe('75.00')
            ->and($newValue->currency)->toBe('EUR')
            ->and($newValue->notes)->toBe('Nieuwe waarde gestempeld');

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(SeriesCatalogValue::query()->count())->toBe(2)
            ->and(StampSeries::query()->count())->toBe(1);

        // Beide wijzigingen moeten in dezelfde geschiedenisbatch staan.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        expect($batch->logs()->count())->toBe(2);

        // Haal de wijzigingslog van de bestaande waarde op.
        $updatedLog = $batch->logs()
            ->where('record_id', $existingValue->id)
            ->where('condition_id', $postfris->id)
            ->sole();

        expect($updatedLog->action)->toBe('updated')
            ->and($updatedLog->record_type)->toBe('series_catalog_value')
            ->and($updatedLog->record_id)->toBe($existingValue->id)
            ->and($updatedLog->stamp_id)->toBeNull()
            ->and($updatedLog->stamp_series_id)->toBe($series->id)
            ->and($updatedLog->condition_id)->toBe($postfris->id)
            ->and($updatedLog->catalog_edition_id)->toBe($edition->id);

        // Controleer de oorspronkelijke gegevens.
        expect($updatedLog->old_values['catalog_number'])->toBe('SER-100')
            ->and((float) $updatedLog->old_values['catalog_value'])->toBe(125.50)
            ->and($updatedLog->old_values['notes'])->toBe('Bestaande waarde postfris');

        // Controleer de gewijzigde gegevens.
        expect($updatedLog->new_values['catalog_number'])->toBe('SER-200')
            ->and((float) $updatedLog->new_values['catalog_value'])->toBe(150.00)
            ->and($updatedLog->new_values['notes'])->toBe('Gewijzigde waarde postfris');

        // Ongewijzigde velden mogen niet geregistreerd worden.
        expect($updatedLog->old_values)->not->toHaveKey('currency')
            ->and($updatedLog->new_values)->not->toHaveKey('currency')
            ->and($updatedLog->old_values)->not->toHaveKey('updated_at')
            ->and($updatedLog->new_values)->not->toHaveKey('updated_at');

        // Haal de wijzigingslog van de nieuwe waarde op.
        $createdLog = $batch->logs()
            ->where('record_id', $newValue->id)
            ->where('condition_id', $gestempeld->id)
            ->sole();

        expect($createdLog->action)->toBe('created')
            ->and($createdLog->record_type)->toBe('series_catalog_value')
            ->and($createdLog->record_id)->toBe($newValue->id)
            ->and($createdLog->stamp_id)->toBeNull()
            ->and($createdLog->stamp_series_id)->toBe($series->id)
            ->and($createdLog->condition_id)->toBe($gestempeld->id)
            ->and($createdLog->catalog_edition_id)->toBe($edition->id)
            ->and($createdLog->old_values)->toBeNull();

        // Controleer de geregistreerde nieuwe gegevens.
        expect($createdLog->new_values['catalog_edition_id'])->toBe($edition->id)
            ->and($createdLog->new_values['stamp_series_id'])->toBe($series->id)
            ->and($createdLog->new_values['condition_id'])->toBe($gestempeld->id)
            ->and($createdLog->new_values['catalog_number'])->toBe('SER-200')
            ->and((float) $createdLog->new_values['catalog_value'])->toBe(75.00)
            ->and($createdLog->new_values['currency'])->toBe('EUR')
            ->and($createdLog->new_values['notes'])->toBe('Nieuwe waarde gestempeld');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('importmethode aanvullen registreert geen geschiedenis voor een bestaande catalogusvermelding', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis aanvullen bestaande vermelding',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-aanvullen-vermelding',
        'iso_code' => 'TAP',
        'name' => 'Testland aanvullen',
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker aanvullen bestaande vermelding',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
        'notes' => 'Bestaande catalogusopmerking',
    ]);

    $originalEntryId = $entry->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Wijzig bewust de postzegelnaam en catalogusopmerking.
        $stampsSheet->setCellValue('G2', 'Gewijzigde postzegel vanuit Excel');
        $stampsSheet->setCellValue('Q2', 'Gewijzigde catalogusopmerking');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Aanvullen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'merge', $user);

        $stamp->refresh();
        $entry->refresh();

        // Controleer dat de bestaande cataloguseditie behouden blijft.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer dat de catalogusvermelding ongewijzigd blijft.
        expect($entry->id)->toBe($originalEntryId)
            ->and($entry->catalog_edition_id)->toBe($edition->id)
            ->and($entry->stamp_id)->toBe($stamp->id)
            ->and($entry->catalog_number)->toBe('100')
            ->and($entry->notes)->toBe('Bestaande catalogusopmerking');

        // Controleer dat de oorspronkelijke postzegelgegevens behouden blijven.
        expect($stamp->name)->toBe('Bestaande postzegel')
            ->and($stamp->face_value)->toBe('1/2')
            ->and($stamp->currency)->toBe('EUR');

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(Stamp::query()->count())->toBe(1)
            ->and(StampCatalogEntry::query()->count())->toBe(1);

        // Zonder cataloguswijzigingen mag geen geschiedenisbatch bestaan.
        expect(CatalogChangeBatch::query()->count())->toBe(0);

        // Controleer dat ook geen wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('importmethode aanvullen registreert uitsluitend de nieuwe catalogusvermelding in de geschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis aanvullen nieuwe vermelding',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-aanvullen-nieuw',
        'iso_code' => 'TAN',
        'name' => 'Testland aanvullen nieuw',
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker aanvullen nieuwe vermelding',
    ]);

    // Maak een bestaande postzegel aan.
    $existingStamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    $existingEntry = StampCatalogEntry::create([
        'stamp_id' => $existingStamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
        'notes' => 'Bestaande catalogusopmerking',
    ]);

    $originalEntryId = $existingEntry->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Wijzig bewust de bestaande catalogusopmerking.
        $stampsSheet->setCellValue('Q2', 'Gewijzigde catalogusopmerking');

        // Voeg een nieuwe postzegel en catalogusvermelding toe.
        $stampsSheet->setCellValue('A3', 'nieuwe-postzegel-200');
        $stampsSheet->setCellValue('C3', '200');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Nieuwe postzegel');
        $stampsSheet->setCellValue('I3', 2026);
        $stampsSheet->setCellValue('K3', '1');
        $stampsSheet->setCellValue('L3', 'EUR');
        $stampsSheet->setCellValue('Q3', 'Nieuwe catalogusvermelding');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Aanvullen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'merge', $user);

        $existingStamp->refresh();
        $existingEntry->refresh();

        // Controleer dat de bestaande gegevens behouden blijven.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingEntry->id)->toBe($originalEntryId)
            ->and($existingEntry->catalog_edition_id)->toBe($edition->id)
            ->and($existingEntry->stamp_id)->toBe($existingStamp->id)
            ->and($existingEntry->catalog_number)->toBe('100')
            ->and($existingEntry->notes)->toBe('Bestaande catalogusopmerking')
            ->and($existingStamp->name)->toBe('Bestaande postzegel');

        // Haal de nieuwe postzegel op.
        $newStamp = Stamp::query()
            ->where('name', 'Nieuwe postzegel')
            ->sole();

        expect($newStamp->country_id)->toBe($country->id)
            ->and($newStamp->issue_year)->toBe(2026)
            ->and($newStamp->face_value)->toBe('1')
            ->and($newStamp->currency)->toBe('EUR');

        // Haal de nieuwe catalogusvermelding op.
        $newEntry = StampCatalogEntry::query()
            ->where('catalog_edition_id', $edition->id)
            ->where('catalog_number', '200')
            ->sole();

        expect($newEntry->stamp_id)->toBe($newStamp->id)
            ->and($newEntry->notes)->toBe('Nieuwe catalogusvermelding');

        // Controleer het aantal records.
        expect(Stamp::query()->count())->toBe(2)
            ->and(StampCatalogEntry::query()->count())->toBe(2);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_merge')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Uitsluitend de nieuwe catalogusvermelding mag geregistreerd zijn.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('created')
            ->and($log->record_type)->toBe('stamp_catalog_entry')
            ->and($log->record_id)->toBe($newEntry->id)
            ->and($log->stamp_id)->toBe($newStamp->id)
            ->and($log->stamp_series_id)->toBeNull()
            ->and($log->condition_id)->toBeNull()
            ->and($log->catalog_edition_id)->toBe($edition->id)
            ->and($log->old_values)->toBeNull();

        // Controleer de geregistreerde nieuwe gegevens.
        expect($log->new_values['id'])->toBe($newEntry->id)
            ->and($log->new_values['catalog_edition_id'])->toBe($edition->id)
            ->and($log->new_values['stamp_id'])->toBe($newStamp->id)
            ->and($log->new_values['catalog_number'])->toBe('200')
            ->and($log->new_values['notes'])->toBe('Nieuwe catalogusvermelding');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('importmethode vervangen registreert geen geschiedenis voor een bestaande catalogusvermelding', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis vervangen bestaande vermelding',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-vervangen-vermelding',
        'iso_code' => 'TVP',
        'name' => 'Testland vervangen',
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker vervangen bestaande vermelding',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
        'notes' => 'Bestaande catalogusopmerking',
    ]);

    $originalEntryId = $entry->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Wijzig bewust de bestaande postzegelgegevens.
        $stampsSheet->setCellValue('G2', 'Gewijzigde postzegel vanuit Excel');
        $stampsSheet->setCellValue('K2', '2/3');
        $stampsSheet->setCellValue('L2', 'USD');

        // Wijzig ook de bestaande catalogusopmerking.
        $stampsSheet->setCellValue('Q2', 'Gewijzigde catalogusopmerking');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $stamp->refresh();
        $entry->refresh();

        // Controleer dat de bestaande cataloguseditie behouden blijft.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer dat de catalogusvermelding ongewijzigd blijft.
        expect($entry->id)->toBe($originalEntryId)
            ->and($entry->catalog_edition_id)->toBe($edition->id)
            ->and($entry->stamp_id)->toBe($stamp->id)
            ->and($entry->catalog_number)->toBe('100')
            ->and($entry->notes)->toBe('Bestaande catalogusopmerking');

        // Controleer dat de oorspronkelijke postzegelgegevens behouden blijven.
        expect($stamp->name)->toBe('Bestaande postzegel')
            ->and($stamp->face_value)->toBe('1/2')
            ->and($stamp->currency)->toBe('EUR');

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(Stamp::query()->count())->toBe(1)
            ->and(StampCatalogEntry::query()->count())->toBe(1);

        // Zonder cataloguswijzigingen mag geen geschiedenisbatch bestaan.
        expect(CatalogChangeBatch::query()->count())->toBe(0);

        // Controleer dat ook geen wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('importmethode vervangen registreert uitsluitend de nieuwe catalogusvermelding in de geschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis vervangen nieuwe vermelding',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-vervangen-nieuw',
        'iso_code' => 'TVN',
        'name' => 'Testland vervangen nieuw',
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker vervangen nieuwe vermelding',
    ]);

    // Maak een bestaande postzegel aan.
    $existingStamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    $existingEntry = StampCatalogEntry::create([
        'stamp_id' => $existingStamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
        'notes' => 'Bestaande catalogusopmerking',
    ]);

    $originalEntryId = $existingEntry->id;

    // Exporteer de bestaande cataloguseditie.
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

        // Wijzig bewust de bestaande catalogusopmerking.
        $stampsSheet->setCellValue('Q2', 'Gewijzigde catalogusopmerking');

        // Voeg een nieuwe postzegel en catalogusvermelding toe.
        $stampsSheet->setCellValue('A3', 'nieuwe-postzegel-200');
        $stampsSheet->setCellValue('C3', '200');
        $stampsSheet->setCellValue('E3', $country->slug);
        $stampsSheet->setCellValue('F3', $country->iso_code);
        $stampsSheet->setCellValue('G3', 'Nieuwe postzegel');
        $stampsSheet->setCellValue('I3', 2026);
        $stampsSheet->setCellValue('K3', '1');
        $stampsSheet->setCellValue('L3', 'EUR');
        $stampsSheet->setCellValue('Q3', 'Nieuwe catalogusvermelding');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $existingStamp->refresh();
        $existingEntry->refresh();

        // Controleer dat de bestaande gegevens behouden blijven.
        expect($importedEdition->id)->toBe($edition->id)
            ->and($existingEntry->id)->toBe($originalEntryId)
            ->and($existingEntry->catalog_edition_id)->toBe($edition->id)
            ->and($existingEntry->stamp_id)->toBe($existingStamp->id)
            ->and($existingEntry->catalog_number)->toBe('100')
            ->and($existingEntry->notes)->toBe('Bestaande catalogusopmerking')
            ->and($existingStamp->name)->toBe('Bestaande postzegel')
            ->and($existingStamp->face_value)->toBe('1/2')
            ->and($existingStamp->currency)->toBe('EUR');

        // Haal de nieuwe postzegel op.
        $newStamp = Stamp::query()
            ->where('name', 'Nieuwe postzegel')
            ->sole();

        expect($newStamp->country_id)->toBe($country->id)
            ->and($newStamp->issue_year)->toBe(2026)
            ->and($newStamp->face_value)->toBe('1')
            ->and($newStamp->currency)->toBe('EUR');

        // Haal de nieuwe catalogusvermelding op.
        $newEntry = StampCatalogEntry::query()
            ->where('catalog_edition_id', $edition->id)
            ->where('catalog_number', '200')
            ->sole();

        expect($newEntry->stamp_id)->toBe($newStamp->id)
            ->and($newEntry->notes)->toBe('Nieuwe catalogusvermelding');

        // Controleer het aantal records.
        expect(Stamp::query()->count())->toBe(2)
            ->and(StampCatalogEntry::query()->count())->toBe(2);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Uitsluitend de nieuwe catalogusvermelding mag geregistreerd zijn.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('created')
            ->and($log->record_type)->toBe('stamp_catalog_entry')
            ->and($log->record_id)->toBe($newEntry->id)
            ->and($log->stamp_id)->toBe($newStamp->id)
            ->and($log->stamp_series_id)->toBeNull()
            ->and($log->condition_id)->toBeNull()
            ->and($log->catalog_edition_id)->toBe($edition->id)
            ->and($log->old_values)->toBeNull();

        // Controleer de geregistreerde nieuwe gegevens.
        expect($log->new_values['id'])->toBe($newEntry->id)
            ->and($log->new_values['catalog_edition_id'])->toBe($edition->id)
            ->and($log->new_values['stamp_id'])->toBe($newStamp->id)
            ->and($log->new_values['catalog_number'])->toBe('200')
            ->and($log->new_values['notes'])->toBe('Nieuwe catalogusvermelding');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een automatisch gewijzigde controlemarkering wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis automatische controlemarkering',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-automatische-controlemarkering',
        'iso_code' => 'TAC',
        'name' => 'Testland automatische controlemarkering',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker automatische controlemarkering',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande cataloguswaarde',
        'needs_review' => false,
    ]);

    $originalValueId = $value->id;

    // Exporteer de bestaande cataloguseditie.
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

        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        // Wijzig uitsluitend de cataloguswaarde.
        $catalogValuesSheet->setCellValue('D2', 50.00);

        // Laat de controlemarkering in Excel bewust uitgeschakeld.
        $catalogValuesSheet->setCellValue('G2', 0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer de oorspronkelijke situatie.
        expect($value->catalog_value)->toBe('25.50')
            ->and($value->needs_review)->toBeFalse();

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $value->refresh();

        // Controleer dat de bestaande cataloguseditie behouden blijft.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer de gewijzigde cataloguswaarde.
        expect($value->id)->toBe($originalValueId)
            ->and($value->stamp_catalog_entry_id)->toBe($entry->id)
            ->and($value->condition_id)->toBe($condition->id)
            ->and($value->catalog_value)->toBe('50.00')
            ->and($value->currency)->toBe('EUR')
            ->and($value->notes)->toBe('Bestaande cataloguswaarde')
            ->and($value->needs_review)->toBeTrue();

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(Stamp::query()->count())->toBe(1)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(StampCatalogValue::query()->count())->toBe(1);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Er mag precies één wijzigingslog bestaan.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('updated')
            ->and($log->record_type)->toBe('stamp_catalog_value')
            ->and($log->record_id)->toBe($value->id)
            ->and($log->stamp_id)->toBe($stamp->id)
            ->and($log->stamp_series_id)->toBeNull()
            ->and($log->condition_id)->toBe($condition->id)
            ->and($log->catalog_edition_id)->toBe($edition->id);

        // Controleer de oorspronkelijke waarden in de geschiedenis.
        expect((float) $log->old_values['catalog_value'])->toBe(25.50)
            ->and((bool) $log->old_values['needs_review'])->toBeFalse();

        // Controleer de nieuwe waarden in de geschiedenis.
        expect((float) $log->new_values['catalog_value'])->toBe(50.00)
            ->and((bool) $log->new_values['needs_review'])->toBeTrue();

        // Ongewijzigde velden mogen niet worden geregistreerd.
        expect($log->old_values)->not->toHaveKey('currency')
            ->not->toHaveKey('notes')
            ->not->toHaveKey('condition_id')
            ->not->toHaveKey('updated_at');

        expect($log->new_values)->not->toHaveKey('currency')
            ->not->toHaveKey('notes')
            ->not->toHaveKey('condition_id')
            ->not->toHaveKey('updated_at');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een wijziging van null naar nul wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis null naar nul',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-null-naar-nul',
        'iso_code' => 'TNN',
        'name' => 'Testland null naar nul',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker null naar nul',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => null,
        'currency' => 'EUR',
        'notes' => 'Bestaande cataloguswaarde',
        'needs_review' => false,
    ]);

    $originalValueId = $value->id;

    // Exporteer de bestaande cataloguseditie.
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

        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        // Wijzig de ontbrekende cataloguswaarde naar nul.
        $catalogValuesSheet->setCellValue('D2', 0);

        // Laat de controlemarkering in Excel uitgeschakeld.
        $catalogValuesSheet->setCellValue('G2', 0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer de oorspronkelijke situatie.
        expect($value->catalog_value)->toBeNull()
            ->and($value->needs_review)->toBeFalse();

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $value->refresh();

        // Controleer dat de bestaande cataloguseditie behouden blijft.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer dat nul als cataloguswaarde is opgeslagen.
        expect($value->id)->toBe($originalValueId)
            ->and($value->stamp_catalog_entry_id)->toBe($entry->id)
            ->and($value->condition_id)->toBe($condition->id)
            ->and($value->catalog_value)->toBe('0.00')
            ->and($value->currency)->toBe('EUR')
            ->and($value->notes)->toBe('Bestaande cataloguswaarde')
            ->and($value->needs_review)->toBeTrue();

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(Stamp::query()->count())->toBe(1)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(StampCatalogValue::query()->count())->toBe(1);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Er mag precies één wijzigingslog bestaan.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('updated')
            ->and($log->record_type)->toBe('stamp_catalog_value')
            ->and($log->record_id)->toBe($value->id)
            ->and($log->stamp_id)->toBe($stamp->id)
            ->and($log->stamp_series_id)->toBeNull()
            ->and($log->condition_id)->toBe($condition->id)
            ->and($log->catalog_edition_id)->toBe($edition->id);

        // Controleer dat de cataloguswaarde in beide snapshots aanwezig is.
        expect($log->old_values)->toHaveKey('catalog_value');
        expect($log->new_values)->toHaveKey('catalog_value');

        // De oorspronkelijke waarde moet daadwerkelijk null zijn.
        expect($log->old_values['catalog_value'])->toBeNull();

        // De nieuwe waarde mag niet null zijn.
        expect($log->new_values['catalog_value'])->not->toBeNull();

        // Controleer dat de nieuwe waarde daadwerkelijk nul is.
        expect(is_numeric($log->new_values['catalog_value']))->toBeTrue();

        expect((float) $log->new_values['catalog_value'])->toBe(0.00);

        // Controleer de automatische controlemarkering.
        expect((bool) $log->old_values['needs_review'])->toBeFalse()
            ->and((bool) $log->new_values['needs_review'])->toBeTrue();

        // Ongewijzigde velden mogen niet worden geregistreerd.
        expect($log->old_values)->not->toHaveKey('currency')
            ->not->toHaveKey('notes')
            ->not->toHaveKey('condition_id')
            ->not->toHaveKey('updated_at');

        expect($log->new_values)->not->toHaveKey('currency')
            ->not->toHaveKey('notes')
            ->not->toHaveKey('condition_id')
            ->not->toHaveKey('updated_at');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een wijziging van nul naar null wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis nul naar null',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-nul-naar-null',
        'iso_code' => 'TZN',
        'name' => 'Testland nul naar null',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker nul naar null',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Bestaande cataloguswaarde',
        'needs_review' => false,
    ]);

    $originalValueId = $value->id;

    // Exporteer de bestaande cataloguseditie.
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

        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        // Maak de cel met de cataloguswaarde leeg.
        $catalogValuesSheet->setCellValue('D2', null);

        // Laat de controlemarkering in Excel uitgeschakeld.
        $catalogValuesSheet->setCellValue('G2', 0);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer de oorspronkelijke situatie.
        expect($value->catalog_value)->toBe('0.00')
            ->and($value->needs_review)->toBeFalse();

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $value->refresh();

        // Controleer dat de bestaande cataloguseditie behouden blijft.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer dat de cataloguswaarde daadwerkelijk null is.
        expect($value->id)->toBe($originalValueId)
            ->and($value->stamp_catalog_entry_id)->toBe($entry->id)
            ->and($value->condition_id)->toBe($condition->id)
            ->and($value->catalog_value)->toBeNull()
            ->and($value->currency)->toBe('EUR')
            ->and($value->notes)->toBe('Bestaande cataloguswaarde')
            ->and($value->needs_review)->toBeTrue();

        // Controleer dat geen records zijn verwijderd of toegevoegd.
        expect(Stamp::query()->count())->toBe(1)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(StampCatalogValue::query()->count())->toBe(1);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Er mag precies één wijzigingslog bestaan.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('updated')
            ->and($log->record_type)->toBe('stamp_catalog_value')
            ->and($log->record_id)->toBe($value->id)
            ->and($log->stamp_id)->toBe($stamp->id)
            ->and($log->stamp_series_id)->toBeNull()
            ->and($log->condition_id)->toBe($condition->id)
            ->and($log->catalog_edition_id)->toBe($edition->id);

        // Controleer dat de cataloguswaarde in beide snapshots aanwezig is.
        expect($log->old_values)->toHaveKey('catalog_value');
        expect($log->new_values)->toHaveKey('catalog_value');

        // De oorspronkelijke waarde mag niet null zijn.
        expect($log->old_values['catalog_value'])->not->toBeNull();

        // Controleer dat de oorspronkelijke waarde daadwerkelijk nul is.
        expect(is_numeric($log->old_values['catalog_value']))->toBeTrue();

        expect((float) $log->old_values['catalog_value'])->toBe(0.00);

        // De nieuwe waarde moet daadwerkelijk null zijn.
        expect($log->new_values['catalog_value'])->toBeNull();

        // Controleer de automatische controlemarkering.
        expect((bool) $log->old_values['needs_review'])->toBeFalse()
            ->and((bool) $log->new_values['needs_review'])->toBeTrue();

        // Ongewijzigde velden mogen niet worden geregistreerd.
        expect($log->old_values)->not->toHaveKey('currency')
            ->not->toHaveKey('notes')
            ->not->toHaveKey('condition_id')
            ->not->toHaveKey('updated_at');

        expect($log->new_values)->not->toHaveKey('currency')
            ->not->toHaveKey('notes')
            ->not->toHaveKey('condition_id')
            ->not->toHaveKey('updated_at');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een nieuwe seriecataloguswaarde zonder waarderingscategorie wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis serie zonder waarderingscategorie',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-serie-zonder-categorie',
        'iso_code' => 'TSZ',
        'name' => 'Testland serie zonder categorie',
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker serie zonder categorie',
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie zonder waarderingscategorie',
        'issue_year' => 2026,
    ]);

    // Exporteer de bestaande cataloguseditie.
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

        // Voeg het land toe aan het Excel-bestand.
        $countriesSheet = $spreadsheet->getSheetByName('Landen');

        $countriesSheet->setCellValue('A2', $country->name);
        $countriesSheet->setCellValue('B2', $country->iso_code);
        $countriesSheet->setCellValue('C2', 'country');
        $countriesSheet->setCellValue('F2', 1);
        $countriesSheet->setCellValue('G2', $country->slug);

        // Voeg de postzegelserie toe.
        $seriesSheet = $spreadsheet->getSheetByName('Postzegelseries');

        $seriesSheet->setCellValue('A2', 1001);
        $seriesSheet->setCellValue('B2', $country->slug);
        $seriesSheet->setCellValue('C2', $country->iso_code);
        $seriesSheet->setCellValue('D2', $series->name);
        $seriesSheet->setCellValue('E2', 2026);

        // Voeg een seriecataloguswaarde zonder waarderingscategorie toe.
        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        $seriesValuesSheet->setCellValue('A2', 1001);
        $seriesValuesSheet->setCellValue('B2', null);
        $seriesValuesSheet->setCellValue('C2', 'SER-100');
        $seriesValuesSheet->setCellValue('D2', 125.50);
        $seriesValuesSheet->setCellValue('E2', 'EUR');
        $seriesValuesSheet->setCellValue('F2', 'Algemene seriecataloguswaarde');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer de oorspronkelijke situatie.
        expect(SeriesCatalogValue::query()->count())->toBe(0)
            ->and(CatalogChangeBatch::query()->count())->toBe(0);

        // Importeer met de methode Aanvullen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'merge', $user);

        expect($importedEdition->id)->toBe($edition->id);

        // Controleer de aangemaakte seriecataloguswaarde.
        expect(SeriesCatalogValue::query()->count())->toBe(1);

        $value = SeriesCatalogValue::query()->sole();

        expect($value->catalog_edition_id)->toBe($edition->id)
            ->and($value->stamp_series_id)->toBe($series->id)
            ->and($value->condition_id)->toBeNull()
            ->and($value->catalog_number)->toBe('SER-100')
            ->and($value->catalog_value)->toBe('125.50')
            ->and($value->currency)->toBe('EUR')
            ->and($value->notes)->toBe('Algemene seriecataloguswaarde');

        // Controleer dat de bestaande postzegelserie is hergebruikt.
        expect(StampSeries::query()->count())->toBe(1);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_merge')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Controleer het wijzigingslog.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('created')
            ->and($log->record_type)->toBe('series_catalog_value')
            ->and($log->record_id)->toBe($value->id)
            ->and($log->stamp_id)->toBeNull()
            ->and($log->stamp_series_id)->toBe($series->id)
            ->and($log->condition_id)->toBeNull()
            ->and($log->catalog_edition_id)->toBe($edition->id)
            ->and($log->old_values)->toBeNull();

        // Controleer dat condition_id expliciet in de snapshot aanwezig is.
        expect($log->new_values)->toHaveKey('condition_id');

        // Controleer de geregistreerde nieuwe gegevens.
        expect($log->new_values['id'])->toBe($value->id)
            ->and($log->new_values['catalog_edition_id'])->toBe($edition->id)
            ->and($log->new_values['stamp_series_id'])->toBe($series->id)
            ->and($log->new_values['condition_id'])->toBeNull()
            ->and($log->new_values['catalog_number'])->toBe('SER-100')
            ->and((float) $log->new_values['catalog_value'])->toBe(125.50)
            ->and($log->new_values['currency'])->toBe('EUR')
            ->and($log->new_values['notes'])->toBe('Algemene seriecataloguswaarde');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een gewijzigde seriecataloguswaarde zonder waarderingscategorie wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis wijzigen serie zonder categorie',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-wijzigen-serie-zonder-categorie',
        'iso_code' => 'TWZ',
        'name' => 'Testland wijzigen serie zonder categorie',
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker wijzigen serie zonder categorie',
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie zonder waarderingscategorie',
        'issue_year' => 2026,
    ]);

    $value = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => null,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande algemene seriecataloguswaarde',
    ]);

    $originalValueId = $value->id;

    // Exporteer de bestaande cataloguseditie.
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

        $seriesValuesSheet = $spreadsheet->getSheetByName('Seriecataloguswaarden');

        // Laat de waarderingscategorie leeg.
        $seriesValuesSheet->setCellValue('B2', null);

        // Wijzig de seriecatalogusgegevens.
        $seriesValuesSheet->setCellValue('C2', 'SER-200');
        $seriesValuesSheet->setCellValue('D2', 250.00);
        $seriesValuesSheet->setCellValue('E2', 'USD');
        $seriesValuesSheet->setCellValue('F2', 'Gewijzigde algemene seriecataloguswaarde');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        $value->refresh();

        // Controleer dat de bestaande cataloguseditie behouden blijft.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer dat de bestaande seriecataloguswaarde is bijgewerkt.
        expect($value->id)->toBe($originalValueId)
            ->and($value->catalog_edition_id)->toBe($edition->id)
            ->and($value->stamp_series_id)->toBe($series->id)
            ->and($value->condition_id)->toBeNull()
            ->and($value->catalog_number)->toBe('SER-200')
            ->and($value->catalog_value)->toBe('250.00')
            ->and($value->currency)->toBe('USD')
            ->and($value->notes)->toBe('Gewijzigde algemene seriecataloguswaarde');

        // Controleer dat geen dubbele records zijn aangemaakt.
        expect(SeriesCatalogValue::query()->count())->toBe(1)
            ->and(StampSeries::query()->count())->toBe(1);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Er mag precies één wijzigingslog bestaan.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('updated')
            ->and($log->record_type)->toBe('series_catalog_value')
            ->and($log->record_id)->toBe($value->id)
            ->and($log->stamp_id)->toBeNull()
            ->and($log->stamp_series_id)->toBe($series->id)
            ->and($log->condition_id)->toBeNull()
            ->and($log->catalog_edition_id)->toBe($edition->id);

        // Controleer de oorspronkelijke gegevens.
        expect($log->old_values['catalog_number'])->toBe('SER-100')
            ->and((float) $log->old_values['catalog_value'])->toBe(125.50)
            ->and($log->old_values['currency'])->toBe('EUR')
            ->and($log->old_values['notes'])->toBe('Bestaande algemene seriecataloguswaarde');

        // Controleer de gewijzigde gegevens.
        expect($log->new_values['catalog_number'])->toBe('SER-200')
            ->and((float) $log->new_values['catalog_value'])->toBe(250.00)
            ->and($log->new_values['currency'])->toBe('USD')
            ->and($log->new_values['notes'])->toBe('Gewijzigde algemene seriecataloguswaarde');

        // Ongewijzigde velden mogen niet in de snapshots voorkomen.
        expect($log->old_values)->not->toHaveKey('condition_id')
            ->not->toHaveKey('stamp_series_id')
            ->not->toHaveKey('catalog_edition_id')
            ->not->toHaveKey('updated_at');

        expect($log->new_values)->not->toHaveKey('condition_id')
            ->not->toHaveKey('stamp_series_id')
            ->not->toHaveKey('catalog_edition_id')
            ->not->toHaveKey('updated_at');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een verwijderde seriecataloguswaarde zonder waarderingscategorie wordt correct geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis verwijderen serie zonder categorie',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-verwijderen-serie-zonder-categorie',
        'iso_code' => 'TVZ',
        'name' => 'Testland verwijderen serie zonder categorie',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker verwijderen serie zonder categorie',
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie zonder waarderingscategorie',
        'issue_year' => 2026,
    ]);

    // Maak een seriecataloguswaarde aan die behouden moet blijven.
    $retainedValue = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => $condition->id,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Te behouden seriecataloguswaarde',
    ]);

    // Exporteer de cataloguseditie voordat de tweede waarde wordt aangemaakt.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Xlsx::class
    );

    // Maak een seriecataloguswaarde zonder waarderingscategorie aan.
    // Deze waarde ontbreekt bewust in het Excel-bestand.
    $removedValue = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => null,
        'catalog_number' => 'SER-200',
        'catalog_value' => '250.00',
        'currency' => 'EUR',
        'notes' => 'Te verwijderen algemene seriecataloguswaarde',
    ]);

    $removedValueId = $removedValue->id;

    $filePath = tempnam(sys_get_temp_dir(), 'catalog_exchange_');

    if ($filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    try {
        file_put_contents($filePath, $excelContent);

        // Controleer de oorspronkelijke situatie.
        expect(SeriesCatalogValue::query()->count())->toBe(2)
            ->and($removedValue->condition_id)->toBeNull()
            ->and(CatalogChangeBatch::query()->count())->toBe(0);

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        expect($importedEdition->id)->toBe($edition->id);

        // Controleer dat uitsluitend de ontbrekende waarde is verwijderd.
        expect(SeriesCatalogValue::query()->count())->toBe(1);

        $this->assertDatabaseMissing('series_catalog_values', [
            'id' => $removedValueId,
        ]);

        // Controleer dat de bestaande seriecataloguswaarde behouden blijft.
        $retainedValue->refresh();

        expect($retainedValue->catalog_edition_id)->toBe($edition->id)
            ->and($retainedValue->stamp_series_id)->toBe($series->id)
            ->and($retainedValue->condition_id)->toBe($condition->id)
            ->and($retainedValue->catalog_number)->toBe('SER-100')
            ->and($retainedValue->catalog_value)->toBe('125.50')
            ->and($retainedValue->currency)->toBe('EUR')
            ->and($retainedValue->notes)->toBe('Te behouden seriecataloguswaarde');

        // Controleer dat de postzegelserie zelf behouden blijft.
        expect(StampSeries::query()->count())->toBe(1);

        $this->assertDatabaseHas('stamp_series', [
            'id' => $series->id,
        ]);

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Er mag precies één wijzigingslog bestaan.
        expect($batch->logs()->count())->toBe(1);

        $log = $batch->logs()->sole();

        expect($log->action)->toBe('deleted')
            ->and($log->record_type)->toBe('series_catalog_value')
            ->and($log->record_id)->toBe($removedValueId)
            ->and($log->stamp_id)->toBeNull()
            ->and($log->stamp_series_id)->toBe($series->id)
            ->and($log->condition_id)->toBeNull()
            ->and($log->catalog_edition_id)->toBe($edition->id)
            ->and($log->new_values)->toBeNull();

        // Controleer dat condition_id expliciet in de snapshot aanwezig is.
        expect($log->old_values)->toHaveKey('condition_id');

        // Controleer de oorspronkelijke gegevens van de verwijderde waarde.
        expect($log->old_values['id'])->toBe($removedValueId)
            ->and($log->old_values['catalog_edition_id'])->toBe($edition->id)
            ->and($log->old_values['stamp_series_id'])->toBe($series->id)
            ->and($log->old_values['condition_id'])->toBeNull()
            ->and($log->old_values['catalog_number'])->toBe('SER-200')
            ->and((float) $log->old_values['catalog_value'])->toBe(250.00)
            ->and($log->old_values['currency'])->toBe('EUR')
            ->and($log->old_values['notes'])->toBe('Te verwijderen algemene seriecataloguswaarde');
    } finally {
        unlink($filePath);
    }
});

test('een gewijzigd catalogusnummer wordt als aanmaken en verwijderen geregistreerd in de importgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis gewijzigd catalogusnummer',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-gewijzigd-catalogusnummer',
        'iso_code' => 'TGC',
        'name' => 'Testland gewijzigd catalogusnummer',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker gewijzigd catalogusnummer',
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
        'notes' => 'Oorspronkelijke catalogusvermelding',
    ]);

    $value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde',
        'needs_review' => false,
    ]);

    $originalStampId = $stamp->id;
    $originalEntryId = $entry->id;
    $originalValueId = $value->id;

    // Exporteer de bestaande cataloguseditie.
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
        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        // Wijzig het catalogusnummer van de postzegel.
        $stampsSheet->setCellValue('C2', '200');
        $stampsSheet->setCellValue('Q2', 'Nieuwe catalogusvermelding');

        // Wijzig ook het catalogusnummer bij de cataloguswaarde.
        $catalogValuesSheet->setCellValue('B2', '200');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        // Controleer de oorspronkelijke situatie.
        expect(Stamp::query()->count())->toBe(1)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(StampCatalogValue::query()->count())->toBe(1)
            ->and(CatalogChangeBatch::query()->count())->toBe(0);

        // Importeer met de methode Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        expect($importedEdition->id)->toBe($edition->id);

        // De oorspronkelijke postzegel moet behouden blijven.
        $this->assertDatabaseHas('stamps', [
            'id' => $originalStampId,
        ]);

        // Controleer de aantallen na de import.
        expect(Stamp::query()->count())->toBe(2)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(StampCatalogValue::query()->count())->toBe(1);

        // De oorspronkelijke catalogusvermelding en waarde zijn verwijderd.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $originalEntryId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $originalValueId,
        ]);

        // Controleer de nieuwe catalogusvermelding.
        $newEntry = StampCatalogEntry::query()->sole();

        expect($newEntry->id)->not->toBe($originalEntryId)
            ->and($newEntry->stamp_id)->not->toBe($originalStampId)
            ->and($newEntry->catalog_edition_id)->toBe($edition->id)
            ->and($newEntry->catalog_number)->toBe('200')
            ->and($newEntry->notes)->toBe('Nieuwe catalogusvermelding');

        // Controleer de nieuwe postzegel.
        $newStamp = $newEntry->stamp;

        expect($newStamp->name)->toBe('Bestaande postzegel')
            ->and($newStamp->country_id)->toBe($country->id)
            ->and($newStamp->face_value)->toBe('1/2')
            ->and($newStamp->currency)->toBe('EUR');

        // Controleer de nieuwe cataloguswaarde.
        $newValue = StampCatalogValue::query()->sole();

        expect($newValue->id)->not->toBe($originalValueId)
            ->and($newValue->stamp_catalog_entry_id)->toBe($newEntry->id)
            ->and($newValue->condition_id)->toBe($condition->id)
            ->and($newValue->catalog_value)->toBe('25.50')
            ->and($newValue->currency)->toBe('EUR')
            ->and($newValue->notes)->toBe('Oorspronkelijke cataloguswaarde')
            ->and($newValue->needs_review)->toBeFalse();

        // Controleer de geschiedenisbatch.
        expect(CatalogChangeBatch::query()->count())->toBe(1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch->operation)->toBe('import_replace')
            ->and($batch->catalog_edition_id)->toBe($edition->id)
            ->and($batch->user_id)->toBe($user->id)
            ->and($batch->user_name)->toBe($user->name);

        // Er moeten vier wijzigingslogs bestaan.
        expect($batch->logs()->count())->toBe(4)
            ->and($batch->logs()->where('action', 'created')->count())->toBe(2)
            ->and($batch->logs()->where('action', 'deleted')->count())->toBe(2)
            ->and($batch->logs()->where('action', 'updated')->count())->toBe(0);

        // Zoek het wijzigingslog van de nieuwe catalogusvermelding.
        $createdEntryLog = $batch->logs()
            ->where('action', 'created')
            ->where('record_id', $newEntry->id)
            ->whereNull('condition_id')
            ->sole();

        expect($createdEntryLog->record_type)->toBe('stamp_catalog_entry')
            ->and($createdEntryLog->stamp_id)->toBe($newStamp->id)
            ->and($createdEntryLog->catalog_edition_id)->toBe($edition->id)
            ->and($createdEntryLog->old_values)->toBeNull()
            ->and($createdEntryLog->new_values['catalog_number'])->toBe('200')
            ->and($createdEntryLog->new_values['stamp_id'])->toBe($newStamp->id)
            ->and($createdEntryLog->new_values['notes'])->toBe('Nieuwe catalogusvermelding');

        // Zoek het wijzigingslog van de nieuwe cataloguswaarde.
        $createdValueLog = $batch->logs()
            ->where('action', 'created')
            ->where('record_id', $newValue->id)
            ->where('condition_id', $condition->id)
            ->sole();

        expect($createdValueLog->record_type)->toBe('stamp_catalog_value')
            ->and($createdValueLog->stamp_id)->toBe($newStamp->id)
            ->and($createdValueLog->catalog_edition_id)->toBe($edition->id)
            ->and($createdValueLog->old_values)->toBeNull()
            ->and($createdValueLog->new_values['stamp_catalog_entry_id'])->toBe($newEntry->id)
            ->and($createdValueLog->new_values['condition_id'])->toBe($condition->id)
            ->and((float) $createdValueLog->new_values['catalog_value'])->toBe(25.50)
            ->and($createdValueLog->new_values['currency'])->toBe('EUR')
            ->and($createdValueLog->new_values['notes'])->toBe('Oorspronkelijke cataloguswaarde')
            ->and((bool) $createdValueLog->new_values['needs_review'])->toBeFalse();

        // Zoek het wijzigingslog van de verwijderde catalogusvermelding.
        $deletedEntryLog = $batch->logs()
            ->where('action', 'deleted')
            ->where('record_id', $originalEntryId)
            ->whereNull('condition_id')
            ->sole();

        expect($deletedEntryLog->record_type)->toBe('stamp_catalog_entry')
            ->and($deletedEntryLog->stamp_id)->toBe($originalStampId)
            ->and($deletedEntryLog->catalog_edition_id)->toBe($edition->id)
            ->and($deletedEntryLog->new_values)->toBeNull()
            ->and($deletedEntryLog->old_values['catalog_number'])->toBe('100')
            ->and($deletedEntryLog->old_values['stamp_id'])->toBe($originalStampId)
            ->and($deletedEntryLog->old_values['notes'])->toBe('Oorspronkelijke catalogusvermelding');

        // Zoek het wijzigingslog van de verwijderde cataloguswaarde.
        $deletedValueLog = $batch->logs()
            ->where('action', 'deleted')
            ->where('record_id', $originalValueId)
            ->where('condition_id', $condition->id)
            ->sole();

        expect($deletedValueLog->record_type)->toBe('stamp_catalog_value')
            ->and($deletedValueLog->stamp_id)->toBe($originalStampId)
            ->and($deletedValueLog->catalog_edition_id)->toBe($edition->id)
            ->and($deletedValueLog->new_values)->toBeNull()
            ->and($deletedValueLog->old_values['stamp_catalog_entry_id'])->toBe($originalEntryId)
            ->and($deletedValueLog->old_values['condition_id'])->toBe($condition->id)
            ->and((float) $deletedValueLog->old_values['catalog_value'])->toBe(25.50)
            ->and($deletedValueLog->old_values['currency'])->toBe('EUR')
            ->and($deletedValueLog->old_values['notes'])->toBe('Oorspronkelijke cataloguswaarde')
            ->and((bool) $deletedValueLog->old_values['needs_review'])->toBeFalse();

        // Controleer de volgorde van de geschiedenisregistraties.
        expect($createdEntryLog->id)->toBeLessThan($createdValueLog->id);
        expect($deletedValueLog->id)->toBeLessThan($deletedEntryLog->id);
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});

test('een volledig ongewijzigde cataloguseditie veroorzaakt geen importgeschiedenis bij vervangen', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis ongewijzigde editie',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-geschiedenis-ongewijzigde-editie',
        'iso_code' => 'TOE',
        'name' => 'Testland ongewijzigde editie',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Testgebruiker ongewijzigde editie',
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegelserie',
        'issue_year' => 2026,
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'stamp_series_id' => $series->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
        'face_value' => '1/2',
        'currency' => 'EUR',
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
        'notes' => 'Bestaande catalogusvermelding',
    ]);

    $value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande cataloguswaarde',
        'needs_review' => false,
    ]);

    $seriesValue = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => $condition->id,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande seriecataloguswaarde',
    ]);

    // Bewaar de oorspronkelijke databasegegevens.
    $originalEntry = $entry->fresh()->getAttributes();
    $originalValue = $value->fresh()->getAttributes();
    $originalSeriesValue = $seriesValue->fresh()->getAttributes();

    // Exporteer de volledige cataloguseditie.
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

    try {
        file_put_contents($filePath, $excelContent);

        // Controleer de oorspronkelijke situatie.
        expect(CatalogEdition::query()->count())->toBe(1)
            ->and(Stamp::query()->count())->toBe(1)
            ->and(StampSeries::query()->count())->toBe(1)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(StampCatalogValue::query()->count())->toBe(1)
            ->and(SeriesCatalogValue::query()->count())->toBe(1)
            ->and(CatalogChangeBatch::query()->count())->toBe(0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Importeer het ongewijzigde Excel-bestand met Vervangen.
        $importedEdition = app(CatalogEditionExchangeImporter::class)
            ->importEdition($filePath, $catalog, 'replace', $user);

        // Controleer dat de bestaande cataloguseditie behouden blijft.
        expect($importedEdition->id)->toBe($edition->id);

        // Controleer dat geen records zijn toegevoegd of verwijderd.
        expect(CatalogEdition::query()->count())->toBe(1)
            ->and(Stamp::query()->count())->toBe(1)
            ->and(StampSeries::query()->count())->toBe(1)
            ->and(StampCatalogEntry::query()->count())->toBe(1)
            ->and(StampCatalogValue::query()->count())->toBe(1)
            ->and(SeriesCatalogValue::query()->count())->toBe(1);

        // Haal de actuele databasegegevens op.
        $currentEntry = $entry->fresh()->getAttributes();
        $currentValue = $value->fresh()->getAttributes();
        $currentSeriesValue = $seriesValue->fresh()->getAttributes();

        // Technische wijzigingstijdstippen tellen niet als cataloguswijziging.
        unset(
            $originalEntry['updated_at'],
            $originalValue['updated_at'],
            $originalSeriesValue['updated_at'],
            $currentEntry['updated_at'],
            $currentValue['updated_at'],
            $currentSeriesValue['updated_at']
        );

        // Controleer dat alle inhoudelijke gegevens ongewijzigd zijn.
        expect($currentEntry)->toEqual($originalEntry)
            ->and($currentValue)->toEqual($originalValue)
            ->and($currentSeriesValue)->toEqual($originalSeriesValue);

        // Controleer dat geen geschiedenisbatch is achtergebleven.
        expect(CatalogChangeBatch::query()->count())->toBe(0);

        // Controleer dat geen wijzigingslogs zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        unlink($filePath);
    }
});

test('een volgende import behoudt de bestaande catalogusgeschiedenis', function () {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus behoud importgeschiedenis',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-behoud-importgeschiedenis',
        'iso_code' => 'TBI',
        'name' => 'Testland behoud importgeschiedenis',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Bestaande postzegel',
        'issue_year' => 2026,
    ]);

    $entry = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'needs_review' => false,
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

        // Eerste import: verhoog de cataloguswaarde naar 50 euro.
        $spreadsheet = IOFactory::load($filePath);

        $spreadsheet->getSheetByName('Cataloguswaarden')
            ->setCellValue('D2', '50.00');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        $importer = app(CatalogEditionExchangeImporter::class);

        $importer->importEdition(
            $filePath,
            $catalog,
            'replace',
            $firstUser
        );

        $firstBatch = CatalogChangeBatch::query()->sole();
        $firstLog = $firstBatch->logs()->sole();

        expect($firstBatch->user_id)->toBe($firstUser->id)
            ->and($firstLog->action)->toBe('updated')
            ->and($firstLog->record_type)->toBe('stamp_catalog_value')
            ->and($firstLog->record_id)->toBe($value->id)
            ->and((float) $firstLog->old_values['catalog_value'])->toBe(25.50)
            ->and((float) $firstLog->new_values['catalog_value'])->toBe(50.00);

        // Bewaar de volledige oorspronkelijke geschiedenis.
        $originalBatch = $firstBatch->getAttributes();
        $originalLog = $firstLog->getAttributes();

        // Tweede import: verhoog de cataloguswaarde naar 75 euro.
        $spreadsheet = IOFactory::load($filePath);

        $catalogValuesSheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        $catalogValuesSheet->setCellValue('D2', '75.00');
        $catalogValuesSheet->setCellValue('G2', 1);

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        $importer->importEdition(
            $filePath,
            $catalog,
            'replace',
            $secondUser
        );

        // Beide importbewerkingen hebben een afzonderlijke batch.
        expect(CatalogChangeBatch::query()->count())->toBe(2);

        $this->assertDatabaseCount('catalog_change_logs', 2);

        // De oorspronkelijke geschiedenis is volledig ongewijzigd.
        expect($firstBatch->fresh()->getAttributes())->toEqual($originalBatch)
            ->and($firstLog->fresh()->getAttributes())->toEqual($originalLog)
            ->and($firstBatch->logs()->count())->toBe(1);

        // Controleer de nieuwe geschiedenisbatch.
        $secondBatch = CatalogChangeBatch::query()
            ->where('id', '!=', $firstBatch->id)
            ->sole();

        expect($secondBatch->operation)->toBe('import_replace')
            ->and($secondBatch->catalog_edition_id)->toBe($edition->id)
            ->and($secondBatch->user_id)->toBe($secondUser->id)
            ->and($secondBatch->user_name)->toBe($secondUser->name)
            ->and($secondBatch->logs()->count())->toBe(1);

        $secondLog = $secondBatch->logs()->sole();

        expect($secondLog->action)->toBe('updated')
            ->and($secondLog->record_type)->toBe('stamp_catalog_value')
            ->and($secondLog->record_id)->toBe($value->id)
            ->and((float) $secondLog->old_values['catalog_value'])->toBe(50.00)
            ->and((float) $secondLog->new_values['catalog_value'])->toBe(75.00);

        expect($value->fresh()->catalog_value)->toBe('75.00');
    } finally {
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
});
