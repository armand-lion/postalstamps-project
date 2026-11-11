<?php

use App\Models\Catalog;
use App\Models\CatalogConditionRule;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\User;
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

test('de backend verwijdert een lege cataloguswaarde via catalogusedities', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus verwijderen waarde',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-100',
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

    foreach ([$postfris, $gestempeld] as $index => $condition) {
        CatalogConditionRule::create([
            'catalog_id' => $catalog->id,
            'country_id' => null,
            'condition_id' => $condition->id,
            'issue_year_from' => null,
            'issue_year_to' => null,
            'sort_order' => $index + 1,
        ]);
    }

    $entry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    $entry->values()->create([
        'condition_id' => $gestempeld->id,
        'catalog_value' => 7.50,
        'currency' => 'EUR',
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met verwijderde cataloguswaarde',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-100',
                    'values' => [
                        'postfris' => '',
                        'gestempeld' => '7.50',
                    ],
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('backend.stamps.index'));

    // Postfris moet verwijderd zijn.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
    ]);

    // Gestempeld moet behouden blijven.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 7.50,
    ]);

    // De catalogusvermelding moet blijven bestaan.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $entry->id,
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-100',
    ]);

    // Er mag nog maar één cataloguswaarde bestaan.
    expect($entry->fresh()->values)->toHaveCount(1);
});

test('de backend maakt een nieuwe catalogusvermelding met meerdere waarden aan', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus nieuwe waarden',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'USD',
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

    foreach ([$postfris, $gestempeld] as $index => $condition) {
        CatalogConditionRule::create([
            'catalog_id' => $catalog->id,
            'country_id' => null,
            'condition_id' => $condition->id,
            'issue_year_from' => null,
            'issue_year_to' => null,
            'sort_order' => $index + 1,
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met nieuwe cataloguswaarden',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-200',
                    'values' => [
                        'postfris' => '12.50',
                        'gestempeld' => '6.75',
                    ],
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('backend.stamps.index'));

    // De catalogusvermelding moet zijn aangemaakt.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-200',
    ]);

    $entry = $stamp->fresh()
        ->catalogEntries()
        ->where('catalog_edition_id', $edition->id)
        ->firstOrFail();

    // Postfris moet zijn opgeslagen met de valuta van de editie.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 12.50,
        'currency' => 'USD',
    ]);

    // Gestempeld moet eveneens zijn opgeslagen.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 6.75,
        'currency' => 'USD',
    ]);

    // Er mag maar één catalogusvermelding zijn aangemaakt.
    expect($stamp->fresh()->catalogEntries)->toHaveCount(1);

    // De catalogusvermelding moet precies twee waarden bevatten.
    expect($entry->fresh()->values)->toHaveCount(2);
});

test('de backend werkt bestaande cataloguswaarden via catalogusedities bij', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande waarden',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'USD',
    ]);

    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-300',
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

    foreach ([$postfris, $gestempeld] as $index => $condition) {
        CatalogConditionRule::create([
            'catalog_id' => $catalog->id,
            'country_id' => null,
            'condition_id' => $condition->id,
            'issue_year_from' => null,
            'issue_year_to' => null,
            'sort_order' => $index + 1,
        ]);
    }

    // Bestaande cataloguswaarden.
    $entry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    $entry->values()->create([
        'condition_id' => $gestempeld->id,
        'catalog_value' => 5.00,
        'currency' => 'EUR',
    ]);

    // Werk het catalogusnummer en beide waarden bij.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met bijgewerkte cataloguswaarden',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-300A',
                    'values' => [
                        'postfris' => '12.50',
                        'gestempeld' => '6.75',
                    ],
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('backend.stamps.index'));

    // Het bestaande catalogusnummer moet bijgewerkt zijn.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $entry->id,
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-300A',
    ]);

    // Postfris moet bijgewerkt zijn.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 12.50,
        'currency' => 'USD',
    ]);

    // Gestempeld moet bijgewerkt zijn.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 6.75,
        'currency' => 'USD',
    ]);

    // Er mag geen extra catalogusvermelding ontstaan.
    expect($stamp->fresh()->catalogEntries)->toHaveCount(1);

    // Er moeten precies twee cataloguswaarden blijven bestaan.
    expect($entry->fresh()->values)->toHaveCount(2);
});

test('de backend weigert ongeldige cataloguswaarden via catalogusedities', function (string $invalidValue) {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus ongeldige waarde',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => null,
        'condition_id' => $postfris->id,
        'issue_year_from' => null,
        'issue_year_to' => null,
        'sort_order' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Testcatalogus ongeldige waarde',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-400',
                    'values' => [
                        'postfris' => $invalidValue,
                    ],
                ],
            ],
        ]);

    // Een ongeldige cataloguswaarde moet een validatiefout opleveren.
    $response->assertSessionHasErrors([
        "catalog_editions.{$edition->id}.values.postfris",
    ]);

    // Er mag geen catalogusvermelding zijn aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
    ]);

    // Er mogen geen cataloguswaarden zijn opgeslagen.
    expect(DB::table('stamp_catalog_values')->count())->toBe(0);
})->with([
    'negatieve cataloguswaarde' => ['-2.50'],
    'meer dan twee decimalen' => ['12.345'],
    'niet-numerieke cataloguswaarde' => ['abc'],
]);

test('de backend weigert een niet-toegestane waarderingscategorie via catalogusedities', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus waarderingsregels',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Alleen Postfris is toegestaan voor deze catalogus.
    CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => null,
        'condition_id' => $postfris->id,
        'issue_year_from' => null,
        'issue_year_to' => null,
        'sort_order' => 1,
    ]);

    // Probeer toch een waarde voor Gestempeld op te slaan.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met niet-toegestane waardering',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-500',
                    'values' => [
                        'gestempeld' => '5.00',
                    ],
                ],
            ],
        ]);

    // Gestempeld is niet toegestaan en moet een validatiefout geven.
    $response->assertSessionHasErrors([
        "catalog_editions.{$edition->id}.values.gestempeld",
    ]);

    // Er mag geen catalogusvermelding worden aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
    ]);

    // Er mogen geen cataloguswaarden worden opgeslagen.
    expect(DB::table('stamp_catalog_values')->count())->toBe(0);
});

test('de backend weigert een cataloguswaarde buiten de toegestane uitgiftejaren', function (int $issueYear) {
    app()->setLocale('nl');

    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'issue_year' => $issueYear,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus uitgiftejaren',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Postfris is uitsluitend toegestaan van 2020 t/m 2025.
    CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => null,
        'condition_id' => $postfris->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2025,
        'sort_order' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $issueYear,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel buiten toegestane uitgiftejaren',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-600',
                    'values' => [
                        'postfris' => '10.00',
                    ],
                ],
            ],
        ]);

    // Het uitgiftejaar valt buiten de toegestane periode.
    $response->assertSessionHasErrors([
        "catalog_editions.{$edition->id}.values.postfris",
    ]);

    // Er mag geen catalogusvermelding worden aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
    ]);

    // Er mogen geen cataloguswaarden worden opgeslagen.
    expect(DB::table('stamp_catalog_values')->count())->toBe(0);
})->with([
    'voor het beginjaar (2019)' => [2019],
    'na het eindjaar (2026)' => [2026],
]);

test('de backend accepteert cataloguswaarden op de grensjaren', function (int $issueYear) {
    app()->setLocale('nl');

    $user = User::factory()->create();

    $stamp = Stamp::factory()->create([
        'issue_year' => $issueYear,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus grensjaren',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Postfris is toegestaan van 2020 t/m 2025.
    CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => null,
        'condition_id' => $postfris->id,
        'issue_year_from' => 2020,
        'issue_year_to' => 2025,
        'sort_order' => 1,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $issueYear,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel op grensjaar',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-700',
                    'values' => [
                        'postfris' => '10.00',
                    ],
                ],
            ],
        ]);

    // De invoer moet zonder validatiefouten worden verwerkt.
    $response->assertSessionHasNoErrors();

    // De catalogusvermelding moet zijn aangemaakt.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-700',
    ]);

    // Controleer de opgeslagen cataloguswaarde.
    $entry = DB::table('stamp_catalog_entries')
        ->where('stamp_id', $stamp->id)
        ->where('catalog_edition_id', $edition->id)
        ->first();

    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);
})->with([
    'beginjaar (2020)' => [2020],
    'eindjaar (2025)' => [2025],
]);

test('de backend weigert een cataloguswaarde voor een ander land', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();

    // Maak twee verschillende landen aan.
    $countryA = Country::factory()->create();
    $countryB = Country::factory()->create();

    // De postzegel behoort tot land B.
    $stamp = Stamp::factory()->create([
        'country_id' => $countryB->id,
        'issue_year' => 2024,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus landgebonden waarderingen',
        'abbreviation' => 'TEST',
    ]);

    // Beide landen behoren tot de catalogus.
    $catalog->countries()->attach([
        $countryA->id,
        $countryB->id,
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Postfris is uitsluitend toegestaan voor land A.
    CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => $countryA->id,
        'condition_id' => $postfris->id,
        'issue_year_from' => null,
        'issue_year_to' => null,
        'sort_order' => 1,
    ]);

    // Probeer een Postfris-waarde voor een postzegel uit land B op te slaan.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $countryB->id,
            'issue_year' => 2024,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel uit land B',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-800',
                    'values' => [
                        'postfris' => '10.00',
                    ],
                ],
            ],
        ]);

    // De waarderingsregel geldt niet voor land B.
    $response->assertSessionHasErrors([
        "catalog_editions.{$edition->id}.values.postfris",
    ]);

    // Er mag geen catalogusvermelding worden aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
    ]);

    // Er mogen geen cataloguswaarden worden opgeslagen.
    expect(DB::table('stamp_catalog_values')->count())->toBe(0);
});

test('de backend accepteert een cataloguswaarde voor het juiste land', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();

    // Maak twee verschillende landen aan.
    $countryA = Country::factory()->create();
    $countryB = Country::factory()->create();

    // De postzegel behoort tot land A.
    $stamp = Stamp::factory()->create([
        'country_id' => $countryA->id,
        'issue_year' => 2024,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus juiste land',
        'abbreviation' => 'TEST',
    ]);

    // Beide landen behoren tot de catalogus.
    $catalog->countries()->attach([
        $countryA->id,
        $countryB->id,
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Postfris is uitsluitend toegestaan voor land A.
    CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => $countryA->id,
        'condition_id' => $postfris->id,
        'issue_year_from' => null,
        'issue_year_to' => null,
        'sort_order' => 1,
    ]);

    // Sla een Postfris-waarde op voor een postzegel uit land A.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $countryA->id,
            'issue_year' => 2024,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel uit land A',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-900',
                    'values' => [
                        'postfris' => '10.00',
                    ],
                ],
            ],
        ]);

    // De invoer moet zonder validatiefouten worden verwerkt.
    $response->assertSessionHasNoErrors();

    // De catalogusvermelding moet zijn aangemaakt.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-900',
    ]);

    // Zoek de aangemaakte catalogusvermelding.
    $entry = DB::table('stamp_catalog_entries')
        ->where('stamp_id', $stamp->id)
        ->where('catalog_edition_id', $edition->id)
        ->first();

    // Controleer de opgeslagen cataloguswaarde.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);
});

test('de backend weigert een cataloguseditie van een ander land', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();

    // Maak twee verschillende landen aan.
    $countryA = Country::factory()->create();
    $countryB = Country::factory()->create();

    // De postzegel behoort tot land B.
    $stamp = Stamp::factory()->create([
        'country_id' => $countryB->id,
        'issue_year' => 2024,
    ]);

    $catalog = Catalog::create([
        'name' => 'Testcatalogus verkeerd land',
        'abbreviation' => 'TEST',
    ]);

    // De catalogus is uitsluitend gekoppeld aan land A.
    $catalog->countries()->attach($countryA->id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Probeer de cataloguseditie aan een postzegel uit land B te koppelen.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $countryB->id,
            'issue_year' => 2024,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met verkeerde cataloguseditie',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => 'TEST-1000',
                    'values' => [],
                ],
            ],
        ]);

    // De cataloguseditie behoort niet tot het land van de postzegel.
    $response->assertSessionHasErrors([
        'catalog_editions',
    ]);

    // Er mag geen catalogusvermelding worden aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
    ]);

    // Er mogen geen cataloguswaarden worden opgeslagen.
    expect(DB::table('stamp_catalog_values')->count())->toBe(0);

    // Het land van de postzegel mag niet zijn gewijzigd.
    expect($stamp->fresh()->country_id)->toBe($countryB->id);
});

test('de backend slaat een volledig lege niet-gekoppelde cataloguseditie over', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus lege editie',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Verstuur een volledig lege cataloguseditie.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel zonder cataloguskoppeling',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => '',
                    'values' => [],
                ],
            ],
        ]);

    // Het verzoek moet zonder validatiefouten worden verwerkt.
    $response->assertSessionHasNoErrors();

    // Er mag geen catalogusvermelding zijn aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
    ]);

    // Er mogen geen cataloguswaarden zijn opgeslagen.
    expect(DB::table('stamp_catalog_values')->count())->toBe(0);

    // De postzegel moet gewoon blijven bestaan.
    $this->assertDatabaseHas('stamps', [
        'id' => $stamp->id,
        'country_id' => $stamp->country_id,
    ]);
});

test('de backend weigert cataloguswaarden zonder catalogusnummer bij een nieuwe koppeling', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus zonder catalogusnummer',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Postfris is toegestaan voor deze catalogus.
    CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => null,
        'condition_id' => $postfris->id,
        'issue_year_from' => null,
        'issue_year_to' => null,
        'sort_order' => 1,
    ]);

    // Verstuur een cataloguswaarde zonder catalogusnummer.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel zonder catalogusnummer',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => '',
                    'values' => [
                        'postfris' => '10.00',
                    ],
                ],
            ],
        ]);

    // Een nieuwe koppeling met waarden vereist een catalogusnummer.
    $response->assertSessionHasErrors([
        "catalog_editions.{$edition->id}.catalog_number",
    ]);

    // Er mag geen catalogusvermelding worden aangemaakt.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
    ]);

    // Er mogen geen cataloguswaarden worden opgeslagen.
    expect(DB::table('stamp_catalog_values')->count())->toBe(0);
});

test('de backend behoudt een bestaand catalogusnummer wanneer het veld leeg is', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus nummer behouden',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Bestaande catalogusvermelding.
    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-100',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    CatalogConditionRule::create([
        'catalog_id' => $catalog->id,
        'country_id' => null,
        'condition_id' => $postfris->id,
        'issue_year_from' => null,
        'issue_year_to' => null,
        'sort_order' => 1,
    ]);

    // Bestaande cataloguswaarde.
    $entry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Verstuur een leeg catalogusnummer en een gewijzigde waarde.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met behouden catalogusnummer',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => '',
                    'values' => [
                        'postfris' => '15.00',
                    ],
                ],
            ],
        ]);

    // De invoer moet zonder validatiefouten worden verwerkt.
    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('backend.stamps.index'));

    // Het bestaande catalogusnummer moet behouden blijven.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $entry->id,
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-100',
    ]);

    // De cataloguswaarde moet wel bijgewerkt zijn.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Er mag geen extra catalogusvermelding ontstaan.
    expect($stamp->fresh()->catalogEntries)->toHaveCount(1);

    // Er mag geen dubbele cataloguswaarde ontstaan.
    expect($entry->fresh()->values)->toHaveCount(1);
});

test('de backend behoudt een bestaande cataloguskoppeling bij volledig lege invoer', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus bestaande lege koppeling',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Bestaande cataloguskoppeling zonder cataloguswaarden.
    $entry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-1100',
    ]);

    // Verstuur een volledig lege cataloguseditie.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met behouden cataloguskoppeling',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition->id => [
                    'catalog_number' => '',
                    'values' => [],
                ],
            ],
        ]);

    // De invoer moet zonder validatiefouten worden verwerkt.
    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('backend.stamps.index'));

    // De bestaande cataloguskoppeling moet behouden blijven.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $entry->id,
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition->id,
        'catalog_number' => 'TEST-1100',
    ]);

    // Er mag geen extra cataloguskoppeling ontstaan.
    expect($stamp->fresh()->catalogEntries)->toHaveCount(1);

    // Er mogen geen cataloguswaarden ontstaan.
    expect($entry->fresh()->values)->toHaveCount(0);
});

test('de backend slaat meerdere catalogusedities tegelijk correct op', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus meerdere edities',
        'abbreviation' => 'TEST',
    ]);

    $catalog->countries()->attach($stamp->country_id);

    // Twee verschillende catalogusedities.
    $edition2025 = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    $edition2026 = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'USD',
    ]);

    // Twee waarderingscategorieën.
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

    // Beide waarderingscategorieën zijn toegestaan.
    foreach ([$postfris, $gestempeld] as $index => $condition) {
        CatalogConditionRule::create([
            'catalog_id' => $catalog->id,
            'country_id' => null,
            'condition_id' => $condition->id,
            'issue_year_from' => null,
            'issue_year_to' => null,
            'sort_order' => $index + 1,
        ]);
    }

    // Sla beide catalogusedities in één verzoek op.
    $response = $this
        ->actingAs($user)
        ->put(route('backend.stamps.update', $stamp), [
            'country_id' => $stamp->country_id,
            'issue_year' => $stamp->issue_year,
            'translations' => [
                'nl' => [
                    'name' => 'Postzegel met meerdere catalogusedities',
                    'description' => null,
                ],
            ],
            'catalog_editions' => [
                $edition2025->id => [
                    'catalog_number' => 'TEST-2025',
                    'values' => [
                        'postfris' => '10.00',
                        'gestempeld' => '5.00',
                    ],
                ],
                $edition2026->id => [
                    'catalog_number' => 'TEST-2026',
                    'values' => [
                        'postfris' => '15.00',
                        'gestempeld' => '7.50',
                    ],
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect(route('backend.stamps.index'));

    // Beide catalogusvermeldingen moeten bestaan.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2025->id,
        'catalog_number' => 'TEST-2025',
    ]);

    $this->assertDatabaseHas('stamp_catalog_entries', [
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2026->id,
        'catalog_number' => 'TEST-2026',
    ]);

    // Haal beide opgeslagen catalogusvermeldingen op.
    $entry2025 = $stamp->fresh()->catalogEntries()
        ->where('catalog_edition_id', $edition2025->id)
        ->firstOrFail();

    $entry2026 = $stamp->fresh()->catalogEntries()
        ->where('catalog_edition_id', $edition2026->id)
        ->firstOrFail();

    // Cataloguswaarden van editie 2025.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry2025->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry2025->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 5.00,
        'currency' => 'EUR',
    ]);

    // Cataloguswaarden van editie 2026.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry2026->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'USD',
    ]);

    $this->assertDatabaseHas('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $entry2026->id,
        'condition_id' => $gestempeld->id,
        'catalog_value' => 7.50,
        'currency' => 'USD',
    ]);

    // Precies twee catalogusvermeldingen.
    expect($stamp->fresh()->catalogEntries)->toHaveCount(2);

    // Iedere editie heeft precies twee cataloguswaarden.
    expect($entry2025->fresh()->values)->toHaveCount(2);
    expect($entry2026->fresh()->values)->toHaveCount(2);
});
