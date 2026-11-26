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
use App\Services\CatalogChangeHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

test('een mislukte import draait catalogusgegevens en geschiedenis volledig terug', function (string $importMode) {
    $catalog = Catalog::create([
        'name' => 'Testcatalogus rollback importgeschiedenis',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $country = Country::create([
        'slug' => 'testland-rollback-importgeschiedenis',
        'iso_code' => 'RIG',
        'name' => 'Testland rollback importgeschiedenis',
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

    $user = User::factory()->create();

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Postzegelserie rollback importgeschiedenis',
        'issue_year' => 2026,
    ]);

    $stamp = Stamp::create([
        'country_id' => $country->id,
        'stamp_series_id' => $series->id,
        'name' => 'Postzegel rollback importgeschiedenis',
        'issue_year' => 2026,
    ]);

    $entry = StampCatalogEntry::create([
        'catalog_edition_id' => $edition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100',
    ]);

    $value = StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => '25.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde',
        'needs_review' => false,
    ]);

    $seriesValue = SeriesCatalogValue::create([
        'catalog_edition_id' => $edition->id,
        'stamp_series_id' => $series->id,
        'condition_id' => $postfris->id,
        'catalog_number' => 'SER-100',
        'catalog_value' => '125.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke seriecataloguswaarde',
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
    $originalDispatcher = null;
    $failureReached = false;

    try {
        file_put_contents($filePath, $excelContent);

        $spreadsheet = IOFactory::load($filePath);

        // Voeg de tweede waarderingscategorie toe.
        $conditionsSheet = $spreadsheet->getSheetByName(
            'Waarderingscategorieen'
        );

        $conditionsSheet->setCellValue('A3', $gestempeld->slug);

        // Wijzig de bestaande cataloguswaarde.
        $catalogValuesSheet = $spreadsheet->getSheetByName(
            'Cataloguswaarden'
        );

        $exchangeStampId = $catalogValuesSheet
            ->getCell('A2')
            ->getValue();

        $catalogValuesSheet->setCellValue('D2', '50.00');

        // Voeg een nieuwe cataloguswaarde toe.
        $catalogValuesSheet->setCellValue('A3', $exchangeStampId);
        $catalogValuesSheet->setCellValue('B3', '100');
        $catalogValuesSheet->setCellValue('C3', $gestempeld->slug);
        $catalogValuesSheet->setCellValue('D3', '12.50');
        $catalogValuesSheet->setCellValue('E3', 'EUR');
        $catalogValuesSheet->setCellValue('F3', 'Nieuwe cataloguswaarde');
        $catalogValuesSheet->setCellValue('G3', 0);

        // Wijzig de bestaande seriecataloguswaarde.
        $seriesValuesSheet = $spreadsheet->getSheetByName(
            'Seriecataloguswaarden'
        );

        $exchangeSeriesId = $seriesValuesSheet
            ->getCell('A2')
            ->getValue();

        $seriesValuesSheet->setCellValue('D2', '250.00');

        // Voeg een nieuwe seriecataloguswaarde toe.
        $seriesValuesSheet->setCellValue('A3', $exchangeSeriesId);
        $seriesValuesSheet->setCellValue('B3', $gestempeld->slug);
        $seriesValuesSheet->setCellValue('C3', 'SER-100');
        $seriesValuesSheet->setCellValue('D3', '75.00');
        $seriesValuesSheet->setCellValue('E3', 'EUR');
        $seriesValuesSheet->setCellValue('F3', 'Nieuwe seriecataloguswaarde');

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();
        $spreadsheet = null;

        if ($importMode === 'create') {
            // Verwijder de oorspronkelijke editie voor een nieuwe import.
            $seriesValue->delete();
            $value->delete();
            $entry->delete();
            $edition->delete();
        }

        if ($importMode === 'replace') {
            // Voeg een cataloguswaarde toe die niet in Excel voorkomt.
            $missingCondition = Condition::create([
                'slug' => 'ongebruikt-met-plakker',
                'sort_order' => 3,
                'is_active' => true,
            ]);

            StampCatalogValue::create([
                'stamp_catalog_entry_id' => $entry->id,
                'condition_id' => $missingCondition->id,
                'catalog_value' => '15.00',
                'currency' => 'EUR',
                'needs_review' => false,
            ]);
        }

        // Bewaar de volledige relevante databasesituatie.
        $tables = [
            'catalog_editions',
            'stamps',
            'stamp_series',
            'stamp_catalog_entries',
            'stamp_catalog_values',
            'series_catalog_values',
            'catalog_change_batches',
            'catalog_change_logs',
        ];

        $originalRecords = [];

        foreach ($tables as $table) {
            $originalRecords[$table] = DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => (array) $record)
                ->all();
        }

        $history = app(CatalogChangeHistoryService::class);

        // Gebruik een afzonderlijke Eloquent-event-dispatcher.
        $originalDispatcher = SeriesCatalogValue::getEventDispatcher();

        SeriesCatalogValue::setEventDispatcher(
            clone $originalDispatcher
        );

        // Simuleer een fout nadat eerdere wijzigingen zijn geregistreerd.
        SeriesCatalogValue::creating(
            function (SeriesCatalogValue $record) use (
                $gestempeld,
                $history,
                $importMode,
                &$failureReached
            ) {
                if ($record->condition_id !== $gestempeld->id) {
                    return;
                }

                $batch = $history->getActiveBatch();

                expect($batch)->not->toBeNull()
                    ->and($batch->operation)->toBe('import_'.$importMode)
                    ->and($batch->logs()->count())->toBeGreaterThan(0);

                // Controleer dat daadwerkelijk catalogusgeschiedenis bestaat.
                expect(
                    $batch->logs()
                        ->where('record_type', 'stamp_catalog_value')
                        ->where('action', 'created')
                        ->exists()
                )->toBeTrue();

                if ($importMode === 'replace') {
                    // Zowel een wijziging als een verwijdering is geregistreerd.
                    expect(
                        $batch->logs()
                            ->where('record_type', 'stamp_catalog_value')
                            ->where('action', 'updated')
                            ->exists()
                    )->toBeTrue();

                    expect(
                        $batch->logs()
                            ->where('record_type', 'stamp_catalog_value')
                            ->where('action', 'deleted')
                            ->exists()
                    )->toBeTrue();
                }

                $failureReached = true;

                throw new RuntimeException(
                    'Gesimuleerde fout tijdens importgeschiedenis.'
                );
            }
        );

        $importer = app(CatalogEditionExchangeImporter::class);

        expect(
            fn () => $importer->importEdition(
                $filePath,
                $catalog,
                $importMode,
                $user
            )
        )->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens importgeschiedenis.'
        );

        // Controleer dat de bedoelde fout daadwerkelijk is bereikt.
        expect($failureReached)->toBeTrue();

        // Controleer dat alle oorspronkelijke records zijn hersteld.
        foreach ($tables as $table) {
            $currentRecords = DB::table($table)
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => (array) $record)
                ->all();

            expect($currentRecords)->toEqual($originalRecords[$table]);
        }

        // Er mag geen nieuwe geschiedenis zijn achtergebleven.
        expect(CatalogChangeBatch::query()->count())->toBe(0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // De actieve geschiedeniscontext moet zijn opgeruimd.
        expect($history->getActiveBatch())->toBeNull();

    } finally {
        if ($originalDispatcher !== null) {
            SeriesCatalogValue::setEventDispatcher($originalDispatcher);
        }

        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }

        unlink($filePath);
    }
})->with([
    'nieuwe cataloguseditie' => ['create'],
    'aanvullen' => ['merge'],
    'vervangen' => ['replace'],
]);
