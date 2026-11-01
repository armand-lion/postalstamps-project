<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Stamp;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('een cataloguswaarde moet een conditie hebben', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie',
        'edition_year' => 2026,
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-001',
    ]);

    expect(fn () => DB::table('stamp_catalog_values')->insert([
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => null,
        'catalog_value' => 2.50,
        'currency' => 'EUR',
    ]))->toThrow(QueryException::class);
});

test('een catalogusvermelding mag per conditie maar één cataloguswaarde hebben', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus unieke waarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie',
        'edition_year' => 2026,
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-002',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris-test',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Eerste cataloguswaarde voor deze conditie.
    $entry->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 2.50,
        'currency' => 'EUR',
    ]);

    // Een tweede waarde voor dezelfde conditie moet worden geweigerd.
    expect(fn () => $entry->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 3.00,
        'currency' => 'EUR',
    ]))->toThrow(QueryException::class);

    // De oorspronkelijke cataloguswaarde moet behouden blijven.
    expect($entry->values()->count())->toBe(1);
});

test('een conditie met cataloguswaarden mag niet worden verwijderd', function () {
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus foreign key',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie',
        'edition_year' => 2026,
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-003',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris-foreign-key-test',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $entry->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 2.50,
        'currency' => 'EUR',
    ]);

    // De conditie mag niet worden verwijderd zolang
    // er een cataloguswaarde aan gekoppeld is.
    expect(fn () => $condition->delete())
        ->toThrow(QueryException::class);

    // De conditie moet behouden blijven.
    $this->assertDatabaseHas('conditions', [
        'id' => $condition->id,
    ]);

    // De cataloguswaarde moet behouden blijven.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $condition->id,
        'catalog_value' => 2.50,
    ]);
});
