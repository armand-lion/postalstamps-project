<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Stamp;
use App\Models\StampCatalogValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

test('een niet-ingelogde gebruiker kan geen cataloguseditie naar Excel exporteren', function () {
    $this->get(
        route('backend.catalogs.editions.export.excel', [
            'catalog' => 999999,
            'edition' => 999999,
        ])
    )->assertRedirect(route('login'));
});

test('een lege cataloguseditie kan naar Excel worden geëxporteerd', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus Excel',
        'abbreviation' => 'TCE',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Maak de bestaande outputbuffer leeg voordat Laravel Excel wordt aangeroepen.
    ob_clean();

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export.excel', [
            $catalog,
            $edition,
        ])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'-uitwisseling-v1.1.xlsx'
    );

    $spreadsheet = IOFactory::load(
        $response->baseResponse->getFile()->getPathname()
    );

    try {
        // Het uitwisselingsformaat v1.1 bevat 18 werkbladen.
        expect($spreadsheet->getSheetCount())->toBe(18);

        // Controleer het werkblad met postzegels.
        $worksheet = $spreadsheet->getSheetByName('Postzegels');

        expect($worksheet)->not->toBeNull();

        // Controleer de 17 kolomkoppen.
        $headers = $worksheet->rangeToArray(
            'A1:Q1',
            null,
            true,
            false
        )[0];

        expect($headers)->toBe([
            'exchange_stamp_id',
            'parent_exchange_stamp_id',
            'catalog_number',
            'exchange_series_id',
            'country_slug',
            'country_iso_code',
            'name',
            'description',
            'issue_year',
            'issue_date',
            'face_value',
            'currency',
            'designer',
            'printing_method',
            'catalog_reference',
            'notes',
            'catalog_entry_notes',
        ]);

        // Een lege cataloguseditie bevat geen postzegelrijen.
        expect($worksheet->getHighestDataRow())->toBe(1);

        // Ook het werkblad Cataloguswaarden bevat geen gegevensrijen.
        $valuesWorksheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        expect($valuesWorksheet)->not->toBeNull();
        expect($valuesWorksheet->getHighestDataRow())->toBe(1);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
});

test('een cataloguseditie met cataloguswaarden kan naar Excel worden geëxporteerd', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus Excel waarden',
        'abbreviation' => 'TCEW',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '123a',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Voorkom dat Laravel Excel een gevulde PHPUnit-outputbuffer sluit.
    ob_clean();

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export.excel', [
            $catalog,
            $edition,
        ])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'-uitwisseling-v1.1.xlsx'
    );

    $spreadsheet = IOFactory::load(
        $response->baseResponse->getFile()->getPathname()
    );

    try {
        // Controleer de postzegelgegevens.
        $stampsWorksheet = $spreadsheet->getSheetByName('Postzegels');

        expect($stampsWorksheet)->not->toBeNull();
        expect($stampsWorksheet->getHighestDataRow())->toBe(2);

        expect((int) $stampsWorksheet->getCell('A2')->getValue())
            ->toBe($entry->id);

        expect($stampsWorksheet->getCell('C2')->getValue())
            ->toBe('123a');

        // Controleer de cataloguswaarden.
        $valuesWorksheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        expect($valuesWorksheet)->not->toBeNull();
        expect($valuesWorksheet->getHighestDataRow())->toBe(2);

        expect((int) $valuesWorksheet->getCell('A2')->getValue())
            ->toBe($entry->id);

        expect($valuesWorksheet->getCell('B2')->getValue())
            ->toBe('123a');

        expect($valuesWorksheet->getCell('C2')->getValue())
            ->toBe('postfris');

        expect((float) $valuesWorksheet->getCell('D2')->getValue())
            ->toBe(12.50);

        expect($valuesWorksheet->getCell('E2')->getValue())
            ->toBe('EUR');

        expect((int) $valuesWorksheet->getCell('G2')->getValue())
            ->toBe(0);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
});

test('meerdere cataloguswaarden worden als afzonderlijke Excel-rijen geëxporteerd', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere waarden',
        'abbreviation' => 'TCMW',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '789c',
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

    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 4.75,
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    // Voorkom dat Laravel Excel een gevulde PHPUnit-outputbuffer sluit.
    ob_clean();

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export.excel', [
            $catalog,
            $edition,
        ])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'-uitwisseling-v1.1.xlsx'
    );

    $spreadsheet = IOFactory::load(
        $response->baseResponse->getFile()->getPathname()
    );

    try {
        // Eén postzegel wordt één keer geëxporteerd.
        $stampsWorksheet = $spreadsheet->getSheetByName('Postzegels');

        expect($stampsWorksheet)->not->toBeNull();
        expect($stampsWorksheet->getHighestDataRow())->toBe(2);

        expect((int) $stampsWorksheet->getCell('A2')->getValue())
            ->toBe($entry->id);

        expect($stampsWorksheet->getCell('C2')->getValue())
            ->toBe('789c');

        // Twee cataloguswaarden worden afzonderlijk geëxporteerd.
        $valuesWorksheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        expect($valuesWorksheet)->not->toBeNull();
        expect($valuesWorksheet->getHighestDataRow())->toBe(3);

        // Beide waarderingen verwijzen naar dezelfde catalogusvermelding.
        expect((int) $valuesWorksheet->getCell('A2')->getValue())
            ->toBe($entry->id);

        expect((int) $valuesWorksheet->getCell('A3')->getValue())
            ->toBe($entry->id);

        // Beide waarderingen hebben hetzelfde catalogusnummer.
        expect($valuesWorksheet->getCell('B2')->getValue())
            ->toBe('789c');

        expect($valuesWorksheet->getCell('B3')->getValue())
            ->toBe('789c');

        // Controleer de waarderingen onafhankelijk van hun rijvolgorde.
        $values = [];

        foreach ([2, 3] as $row) {
            $conditionSlug = $valuesWorksheet->getCell('C'.$row)->getValue();

            $values[$conditionSlug] = [
                'catalog_value' => (float) $valuesWorksheet->getCell('D'.$row)->getValue(),
                'currency' => $valuesWorksheet->getCell('E'.$row)->getValue(),
                'needs_review' => (int) $valuesWorksheet->getCell('G'.$row)->getValue(),
            ];
        }

        expect($values)->toHaveCount(2);

        expect($values['postfris'])->toBe([
            'catalog_value' => 12.50,
            'currency' => 'EUR',
            'needs_review' => 0,
        ]);

        expect($values['gestempeld'])->toBe([
            'catalog_value' => 4.75,
            'currency' => 'EUR',
            'needs_review' => 1,
        ]);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
});

test('een postzegel zonder cataloguswaarden wordt naar Excel geëxporteerd', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus zonder waarden',
        'abbreviation' => 'TCZW',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '456b',
    ]);

    // Voorkom dat Laravel Excel een gevulde PHPUnit-outputbuffer sluit.
    ob_clean();

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export.excel', [
            $catalog,
            $edition,
        ])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'-uitwisseling-v1.1.xlsx'
    );

    $spreadsheet = IOFactory::load(
        $response->baseResponse->getFile()->getPathname()
    );

    try {
        // Controleer of de postzegel aanwezig is.
        $stampsWorksheet = $spreadsheet->getSheetByName('Postzegels');

        expect($stampsWorksheet)->not->toBeNull();
        expect($stampsWorksheet->getHighestDataRow())->toBe(2);

        // Controleer de identificatie van de catalogusvermelding.
        expect((int) $stampsWorksheet->getCell('A2')->getValue())
            ->toBe($entry->id);

        // Controleer het catalogusnummer.
        expect($stampsWorksheet->getCell('C2')->getValue())
            ->toBe('456b');

        // Controleer of de postzegelnaam correct is geëxporteerd.
        expect($stampsWorksheet->getCell('G2')->getValue())
            ->toBe($stamp->name);

        // Controleer het werkblad Cataloguswaarden.
        $valuesWorksheet = $spreadsheet->getSheetByName('Cataloguswaarden');

        expect($valuesWorksheet)->not->toBeNull();

        // Er mogen geen gegevensrijen met cataloguswaarden aanwezig zijn.
        expect($valuesWorksheet->getHighestDataRow())->toBe(1);

        // Controleer of de juiste kolomkoppen aanwezig zijn.
        $headers = $valuesWorksheet->rangeToArray(
            'A1:G1',
            null,
            true,
            false
        )[0];

        expect($headers)->toBe([
            'exchange_stamp_id',
            'catalog_number',
            'condition_slug',
            'catalog_value',
            'currency',
            'notes',
            'needs_review',
        ]);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
});

test('een cataloguseditie kan niet via een verkeerde catalogus naar Excel worden geëxporteerd', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    // De catalogus waartoe de editie daadwerkelijk behoort.
    $catalog = Catalog::create([
        'name' => 'Oorspronkelijke catalogus',
        'abbreviation' => 'OC',
    ]);

    // Een andere catalogus.
    $otherCatalog = Catalog::create([
        'name' => 'Andere catalogus',
        'abbreviation' => 'AC',
    ]);

    // De editie behoort uitsluitend bij de oorspronkelijke catalogus.
    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
    ]);

    // Probeer de editie via de verkeerde catalogus te exporteren.
    $this->actingAs($user)
        ->get(
            route('backend.catalogs.editions.export.excel', [
                $otherCatalog,
                $edition,
            ])
        )
        ->assertNotFound();
});
