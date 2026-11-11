<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCopy;
use App\Models\StampSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('de totale cataloguswaarde houdt rekening met het aantal exemplaren', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus waardeberekening',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stamp = Stamp::factory()->create([
        'name' => 'Testzegel cataloguswaarde',
    ]);

    $catalogEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $catalogEntry->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 4,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return isset($totals['EUR'])
            && abs($totals['EUR'] - 50.00) < 0.001;
    });
});

test('de totale cataloguswaarde houdt rekening met verschillende condities', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus condities',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
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

    $stamp = Stamp::factory()->create([
        'name' => 'Testzegel verschillende condities',
    ]);

    $catalogEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '101',
    ]);

    $catalogEntry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
    ]);

    $catalogEntry->values()->create([
        'condition_id' => $gestempeld->id,
        'catalog_value' => 3.75,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $postfris->id,
        'quantity' => 4,
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $gestempeld->id,
        'quantity' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return isset($totals['EUR'])
            && abs($totals['EUR'] - 61.25) < 0.001;
    });
});

test('ontbrekende cataloguswaarden worden overgeslagen bij de totaalberekening', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende waarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stampWithValue = Stamp::factory()->create([
        'name' => 'Testzegel met cataloguswaarde',
    ]);

    $entryWithValue = $stampWithValue->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $entryWithValue->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampWithValue->id,
        'condition_id' => $condition->id,
        'quantity' => 2,
    ]);

    $stampWithoutValue = Stamp::factory()->create([
        'name' => 'Testzegel zonder cataloguswaarde',
    ]);

    $stampWithoutValue->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '101',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampWithoutValue->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return count($totals) === 1
            && isset($totals['EUR'])
            && abs($totals['EUR'] - 20.00) < 0.001;
    });
});

test('cataloguswaarden in verschillende valuta worden afzonderlijk opgeteld', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus verschillende valuta',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stampEUR = Stamp::factory()->create([
        'name' => 'Testzegel EUR',
    ]);

    $entryEUR = $stampEUR->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $entryEUR->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampEUR->id,
        'condition_id' => $condition->id,
        'quantity' => 2,
    ]);

    $stampUSD = Stamp::factory()->create([
        'name' => 'Testzegel USD',
    ]);

    $entryUSD = $stampUSD->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '101',
    ]);

    $entryUSD->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 8.00,
        'currency' => 'USD',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampUSD->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return count($totals) === 2
            && isset($totals['EUR'], $totals['USD'])
            && abs($totals['EUR'] - 25.00) < 0.001
            && abs($totals['USD'] - 24.00) < 0.001;
    });
});

test('de totale cataloguswaarde bevat alleen exemplaren van de aangemelde gebruiker', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus gebruikers',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stamp = Stamp::factory()->create([
        'name' => 'Testzegel gebruikers',
    ]);

    $catalogEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $catalogEntry->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Twee exemplaren van de aangemelde gebruiker.
    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 2,
    ]);

    // Vijf exemplaren van een andere gebruiker.
    StampCopy::create([
        'user_id' => $otherUser->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 5,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return count($totals) === 1
            && isset($totals['EUR'])
            && abs($totals['EUR'] - 20.00) < 0.001;
    });
});

test('de totale cataloguswaarde houdt rekening met het landfilter', function () {
    $user = User::factory()->create();

    $netherlands = Country::factory()->create();
    $germany = Country::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus landen',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Nederlandse postzegel.
    $stampNL = Stamp::factory()->create([
        'country_id' => $netherlands->id,
        'name' => 'Testzegel Nederland',
    ]);

    $entryNL = $stampNL->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $entryNL->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampNL->id,
        'condition_id' => $condition->id,
        'quantity' => 2,
    ]);

    // Duitse postzegel.
    $stampDE = Stamp::factory()->create([
        'country_id' => $germany->id,
        'name' => 'Testzegel Duitsland',
    ]);

    $entryDE = $stampDE->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '101',
    ]);

    $entryDE->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampDE->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
    ]);

    // Alleen Nederlandse postzegels selecteren.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
            'country_id' => $netherlands->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return count($totals) === 1
            && isset($totals['EUR'])
            && abs($totals['EUR'] - 20.00) < 0.001;
    });
});

test('de totale cataloguswaarde houdt rekening met het conditiefilter', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus conditiefilter',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
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

    $stamp = Stamp::factory()->create([
        'name' => 'Testzegel conditiefilter',
    ]);

    $catalogEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $catalogEntry->values()->create([
        'condition_id' => $postfris->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
    ]);

    $catalogEntry->values()->create([
        'condition_id' => $gestempeld->id,
        'catalog_value' => 3.75,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $postfris->id,
        'quantity' => 4,
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $gestempeld->id,
        'quantity' => 3,
    ]);

    // Alleen gestempelde exemplaren selecteren.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
            'condition_id' => $gestempeld->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return count($totals) === 1
            && isset($totals['EUR'])
            && abs($totals['EUR'] - 11.25) < 0.001;
    });
});

test('de totale cataloguswaarde gebruikt uitsluitend de geselecteerde cataloguseditie', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus verschillende edities',
    ]);

    $edition2025 = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    $edition2026 = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stamp = Stamp::factory()->create([
        'name' => 'Testzegel verschillende catalogusedities',
    ]);

    // Cataloguswaarde voor editie 2025.
    $entry2025 = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition2025->id,
        'catalog_number' => '100',
    ]);

    $entry2025->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Cataloguswaarde voor editie 2026.
    $entry2026 = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition2026->id,
        'catalog_number' => '100',
    ]);

    $entry2026->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
    ]);

    // Selecteer cataloguseditie 2026.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition2026->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return count($totals) === 1
            && isset($totals['EUR'])
            && abs($totals['EUR'] - 45.00) < 0.001;
    });
});

test('een cataloguswaarde van nul wordt correct meegenomen in de totaalberekening', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus nulwaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Postzegel met cataloguswaarde € 0,00.
    $stampZero = Stamp::factory()->create([
        'name' => 'Testzegel nulwaarde',
    ]);

    $entryZero = $stampZero->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $entryZero->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampZero->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
    ]);

    // Postzegel met cataloguswaarde € 12,50.
    $stampValue = Stamp::factory()->create([
        'name' => 'Testzegel met waarde',
    ]);

    $entryValue = $stampValue->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '101',
    ]);

    $entryValue->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 12.50,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stampValue->id,
        'condition_id' => $condition->id,
        'quantity' => 2,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return count($totals) === 1
            && isset($totals['EUR'])
            && abs($totals['EUR'] - 25.00) < 0.001;
    });
});

test('uitsluitend cataloguswaarden van nul blijven zichtbaar in de totaalberekening', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus uitsluitend nulwaarden',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $stamp = Stamp::factory()->create([
        'name' => 'Testzegel uitsluitend nulwaarde',
    ]);

    $catalogEntry = $stamp->catalogEntries()->create([
        'catalog_edition_id' => $edition->id,
        'catalog_number' => '100',
    ]);

    $catalogEntry->values()->create([
        'condition_id' => $condition->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
    ]);

    StampCopy::create([
        'user_id' => $user->id,
        'stamp_id' => $stamp->id,
        'condition_id' => $condition->id,
        'quantity' => 3,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('totalCatalogValues', function ($totals) {
        return count($totals) === 1
            && array_key_exists('EUR', $totals)
            && abs($totals['EUR']) < 0.001;
    });
});

test('de cataloguswaarden van postzegels binnen dezelfde serie worden opgeteld', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus seriewaardering',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $country = Country::create([
        'name' => 'Testland seriewaardering',
        'slug' => 'testland-seriewaardering',
        'type' => 'country',
    ]);

    $series = StampSeries::create([
        'country_id' => $country->id,
        'name' => 'Testserie persoonlijke waardering',
        'issue_year' => 2026,
    ]);

    foreach ([
        ['name' => 'Seriezegel 1', 'value' => 10.00, 'quantity' => 2],
        ['name' => 'Seriezegel 2', 'value' => 15.00, 'quantity' => 3],
    ] as $index => $data) {
        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
            'stamp_series_id' => $series->id,
            'name' => $data['name'],
        ]);

        $entry = $stamp->catalogEntries()->create([
            'catalog_edition_id' => $edition->id,
            'catalog_number' => (string) (100 + $index),
        ]);

        $entry->values()->create([
            'condition_id' => $condition->id,
            'catalog_value' => $data['value'],
            'currency' => 'EUR',
        ]);

        StampCopy::create([
            'user_id' => $user->id,
            'stamp_id' => $stamp->id,
            'condition_id' => $condition->id,
            'quantity' => $data['quantity'],
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('seriesCatalogValues', function ($totals) use ($series) {
        return isset($totals[$series->id]['currencies']['EUR'])
            && abs($totals[$series->id]['currencies']['EUR'] - 65.00) < 0.001;
    });

    $response->assertSee('Testserie persoonlijke waardering');
    $response->assertSee('65,00 EUR');
});

test('de cataloguswaarden van postzegels binnen hetzelfde land worden opgeteld', function () {
    $user = User::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus landwaardering',
    ]);

    $edition = CatalogEdition::create([
        'catalog_id' => $catalog->id,
        'edition_name' => 'Testeditie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $condition = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $country = Country::create([
        'name' => 'Testland cataloguswaarde',
        'slug' => 'testland-cataloguswaarde',
        'entity_type' => 'country',
    ]);

    foreach ([
        ['name' => 'Landzegel 1', 'value' => 12.50, 'quantity' => 2],
        ['name' => 'Landzegel 2', 'value' => 7.50, 'quantity' => 3],
    ] as $index => $data) {
        $stamp = Stamp::factory()->create([
            'country_id' => $country->id,
            'name' => $data['name'],
        ]);

        $entry = $stamp->catalogEntries()->create([
            'catalog_edition_id' => $edition->id,
            'catalog_number' => (string) (200 + $index),
        ]);

        $entry->values()->create([
            'condition_id' => $condition->id,
            'catalog_value' => $data['value'],
            'currency' => 'EUR',
        ]);

        StampCopy::create([
            'user_id' => $user->id,
            'stamp_id' => $stamp->id,
            'condition_id' => $condition->id,
            'quantity' => $data['quantity'],
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamp-copies.index', [
            'catalog_edition_id' => $edition->id,
        ]));

    $response->assertOk();

    $response->assertViewHas('countryCatalogValues', function ($totals) use ($country) {
        return isset($totals[$country->id]['currencies']['EUR'])
            && abs($totals[$country->id]['currencies']['EUR'] - 47.50) < 0.001;
    });

    $response->assertSee('Testland cataloguswaarde');
    $response->assertSee('47,50 EUR');
});
