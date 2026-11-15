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

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export.excel', [
            $catalog,
            $edition,
        ])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'.xlsx'
    );

    // Lees het gegenereerde Excel-bestand.
    $spreadsheet = IOFactory::load(
        $response->baseResponse->getFile()->getPathname()
    );

    try {
        $worksheet = $spreadsheet->getActiveSheet();

        // Lees de 15 kolomkoppen uit de eerste rij.
        $headers = $worksheet->rangeToArray(
            'A1:O1',
            null,
            true,
            false
        )[0];

        expect($headers)->toBe([
            'stamp_id',
            'country_id',
            'stamp_name',
            'issue_year',
            'catalog_id',
            'catalog_name',
            'catalog_abbreviation',
            'catalog_edition_id',
            'edition_name',
            'edition_year',
            'catalog_number',
            'condition_slug',
            'catalog_value',
            'currency',
            'needs_review',
        ]);

        // Een lege cataloguseditie bevat uitsluitend de kopregel.
        expect($worksheet->getHighestRow())->toBe(1);
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

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export.excel', [
            $catalog,
            $edition,
        ])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'.xlsx'
    );

    // Open het daadwerkelijk gegenereerde Excel-bestand.
    $spreadsheet = IOFactory::load(
        $response->baseResponse->getFile()->getPathname()
    );

    try {
        $worksheet = $spreadsheet->getActiveSheet();

        // Eén kopregel en één gegevensregel.
        expect($worksheet->getHighestRow())->toBe(2);

        // Controleer de gegevens van de postzegel.
        expect($worksheet->getCell('A2')->getValue())->toBe($stamp->id);
        expect($worksheet->getCell('K2')->getValue())->toBe('123a');

        // Controleer de cataloguswaarde.
        expect($worksheet->getCell('L2')->getValue())->toBe('postfris');
        expect((float) $worksheet->getCell('M2')->getValue())->toBe(12.50);
        expect($worksheet->getCell('N2')->getValue())->toBe('EUR');
        expect((int) $worksheet->getCell('O2')->getValue())->toBe(0);
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

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export.excel', [
            $catalog,
            $edition,
        ])
    );

    $response->assertOk();

    $spreadsheet = IOFactory::load(
        $response->baseResponse->getFile()->getPathname()
    );

    try {
        $worksheet = $spreadsheet->getActiveSheet();

        // Eén kopregel en twee gegevensregels.
        expect($worksheet->getHighestRow())->toBe(3);

        // Beide regels verwijzen naar dezelfde postzegel.
        expect($worksheet->getCell('A2')->getValue())->toBe($stamp->id);
        expect($worksheet->getCell('A3')->getValue())->toBe($stamp->id);

        // Beide regels bevatten hetzelfde catalogusnummer.
        expect($worksheet->getCell('K2')->getValue())->toBe('789c');
        expect($worksheet->getCell('K3')->getValue())->toBe('789c');

        // Controleer beide waarderingscategorieën.
        expect($worksheet->getCell('L2')->getValue())->toBe('postfris');
        expect($worksheet->getCell('L3')->getValue())->toBe('gestempeld');

        // Controleer de cataloguswaarden.
        expect((float) $worksheet->getCell('M2')->getValue())->toBe(12.50);
        expect((float) $worksheet->getCell('M3')->getValue())->toBe(4.75);

        // Controleer needs_review.
        expect((int) $worksheet->getCell('O2')->getValue())->toBe(0);
        expect((int) $worksheet->getCell('O3')->getValue())->toBe(1);
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

    $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '456b',
    ]);

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export.excel', [
            $catalog,
            $edition,
        ])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'.xlsx'
    );

    $spreadsheet = IOFactory::load(
        $response->baseResponse->getFile()->getPathname()
    );

    try {
        $worksheet = $spreadsheet->getActiveSheet();

        // Eén kopregel en één gegevensregel.
        expect($worksheet->getHighestRow())->toBe(2);

        // Controleer de postzegel en het catalogusnummer.
        expect($worksheet->getCell('A2')->getValue())->toBe($stamp->id);
        expect($worksheet->getCell('K2')->getValue())->toBe('456b');

        // De vier waarderingsvelden moeten leeg zijn.
        foreach (['L2', 'M2', 'N2', 'O2'] as $cell) {
            expect($worksheet->getCell($cell)->getValue())->toBeNull();
        }
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
