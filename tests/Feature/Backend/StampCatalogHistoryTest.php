<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\User;

test('de backend toont het onderdeel historische cataloguswaarden op de postzegeldetailpagina', function () {
    app()->setLocale('nl');

    // Maak een gebruiker en een postzegel aan.
    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    // Open de detailpagina als ingelogde gebruiker.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamps.show', $stamp));

    // De pagina moet succesvol worden geladen.
    $response->assertOk();

    // De historische cataloguscomponent moet zichtbaar zijn.
    $response->assertSee('Historische cataloguswaarden');

    // Zonder catalogusregistraties verschijnt de lege toestand.
    $response->assertSee('Er zijn nog geen historische cataloguswaarden beschikbaar.');
});

test('de backend toont historische cataloguswaarden uit meerdere edities', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    // Maak één catalogus aan.
    $catalog = Catalog::create([
        'name' => 'Testcatalogus geschiedenis',
        'abbreviation' => 'TEST',
    ]);

    // Maak twee catalogusedities aan.
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
        'currency' => 'EUR',
    ]);

    // Maak de conditie Postfris aan.
    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Koppel de postzegel aan beide edities.
    $entry2025 = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2025->id,
        'catalog_number' => 'TEST-2025',
    ]);

    $entry2026 = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2026->id,
        'catalog_number' => 'TEST-2026',
    ]);

    // Cataloguswaarde 2025.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry2025->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Cataloguswaarde 2026.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry2026->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    // Open de detailpagina.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamps.show', $stamp));

    $response->assertOk();

    // Controleer beide catalogusedities.
    $response->assertSee('Editie 2025');
    $response->assertSee('Editie 2026');

    // Controleer beide cataloguswaarden.
    $response->assertSee('10,00');
    $response->assertSee('15,00');

    // Controleer de absolute waardeverandering.
    $response->assertSee('5,00');

    // Controleer de procentuele waardeverandering.
    $response->assertSee('50,00%');
});

test('de backend vergelijkt geen historische cataloguswaarden met verschillende valuta', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus verschillende valuta',
        'abbreviation' => 'TEST',
    ]);

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

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $entry2025 = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2025->id,
        'catalog_number' => 'TEST-2025',
    ]);

    $entry2026 = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2026->id,
        'catalog_number' => 'TEST-2026',
    ]);

    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry2025->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry2026->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'USD',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamps.show', $stamp));

    $response->assertOk();

    // Beide cataloguswaarden moeten zichtbaar zijn.
    $response->assertSee('10,00');
    $response->assertSee('15,00');

    // Er mag geen procentuele vergelijking plaatsvinden.
    $response->assertDontSee('50,00%');

    // Er mag geen absolute waardeverandering worden weergegeven.
    // Een bedrag van 15,00 mag niet worden verward met een verschil van 5,00.
    $response->assertDontSee('USD 5,00');
});

test('de backend berekent geen waardeverandering wanneer een cataloguswaarde ontbreekt', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus ontbrekende waarden',
        'abbreviation' => 'TEST',
    ]);

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
        'currency' => 'EUR',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $entry2025 = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2025->id,
        'catalog_number' => 'TEST-2025',
    ]);

    $entry2026 = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2026->id,
        'catalog_number' => 'TEST-2026',
    ]);

    // De editie van 2025 heeft een cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry2025->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // De editie van 2026 heeft wel een registratie,
    // maar geen ingevulde cataloguswaarde.
    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry2026->id,
        'condition_id' => $postfris->id,
        'catalog_value' => null,
        'currency' => 'EUR',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamps.show', $stamp));

    $response->assertOk();

    // Beide edities moeten zichtbaar blijven.
    $response->assertSee('Editie 2025');
    $response->assertSee('Editie 2026');

    // De bestaande waarde moet zichtbaar blijven.
    $response->assertSee('EUR');
    $response->assertSee('10,00');

    // Controleer specifiek de editie van 2026.
    // Een ontbrekende cataloguswaarde moet als een streepje verschijnen.
    $html = $response->getContent();

    preg_match(
        '/Editie 2026.*?Postfris.*?<div class="mt-1 font-medium">\s*—\s*<\/div>/s',
        $html,
        $matches
    );

    expect($matches)->not->toBeEmpty();

    // Een echte cataloguswaarde van 0,00 moet zichtbaar blijven.
    StampCatalogValue::query()
        ->where('stamp_catalog_entry_id', $entry2026->id)
        ->where('condition_id', $postfris->id)
        ->update([
            'catalog_value' => 0.00,
        ]);

    // Laad de detailpagina opnieuw met de gewijzigde cataloguswaarde.
    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamps.show', $stamp));

    $response->assertOk();

    // Controleer specifiek de cataloguswaarde van editie 2026.
    $html = $response->getContent();

    preg_match(
        '/Editie 2026.*?Postfris.*?<div class="mt-1 font-medium">\s*EUR\s+0,00\s*<\/div>/s',
        $html,
        $matches
    );

    expect($matches)->not->toBeEmpty();

    // Een echte cataloguswaarde van 0,00 moet een waardedaling opleveren.
    // Van EUR 10,00 naar EUR 0,00 betekent een daling van 100%.
    $response->assertSee('100,00%');

    // Controleer de absolute waardedaling van EUR 10,00.
    $html = $response->getContent();

    expect($html)->toMatch('/−\s*EUR\s+10,00/u');
});

test('de backend berekent geen percentage wanneer de vorige cataloguswaarde nul is', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Testcatalogus nulwaarde',
        'abbreviation' => 'TEST',
    ]);

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
        'currency' => 'EUR',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $entry2025 = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2025->id,
        'catalog_number' => 'TEST-2025',
    ]);

    $entry2026 = StampCatalogEntry::create([
        'stamp_id' => $stamp->id,
        'catalog_edition_id' => $edition2026->id,
        'catalog_number' => 'TEST-2026',
    ]);

    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry2025->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 0.00,
        'currency' => 'EUR',
    ]);

    StampCatalogValue::create([
        'stamp_catalog_entry_id' => $entry2026->id,
        'condition_id' => $postfris->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamps.show', $stamp));

    $response->assertOk();

    // Beide edities moeten zichtbaar zijn.
    $response->assertSee('Editie 2025');
    $response->assertSee('Editie 2026');

    // De absolute waardestijging moet zichtbaar zijn.
    $html = $response->getContent();

    expect($html)->toMatch('/\+\s*EUR\s+10,00/u');

    // Bij een vorige waarde van nul mag geen percentage
    // achter de absolute waardestijging verschijnen.
    expect($html)->not->toMatch(
        '/\+\s*EUR\s+10,00\s*\([^)]*%[^)]*\)/u'
    );
});

test('de backend houdt historische cataloguswaarden van verschillende catalogi gescheiden', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Twee afzonderlijke catalogi.
    $catalogs = [
        'NVPH geschiedenis' => [
            2025 => 10.00,
            2026 => 15.00,
        ],
        'Michel geschiedenis' => [
            2025 => 20.00,
            2026 => 30.00,
        ],
    ];

    foreach ($catalogs as $catalogName => $values) {
        $catalog = Catalog::create([
            'name' => $catalogName,
        ]);

        foreach ($values as $year => $amount) {
            $edition = CatalogEdition::create([
                'catalog_id' => $catalog->id,
                'edition_name' => 'Editie '.$year,
                'edition_year' => $year,
                'currency' => 'EUR',
            ]);

            $entry = StampCatalogEntry::create([
                'stamp_id' => $stamp->id,
                'catalog_edition_id' => $edition->id,
                'catalog_number' => $catalogName.'-'.$year,
            ]);

            StampCatalogValue::create([
                'stamp_catalog_entry_id' => $entry->id,
                'condition_id' => $postfris->id,
                'catalog_value' => $amount,
                'currency' => 'EUR',
            ]);
        }
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamps.show', $stamp));

    $response->assertOk();

    $html = $response->getContent();

    // Controleer of beide catalogi worden weergegeven.
    $response->assertSee('NVPH geschiedenis');
    $response->assertSee('Michel geschiedenis');

    // Splits de historische weergave per catalogus.
    $nvphStart = strpos($html, 'NVPH geschiedenis');
    $michelStart = strpos($html, 'Michel geschiedenis');

    expect($nvphStart)->not->toBeFalse();
    expect($michelStart)->not->toBeFalse();

    // Controleer de waardeveranderingen binnen iedere catalogus.
    // Beide catalogi moeten een stijging van 50% tonen.
    preg_match_all('/50,00%/', $html, $matches);

    expect(count($matches[0]))->toBe(2);

    // NVPH: EUR 10,00 naar EUR 15,00 = + EUR 5,00.
    expect($html)->toMatch('/\+\s*EUR\s+5,00/u');

    // Michel: EUR 20,00 naar EUR 30,00 = + EUR 10,00.
    expect($html)->toMatch('/\+\s*EUR\s+10,00/u');

    // Zoek het begin van de historische cataloguswaarden.
    $historyStart = strpos($html, 'Historische cataloguswaarden');

    expect($historyStart)->not->toBeFalse();

    // Gebruik uitsluitend de HTML vanaf dit onderdeel.
    $historyHtml = substr($html, $historyStart);

    // Zoek beide catalogi binnen de historische component.
    $nvphHistoryStart = strpos($historyHtml, 'NVPH geschiedenis');
    $michelHistoryStart = strpos($historyHtml, 'Michel geschiedenis');

    expect($nvphHistoryStart)->not->toBeFalse();
    expect($michelHistoryStart)->not->toBeFalse();

    // Controleer de volgorde van de catalogi.
    expect($nvphHistoryStart)->toBeLessThan($michelHistoryStart);

    // Isoleer de HTML van beide catalogi.
    $nvphHtml = substr(
        $historyHtml,
        $nvphHistoryStart,
        $michelHistoryStart - $nvphHistoryStart
    );

    $michelHtml = substr($historyHtml, $michelHistoryStart);

    // NVPH moet uitsluitend zijn eigen waardestijging bevatten.
    expect($nvphHtml)->toMatch('/\+\s*EUR\s+5,00/u');
    expect($nvphHtml)->not->toMatch('/\+\s*EUR\s+10,00/u');

    // Michel moet uitsluitend zijn eigen waardestijging bevatten.
    expect($michelHtml)->toMatch('/\+\s*EUR\s+10,00/u');
    expect($michelHtml)->not->toMatch('/\+\s*EUR\s+5,00/u');
});

test('de backend toont historische cataloguswaarden in chronologische volgorde', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Chronologische testcatalogus',
    ]);

    $postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak de edities bewust in een verkeerde volgorde aan.
    $editions = [
        2026 => 20.00,
        2024 => 10.00,
        2025 => 15.00,
    ];

    foreach ($editions as $year => $amount) {
        $edition = CatalogEdition::create([
            'catalog_id' => $catalog->id,
            'edition_name' => 'Editie '.$year,
            'edition_year' => $year,
            'currency' => 'EUR',
        ]);

        $entry = StampCatalogEntry::create([
            'stamp_id' => $stamp->id,
            'catalog_edition_id' => $edition->id,
            'catalog_number' => 'CHRONO-'.$year,
        ]);

        StampCatalogValue::create([
            'stamp_catalog_entry_id' => $entry->id,
            'condition_id' => $postfris->id,
            'catalog_value' => $amount,
            'currency' => 'EUR',
        ]);
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamps.show', $stamp));

    $response->assertOk();

    $html = $response->getContent();

    // Beperk de controle tot de historische component.
    $historyStart = strpos($html, 'Historische cataloguswaarden');

    expect($historyStart)->not->toBeFalse();

    $historyHtml = substr($html, $historyStart);

    // Zoek de drie edities binnen de historische component.
    $edition2024 = strpos($historyHtml, 'Editie 2024');
    $edition2025 = strpos($historyHtml, 'Editie 2025');
    $edition2026 = strpos($historyHtml, 'Editie 2026');

    expect($edition2024)->not->toBeFalse();
    expect($edition2025)->not->toBeFalse();
    expect($edition2026)->not->toBeFalse();

    // Controleer de chronologische volgorde.
    expect($edition2024)->toBeLessThan($edition2025);
    expect($edition2025)->toBeLessThan($edition2026);

    // Controleer de twee opeenvolgende waardestijgingen.
    // 2024 → 2025: EUR 10,00 naar EUR 15,00.
    // 2025 → 2026: EUR 15,00 naar EUR 20,00.
    preg_match_all('/\+\s*EUR\s+5,00/u', $historyHtml, $matches);

    expect(count($matches[0]))->toBe(2);

    // De percentages moeten respectievelijk 50% en 33,33% zijn.
    expect($historyHtml)->toContain('50,00%');
    expect($historyHtml)->toContain('33,33%');
});

test('de backend berekent historische cataloguswaarden per waarderingscategorie', function () {
    app()->setLocale('nl');

    $user = User::factory()->create();
    $stamp = Stamp::factory()->create();

    $catalog = Catalog::create([
        'name' => 'Catalogus waarderingscategorieën',
    ]);

    // Maak drie verschillende waarderingscategorieën aan.
    $conditions = [];

    foreach ([
        'postfris',
        'ongebruikt-met-plakker',
        'gestempeld',
    ] as $index => $slug) {
        $conditions[$slug] = Condition::create([
            'slug' => $slug,
            'sort_order' => $index + 1,
            'is_active' => true,
        ]);
    }

    // Iedere categorie krijgt een andere waardeverandering.
    $editions = [
        2025 => [
            'postfris' => 10.00,
            'ongebruikt-met-plakker' => 20.00,
            'gestempeld' => 40.00,
        ],
        2026 => [
            'postfris' => 15.00,
            'ongebruikt-met-plakker' => 30.00,
            'gestempeld' => 50.00,
        ],
    ];

    foreach ($editions as $year => $values) {
        $edition = CatalogEdition::create([
            'catalog_id' => $catalog->id,
            'edition_name' => 'Editie '.$year,
            'edition_year' => $year,
            'currency' => 'EUR',
        ]);

        // Bewaar het ID van de cataloguseditie uit 2026.
        if ($year === 2026) {
            $edition2026Id = $edition->id;
        }

        $entry = StampCatalogEntry::create([
            'stamp_id' => $stamp->id,
            'catalog_edition_id' => $edition->id,
            'catalog_number' => 'CONDITION-'.$year,
        ]);

        foreach ($values as $slug => $amount) {
            StampCatalogValue::create([
                'stamp_catalog_entry_id' => $entry->id,
                'condition_id' => $conditions[$slug]->id,
                'catalog_value' => $amount,
                'currency' => 'EUR',
            ]);
        }
    }

    $response = $this
        ->actingAs($user)
        ->get(route('backend.stamps.show', $stamp));

    $response->assertOk();

    $html = $response->getContent();

    // Beperk de controle tot de historische component.
    $historyStart = strpos($html, 'Historische cataloguswaarden');

    expect($historyStart)->not->toBeFalse();

    $historyHtml = substr($html, $historyStart);

    // Controleer de drie verschillende absolute waardestijgingen.
    expect($historyHtml)->toMatch('/\+\s*EUR\s+5,00/u');
    expect($historyHtml)->toMatch('/\+\s*EUR\s+10,00/u');

    // Controleer de drie percentages.
    // Postfris: 10 → 15 = +50%.
    // Ongebruikt met plakker: 20 → 30 = +50%.
    // Gestempeld: 40 → 50 = +25%.
    preg_match_all('/50,00%/', $historyHtml, $fiftyPercentMatches);

    expect(count($fiftyPercentMatches[0]))->toBe(2);

    preg_match_all('/25,00%/', $historyHtml, $twentyFivePercentMatches);

    expect(count($twentyFivePercentMatches[0]))->toBe(1);

    // Controleer specifiek de waarderingscategorieën van editie 2026.
    // Zoek de juiste editiekaart via het unieke data-attribuut.
    $edition2026Start = strpos(
        $historyHtml,
        'data-catalog-history-edition="'.$edition2026Id.'"'
    );

    expect($edition2026Start)->not->toBeFalse();

    $edition2026Html = substr($historyHtml, $edition2026Start);

    $edition2026NameStart = strpos($edition2026Html, 'Editie 2026');

    expect($edition2026NameStart)->not->toBeFalse();

    $edition2026Html = substr($edition2026Html, $edition2026NameStart);

    // Zoek de categorieën binnen editie 2026.
    $postfrisStart = strpos($edition2026Html, 'Postfris');
    $plakkerStart = strpos($edition2026Html, 'Ongebruikt met plakker');
    $gestempeldStart = strpos($edition2026Html, 'Gestempeld');

    expect($postfrisStart)->not->toBeFalse();
    expect($plakkerStart)->not->toBeFalse();
    expect($gestempeldStart)->not->toBeFalse();

    // Controleer de volgorde van de categorieën.
    expect($postfrisStart)->toBeLessThan($plakkerStart);
    expect($plakkerStart)->toBeLessThan($gestempeldStart);

    // Isoleer de HTML per waarderingscategorie.
    $postfrisHtml = substr(
        $edition2026Html,
        $postfrisStart,
        $plakkerStart - $postfrisStart
    );

    $plakkerHtml = substr(
        $edition2026Html,
        $plakkerStart,
        $gestempeldStart - $plakkerStart
    );

    $gestempeldHtml = substr($edition2026Html, $gestempeldStart);

    // Postfris: EUR 10,00 → EUR 15,00.
    expect($postfrisHtml)->toMatch('/\+\s*EUR\s+5,00/u');
    expect($postfrisHtml)->toContain('50,00%');
    expect($postfrisHtml)->not->toContain('25,00%');

    // Ongebruikt met plakker: EUR 20,00 → EUR 30,00.
    expect($plakkerHtml)->toMatch('/\+\s*EUR\s+10,00/u');
    expect($plakkerHtml)->toContain('50,00%');
    expect($plakkerHtml)->not->toContain('25,00%');

    // Gestempeld: EUR 40,00 → EUR 50,00.
    expect($gestempeldHtml)->toMatch('/\+\s*EUR\s+10,00/u');
    expect($gestempeldHtml)->toContain('25,00%');
    expect($gestempeldHtml)->not->toContain('50,00%');
});
