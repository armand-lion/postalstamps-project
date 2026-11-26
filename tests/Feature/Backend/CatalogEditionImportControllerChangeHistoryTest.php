<?php

use App\Exports\Exchange\CatalogEditionExchangeExcelExport;
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
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

test('een import via HTTP registreert de ingelogde beheerder in de catalogusgeschiedenis', function () {
    Storage::fake('local');

    $admin = User::factory()->create([
        'name' => 'Beheerder importgeschiedenis',
        'is_admin' => true,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus HTTP importgeschiedenis',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-http-importgeschiedenis',
        'iso_code' => 'HIG',
        'name' => 'Testland HTTP importgeschiedenis',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'name' => 'Postzegel HTTP importgeschiedenis',
        'issue_year' => 2026,
    ]);

    $entry = StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    $value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Exporteer de bestaande cataloguseditie.
    $excelContent = Excel::raw(
        new CatalogEditionExchangeExcelExport($catalog, $edition),
        Maatwebsite\Excel\Excel::XLSX
    );

    $filePath = 'catalog-edition-imports/http-importgeschiedenis.xlsx';

    Storage::disk('local')->put($filePath, $excelContent);

    $absolutePath = Storage::disk('local')->path($filePath);

    // Wijzig de cataloguswaarde in het Excel-bestand.
    $spreadsheet = IOFactory::load($absolutePath);

    try {
        $spreadsheet->getSheetByName('Cataloguswaarden')
            ->setCellValue('D2', '50.00');

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }

    // Controleer dat vooraf geen geschiedenis bestaat.
    expect(CatalogChangeBatch::query()->count())->toBe(0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Bevestig de import als ingelogde beheerder.
    $response = $this
        ->actingAs($admin)
        ->withSession([
            'catalog_edition_import' => [
                'user_id' => $admin->id,
                'catalog_id' => $catalog->id,
                'file_path' => $filePath,
            ],
        ])
        ->post(
            route('backend.catalogs.editions.import.confirm', $catalog),
            [
                'import_mode' => 'replace',
            ]
        );

    // Controleer de succesvolle HTTP-afhandeling.
    $response->assertRedirect(
        route('backend.catalogs.editions.show', [
            'catalog' => $catalog,
            'edition' => $edition,
        ])
    );

    $response->assertSessionHas(
        'success',
        'De cataloguseditie is succesvol geïmporteerd.'
    );

    $response->assertSessionMissing('catalog_edition_import');

    Storage::disk('local')->assertMissing($filePath);

    // Controleer de gewijzigde cataloguswaarde.
    expect($value->fresh()->catalog_value)->toBe('50.00')
        ->and($value->fresh()->needs_review)->toBeTrue()
        ->and(CatalogEdition::query()->count())->toBe(1);

    // Controleer de geschiedenisbatch en gebruikersregistratie.
    $batch = CatalogChangeBatch::query()->sole();

    expect($batch->operation)->toBe('import_replace')
        ->and($batch->catalog_edition_id)->toBe($edition->id)
        ->and($batch->catalog_name)->toBe($catalog->name)
        ->and($batch->catalog_edition_name)->toBe($edition->edition_name)
        ->and($batch->user_id)->toBe($admin->id)
        ->and($batch->user_name)->toBe($admin->name)
        ->and($batch->logs()->count())->toBe(1);

    // Controleer de bijbehorende wijzigingsregistratie.
    $log = $batch->logs()->sole();

    expect($log->record_type)->toBe('stamp_catalog_value')
        ->and($log->action)->toBe('updated')
        ->and($log->record_id)->toBe($value->id)
        ->and($log->stamp_id)->toBe($stamp->id)
        ->and($log->condition_id)->toBe($condition->id)
        ->and($log->catalog_edition_id)->toBe($edition->id)
        ->and((float) $log->old_values['catalog_value'])->toBe(25.50)
        ->and((float) $log->new_values['catalog_value'])->toBe(50.00);

    $this->assertDatabaseCount('catalog_change_logs', 1);

    // Controleer dat de actieve geschiedeniscontext is opgeruimd.
    expect(
        app(CatalogChangeHistoryService::class)->getActiveBatch()
    )->toBeNull();
});
