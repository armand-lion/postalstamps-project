<?php

use App\Exports\Exchange\CatalogEditionExchangeExcelExport;
use App\Imports\Exchange\CatalogEditionExchangeImporter;
use App\Models\Catalog;
use App\Models\CatalogChangeBatch;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\User;
use App\Services\CatalogChangeHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->catalog = Catalog::create([
        'name' => 'Testcatalogus importgeschiedeniscontext',
    ]);

    $this->edition = CatalogEdition::create([
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-importgeschiedeniscontext',
        'iso_code' => 'IGC',
        'name' => 'Testland importgeschiedeniscontext',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->user = User::factory()->create();

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Postzegel importgeschiedeniscontext',
        'issue_year' => 2026,
    ]);

    $entry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    $this->value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport(
            $this->catalog,
            $this->edition
        ),
        Xlsx::class
    );

    $this->filePath = tempnam(
        sys_get_temp_dir(),
        'catalog_exchange_'
    );

    if ($this->filePath === false) {
        throw new RuntimeException(
            'Kan tijdelijk Excel-bestand niet aanmaken.'
        );
    }

    file_put_contents($this->filePath, $excelContent);

    $spreadsheet = IOFactory::load($this->filePath);

    try {
        $spreadsheet->getSheetByName('Cataloguswaarden')
            ->setCellValue('D2', '50.00');

        $writer = new Xlsx($spreadsheet);
        $writer->save($this->filePath);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }

    $this->history = app(CatalogChangeHistoryService::class);
    $this->importer = app(CatalogEditionExchangeImporter::class);
});

afterEach(function () {
    if (isset($this->history)) {
        $this->history->clearActiveBatch();
    }

    if (
        isset($this->filePath)
        && is_string($this->filePath)
        && is_file($this->filePath)
    ) {
        unlink($this->filePath);
    }
});

test('een succesvolle import ruimt de actieve geschiedeniscontext volledig op', function () {
    expect($this->history->getActiveBatch())->toBeNull();

    // Voer een import met een daadwerkelijke wijziging uit.
    $importedEdition = $this->importer->importEdition(
        $this->filePath,
        $this->catalog,
        'replace',
        $this->user
    );

    expect($importedEdition->id)->toBe($this->edition->id)
        ->and($this->value->fresh()->catalog_value)->toBe('50.00')
        ->and($this->history->getActiveBatch())->toBeNull();

    // Controleer de geregistreerde geschiedenis.
    $batch = CatalogChangeBatch::query()->sole();
    $log = $batch->logs()->sole();

    expect($batch->operation)->toBe('import_replace')
        ->and($batch->user_id)->toBe($this->user->id)
        ->and($log->record_type)->toBe('stamp_catalog_value')
        ->and($log->action)->toBe('updated')
        ->and($log->record_id)->toBe($this->value->id);

    // Voer buiten de import een afzonderlijke wijziging uit.
    $this->value->refresh();

    $this->value->update([
        'catalog_value' => '75.00',
    ]);

    // Deze wijziging mag niet aan de afgesloten importbatch
    // worden toegevoegd.
    expect($this->value->fresh()->catalog_value)->toBe('75.00')
        ->and($this->history->getActiveBatch())->toBeNull()
        ->and(CatalogChangeBatch::query()->count())->toBe(1)
        ->and($batch->logs()->count())->toBe(1);

    $this->assertDatabaseCount('catalog_change_logs', 1);

    expect(
        (float) $log->fresh()->new_values['catalog_value']
    )->toBe(50.00);
});

test('een import beschermt een reeds actieve geschiedenisbatch', function () {
    // Maak vooraf een afzonderlijke geschiedenisbatch aan.
    $existingBatch = $this->history->createBatch(
        $this->edition,
        'import_replace',
        $this->user,
        'Reeds actieve geschiedenisbatch.'
    );

    $this->history->setActiveBatch($existingBatch);

    // Registreer een bestaande wijziging in deze batch.
    $this->value->update([
        'catalog_value' => '30.00',
    ]);

    $existingLog = $existingBatch->logs()->sole();

    // Bewaar de volledige oorspronkelijke gegevens.
    $originalBatch = $existingBatch->fresh()->getAttributes();
    $originalLog = $existingLog->fresh()->getAttributes();
    $originalValue = $this->value->fresh()->getAttributes();

    // De import mag de actieve batch niet overschrijven.
    expect(
        fn () => $this->importer->importEdition(
            $this->filePath,
            $this->catalog,
            'replace',
            $this->user
        )
    )->toThrow(
        RuntimeException::class,
        'Er is al een actieve catalogusgeschiedenisbatch.'
    );

    // Controleer dat dezelfde batch actief is gebleven.
    expect($this->history->getActiveBatch())->toBe($existingBatch);

    // Controleer dat geen gegevens zijn gewijzigd.
    expect($this->value->fresh()->getAttributes())
        ->toEqual($originalValue);

    expect($existingBatch->fresh()->getAttributes())
        ->toEqual($originalBatch);

    expect($existingLog->fresh()->getAttributes())
        ->toEqual($originalLog);

    // Er mogen geen aanvullende batches of logs bestaan.
    expect(CatalogChangeBatch::query()->count())->toBe(1)
        ->and($existingBatch->logs()->count())->toBe(1)
        ->and(CatalogEdition::query()->count())->toBe(1);

    $this->assertDatabaseCount('catalog_change_logs', 1);
});
