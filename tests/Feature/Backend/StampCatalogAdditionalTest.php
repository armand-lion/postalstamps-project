<?php

use App\Models\Catalog;
use App\Models\CatalogConditionRule;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->setLocale('nl');

    // Gebruiker.
    $this->user = User::factory()->create();

    // Postzegel en landen.
    $this->stamp = Stamp::factory()->create([
        'issue_year' => 2020,
    ]);

    $this->country = $this->stamp->country;

    $this->otherCountry = Country::factory()->create();

    // Waarderingscategorieën.
    $this->postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Catalogus A: uitsluitend het oorspronkelijke land.
    $this->catalogA = Catalog::create([
        'name' => 'Testcatalogus A',
        'abbreviation' => 'TCA',
    ]);

    $this->catalogA->countries()->attach($this->country->id);

    // Catalogus B: beide landen.
    $this->catalogB = Catalog::create([
        'name' => 'Testcatalogus B',
        'abbreviation' => 'TCB',
    ]);

    $this->catalogB->countries()->attach([
        $this->country->id,
        $this->otherCountry->id,
    ]);

    // Catalogus C: uitsluitend het andere land.
    $this->catalogC = Catalog::create([
        'name' => 'Testcatalogus C',
        'abbreviation' => 'TCC',
    ]);

    $this->catalogC->countries()->attach($this->otherCountry->id);

    // Catalogusedities.
    $this->editionA1 = CatalogEdition::create([
        'catalog_id' => $this->catalogA->id,
        'edition_name' => 'Editie 2025',
        'edition_year' => 2025,
        'currency' => 'EUR',
    ]);

    $this->editionA2 = CatalogEdition::create([
        'catalog_id' => $this->catalogA->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    $this->editionB1 = CatalogEdition::create([
        'catalog_id' => $this->catalogB->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'USD',
    ]);

    $this->editionC1 = CatalogEdition::create([
        'catalog_id' => $this->catalogC->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'GBP',
    ]);

    // Waarderingsregels voor alle catalogi.
    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        foreach ([
            $this->postfris,
            $this->gestempeld,
        ] as $index => $condition) {
            CatalogConditionRule::create([
                'catalog_id' => $catalog->id,
                'country_id' => null,
                'condition_id' => $condition->id,
                'issue_year_from' => null,
                'issue_year_to' => null,
                'sort_order' => $index + 1,
            ]);
        }
    }

    // Bestaande catalogusvermeldingen.
    $this->entryA1 = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionA1->id,
        'catalog_number' => 'A-100',
    ]);

    $this->entryA2 = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionA2->id,
        'catalog_number' => 'A-200',
    ]);

    $this->entryB1 = $this->stamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionB1->id,
        'catalog_number' => 'B-100',
    ]);

    // Cataloguswaarden editie A1.
    $this->valueA1Postfris = $this->entryA1->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
    ]);

    $this->valueA1Gestempeld = $this->entryA1->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 7.50,
        'currency' => 'EUR',
    ]);

    // Cataloguswaarden editie A2.
    $this->valueA2Postfris = $this->entryA2->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 20.00,
        'currency' => 'EUR',
    ]);

    $this->valueA2Gestempeld = $this->entryA2->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
    ]);

    // Cataloguswaarden editie B1.
    $this->valueB1Postfris = $this->entryB1->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 25.00,
        'currency' => 'USD',
    ]);

    $this->valueB1Gestempeld = $this->entryB1->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 12.50,
        'currency' => 'USD',
    ]);

    // Standaardformuliergegevens voor het wijzigen van een postzegel.
    $this->payload = [
        'country_id' => $this->country->id,
        'issue_year' => $this->stamp->issue_year,
        'translations' => [
            'nl' => [
                'name' => 'Testpostzegel aanvullende catalogustests',
                'description' => null,
            ],
        ],
    ];

    // Authenticeer de gebruiker.
    $this->actingAs($this->user);
});

test('de backend verwijdert een ongeldige cataloguskoppeling bij een landwijziging', function () {
    // Verwijder editie A2 uit de uitgangssituatie van deze test.
    foreach ($this->entryA2->values()->get() as $value) {
        $value->delete();
    }

    $this->entryA2->delete();

    // Bewaar de volledige oorspronkelijke gegevens van de geldige koppeling.
    $originalEntryB1 = $this->entryB1->fresh()->getAttributes();

    $originalValueB1Postfris = $this->valueB1Postfris
        ->fresh()
        ->getAttributes();

    $originalValueB1Gestempeld = $this->valueB1Gestempeld
        ->fresh()
        ->getAttributes();

    // Controleer de uitgangssituatie.
    expect($this->stamp->fresh()->country_id)
        ->toBe($this->country->id);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(2);

    expect($this->entryA1->values()->count())
        ->toBe(2);

    expect($this->entryB1->values()->count())
        ->toBe(2);

    // Wijzig uitsluitend het land van de postzegel.
    $payload = $this->payload;

    $payload['country_id'] = $this->otherCountry->id;

    $response = $this->put(
        route('backend.stamps.update', $this->stamp),
        $payload
    );

    // Controleer de HTTP-response.
    $response->assertSessionHasNoErrors();

    $response->assertRedirect(
        route('backend.stamps.index')
    );

    $response->assertSessionHas('success');

    // Controleer of de landwijziging is opgeslagen.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->otherCountry->id,
    ]);

    // De ongeldige catalogusvermelding moet verwijderd zijn.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $this->entryA1->id,
    ]);

    // Beide bijbehorende cataloguswaarden moeten verwijderd zijn.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $this->valueA1Postfris->id,
    ]);

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $this->valueA1Gestempeld->id,
    ]);

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $this->entryA1->id,
    ]);

    // De geldige catalogusvermelding moet volledig behouden blijven.
    expect($this->entryB1->fresh()->getAttributes())
        ->toBe($originalEntryB1);

    // Beide geldige cataloguswaarden moeten volledig behouden blijven.
    expect($this->valueB1Postfris->fresh()->getAttributes())
        ->toBe($originalValueB1Postfris);

    expect($this->valueB1Gestempeld->fresh()->getAttributes())
        ->toBe($originalValueB1Gestempeld);

    // Er mag uitsluitend één geldige catalogusvermelding overblijven.
    expect($this->stamp->catalogEntries()->count())
        ->toBe(1);

    expect($this->entryB1->values()->count())
        ->toBe(2);

    // Er mag niet automatisch een nieuwe cataloguskoppeling ontstaan.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    // Controleer de totale aantallen.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);

    $this->assertDatabaseCount('stamp_catalog_values', 2);
});

test('de backend verwijdert meerdere ongeldige cataloguskoppelingen bij een landwijziging', function () {
    // Bewaar de volledige oorspronkelijke gegevens van de geldige koppeling.
    $originalEntryB1 = $this->entryB1->fresh()->getAttributes();

    $originalValueB1Postfris = $this->valueB1Postfris
        ->fresh()
        ->getAttributes();

    $originalValueB1Gestempeld = $this->valueB1Gestempeld
        ->fresh()
        ->getAttributes();

    // Controleer de uitgangssituatie.
    expect($this->stamp->fresh()->country_id)
        ->toBe($this->country->id);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    // Wijzig het land van de postzegel.
    $payload = $this->payload;

    $payload['country_id'] = $this->otherCountry->id;

    $response = $this->put(
        route('backend.stamps.update', $this->stamp),
        $payload
    );

    // Controleer de HTTP-response.
    $response->assertSessionHasNoErrors();

    $response->assertRedirect(
        route('backend.stamps.index')
    );

    $response->assertSessionHas('success');

    // Controleer of de landwijziging is opgeslagen.
    $this->assertDatabaseHas('stamps', [
        'id' => $this->stamp->id,
        'country_id' => $this->otherCountry->id,
    ]);

    // Beide ongeldige catalogusvermeldingen moeten verwijderd zijn.
    foreach ([
        $this->entryA1,
        $this->entryA2,
    ] as $entry) {
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $entry->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $entry->id,
        ]);
    }

    // Alle vier ongeldige cataloguswaarden moeten verwijderd zijn.
    foreach ([
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
    ] as $value) {
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $value->id,
        ]);
    }

    // De geldige catalogusvermelding moet volledig behouden blijven.
    expect($this->entryB1->fresh()->getAttributes())
        ->toBe($originalEntryB1);

    // De geldige cataloguswaarden moeten volledig behouden blijven.
    expect($this->valueB1Postfris->fresh()->getAttributes())
        ->toBe($originalValueB1Postfris);

    expect($this->valueB1Gestempeld->fresh()->getAttributes())
        ->toBe($originalValueB1Gestempeld);

    // Controleer de resterende koppelingen.
    expect($this->stamp->catalogEntries()->count())
        ->toBe(1);

    expect($this->entryB1->values()->count())
        ->toBe(2);

    // Er mag geen automatische koppeling met catalogus C ontstaan.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    // De catalogi zelf moeten behouden blijven.
    $this->assertDatabaseCount('catalogs', 3);

    // Alle catalogusedities moeten behouden blijven.
    $this->assertDatabaseCount('catalog_editions', 4);

    // De waarderingscategorieën en waarderingsregels blijven behouden.
    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    // Controleer de definitieve aantallen.
    $this->assertDatabaseCount('stamp_catalog_entries', 1);

    $this->assertDatabaseCount('stamp_catalog_values', 2);
});

test('de backend behoudt alle geldige cataloguskoppelingen bij een landwijziging', function () {
    // Maak catalogus A ook geldig voor het andere land.
    $this->catalogA->countries()->attach(
        $this->otherCountry->id
    );

    // Verzamel alle bestaande catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    // Bewaar de volledige oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de volledige oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    expect($this->stamp->fresh()->country_id)
        ->toBe($this->country->id);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    // Controleer of beide catalogi geldig zijn voor het nieuwe land.
    foreach ([
        $this->catalogA,
        $this->catalogB,
    ] as $catalog) {
        expect(
            $catalog->countries()
                ->where('countries.id', $this->otherCountry->id)
                ->exists()
        )->toBeTrue();
    }

    // Wijzig het land van de postzegel.
    $payload = $this->payload;

    $payload['country_id'] = $this->otherCountry->id;

    // Verplaats de tijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // De landwijziging moet succesvol zijn opgeslagen.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // Alle catalogusvermeldingen moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Alle cataloguswaarden moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Er mag geen automatische koppeling met catalogus C ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Alle oorspronkelijke catalogusgegevens moeten behouden blijven.
        expect($this->stamp->catalogEntries()->count())
            ->toBe(3);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend verwijdert een bestaande cataloguswaarde via route B', function () {
    // Bewaar de oorspronkelijke catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Verzamel alle cataloguswaarden die behouden moeten blijven.
    $preservedValues = [
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($preservedValues as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    expect($this->entryA1->values()->count())
        ->toBe(2);

    expect($this->valueA1Postfris->fresh()->catalog_value)
        ->toBe('15.00');

    // Stel de formuliergegevens voor route B samen.
    $payload = $this->payload;

    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-100';

    $payload['catalog_values'] = [
        'postfris' => '',
    ];

    // Verplaats de tijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // De postfrisse cataloguswaarde moet verwijderd zijn.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueA1Postfris->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryA1->id,
            'condition_id' => $this->postfris->id,
        ]);

        // Alle catalogusvermeldingen moeten volledig behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);
        }

        // Alle overige cataloguswaarden moeten ongewijzigd blijven.
        foreach ($preservedValues as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de resterende waarden per cataloguseditie.
        expect($this->entryA1->values()->count())
            ->toBe(1);

        expect($this->entryA2->values()->count())
            ->toBe(2);

        expect($this->entryB1->values()->count())
            ->toBe(2);

        // Er mogen geen nieuwe cataloguskoppelingen ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Het land van de postzegel moet behouden blijven.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        // Controleer de definitieve aantallen.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend verwerkt een ontbrekende cataloguswaarde via route B zonder wijzigingen', function () {
    // Verwijder de postfrisse waarde tijdens de testvoorbereiding.
    $this->valueA1Postfris->delete();

    // Verzamel alle bestaande catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    // Bewaar de volledige oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Verzamel alle resterende cataloguswaarden.
    $values = [
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    // Bewaar de volledige oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 5);

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $this->entryA1->id,
        'condition_id' => $this->postfris->id,
    ]);

    expect($this->entryA1->values()->count())
        ->toBe(1);

    // Stel de formuliergegevens voor route B samen.
    $payload = $this->payload;

    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-100';

    $payload['catalog_values'] = [
        'postfris' => '',
    ];

    // Verplaats de tijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // De ontbrekende cataloguswaarde mag niet worden aangemaakt.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryA1->id,
            'condition_id' => $this->postfris->id,
        ]);

        // De eerder verwijderde cataloguswaarde mag niet terugkeren.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueA1Postfris->id,
        ]);

        // Alle catalogusvermeldingen moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);
        }

        // Alle resterende cataloguswaarden moeten ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de aantallen per cataloguseditie.
        expect($this->entryA1->values()->count())
            ->toBe(1);

        expect($this->entryA2->values()->count())
            ->toBe(2);

        expect($this->entryB1->values()->count())
            ->toBe(2);

        // Er mogen geen nieuwe cataloguskoppelingen ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Het land van de postzegel moet behouden blijven.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        // Controleer de definitieve aantallen.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend slaat een cataloguswaarde van nul correct op via route B', function () {
    // Zorg dat de oorspronkelijke cataloguswaarde gecontroleerd is.
    $this->valueA1Postfris->update([
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke cataloguswaarde.
    $originalValue = $this->valueA1Postfris
        ->fresh()
        ->getAttributes();

    // Verzamel alle bestaande catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Verzamel alle cataloguswaarden die ongewijzigd moeten blijven.
    $preservedValues = [
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($preservedValues as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    expect($this->valueA1Postfris->fresh()->catalog_value)
        ->toBe('15.00');

    expect($this->valueA1Postfris->fresh()->needs_review)
        ->toBeFalse();

    // Stel de formuliergegevens voor route B samen.
    $payload = $this->payload;

    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-100';

    $payload['catalog_values'] = [
        'postfris' => '0.00',
    ];

    // Verplaats de tijd om timestampwijzigingen te controleren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // De bestaande cataloguswaarde moet behouden blijven.
        $freshValue = $this->valueA1Postfris->fresh();

        expect($freshValue)->not->toBeNull();

        expect($freshValue->id)
            ->toBe($this->valueA1Postfris->id);

        expect($freshValue->catalog_value)
            ->toBe('0.00');

        expect($freshValue->currency)
            ->toBe('EUR');

        expect($freshValue->needs_review)
            ->toBeTrue();

        // Controleer de opgeslagen databasegegevens.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueA1Postfris->id,
            'stamp_catalog_entry_id' => $this->entryA1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 0.00,
            'currency' => 'EUR',
            'needs_review' => true,
        ]);

        // De aanmaakdatum moet behouden blijven.
        expect($freshValue->getRawOriginal('created_at'))
            ->toBe($originalValue['created_at']);

        // De wijzigingsdatum moet zijn bijgewerkt.
        expect($freshValue->getRawOriginal('updated_at'))
            ->not->toBe($originalValue['updated_at']);

        // Alleen de waarde, controlemarkering en wijzigingsdatum
        // mogen zijn gewijzigd.
        $expectedValue = $originalValue;

        $expectedValue['catalog_value'] =
            $freshValue->getRawOriginal('catalog_value');

        $expectedValue['needs_review'] =
            $freshValue->getRawOriginal('needs_review');

        $expectedValue['updated_at'] =
            $freshValue->getRawOriginal('updated_at');

        expect($freshValue->getAttributes())
            ->toBe($expectedValue);

        // Alle catalogusvermeldingen moeten ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);
        }

        // Alle overige cataloguswaarden moeten ongewijzigd blijven.
        foreach ($preservedValues as $value) {
            $preservedValue = $value->fresh();

            expect($preservedValue)->not->toBeNull();

            expect($preservedValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de aantallen per cataloguseditie.
        expect($this->entryA1->values()->count())
            ->toBe(2);

        expect($this->entryA2->values()->count())
            ->toBe(2);

        expect($this->entryB1->values()->count())
            ->toBe(2);

        // Er mogen geen nieuwe cataloguskoppelingen ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Het land van de postzegel moet behouden blijven.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        // Controleer de definitieve aantallen.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend behoudt bestaande cataloguswaarden bij lege invoer via route C', function () {
    // Verzamel alle bestaande catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    // Bewaar de volledige oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    // Bewaar de volledige oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    expect($this->valueA1Postfris->fresh()->catalog_value)
        ->toBe('15.00');

    expect($this->valueA1Gestempeld->fresh()->catalog_value)
        ->toBe('7.50');

    // Stel de formuliergegevens voor route C samen.
    $payload = $this->payload;

    $payload['catalog_entries'] = [
        $this->entryA1->id => [
            'catalog_number' => 'A-100',
            'values' => [
                'postfris' => '',
                'gestempeld' => null,
            ],
        ],
    ];

    // Verplaats de tijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Beide cataloguswaarden van editie A1 moeten behouden blijven.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueA1Postfris->id,
            'stamp_catalog_entry_id' => $this->entryA1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 15.00,
            'currency' => 'EUR',
        ]);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueA1Gestempeld->id,
            'stamp_catalog_entry_id' => $this->entryA1->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 7.50,
            'currency' => 'EUR',
        ]);

        // Alle catalogusvermeldingen moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);
        }

        // Alle cataloguswaarden moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de aantallen per cataloguseditie.
        expect($this->entryA1->values()->count())
            ->toBe(2);

        expect($this->entryA2->values()->count())
            ->toBe(2);

        expect($this->entryB1->values()->count())
            ->toBe(2);

        // Er mogen geen nieuwe cataloguskoppelingen ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Het land van de postzegel moet behouden blijven.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        // Controleer de definitieve aantallen.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend voegt een ontbrekende cataloguswaarde toe via route C', function () {
    // Verwijder de postfrisse waarde tijdens de testvoorbereiding.
    $this->valueB1Postfris->delete();

    // Verzamel alle bestaande catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    // Bewaar de volledige oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Verzamel alle cataloguswaarden die behouden moeten blijven.
    $preservedValues = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Gestempeld,
    ];

    // Bewaar de volledige oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($preservedValues as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 5);

    expect($this->entryB1->values()->count())
        ->toBe(1);

    expect($this->editionB1->fresh()->currency)
        ->toBe('USD');

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
    ]);

    // Stel de formuliergegevens voor route C samen.
    $payload = $this->payload;

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-100',
            'values' => [
                'postfris' => '32.75',
            ],
        ],
    ];

    // Verplaats de tijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Haal de nieuw aangemaakte cataloguswaarde op.
        $newValue = $this->entryB1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Controleer de nieuwe cataloguswaarde.
        expect($newValue->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($newValue->condition_id)
            ->toBe($this->postfris->id);

        expect($newValue->catalog_value)
            ->toBe('32.75');

        expect($newValue->currency)
            ->toBe('USD');

        // Controleer de opgeslagen databasegegevens.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $newValue->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 32.75,
            'currency' => 'USD',
        ]);

        // Er mag slechts één postfrisse waarde voor editie B1 bestaan.
        expect(
            $this->entryB1->values()
                ->where('condition_id', $this->postfris->id)
                ->count()
        )->toBe(1);

        // Alle catalogusvermeldingen moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);
        }

        // Alle bestaande cataloguswaarden moeten ongewijzigd blijven.
        foreach ($preservedValues as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de aantallen per cataloguseditie.
        expect($this->entryA1->values()->count())
            ->toBe(2);

        expect($this->entryA2->values()->count())
            ->toBe(2);

        expect($this->entryB1->values()->count())
            ->toBe(2);

        // Er mogen geen nieuwe cataloguskoppelingen ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Het land van de postzegel moet behouden blijven.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        // Controleer de definitieve aantallen.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend verwerkt dezelfde cataloguseditie correct via routes B C en D', function () {
    // Zorg dat beide oorspronkelijke cataloguswaarden gecontroleerd zijn.
    $this->valueA1Postfris->update([
        'needs_review' => false,
    ]);

    $this->valueA1Gestempeld->update([
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke gegevens van editie A1.
    $originalEntryA1 = $this->entryA1
        ->fresh()
        ->getAttributes();

    $originalValueA1Postfris = $this->valueA1Postfris
        ->fresh()
        ->getAttributes();

    $originalValueA1Gestempeld = $this->valueA1Gestempeld
        ->fresh()
        ->getAttributes();

    // Verzamel de catalogusvermeldingen die behouden moeten blijven.
    $preservedEntries = [
        $this->entryA2,
        $this->entryB1,
    ];

    $originalEntries = [];

    foreach ($preservedEntries as $entry) {
        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Verzamel de cataloguswaarden die behouden moeten blijven.
    $preservedValues = [
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($preservedValues as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    expect($this->entryA1->fresh()->catalog_number)
        ->toBe('A-100');

    expect($this->valueA1Postfris->fresh()->catalog_value)
        ->toBe('15.00');

    expect($this->valueA1Gestempeld->fresh()->catalog_value)
        ->toBe('7.50');

    expect($this->valueA1Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($this->valueA1Gestempeld->fresh()->needs_review)
        ->toBeFalse();

    // Stel de gezamenlijke formuliergegevens samen.
    $payload = $this->payload;

    // Route B: oorspronkelijke invoermethode.
    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-101';

    $payload['catalog_values'] = [
        'postfris' => '16.00',
        'gestempeld' => '8.00',
    ];

    // Route C: bestaande catalogusvermeldingen.
    $payload['catalog_entries'] = [
        $this->entryA1->id => [
            'catalog_number' => 'A-102',
            'values' => [
                'postfris' => '17.00',
                'gestempeld' => '9.00',
            ],
        ],
    ];

    // Route D: verwerking per cataloguseditie.
    $payload['catalog_editions'] = [
        $this->editionA1->id => [
            'catalog_number' => 'A-103',
            'values' => [
                'postfris' => '18.00',
                'gestempeld' => '10.00',
            ],
        ],
    ];

    // Verplaats de tijd om timestampwijzigingen te controleren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Haal de definitieve catalogusvermelding op.
        $freshEntryA1 = $this->entryA1->fresh();

        expect($freshEntryA1)->not->toBeNull();

        expect($freshEntryA1->id)
            ->toBe($this->entryA1->id);

        expect($freshEntryA1->catalog_number)
            ->toBe('A-103');

        // De aanmaakdatum moet behouden blijven.
        expect($freshEntryA1->getRawOriginal('created_at'))
            ->toBe($originalEntryA1['created_at']);

        // De wijzigingsdatum moet zijn bijgewerkt.
        expect($freshEntryA1->getRawOriginal('updated_at'))
            ->not->toBe($originalEntryA1['updated_at']);

        // Alleen het catalogusnummer en de wijzigingsdatum
        // mogen zijn gewijzigd.
        $expectedEntryA1 = $originalEntryA1;

        $expectedEntryA1['catalog_number'] = 'A-103';

        $expectedEntryA1['updated_at'] =
            $freshEntryA1->getRawOriginal('updated_at');

        expect($freshEntryA1->getAttributes())
            ->toBe($expectedEntryA1);

        // Haal beide definitieve cataloguswaarden op.
        $freshPostfris = $this->valueA1Postfris->fresh();

        $freshGestempeld = $this->valueA1Gestempeld->fresh();

        expect($freshPostfris)->not->toBeNull();

        expect($freshGestempeld)->not->toBeNull();

        // Controleer de definitieve postfrisse cataloguswaarde.
        expect($freshPostfris->id)
            ->toBe($this->valueA1Postfris->id);

        expect($freshPostfris->catalog_value)
            ->toBe('18.00');

        expect($freshPostfris->currency)
            ->toBe('EUR');

        expect($freshPostfris->needs_review)
            ->toBeTrue();

        // Controleer de definitieve gestempelde cataloguswaarde.
        expect($freshGestempeld->id)
            ->toBe($this->valueA1Gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('10.00');

        expect($freshGestempeld->currency)
            ->toBe('EUR');

        expect($freshGestempeld->needs_review)
            ->toBeTrue();

        // Controleer welke attributen gewijzigd mogen zijn.
        foreach ([
            [$freshPostfris, $originalValueA1Postfris],
            [$freshGestempeld, $originalValueA1Gestempeld],
        ] as [$freshValue, $originalValue]) {
            expect($freshValue->getRawOriginal('created_at'))
                ->toBe($originalValue['created_at']);

            expect($freshValue->getRawOriginal('updated_at'))
                ->not->toBe($originalValue['updated_at']);

            $expectedValue = $originalValue;

            $expectedValue['catalog_value'] =
                $freshValue->getRawOriginal('catalog_value');

            $expectedValue['needs_review'] =
                $freshValue->getRawOriginal('needs_review');

            $expectedValue['updated_at'] =
                $freshValue->getRawOriginal('updated_at');

            expect($freshValue->getAttributes())
                ->toBe($expectedValue);
        }

        // Controleer de definitieve databasegegevens.
        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryA1->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionA1->id,
            'catalog_number' => 'A-103',
        ]);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueA1Postfris->id,
            'stamp_catalog_entry_id' => $this->entryA1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 18.00,
            'currency' => 'EUR',
            'needs_review' => true,
        ]);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueA1Gestempeld->id,
            'stamp_catalog_entry_id' => $this->entryA1->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 10.00,
            'currency' => 'EUR',
            'needs_review' => true,
        ]);

        // Er mag slechts één catalogusvermelding voor editie A1 bestaan.
        expect(
            $this->stamp->catalogEntries()
                ->where('catalog_edition_id', $this->editionA1->id)
                ->count()
        )->toBe(1);

        // Er mag slechts één waarde per waarderingscategorie bestaan.
        foreach ([
            $this->postfris,
            $this->gestempeld,
        ] as $condition) {
            expect(
                $this->entryA1->values()
                    ->where('condition_id', $condition->id)
                    ->count()
            )->toBe(1);
        }

        // Alle overige catalogusvermeldingen moeten ongewijzigd blijven.
        foreach ($preservedEntries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);
        }

        // Alle overige cataloguswaarden moeten ongewijzigd blijven.
        foreach ($preservedValues as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de aantallen per cataloguseditie.
        expect($this->entryA1->values()->count())
            ->toBe(2);

        expect($this->entryA2->values()->count())
            ->toBe(2);

        expect($this->entryB1->values()->count())
            ->toBe(2);

        // Er mogen geen nieuwe cataloguskoppelingen ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Het land van de postzegel moet behouden blijven.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        // Controleer de definitieve aantallen.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend verwerkt verschillende catalogusedities correct via routes B C en D', function () {
    // Verzamel alle bestaande catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    // Zorg dat alle oorspronkelijke cataloguswaarden gecontroleerd zijn.
    foreach ($values as $value) {
        $value->update([
            'needs_review' => false,
        ]);
    }

    // Bewaar de volledige oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de volledige oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    expect($this->entryA1->fresh()->catalog_number)
        ->toBe('A-100');

    expect($this->entryA2->fresh()->catalog_number)
        ->toBe('A-200');

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    foreach ($values as $value) {
        expect($value->fresh()->needs_review)
            ->toBeFalse();
    }

    // Stel de gezamenlijke formuliergegevens samen.
    $payload = $this->payload;

    // Route B: cataloguseditie A1.
    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-101';

    $payload['catalog_values'] = [
        'postfris' => '16.50',
        'gestempeld' => '8.25',
    ];

    // Route C: cataloguseditie A2.
    $payload['catalog_entries'] = [
        $this->entryA2->id => [
            'catalog_number' => 'A-201',
            'values' => [
                'postfris' => '22.50',
                'gestempeld' => '11.25',
            ],
        ],
    ];

    // Route D: cataloguseditie B1.
    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-101',
            'values' => [
                'postfris' => '30.75',
                'gestempeld' => '15.50',
            ],
        ],
    ];

    // Verplaats de tijd om timestampwijzigingen te controleren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Definieer de verwachte catalogusvermeldingen.
        $expectedEntries = [
            [
                $this->entryA1,
                $this->editionA1,
                'A-101',
            ],
            [
                $this->entryA2,
                $this->editionA2,
                'A-201',
            ],
            [
                $this->entryB1,
                $this->editionB1,
                'B-101',
            ],
        ];

        // Controleer iedere catalogusvermelding afzonderlijk.
        foreach ($expectedEntries as [
            $entry,
            $edition,
            $catalogNumber,
        ]) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->id)
                ->toBe($entry->id);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalog_edition_id)
                ->toBe($edition->id);

            expect($freshEntry->catalog_number)
                ->toBe($catalogNumber);

            // Controleer de timestamps.
            expect($freshEntry->getRawOriginal('created_at'))
                ->toBe($originalEntries[$entry->id]['created_at']);

            expect($freshEntry->getRawOriginal('updated_at'))
                ->not->toBe($originalEntries[$entry->id]['updated_at']);

            // Alleen het catalogusnummer en de wijzigingsdatum
            // mogen zijn gewijzigd.
            $expectedEntry = $originalEntries[$entry->id];

            $expectedEntry['catalog_number'] = $catalogNumber;

            $expectedEntry['updated_at'] =
                $freshEntry->getRawOriginal('updated_at');

            expect($freshEntry->getAttributes())
                ->toBe($expectedEntry);

            // Controleer de opgeslagen databasegegevens.
            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $entry->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $edition->id,
                'catalog_number' => $catalogNumber,
            ]);

            // Er mag slechts één vermelding per editie bestaan.
            expect(
                $this->stamp->catalogEntries()
                    ->where('catalog_edition_id', $edition->id)
                    ->count()
            )->toBe(1);

            // Iedere catalogusvermelding moet twee waarden bevatten.
            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Definieer de verwachte cataloguswaarden.
        $expectedValues = [
            [
                $this->valueA1Postfris,
                $this->entryA1,
                $this->postfris,
                '16.50',
                'EUR',
            ],
            [
                $this->valueA1Gestempeld,
                $this->entryA1,
                $this->gestempeld,
                '8.25',
                'EUR',
            ],
            [
                $this->valueA2Postfris,
                $this->entryA2,
                $this->postfris,
                '22.50',
                'EUR',
            ],
            [
                $this->valueA2Gestempeld,
                $this->entryA2,
                $this->gestempeld,
                '11.25',
                'EUR',
            ],
            [
                $this->valueB1Postfris,
                $this->entryB1,
                $this->postfris,
                '30.75',
                'USD',
            ],
            [
                $this->valueB1Gestempeld,
                $this->entryB1,
                $this->gestempeld,
                '15.50',
                'USD',
            ],
        ];

        // Controleer iedere cataloguswaarde afzonderlijk.
        foreach ($expectedValues as [
            $value,
            $entry,
            $condition,
            $amount,
            $currency,
        ]) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->id)
                ->toBe($value->id);

            expect($freshValue->stamp_catalog_entry_id)
                ->toBe($entry->id);

            expect($freshValue->condition_id)
                ->toBe($condition->id);

            expect($freshValue->catalog_value)
                ->toBe($amount);

            expect($freshValue->currency)
                ->toBe($currency);

            expect($freshValue->needs_review)
                ->toBeTrue();

            // Controleer de timestamps.
            expect($freshValue->getRawOriginal('created_at'))
                ->toBe($originalValues[$value->id]['created_at']);

            expect($freshValue->getRawOriginal('updated_at'))
                ->not->toBe($originalValues[$value->id]['updated_at']);

            // Alleen de waarde, controlemarkering en wijzigingsdatum
            // mogen zijn gewijzigd.
            $expectedValue = $originalValues[$value->id];

            $expectedValue['catalog_value'] =
                $freshValue->getRawOriginal('catalog_value');

            $expectedValue['needs_review'] =
                $freshValue->getRawOriginal('needs_review');

            $expectedValue['updated_at'] =
                $freshValue->getRawOriginal('updated_at');

            expect($freshValue->getAttributes())
                ->toBe($expectedValue);

            // Controleer de opgeslagen databasegegevens.
            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $value->id,
                'stamp_catalog_entry_id' => $entry->id,
                'condition_id' => $condition->id,
                'catalog_value' => (float) $amount,
                'currency' => $currency,
                'needs_review' => true,
            ]);

            // Er mag slechts één waarde per waarderingscategorie bestaan.
            expect(
                $entry->values()
                    ->where('condition_id', $condition->id)
                    ->count()
            )->toBe(1);
        }

        // Er mogen geen nieuwe cataloguskoppelingen ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Het land van de postzegel moet behouden blijven.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        // Controleer de definitieve aantallen.
        expect($this->stamp->catalogEntries()->count())
            ->toBe(3);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend behoudt catalogusgegevens van andere postzegels bij gecombineerde wijzigingen', function () {
    // Maak een tweede postzegel aan binnen hetzelfde land.
    $otherStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2020,
    ]);

    // Maak catalogusvermeldingen voor de tweede postzegel.
    $otherEntryA1 = $otherStamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionA1->id,
        'catalog_number' => 'A-900',
        'notes' => 'Andere postzegel editie A1',
    ]);

    $otherEntryA2 = $otherStamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionA2->id,
        'catalog_number' => 'A-901',
        'notes' => 'Andere postzegel editie A2',
    ]);

    $otherEntryB1 = $otherStamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionB1->id,
        'catalog_number' => 'B-900',
        'notes' => 'Andere postzegel editie B1',
    ]);

    // Maak cataloguswaarden voor de tweede postzegel.
    $otherValueA1Postfris = $otherEntryA1->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 150.00,
        'currency' => 'EUR',
        'notes' => 'Postfrisse waarde andere postzegel A1',
        'needs_review' => false,
    ]);

    $otherValueA1Gestempeld = $otherEntryA1->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 75.00,
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    $otherValueA2Postfris = $otherEntryA2->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 200.00,
        'currency' => 'EUR',
        'needs_review' => true,
    ]);

    $otherValueA2Gestempeld = $otherEntryA2->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 100.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    $otherValueB1Postfris = $otherEntryB1->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 250.00,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    $otherValueB1Gestempeld = $otherEntryB1->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 125.00,
        'currency' => 'USD',
        'notes' => 'Gestempelde waarde andere postzegel B1',
        'needs_review' => true,
    ]);

    // Bewaar de volledige oorspronkelijke tweede postzegel.
    $originalOtherStamp = $otherStamp
        ->fresh()
        ->getAttributes();

    // Verzamel de catalogusvermeldingen van de tweede postzegel.
    $otherEntries = [
        $otherEntryA1,
        $otherEntryA2,
        $otherEntryB1,
    ];

    // Bewaar de volledige oorspronkelijke catalogusvermeldingen.
    $originalOtherEntries = [];

    foreach ($otherEntries as $entry) {
        $originalOtherEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Verzamel de cataloguswaarden van de tweede postzegel.
    $otherValues = [
        $otherValueA1Postfris,
        $otherValueA1Gestempeld,
        $otherValueA2Postfris,
        $otherValueA2Gestempeld,
        $otherValueB1Postfris,
        $otherValueB1Gestempeld,
    ];

    // Bewaar de volledige oorspronkelijke cataloguswaarden.
    $originalOtherValues = [];

    foreach ($otherValues as $value) {
        $originalOtherValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 2);

    $this->assertDatabaseCount('stamp_catalog_entries', 6);

    $this->assertDatabaseCount('stamp_catalog_values', 12);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    expect($otherStamp->catalogEntries()->count())
        ->toBe(3);

    foreach ($otherEntries as $entry) {
        expect($entry->values()->count())
            ->toBe(2);
    }

    // Stel de formuliergegevens voor de eerste postzegel samen.
    $payload = $this->payload;

    // Route B: wijzig cataloguseditie A1.
    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-101';

    $payload['catalog_values'] = [
        'postfris' => '16.50',
        'gestempeld' => '8.25',
    ];

    // Route C: wijzig cataloguseditie A2.
    $payload['catalog_entries'] = [
        $this->entryA2->id => [
            'catalog_number' => 'A-201',
            'values' => [
                'postfris' => '22.50',
                'gestempeld' => '11.25',
            ],
        ],
    ];

    // Route D: wijzig cataloguseditie B1.
    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-101',
            'values' => [
                'postfris' => '30.75',
                'gestempeld' => '15.50',
            ],
        ],
    ];

    // Verplaats de tijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Controleer de gewijzigde catalogusvermeldingen
        // van de eerste postzegel.
        $expectedEntries = [
            [
                $this->entryA1,
                $this->editionA1,
                'A-101',
            ],
            [
                $this->entryA2,
                $this->editionA2,
                'A-201',
            ],
            [
                $this->entryB1,
                $this->editionB1,
                'B-101',
            ],
        ];

        foreach ($expectedEntries as [
            $entry,
            $edition,
            $catalogNumber,
        ]) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalog_edition_id)
                ->toBe($edition->id);

            expect($freshEntry->catalog_number)
                ->toBe($catalogNumber);

            expect($freshEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $entry->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $edition->id,
                'catalog_number' => $catalogNumber,
            ]);
        }

        // Controleer de gewijzigde cataloguswaarden
        // van de eerste postzegel.
        $expectedValues = [
            [
                $this->valueA1Postfris,
                $this->entryA1,
                $this->postfris,
                '16.50',
                'EUR',
            ],
            [
                $this->valueA1Gestempeld,
                $this->entryA1,
                $this->gestempeld,
                '8.25',
                'EUR',
            ],
            [
                $this->valueA2Postfris,
                $this->entryA2,
                $this->postfris,
                '22.50',
                'EUR',
            ],
            [
                $this->valueA2Gestempeld,
                $this->entryA2,
                $this->gestempeld,
                '11.25',
                'EUR',
            ],
            [
                $this->valueB1Postfris,
                $this->entryB1,
                $this->postfris,
                '30.75',
                'USD',
            ],
            [
                $this->valueB1Gestempeld,
                $this->entryB1,
                $this->gestempeld,
                '15.50',
                'USD',
            ],
        ];

        foreach ($expectedValues as [
            $value,
            $entry,
            $condition,
            $amount,
            $currency,
        ]) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->stamp_catalog_entry_id)
                ->toBe($entry->id);

            expect($freshValue->condition_id)
                ->toBe($condition->id);

            expect($freshValue->catalog_value)
                ->toBe($amount);

            expect($freshValue->currency)
                ->toBe($currency);

            expect($freshValue->needs_review)
                ->toBeTrue();

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $value->id,
                'stamp_catalog_entry_id' => $entry->id,
                'condition_id' => $condition->id,
                'catalog_value' => (float) $amount,
                'currency' => $currency,
                'needs_review' => true,
            ]);
        }

        // De tweede postzegel moet volledig ongewijzigd blijven.
        $freshOtherStamp = $otherStamp->fresh();

        expect($freshOtherStamp)->not->toBeNull();

        expect($freshOtherStamp->getAttributes())
            ->toBe($originalOtherStamp);

        // Alle catalogusvermeldingen van de tweede postzegel
        // moeten volledig ongewijzigd blijven.
        foreach ($otherEntries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->stamp_id)
                ->toBe($otherStamp->id);

            expect($freshEntry->getAttributes())
                ->toBe($originalOtherEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Alle cataloguswaarden van de tweede postzegel
        // moeten volledig ongewijzigd blijven.
        foreach ($otherValues as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalOtherValues[$value->id]);
        }

        // Controleer het aantal catalogusvermeldingen per postzegel.
        expect($this->stamp->catalogEntries()->count())
            ->toBe(3);

        expect($otherStamp->catalogEntries()->count())
            ->toBe(3);

        // Er mogen geen nieuwe koppelingen met editie C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $otherStamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Beide postzegels moeten hun oorspronkelijke land behouden.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        $this->assertDatabaseHas('stamps', [
            'id' => $otherStamp->id,
            'country_id' => $this->country->id,
        ]);

        // Controleer de definitieve aantallen.
        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 6);

        $this->assertDatabaseCount('stamp_catalog_values', 12);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend laat niet ingediende catalogusedities volledig ongemoeid', function () {
    // Zorg dat de te wijzigen cataloguswaarde gecontroleerd is.
    $this->valueA2Postfris->update([
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke catalogusvermelding van editie A2.
    $originalEntryA2 = $this->entryA2
        ->fresh()
        ->getAttributes();

    // Bewaar de oorspronkelijke postfrisse cataloguswaarde.
    $originalValueA2Postfris = $this->valueA2Postfris
        ->fresh()
        ->getAttributes();

    // Verzamel de catalogusvermeldingen die niet worden ingediend.
    $preservedEntries = [
        $this->entryA1,
        $this->entryB1,
    ];

    // Bewaar de volledige oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($preservedEntries as $entry) {
        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();
    }

    // Verzamel de cataloguswaarden die niet worden ingediend.
    $preservedValues = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    // Bewaar de volledige oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($preservedValues as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    expect($this->entryA2->fresh()->catalog_number)
        ->toBe('A-200');

    expect($this->valueA2Postfris->fresh()->catalog_value)
        ->toBe('20.00');

    expect($this->valueA2Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($this->valueA2Gestempeld->fresh()->catalog_value)
        ->toBe('10.00');

    expect($this->entryA2->values()->count())
        ->toBe(2);

    // Stel de formuliergegevens uitsluitend voor editie A2 samen.
    $payload = $this->payload;

    $payload['catalog_editions'] = [
        $this->editionA2->id => [
            'catalog_number' => 'A-250',
            'values' => [
                'postfris' => '24.75',
                'gestempeld' => '',
            ],
        ],
    ];

    // Controleer dat uitsluitend editie A2 wordt ingediend.
    expect(array_keys($payload['catalog_editions']))
        ->toBe([$this->editionA2->id]);

    // Verplaats de tijd om ongewenste timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Controleer de gewijzigde catalogusvermelding van editie A2.
        $freshEntryA2 = $this->entryA2->fresh();

        expect($freshEntryA2)->not->toBeNull();

        expect($freshEntryA2->id)
            ->toBe($this->entryA2->id);

        expect($freshEntryA2->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntryA2->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($freshEntryA2->catalog_number)
            ->toBe('A-250');

        // Controleer de timestamps van de catalogusvermelding.
        expect($freshEntryA2->getRawOriginal('created_at'))
            ->toBe($originalEntryA2['created_at']);

        expect($freshEntryA2->getRawOriginal('updated_at'))
            ->not->toBe($originalEntryA2['updated_at']);

        // Alleen het catalogusnummer en de wijzigingsdatum
        // mogen zijn gewijzigd.
        $expectedEntryA2 = $originalEntryA2;

        $expectedEntryA2['catalog_number'] = 'A-250';

        $expectedEntryA2['updated_at'] =
            $freshEntryA2->getRawOriginal('updated_at');

        expect($freshEntryA2->getAttributes())
            ->toBe($expectedEntryA2);

        // Controleer de opgeslagen catalogusvermelding.
        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryA2->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionA2->id,
            'catalog_number' => 'A-250',
        ]);

        // Controleer de gewijzigde postfrisse cataloguswaarde.
        $freshPostfris = $this->valueA2Postfris->fresh();

        expect($freshPostfris)->not->toBeNull();

        expect($freshPostfris->id)
            ->toBe($this->valueA2Postfris->id);

        expect($freshPostfris->stamp_catalog_entry_id)
            ->toBe($this->entryA2->id);

        expect($freshPostfris->condition_id)
            ->toBe($this->postfris->id);

        expect($freshPostfris->catalog_value)
            ->toBe('24.75');

        expect($freshPostfris->currency)
            ->toBe('EUR');

        expect($freshPostfris->needs_review)
            ->toBeTrue();

        // Controleer de timestamps van de cataloguswaarde.
        expect($freshPostfris->getRawOriginal('created_at'))
            ->toBe($originalValueA2Postfris['created_at']);

        expect($freshPostfris->getRawOriginal('updated_at'))
            ->not->toBe($originalValueA2Postfris['updated_at']);

        // Alleen de waarde, controlemarkering en wijzigingsdatum
        // mogen zijn gewijzigd.
        $expectedPostfris = $originalValueA2Postfris;

        $expectedPostfris['catalog_value'] =
            $freshPostfris->getRawOriginal('catalog_value');

        $expectedPostfris['needs_review'] =
            $freshPostfris->getRawOriginal('needs_review');

        $expectedPostfris['updated_at'] =
            $freshPostfris->getRawOriginal('updated_at');

        expect($freshPostfris->getAttributes())
            ->toBe($expectedPostfris);

        // Controleer de opgeslagen postfrisse cataloguswaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueA2Postfris->id,
            'stamp_catalog_entry_id' => $this->entryA2->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 24.75,
            'currency' => 'EUR',
            'needs_review' => true,
        ]);

        // De gestempelde cataloguswaarde moet verwijderd zijn.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueA2Gestempeld->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryA2->id,
            'condition_id' => $this->gestempeld->id,
        ]);

        expect($this->valueA2Gestempeld->fresh())
            ->toBeNull();

        // Editie A2 moet precies één cataloguswaarde bevatten.
        expect($freshEntryA2->values()->count())
            ->toBe(1);

        expect(
            $freshEntryA2->values()
                ->where('condition_id', $this->postfris->id)
                ->count()
        )->toBe(1);

        // Alle niet-ingediende catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($preservedEntries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Alle niet-ingediende cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($preservedValues as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Er mag slechts één catalogusvermelding per editie bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
        ] as $edition) {
            expect(
                $this->stamp->catalogEntries()
                    ->where('catalog_edition_id', $edition->id)
                    ->count()
            )->toBe(1);
        }

        // Er mag geen nieuwe koppeling met editie C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Het land van de postzegel moet behouden blijven.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        // Controleer de definitieve aantallen per editie.
        expect($this->entryA1->values()->count())
            ->toBe(2);

        expect($this->entryA2->values()->count())
            ->toBe(1);

        expect($this->entryB1->values()->count())
            ->toBe(2);

        expect($this->stamp->catalogEntries()->count())
            ->toBe(3);

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);
    } finally {
        $this->travelBack();
    }
});
