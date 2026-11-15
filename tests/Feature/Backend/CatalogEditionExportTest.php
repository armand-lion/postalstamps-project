<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Stamp;
use App\Models\StampCatalogValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('een niet-ingelogde gebruiker kan geen cataloguseditie exporteren', function () {
    $this->get(
        route('backend.catalogs.editions.export', [
            'catalog' => 999999,
            'edition' => 999999,
        ])
    )->assertRedirect(route('login'));
});

test('een cataloguseditie met cataloguswaarden kan worden geëxporteerd', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus export',
        'abbreviation' => 'TCE',
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
        route('backend.catalogs.editions.export', [$catalog, $edition])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'.csv'
    );

    // Controleer de daadwerkelijke CSV-inhoud.
    ob_start();

    $response->baseResponse->sendContent();

    $csv = ob_get_clean();

    expect($csv)
        ->toContain('stamp_id;country_id;stamp_name')
        ->toContain('123a')
        ->toContain('postfris')
        ->toContain('12.50')
        ->toContain('EUR');
});

test('een cataloguseditie zonder cataloguswaarden kan worden geëxporteerd', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus zonder waarden',
        'abbreviation' => 'TCZ',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie zonder waarden',
        'edition_year' => 2027,
    ]);

    $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '456b',
    ]);

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export', [$catalog, $edition])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'.csv'
    );

    // Lees de daadwerkelijke CSV-inhoud.
    ob_start();

    $response->baseResponse->sendContent();

    $csv = ob_get_clean();

    // Verwijder de UTF-8 BOM.
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);

    // Splits het CSV-bestand in regels.
    $lines = array_values(array_filter(
        preg_split('/\r\n|\r|\n/', trim($csv)),
        fn ($line) => $line !== ''
    ));

    // Eén kopregel en één gegevensregel.
    expect($lines)->toHaveCount(2);

    // Controleer de 15 kolommen.
    $headers = str_getcsv($lines[0], ';', '"', '');
    $values = str_getcsv($lines[1], ';', '"', '');

    expect($headers)->toHaveCount(15)
        ->and($values)->toHaveCount(15)
        ->and($values[10])->toBe('456b')
        ->and($values[11])->toBe('')
        ->and($values[12])->toBe('')
        ->and($values[13])->toBe('')
        ->and($values[14])->toBe('');
});

test('een lege cataloguseditie kan worden geëxporteerd', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus lege export',
        'abbreviation' => 'TCLE',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Lege editie 2028',
        'edition_year' => 2028,
    ]);

    $response = $this->actingAs($user)->get(
        route('backend.catalogs.editions.export', [$catalog, $edition])
    );

    $response->assertOk();

    $response->assertDownload(
        'catalogus-'.$catalog->id.'-editie-'.$edition->id.'.csv'
    );

    // Lees de CSV-inhoud.
    ob_start();

    $response->baseResponse->sendContent();

    $csv = ob_get_clean();

    // Verwijder de UTF-8 BOM.
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);

    // Splits de CSV-inhoud in regels.
    $lines = array_values(array_filter(
        preg_split('/\r\n|\r|\n/', trim($csv)),
        fn ($line) => $line !== ''
    ));

    // Een lege editie bevat uitsluitend de kopregel.
    expect($lines)->toHaveCount(1);

    $headers = str_getcsv($lines[0], ';', '"', '');

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
});

test('een cataloguseditie kan niet via een verkeerde catalogus worden geëxporteerd', function () {
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
            route('backend.catalogs.editions.export', [
                $otherCatalog,
                $edition,
            ])
        )
        ->assertNotFound();
});
