<?php

use App\Models\Catalog;
use App\Models\CatalogChangeBatch;
use App\Models\CatalogChangeLog;
use App\Models\CatalogConditionRule;
use App\Models\CatalogEdition;
use App\Models\Condition;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampCatalogEntry;
use App\Models\StampCatalogValue;
use App\Models\User;
use App\Services\CatalogChangeHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Gebruik Nederlands als actieve taal.
    app()->setLocale('nl');

    // Maak de gebruiker aan die de cataloguswijzigingen uitvoert.
    $this->user = User::factory()->create([
        'name' => 'Testgebruiker catalogusgeschiedenis',
        'is_admin' => false,
    ]);

    // Maak een tweede gebruiker aan voor isolatiecontroles.
    $this->otherUser = User::factory()->create([
        'name' => 'Andere testgebruiker',
        'is_admin' => false,
    ]);

    // Maak de oorspronkelijke postzegel aan.
    $this->stamp = Stamp::factory()->create([
        'issue_year' => 2020,
    ]);

    // Gebruik het land van de oorspronkelijke postzegel.
    $this->country = $this->stamp->country;

    // Maak een tweede land aan voor landwijzigingen.
    $this->otherCountry = Country::factory()->create();

    // Maak de waarderingscategorieën aan.
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

    // Catalogus A is uitsluitend beschikbaar
    // voor het oorspronkelijke land.
    $this->catalogA = Catalog::create([
        'name' => 'Testcatalogus A',
        'abbreviation' => 'TCA',
    ]);

    $this->catalogA->countries()->attach(
        $this->country->id
    );

    // Catalogus B is beschikbaar voor beide landen.
    $this->catalogB = Catalog::create([
        'name' => 'Testcatalogus B',
        'abbreviation' => 'TCB',
    ]);

    $this->catalogB->countries()->attach([
        $this->country->id,
        $this->otherCountry->id,
    ]);

    // Catalogus C is uitsluitend beschikbaar
    // voor het andere land.
    $this->catalogC = Catalog::create([
        'name' => 'Testcatalogus C',
        'abbreviation' => 'TCC',
    ]);

    $this->catalogC->countries()->attach(
        $this->otherCountry->id
    );

    // Maak de catalogusedities aan.
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

    // Maak algemene waarderingsregels aan
    // voor alle drie de catalogi.
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

    // Maak de oorspronkelijke catalogusvermeldingen aan.
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

    // Maak de oorspronkelijke cataloguswaarden
    // voor cataloguseditie A1 aan.
    $this->valueA1Postfris = $this->entryA1->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 15.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    $this->valueA1Gestempeld = $this->entryA1->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 7.50,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke cataloguswaarden
    // voor cataloguseditie A2 aan.
    $this->valueA2Postfris = $this->entryA2->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 20.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    $this->valueA2Gestempeld = $this->entryA2->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 10.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Maak de oorspronkelijke cataloguswaarden
    // voor cataloguseditie B1 aan.
    $this->valueB1Postfris = $this->entryB1->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 25.00,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    $this->valueB1Gestempeld = $this->entryB1->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 12.50,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    // Gemeenschappelijke formuliergegevens voor store().
    $this->storePayload = [
        'country_id' => $this->country->id,
        'issue_year' => 2020,
        'translations' => [
            'nl' => [
                'name' => 'Nieuwe postzegel catalogusgeschiedenis',
                'description' => null,
            ],
        ],
    ];

    // Gemeenschappelijke formuliergegevens voor update().
    $this->updatePayload = [
        'country_id' => $this->country->id,
        'issue_year' => $this->stamp->issue_year,
        'translations' => [
            'nl' => [
                'name' => 'Gewijzigde postzegel catalogusgeschiedenis',
                'description' => null,
            ],
        ],
    ];

    // Haal de actieve instantie van de geschiedenisdienst op.
    $this->history = app(CatalogChangeHistoryService::class);

    // Controleer dat de testvoorbereiding geen
    // wijzigingsgeschiedenis heeft aangemaakt.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Controleer dat er geen actieve geschiedenisbatch bestaat.
    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Meld de oorspronkelijke testgebruiker aan.
    $this->actingAs($this->user);
});

test('de backend maakt een postzegel zonder catalogusgegevens aan zonder wijzigingsgeschiedenis', function () {
    // Bewaar de volledige oorspronkelijke postzegel.
    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

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
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Gebruik uitsluitend de algemene postzegelgegevens.
    $payload = $this->storePayload;

    // Controleer dat er geen catalogusgegevens worden ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_id')
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values');

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Maak de nieuwe postzegel aan via de backend.
        $response = $this->post(
            route('backend.stamps.store'),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Er moet precies één nieuwe postzegel zijn aangemaakt.
        $this->assertDatabaseCount('stamps', 2);

        // Haal de nieuwe postzegel op.
        $newStamp = Stamp::query()
            ->where('id', '!=', $this->stamp->id)
            ->sole();

        expect($newStamp->id)
            ->not->toBe($this->stamp->id);

        expect($newStamp->country_id)
            ->toBe($this->country->id);

        expect($newStamp->issue_year)
            ->toBe(2020);

        expect($newStamp->name)
            ->toBe('Nieuwe postzegel catalogusgeschiedenis');

        // Controleer de opgeslagen postzegelgegevens.
        $this->assertDatabaseHas('stamps', [
            'id' => $newStamp->id,
            'country_id' => $this->country->id,
            'issue_year' => 2020,
            'name' => 'Nieuwe postzegel catalogusgeschiedenis',
        ]);

        // Controleer de Nederlandse vertaling.
        $this->assertDatabaseHas('stamp_translations', [
            'stamp_id' => $newStamp->id,
            'locale' => 'nl',
            'name' => 'Nieuwe postzegel catalogusgeschiedenis',
            'description' => null,
        ]);

        expect($newStamp->translations()->count())
            ->toBe(1);

        // De nieuwe postzegel mag geen catalogusvermeldingen bevatten.
        expect($newStamp->catalogEntries()->count())
            ->toBe(0);

        // De nieuwe postzegel mag geen cataloguswaarden bevatten.
        expect($newStamp->catalogValues()->count())
            ->toBe(0);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $newStamp->id,
        ]);

        // Er mag geen geschiedenisbatch zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        expect(CatalogChangeBatch::query()->count())
            ->toBe(0);

        // Er mogen geen wijzigingslogs zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(CatalogChangeLog::query()->count())
            ->toBe(0);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // De oorspronkelijke postzegel moet volledig behouden blijven.
        $freshOriginalStamp = $this->stamp->fresh();

        expect($freshOriginalStamp)->not->toBeNull();

        expect($freshOriginalStamp->getAttributes())
            ->toBe($originalStamp);

        // Alle bestaande catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Alle bestaande cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de catalogusvermeldingen per postzegel.
        expect($this->stamp->catalogEntries()->count())
            ->toBe(3);

        expect($newStamp->catalogEntries()->count())
            ->toBe(0);

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het aanmaken van een postzegel met uitsluitend een catalogusnummer', function () {
    // Bewaar de volledige oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Stel de formuliergegevens samen.
    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogA->id;

    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-300';

    // Er worden bewust geen cataloguswaarden ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_values');

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Maak de nieuwe postzegel aan.
        $response = $this->post(
            route('backend.stamps.store'),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Controleer de nieuwe postzegel.
        $this->assertDatabaseCount('stamps', 2);

        $newStamp = Stamp::query()
            ->where('id', '!=', $this->stamp->id)
            ->sole();

        expect($newStamp->country_id)
            ->toBe($this->country->id);

        expect($newStamp->issue_year)
            ->toBe(2020);

        expect($newStamp->name)
            ->toBe('Nieuwe postzegel catalogusgeschiedenis');

        // Haal de nieuwe catalogusvermelding op.
        $newEntry = $newStamp->catalogEntries()
            ->sole();

        expect($newEntry)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect($newEntry->stamp_id)
            ->toBe($newStamp->id);

        expect($newEntry->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($newEntry->catalog_number)
            ->toBe('A-300');

        // Er mogen geen cataloguswaarden zijn aangemaakt.
        expect($newEntry->values()->count())
            ->toBe(0);

        expect($newStamp->catalogValues()->count())
            ->toBe(0);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $newEntry->id,
            'stamp_id' => $newStamp->id,
            'catalog_edition_id' => $this->editionA1->id,
            'catalog_number' => 'A-300',
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_create');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het aanmaken van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogA->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionA1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        // Controleer de opgeslagen geschiedenisbatch.
        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionA1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_create',
            'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
            'catalog_name' => $this->catalogA->name,
            'catalog_edition_name' => $this->editionA1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de identificatie van het wijzigingslog.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($log->stamp_id)
            ->toBe($newStamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBeNull();

        expect($log->record_type)
            ->toBe('stamp_catalog_entry');

        expect($log->record_id)
            ->toBe($newEntry->id);

        expect($log->action)
            ->toBe('created');

        // Bij een nieuw record bestaan geen oude attribuutwaarden.
        expect($log->old_values)
            ->toBeNull();

        // Controleer de nieuwe attribuutwaarden.
        expect($log->new_values)
            ->toBeArray();

        expect($log->new_values['id'])
            ->toBe($newEntry->id);

        expect($log->new_values['stamp_id'])
            ->toBe($newStamp->id);

        expect($log->new_values['catalog_edition_id'])
            ->toBe($this->editionA1->id);

        expect($log->new_values['catalog_number'])
            ->toBe('A-300');

        expect($log->new_values['created_at'])
            ->toBe($newEntry->getRawOriginal('created_at'));

        expect($log->new_values['updated_at'])
            ->toBe($newEntry->getRawOriginal('updated_at'));

        // Controleer alle attributen die tijdens het created-event
        // in het wijzigingslog zijn vastgelegd.
        foreach ($log->new_values as $attribute => $value) {
            expect($newEntry->getAttributes())
                ->toHaveKey($attribute);

            expect($value)
                ->toBe($newEntry->getRawOriginal($attribute));
        }

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($log->stamp->id)
            ->toBe($newStamp->id);

        expect($log->stampSeries)
            ->toBeNull();

        expect($log->condition)
            ->toBeNull();

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionA1->id,
            'stamp_id' => $newStamp->id,
            'stamp_series_id' => null,
            'condition_id' => null,
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $newEntry->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        // Er mogen geen wijzigingslogs voor cataloguswaarden bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
        ]);

        // Er mogen geen wijzigingslogs voor de oorspronkelijke
        // postzegel bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // Er mogen geen geschiedenisbatches voor andere edities bestaan.
        foreach ([
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // De oorspronkelijke postzegel moet volledig behouden blijven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStamp);

        // Alle bestaande catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Alle bestaande cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer het aantal catalogusvermeldingen per postzegel.
        expect($this->stamp->catalogEntries()->count())
            ->toBe(3);

        expect($newStamp->catalogEntries()->count())
            ->toBe(1);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het aanmaken van een postzegel met een cataloguswaarde', function () {
    // Bewaar de volledige oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Stel de formuliergegevens samen.
    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-300';

    $payload['catalog_values'] = [
        'postfris' => '32.75',
    ];

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Maak de nieuwe postzegel aan.
        $response = $this->post(
            route('backend.stamps.store'),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Controleer de nieuwe postzegel.
        $this->assertDatabaseCount('stamps', 2);

        $newStamp = Stamp::query()
            ->where('id', '!=', $this->stamp->id)
            ->sole();

        expect($newStamp->country_id)
            ->toBe($this->country->id);

        expect($newStamp->issue_year)
            ->toBe(2020);

        expect($newStamp->name)
            ->toBe('Nieuwe postzegel catalogusgeschiedenis');

        // Controleer de nieuwe catalogusvermelding.
        $newEntry = $newStamp->catalogEntries()->sole();

        expect($newEntry)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect($newEntry->stamp_id)
            ->toBe($newStamp->id);

        expect($newEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($newEntry->catalog_number)
            ->toBe('B-300');

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $newEntry->id,
            'stamp_id' => $newStamp->id,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-300',
        ]);

        // Controleer de nieuwe cataloguswaarde.
        $newValue = $newEntry->values()->sole();

        expect($newValue)
            ->toBeInstanceOf(StampCatalogValue::class);

        expect($newValue->stamp_catalog_entry_id)
            ->toBe($newEntry->id);

        expect($newValue->condition_id)
            ->toBe($this->postfris->id);

        expect($newValue->catalog_value)
            ->toBe('32.75');

        expect($newValue->currency)
            ->toBe('USD');

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $newValue->id,
            'stamp_catalog_entry_id' => $newEntry->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 32.75,
            'currency' => 'USD',
        ]);

        // Er mag geen gestempelde cataloguswaarde zijn aangemaakt.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $newEntry->id,
            'condition_id' => $this->gestempeld->id,
        ]);

        expect($newStamp->catalogEntries()->count())
            ->toBe(1);

        expect($newStamp->catalogValues()->count())
            ->toBe(1);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_create');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het aanmaken van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_create',
            'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moeten precies twee wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 2);

        expect($batch->logs()->count())
            ->toBe(2);

        // Haal het wijzigingslog van de catalogusvermelding op.
        $entryLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->sole();

        // Haal het wijzigingslog van de cataloguswaarde op.
        $valueLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->sole();

        // Controleer de gemeenschappelijke loggegevens.
        foreach ([$entryLog, $valueLog] as $log) {
            expect($log)
                ->toBeInstanceOf(CatalogChangeLog::class);

            expect($log->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($log->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($log->stamp_id)
                ->toBe($newStamp->id);

            expect($log->stamp_series_id)
                ->toBeNull();

            expect($log->action)
                ->toBe('created');

            expect($log->old_values)
                ->toBeNull();

            expect($log->new_values)
                ->toBeArray();

            // Controleer de gemeenschappelijke relaties.
            expect($log->batch->id)
                ->toBe($batch->id);

            expect($log->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($log->stamp->id)
                ->toBe($newStamp->id);

            expect($log->stampSeries)
                ->toBeNull();
        }

        // Controleer het wijzigingslog van de catalogusvermelding.
        expect($entryLog->record_type)
            ->toBe('stamp_catalog_entry');

        expect($entryLog->record_id)
            ->toBe($newEntry->id);

        expect($entryLog->condition_id)
            ->toBeNull();

        expect($entryLog->condition)
            ->toBeNull();

        // Controleer de geregistreerde nieuwe attribuutwaarden.
        expect($entryLog->new_values['id'])
            ->toBe($newEntry->id);

        expect($entryLog->new_values['stamp_id'])
            ->toBe($newStamp->id);

        expect($entryLog->new_values['catalog_edition_id'])
            ->toBe($this->editionB1->id);

        expect($entryLog->new_values['catalog_number'])
            ->toBe('B-300');

        expect($entryLog->new_values['created_at'])
            ->toBe($newEntry->getRawOriginal('created_at'));

        expect($entryLog->new_values['updated_at'])
            ->toBe($newEntry->getRawOriginal('updated_at'));

        // Vergelijk alle geregistreerde attributen
        // met de opgeslagen catalogusvermelding.
        foreach ($entryLog->new_values as $attribute => $value) {
            expect($newEntry->getAttributes())
                ->toHaveKey($attribute);

            expect($value)
                ->toBe($newEntry->getRawOriginal($attribute));
        }

        // Controleer het wijzigingslog van de cataloguswaarde.
        expect($valueLog->record_type)
            ->toBe('stamp_catalog_value');

        expect($valueLog->record_id)
            ->toBe($newValue->id);

        expect($valueLog->condition_id)
            ->toBe($this->postfris->id);

        expect($valueLog->condition->id)
            ->toBe($this->postfris->id);

        // Controleer de geregistreerde nieuwe attribuutwaarden.
        expect($valueLog->new_values['id'])
            ->toBe($newValue->id);

        expect($valueLog->new_values['stamp_catalog_entry_id'])
            ->toBe($newEntry->id);

        expect($valueLog->new_values['condition_id'])
            ->toBe($this->postfris->id);

        expect((float) $valueLog->new_values['catalog_value'])
            ->toBe(32.75);

        expect($valueLog->new_values['currency'])
            ->toBe('USD');

        expect($valueLog->new_values['created_at'])
            ->toBe($newValue->getRawOriginal('created_at'));

        expect($valueLog->new_values['updated_at'])
            ->toBe($newValue->getRawOriginal('updated_at'));

        // Vergelijk alle geregistreerde attributen
        // met de opgeslagen cataloguswaarde.
        foreach ($valueLog->new_values as $attribute => $value) {
            expect($newValue->getAttributes())
                ->toHaveKey($attribute);

            // Normaliseer numerieke databasewaarden.
            if ($attribute === 'catalog_value') {
                expect((float) $value)
                    ->toBe(
                        (float) $newValue->getRawOriginal($attribute)
                    );

                continue;
            }

            // Normaliseer de controlemarkering indien aanwezig.
            if ($attribute === 'needs_review') {
                expect((bool) $value)
                    ->toBe($newValue->needs_review);

                continue;
            }

            expect($value)
                ->toBe($newValue->getRawOriginal($attribute));
        }

        // De catalogusvermelding moet vóór de cataloguswaarde
        // in de wijzigingsgeschiedenis zijn geregistreerd.
        expect($entryLog->id)
            ->toBeLessThan($valueLog->id);

        // Controleer het opgeslagen wijzigingslog
        // van de catalogusvermelding.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $entryLog->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $newStamp->id,
            'stamp_series_id' => null,
            'condition_id' => null,
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $newEntry->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        // Controleer het opgeslagen wijzigingslog
        // van de cataloguswaarde.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $valueLog->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $newStamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $newValue->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        // Er mogen geen wijzigingslogs voor de oorspronkelijke
        // postzegel bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // Er mogen geen wijzigingslogs voor de gestempelde
        // waarderingscategorie bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'condition_id' => $this->gestempeld->id,
        ]);

        // Er mogen geen geschiedenisbatches voor andere edities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // De oorspronkelijke postzegel moet volledig behouden blijven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStamp);

        // Alle bestaande catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Alle bestaande cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de catalogusvermeldingen per postzegel.
        expect($this->stamp->catalogEntries()->count())
            ->toBe(3);

        expect($newStamp->catalogEntries()->count())
            ->toBe(1);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 7);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 2);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het aanmaken van een postzegel met meerdere cataloguswaarden', function () {
    // Bewaar de volledige oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Stel de formuliergegevens samen.
    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogA->id;

    $payload['catalog_edition_id'] = $this->editionA2->id;

    $payload['catalog_number'] = 'A-400';

    $payload['catalog_values'] = [
        'postfris' => '42.75',
        'gestempeld' => '18.50',
    ];

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Maak de nieuwe postzegel aan.
        $response = $this->post(
            route('backend.stamps.store'),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Controleer de nieuwe postzegel.
        $this->assertDatabaseCount('stamps', 2);

        $newStamp = Stamp::query()
            ->where('id', '!=', $this->stamp->id)
            ->sole();

        expect($newStamp->country_id)
            ->toBe($this->country->id);

        expect($newStamp->issue_year)
            ->toBe(2020);

        expect($newStamp->name)
            ->toBe('Nieuwe postzegel catalogusgeschiedenis');

        // Controleer de nieuwe catalogusvermelding.
        $newEntry = $newStamp->catalogEntries()->sole();

        expect($newEntry)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect($newEntry->stamp_id)
            ->toBe($newStamp->id);

        expect($newEntry->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($newEntry->catalog_number)
            ->toBe('A-400');

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $newEntry->id,
            'stamp_id' => $newStamp->id,
            'catalog_edition_id' => $this->editionA2->id,
            'catalog_number' => 'A-400',
        ]);

        // Er moeten precies twee cataloguswaarden bestaan.
        expect($newEntry->values()->count())
            ->toBe(2);

        expect($newStamp->catalogValues()->count())
            ->toBe(2);

        // Haal beide nieuwe cataloguswaarden op.
        $newPostfris = $newEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $newGestempeld = $newEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        // Definieer de verwachte cataloguswaarden.
        $expectedValues = [
            [
                $newPostfris,
                $this->postfris,
                '42.75',
            ],
            [
                $newGestempeld,
                $this->gestempeld,
                '18.50',
            ],
        ];

        // Controleer beide nieuwe cataloguswaarden.
        foreach ($expectedValues as [
            $newValue,
            $condition,
            $amount,
        ]) {
            expect($newValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($newValue->stamp_catalog_entry_id)
                ->toBe($newEntry->id);

            expect($newValue->condition_id)
                ->toBe($condition->id);

            expect($newValue->catalog_value)
                ->toBe($amount);

            expect($newValue->currency)
                ->toBe('EUR');

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $newValue->id,
                'stamp_catalog_entry_id' => $newEntry->id,
                'condition_id' => $condition->id,
                'catalog_value' => (float) $amount,
                'currency' => 'EUR',
            ]);
        }

        // Beide cataloguswaarden moeten afzonderlijke records zijn.
        expect($newPostfris->id)
            ->not->toBe($newGestempeld->id);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_create');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het aanmaken van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogA->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionA2->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionA2->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionA2->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_create',
            'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
            'catalog_name' => $this->catalogA->name,
            'catalog_edition_name' => $this->editionA2->edition_name,
        ]);

        // Er moeten precies drie wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 3);

        expect($batch->logs()->count())
            ->toBe(3);

        // Controleer het aantal logs per recordtype.
        expect(
            $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->count()
        )->toBe(1);

        expect(
            $batch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(2);

        // Alle wijzigingslogs moeten de actie created bevatten.
        expect(
            $batch->logs()
                ->where('action', 'created')
                ->count()
        )->toBe(3);

        // Haal de drie afzonderlijke wijzigingslogs op.
        $entryLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->sole();

        $postfrisLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $gestempeldLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        // Controleer de gemeenschappelijke loggegevens.
        foreach ([
            $entryLog,
            $postfrisLog,
            $gestempeldLog,
        ] as $log) {
            expect($log)
                ->toBeInstanceOf(CatalogChangeLog::class);

            expect($log->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($log->catalog_edition_id)
                ->toBe($this->editionA2->id);

            expect($log->stamp_id)
                ->toBe($newStamp->id);

            expect($log->stamp_series_id)
                ->toBeNull();

            expect($log->action)
                ->toBe('created');

            expect($log->old_values)
                ->toBeNull();

            expect($log->new_values)
                ->toBeArray();

            // Controleer de gemeenschappelijke relaties.
            expect($log->batch->id)
                ->toBe($batch->id);

            expect($log->catalogEdition->id)
                ->toBe($this->editionA2->id);

            expect($log->stamp->id)
                ->toBe($newStamp->id);

            expect($log->stampSeries)
                ->toBeNull();
        }

        // Controleer het wijzigingslog van de catalogusvermelding.
        expect($entryLog->record_type)
            ->toBe('stamp_catalog_entry');

        expect($entryLog->record_id)
            ->toBe($newEntry->id);

        expect($entryLog->condition_id)
            ->toBeNull();

        expect($entryLog->condition)
            ->toBeNull();

        // Controleer de geregistreerde nieuwe attribuutwaarden.
        expect($entryLog->new_values['id'])
            ->toBe($newEntry->id);

        expect($entryLog->new_values['stamp_id'])
            ->toBe($newStamp->id);

        expect($entryLog->new_values['catalog_edition_id'])
            ->toBe($this->editionA2->id);

        expect($entryLog->new_values['catalog_number'])
            ->toBe('A-400');

        expect($entryLog->new_values['created_at'])
            ->toBe($newEntry->getRawOriginal('created_at'));

        expect($entryLog->new_values['updated_at'])
            ->toBe($newEntry->getRawOriginal('updated_at'));

        // Vergelijk alle geregistreerde attributen
        // met de opgeslagen catalogusvermelding.
        foreach ($entryLog->new_values as $attribute => $value) {
            expect($newEntry->getAttributes())
                ->toHaveKey($attribute);

            expect($value)
                ->toBe($newEntry->getRawOriginal($attribute));
        }

        // Controleer het opgeslagen wijzigingslog
        // van de catalogusvermelding.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $entryLog->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionA2->id,
            'stamp_id' => $newStamp->id,
            'stamp_series_id' => null,
            'condition_id' => null,
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $newEntry->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        // Definieer de verwachte wijzigingslogs
        // van beide cataloguswaarden.
        $expectedValueLogs = [
            [
                $postfrisLog,
                $newPostfris,
                $this->postfris,
                42.75,
            ],
            [
                $gestempeldLog,
                $newGestempeld,
                $this->gestempeld,
                18.50,
            ],
        ];

        // Controleer beide wijzigingslogs afzonderlijk.
        foreach ($expectedValueLogs as [
            $log,
            $newValue,
            $condition,
            $amount,
        ]) {
            expect($log->record_type)
                ->toBe('stamp_catalog_value');

            expect($log->record_id)
                ->toBe($newValue->id);

            expect($log->condition_id)
                ->toBe($condition->id);

            expect($log->condition->id)
                ->toBe($condition->id);

            // Controleer de geregistreerde nieuwe attribuutwaarden.
            expect($log->new_values['id'])
                ->toBe($newValue->id);

            expect($log->new_values['stamp_catalog_entry_id'])
                ->toBe($newEntry->id);

            expect($log->new_values['condition_id'])
                ->toBe($condition->id);

            expect((float) $log->new_values['catalog_value'])
                ->toBe($amount);

            expect($log->new_values['currency'])
                ->toBe('EUR');

            expect($log->new_values['created_at'])
                ->toBe($newValue->getRawOriginal('created_at'));

            expect($log->new_values['updated_at'])
                ->toBe($newValue->getRawOriginal('updated_at'));

            // Vergelijk alle geregistreerde attributen
            // met de opgeslagen cataloguswaarde.
            foreach ($log->new_values as $attribute => $value) {
                expect($newValue->getAttributes())
                    ->toHaveKey($attribute);

                // Normaliseer numerieke databasewaarden.
                if ($attribute === 'catalog_value') {
                    expect((float) $value)
                        ->toBe(
                            (float) $newValue->getRawOriginal($attribute)
                        );

                    continue;
                }

                // Normaliseer de controlemarkering indien aanwezig.
                if ($attribute === 'needs_review') {
                    expect((bool) $value)
                        ->toBe($newValue->needs_review);

                    continue;
                }

                expect($value)
                    ->toBe($newValue->getRawOriginal($attribute));
            }

            // Controleer het opgeslagen wijzigingslog.
            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $log->id,
                'catalog_change_batch_id' => $batch->id,
                'catalog_edition_id' => $this->editionA2->id,
                'stamp_id' => $newStamp->id,
                'stamp_series_id' => null,
                'condition_id' => $condition->id,
                'record_type' => 'stamp_catalog_value',
                'record_id' => $newValue->id,
                'action' => 'created',
                'old_values' => null,
            ]);

            // Er mag slechts één wijzigingslog
            // per nieuwe cataloguswaarde bestaan.
            expect(
                $batch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $newValue->id)
                    ->count()
            )->toBe(1);

            // De catalogusvermelding moet vóór
            // de cataloguswaarde geregistreerd zijn.
            expect($entryLog->id)
                ->toBeLessThan($log->id);
        }

        // Beide cataloguswaarden moeten afzonderlijke logs hebben.
        expect($postfrisLog->id)
            ->not->toBe($gestempeldLog->id);

        // Er mogen geen wijzigingslogs voor de oorspronkelijke
        // postzegel bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // Er mogen geen geschiedenisbatches
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // De oorspronkelijke postzegel moet volledig behouden blijven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStamp);

        // Alle bestaande catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Alle bestaande cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de catalogusvermeldingen per postzegel.
        expect($this->stamp->catalogEntries()->count())
            ->toBe(3);

        expect($newStamp->catalogEntries()->count())
            ->toBe(1);

        // Controleer de cataloguswaarden per postzegel.
        expect($this->stamp->catalogValues()->count())
            ->toBe(6);

        expect($newStamp->catalogValues()->count())
            ->toBe(2);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het aanmaken van een postzegel met een cataloguswaarde van nul', function () {
    // Bewaar de volledige oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Stel de formuliergegevens samen.
    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-500';

    $payload['catalog_values'] = [
        'postfris' => '0.00',
        'gestempeld' => '',
    ];

    // Controleer het onderscheid tussen nul en een leeg veld.
    expect($payload['catalog_values']['postfris'])
        ->toBe('0.00');

    expect($payload['catalog_values']['gestempeld'])
        ->toBe('');

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Maak de nieuwe postzegel aan.
        $response = $this->post(
            route('backend.stamps.store'),
            $payload
        );

        // Controleer de HTTP-response.
        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // Controleer de nieuwe postzegel.
        $this->assertDatabaseCount('stamps', 2);

        $newStamp = Stamp::query()
            ->where('id', '!=', $this->stamp->id)
            ->sole();

        expect($newStamp->country_id)
            ->toBe($this->country->id);

        expect($newStamp->issue_year)
            ->toBe(2020);

        expect($newStamp->name)
            ->toBe('Nieuwe postzegel catalogusgeschiedenis');

        // Controleer de nieuwe catalogusvermelding.
        $newEntry = $newStamp->catalogEntries()->sole();

        expect($newEntry)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect($newEntry->stamp_id)
            ->toBe($newStamp->id);

        expect($newEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($newEntry->catalog_number)
            ->toBe('B-500');

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $newEntry->id,
            'stamp_id' => $newStamp->id,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-500',
        ]);

        // Er moet precies één cataloguswaarde bestaan.
        expect($newEntry->values()->count())
            ->toBe(1);

        expect($newStamp->catalogValues()->count())
            ->toBe(1);

        $newValue = $newEntry->values()->sole();

        expect($newValue)
            ->toBeInstanceOf(StampCatalogValue::class);

        expect($newValue->stamp_catalog_entry_id)
            ->toBe($newEntry->id);

        expect($newValue->condition_id)
            ->toBe($this->postfris->id);

        // Controleer dat de nulwaarde daadwerkelijk is opgeslagen.
        expect($newValue->catalog_value)
            ->not->toBeNull();

        expect($newValue->catalog_value)
            ->toBe('0.00');

        expect((float) $newValue->catalog_value)
            ->toBe(0.0);

        expect($newValue->currency)
            ->toBe('USD');

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $newValue->id,
            'stamp_catalog_entry_id' => $newEntry->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 0,
            'currency' => 'USD',
        ]);

        // Het lege gestempelde invoerveld mag geen record opleveren.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $newEntry->id,
            'condition_id' => $this->gestempeld->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_create');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het aanmaken van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_create',
            'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moeten precies twee wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 2);

        expect($batch->logs()->count())
            ->toBe(2);

        // Haal beide afzonderlijke wijzigingslogs op.
        $entryLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->sole();

        $valueLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->sole();

        // Controleer de gemeenschappelijke loggegevens.
        foreach ([$entryLog, $valueLog] as $log) {
            expect($log)
                ->toBeInstanceOf(CatalogChangeLog::class);

            expect($log->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($log->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($log->stamp_id)
                ->toBe($newStamp->id);

            expect($log->stamp_series_id)
                ->toBeNull();

            expect($log->action)
                ->toBe('created');

            expect($log->old_values)
                ->toBeNull();

            expect($log->new_values)
                ->toBeArray();

            // Controleer de gemeenschappelijke relaties.
            expect($log->batch->id)
                ->toBe($batch->id);

            expect($log->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($log->stamp->id)
                ->toBe($newStamp->id);

            expect($log->stampSeries)
                ->toBeNull();
        }

        // Controleer het wijzigingslog van de catalogusvermelding.
        expect($entryLog->record_type)
            ->toBe('stamp_catalog_entry');

        expect($entryLog->record_id)
            ->toBe($newEntry->id);

        expect($entryLog->condition_id)
            ->toBeNull();

        expect($entryLog->condition)
            ->toBeNull();

        expect($entryLog->new_values['id'])
            ->toBe($newEntry->id);

        expect($entryLog->new_values['stamp_id'])
            ->toBe($newStamp->id);

        expect($entryLog->new_values['catalog_edition_id'])
            ->toBe($this->editionB1->id);

        expect($entryLog->new_values['catalog_number'])
            ->toBe('B-500');

        expect($entryLog->new_values['created_at'])
            ->toBe($newEntry->getRawOriginal('created_at'));

        expect($entryLog->new_values['updated_at'])
            ->toBe($newEntry->getRawOriginal('updated_at'));

        // Vergelijk alle geregistreerde attributen
        // met de opgeslagen catalogusvermelding.
        foreach ($entryLog->new_values as $attribute => $value) {
            expect($newEntry->getAttributes())
                ->toHaveKey($attribute);

            expect($value)
                ->toBe($newEntry->getRawOriginal($attribute));
        }

        // Controleer het wijzigingslog van de cataloguswaarde.
        expect($valueLog->record_type)
            ->toBe('stamp_catalog_value');

        expect($valueLog->record_id)
            ->toBe($newValue->id);

        expect($valueLog->condition_id)
            ->toBe($this->postfris->id);

        expect($valueLog->condition->id)
            ->toBe($this->postfris->id);

        // Controleer de geregistreerde nieuwe attribuutwaarden.
        expect($valueLog->new_values['id'])
            ->toBe($newValue->id);

        expect($valueLog->new_values['stamp_catalog_entry_id'])
            ->toBe($newEntry->id);

        expect($valueLog->new_values['condition_id'])
            ->toBe($this->postfris->id);

        // Controleer expliciet dat de nulwaarde aanwezig is.
        expect($valueLog->new_values)
            ->toHaveKey('catalog_value');

        expect($valueLog->new_values['catalog_value'])
            ->not->toBeNull();

        expect($valueLog->new_values['catalog_value'])
            ->not->toBe('');

        expect(is_numeric($valueLog->new_values['catalog_value']))
            ->toBeTrue();

        expect((float) $valueLog->new_values['catalog_value'])
            ->toBe(0.0);

        expect($valueLog->new_values['currency'])
            ->toBe('USD');

        expect($valueLog->new_values['created_at'])
            ->toBe($newValue->getRawOriginal('created_at'));

        expect($valueLog->new_values['updated_at'])
            ->toBe($newValue->getRawOriginal('updated_at'));

        // Vergelijk alle geregistreerde attributen
        // met de opgeslagen cataloguswaarde.
        foreach ($valueLog->new_values as $attribute => $value) {
            expect($newValue->getAttributes())
                ->toHaveKey($attribute);

            // Normaliseer numerieke databasewaarden.
            if ($attribute === 'catalog_value') {
                expect((float) $value)
                    ->toBe(
                        (float) $newValue->getRawOriginal($attribute)
                    );

                continue;
            }

            // Normaliseer de controlemarkering indien aanwezig.
            if ($attribute === 'needs_review') {
                expect((bool) $value)
                    ->toBe($newValue->needs_review);

                continue;
            }

            expect($value)
                ->toBe($newValue->getRawOriginal($attribute));
        }

        // De catalogusvermelding moet vóór de cataloguswaarde
        // in de wijzigingsgeschiedenis zijn geregistreerd.
        expect($entryLog->id)
            ->toBeLessThan($valueLog->id);

        // Controleer het opgeslagen wijzigingslog
        // van de catalogusvermelding.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $entryLog->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $newStamp->id,
            'stamp_series_id' => null,
            'condition_id' => null,
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $newEntry->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        // Controleer het opgeslagen wijzigingslog
        // van de cataloguswaarde.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $valueLog->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $newStamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $newValue->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        // Het lege gestempelde invoerveld
        // mag geen wijzigingslog opleveren.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'condition_id' => $this->gestempeld->id,
        ]);

        // Er mogen geen wijzigingslogs voor de oorspronkelijke
        // postzegel bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // Er mogen geen geschiedenisbatches
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // De oorspronkelijke postzegel moet volledig behouden blijven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStamp);

        // Alle bestaande catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Alle bestaande cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Controleer de catalogusvermeldingen per postzegel.
        expect($this->stamp->catalogEntries()->count())
            ->toBe(3);

        expect($newStamp->catalogEntries()->count())
            ->toBe(1);

        // Controleer de cataloguswaarden per postzegel.
        expect($this->stamp->catalogValues()->count())
            ->toBe(6);

        expect($newStamp->catalogValues()->count())
            ->toBe(1);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 7);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 2);
    } finally {
        $this->travelBack();
    }
});

test('de backend behoudt de oorspronkelijke gebruikers catalogus en editiesnapshots in de wijzigingsgeschiedenis', function () {
    // Bewaar de oorspronkelijke namen.
    $originalUserName = $this->otherUser->name;

    $originalCatalogName = $this->catalogB->name;

    $originalEditionName = $this->editionB1->edition_name;

    // Bewaar de volledige oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Meld bewust de tweede testgebruiker aan.
    $this->actingAs($this->otherUser);

    // Controleer de aangemelde gebruiker.
    $this->assertAuthenticatedAs($this->otherUser);

    expect($this->otherUser->id)
        ->not->toBe($this->user->id);

    expect($this->otherUser->is_admin)
        ->toBeFalse();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    // Stel de formuliergegevens samen.
    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-600';

    $payload['catalog_values'] = [
        'postfris' => '36.75',
        'gestempeld' => '19.25',
    ];

    // Maak de nieuwe postzegel aan.
    $response = $this->post(
        route('backend.stamps.store'),
        $payload
    );

    // Controleer de HTTP-response.
    $response->assertSessionHasNoErrors();

    $response->assertRedirect(
        route('backend.stamps.index')
    );

    $response->assertSessionHas('success');

    // Controleer de nieuwe postzegel.
    $this->assertDatabaseCount('stamps', 2);

    $newStamp = Stamp::query()
        ->where('id', '!=', $this->stamp->id)
        ->sole();

    expect($newStamp->country_id)
        ->toBe($this->country->id);

    expect($newStamp->issue_year)
        ->toBe(2020);

    // Controleer de nieuwe catalogusvermelding.
    $newEntry = $newStamp->catalogEntries()->sole();

    expect($newEntry->catalog_number)
        ->toBe('B-600');

    expect($newEntry->catalog_edition_id)
        ->toBe($this->editionB1->id);

    // Controleer beide nieuwe cataloguswaarden.
    $newPostfris = $newEntry->values()
        ->where('condition_id', $this->postfris->id)
        ->sole();

    $newGestempeld = $newEntry->values()
        ->where('condition_id', $this->gestempeld->id)
        ->sole();

    expect($newPostfris->catalog_value)
        ->toBe('36.75');

    expect($newGestempeld->catalog_value)
        ->toBe('19.25');

    expect($newPostfris->currency)
        ->toBe('USD');

    expect($newGestempeld->currency)
        ->toBe('USD');

    // Er moet precies één geschiedenisbatch bestaan.
    $this->assertDatabaseCount('catalog_change_batches', 1);

    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de oorspronkelijke geschiedenisbatch.
    expect($batch->operation)
        ->toBe('stamp_create');

    expect($batch->description)
        ->toBe('Catalogusgegevens bij het aanmaken van een postzegel');

    expect($batch->user_id)
        ->toBe($this->otherUser->id);

    expect($batch->user_id)
        ->not->toBe($this->user->id);

    expect($batch->user_name)
        ->toBe($originalUserName);

    expect($batch->catalog_edition_id)
        ->toBe($this->editionB1->id);

    expect($batch->catalog_name)
        ->toBe($originalCatalogName);

    expect($batch->catalog_edition_name)
        ->toBe($originalEditionName);

    // Controleer de oorspronkelijke relaties.
    expect($batch->user->id)
        ->toBe($this->otherUser->id);

    expect($batch->user->name)
        ->toBe($originalUserName);

    expect($batch->catalogEdition->id)
        ->toBe($this->editionB1->id);

    expect($batch->catalogEdition->edition_name)
        ->toBe($originalEditionName);

    expect($batch->catalogEdition->catalog->id)
        ->toBe($this->catalogB->id);

    expect($batch->catalogEdition->catalog->name)
        ->toBe($originalCatalogName);

    // Controleer de opgeslagen oorspronkelijke snapshots.
    $this->assertDatabaseHas('catalog_change_batches', [
        'id' => $batch->id,
        'user_id' => $this->otherUser->id,
        'user_name' => $originalUserName,
        'catalog_edition_id' => $this->editionB1->id,
        'catalog_name' => $originalCatalogName,
        'catalog_edition_name' => $originalEditionName,
        'operation' => 'stamp_create',
    ]);

    // Er moeten precies drie wijzigingslogs bestaan.
    $this->assertDatabaseCount('catalog_change_logs', 3);

    expect($batch->logs()->count())
        ->toBe(3);

    $entryLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_entry')
        ->sole();

    $postfrisLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_value')
        ->where('condition_id', $this->postfris->id)
        ->sole();

    $gestempeldLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_value')
        ->where('condition_id', $this->gestempeld->id)
        ->sole();

    // Controleer de identificatie van de drie wijzigingslogs.
    expect($entryLog->record_id)
        ->toBe($newEntry->id);

    expect($postfrisLog->record_id)
        ->toBe($newPostfris->id);

    expect($gestempeldLog->record_id)
        ->toBe($newGestempeld->id);

    expect($entryLog->new_values['catalog_number'])
        ->toBe('B-600');

    expect((float) $postfrisLog->new_values['catalog_value'])
        ->toBe(36.75);

    expect((float) $gestempeldLog->new_values['catalog_value'])
        ->toBe(19.25);

    $logs = [
        $entryLog,
        $postfrisLog,
        $gestempeldLog,
    ];

    // Controleer de gemeenschappelijke loggegevens.
    foreach ($logs as $log) {
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($newStamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->action)
            ->toBe('created');

        expect($log->old_values)
            ->toBeNull();

        expect($log->new_values)
            ->toBeArray();
    }

    // Bewaar de volledige oorspronkelijke geschiedenisbatch.
    $originalBatch = $batch
        ->fresh()
        ->getAttributes();

    // Bewaar de volledige oorspronkelijke wijzigingslogs.
    $originalLogs = [];

    foreach ($logs as $log) {
        $originalLogs[$log->id] = $log
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de volledige nieuwe postzegel en catalogusgegevens.
    $originalNewStamp = $newStamp
        ->fresh()
        ->getAttributes();

    $originalNewEntry = $newEntry
        ->fresh()
        ->getAttributes();

    $originalNewPostfris = $newPostfris
        ->fresh()
        ->getAttributes();

    $originalNewGestempeld = $newGestempeld
        ->fresh()
        ->getAttributes();

    // Er mag geen actieve geschiedenisbatch achterblijven.
    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Definieer de nieuwe namen.
    $newUserName = 'Gewijzigde testgebruiker';

    $newCatalogName = 'Gewijzigde catalogus B';

    $newEditionName = 'Gewijzigde editie 2026';

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan de geschiedenisrecords te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig de naam van de gebruiker.
        $this->otherUser->update([
            'name' => $newUserName,
        ]);

        // Wijzig de naam van de catalogus.
        $this->catalogB->update([
            'name' => $newCatalogName,
        ]);

        // Wijzig de naam van de cataloguseditie.
        $this->editionB1->update([
            'edition_name' => $newEditionName,
        ]);

        // Controleer dat de nieuwe namen daadwerkelijk zijn opgeslagen.
        $this->assertDatabaseHas('users', [
            'id' => $this->otherUser->id,
            'name' => $newUserName,
        ]);

        $this->assertDatabaseHas('catalogs', [
            'id' => $this->catalogB->id,
            'name' => $newCatalogName,
        ]);

        $this->assertDatabaseHas('catalog_editions', [
            'id' => $this->editionB1->id,
            'edition_name' => $newEditionName,
        ]);

        // Controleer dat de oorspronkelijke namen niet meer
        // in de actuele records aanwezig zijn.
        $this->assertDatabaseMissing('users', [
            'id' => $this->otherUser->id,
            'name' => $originalUserName,
        ]);

        $this->assertDatabaseMissing('catalogs', [
            'id' => $this->catalogB->id,
            'name' => $originalCatalogName,
        ]);

        $this->assertDatabaseMissing('catalog_editions', [
            'id' => $this->editionB1->id,
            'edition_name' => $originalEditionName,
        ]);

        // Haal de geschiedenisbatch opnieuw uit de database.
        $freshBatch = $batch->fresh();

        expect($freshBatch)->not->toBeNull();

        // De volledige geschiedenisbatch moet ongewijzigd blijven.
        expect($freshBatch->getAttributes())
            ->toBe($originalBatch);

        // Controleer expliciet de historische snapshots.
        expect($freshBatch->user_name)
            ->toBe($originalUserName);

        expect($freshBatch->catalog_name)
            ->toBe($originalCatalogName);

        expect($freshBatch->catalog_edition_name)
            ->toBe($originalEditionName);

        // De snapshots mogen niet de nieuwe namen bevatten.
        expect($freshBatch->user_name)
            ->not->toBe($newUserName);

        expect($freshBatch->catalog_name)
            ->not->toBe($newCatalogName);

        expect($freshBatch->catalog_edition_name)
            ->not->toBe($newEditionName);

        // Controleer dat de identificatiegegevens behouden blijven.
        expect($freshBatch->user_id)
            ->toBe($this->otherUser->id);

        expect($freshBatch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        // De relaties moeten wel de actuele namen weergeven.
        expect($freshBatch->user->name)
            ->toBe($newUserName);

        expect($freshBatch->catalogEdition->edition_name)
            ->toBe($newEditionName);

        expect($freshBatch->catalogEdition->catalog->name)
            ->toBe($newCatalogName);

        // Controleer de oorspronkelijke snapshots in de database.
        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'user_id' => $this->otherUser->id,
            'user_name' => $originalUserName,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_name' => $originalCatalogName,
            'catalog_edition_name' => $originalEditionName,
        ]);

        // De nieuwe namen mogen niet in de bestaande
        // geschiedenisbatch zijn terechtgekomen.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $batch->id,
            'user_name' => $newUserName,
        ]);

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_name' => $newCatalogName,
        ]);

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_name' => $newEditionName,
        ]);

        // Alle oorspronkelijke wijzigingslogs
        // moeten volledig ongewijzigd blijven.
        foreach ($logs as $log) {
            $freshLog = $log->fresh();

            expect($freshLog)->not->toBeNull();

            expect($freshLog->getAttributes())
                ->toBe($originalLogs[$log->id]);

            expect($freshLog->batch->id)
                ->toBe($batch->id);

            // Controleer de historische namen via de batchrelatie.
            expect($freshLog->batch->user_name)
                ->toBe($originalUserName);

            expect($freshLog->batch->catalog_name)
                ->toBe($originalCatalogName);

            expect($freshLog->batch->catalog_edition_name)
                ->toBe($originalEditionName);

            // Controleer de actuele namen via de editierelatie.
            expect($freshLog->catalogEdition->edition_name)
                ->toBe($newEditionName);

            expect($freshLog->catalogEdition->catalog->name)
                ->toBe($newCatalogName);
        }

        // De oorspronkelijke postzegel moet volledig behouden blijven.
        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStamp);

        // Alle oorspronkelijke catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);
        }

        // Alle oorspronkelijke cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);
        }

        // Ook de nieuwe postzegel en catalogusgegevens
        // moeten volledig ongewijzigd blijven.
        expect($newStamp->fresh()->getAttributes())
            ->toBe($originalNewStamp);

        expect($newEntry->fresh()->getAttributes())
            ->toBe($originalNewEntry);

        expect($newPostfris->fresh()->getAttributes())
            ->toBe($originalNewPostfris);

        expect($newGestempeld->fresh()->getAttributes())
            ->toBe($originalNewGestempeld);

        // Er mag geen geschiedenisbatch voor de eerste gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->user->id,
        ]);

        // Er mogen geen wijzigingslogs voor de oorspronkelijke
        // postzegel bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // Er mogen geen geschiedenisbatches
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);

        expect($freshBatch->logs()->count())
            ->toBe(3);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert geen wijzigingsgeschiedenis bij het aanmaken van een postzegel met ongeldige catalogusgegevens', function () {
    // Bewaar de volledige oorspronkelijke postzegel.
    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    // Bewaar alle oorspronkelijke postzegelvertalingen.
    $originalTranslations = $this->stamp->translations()
        ->orderBy('id')
        ->get()
        ->map(fn ($translation) => $translation->getAttributes())
        ->all();

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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar alle oorspronkelijke catalogi.
    $catalogs = [
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ];

    $originalCatalogs = [];

    foreach ($catalogs as $catalog) {
        $originalCatalogs[$catalog->id] = $catalog
            ->fresh()
            ->getAttributes();
    }

    // Bewaar alle oorspronkelijke catalogusedities.
    $editions = [
        $this->editionA1,
        $this->editionA2,
        $this->editionB1,
        $this->editionC1,
    ];

    $originalEditions = [];

    foreach ($editions as $edition) {
        $originalEditions[$edition->id] = $edition
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar het oorspronkelijke aantal postzegelvertalingen.
    $originalTranslationCount = Stamp::query()
        ->withCount('translations')
        ->get()
        ->sum('translations_count');

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Stel de formuliergegevens samen.
    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-700';

    $payload['catalog_values'] = [
        'postfris' => '35.75',
        'gestempeld' => '-12.50',
    ];

    // Controleer de geldige en ongeldige cataloguswaarden.
    expect((float) $payload['catalog_values']['postfris'])
        ->toBeGreaterThan(0);

    expect((float) $payload['catalog_values']['gestempeld'])
        ->toBeLessThan(0);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Dien de ongeldige formuliergegevens in.
        $response = $this
            ->from(route('backend.stamps.create'))
            ->post(
                route('backend.stamps.store'),
                $payload
            );

        // De aanvraag moet worden afgewezen.
        $response->assertRedirect(
            route('backend.stamps.create')
        );

        // Controleer de specifieke validatiefout.
        $response->assertSessionHasErrors([
            'catalog_values.gestempeld',
        ]);

        // De geldige postfrisse cataloguswaarde
        // mag geen validatiefout veroorzaken.
        $response->assertSessionDoesntHaveErrors([
            'catalog_values.postfris',
        ]);

        // Ook de overige geldige formuliergegevens
        // mogen geen validatiefouten veroorzaken.
        $response->assertSessionDoesntHaveErrors([
            'country_id',
            'issue_year',
            'translations.nl.name',
            'catalog_id',
            'catalog_edition_id',
            'catalog_number',
        ]);

        // Er mag geen succesmelding aanwezig zijn.
        $response->assertSessionMissing('success');

        // Er mag geen nieuwe postzegel zijn aangemaakt.
        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseMissing('stamps', [
            'name' => 'Nieuwe postzegel catalogusgeschiedenis',
        ]);

        // Er mag geen nieuwe postzegelvertaling zijn aangemaakt.
        $this->assertDatabaseMissing('stamp_translations', [
            'locale' => 'nl',
            'name' => 'Nieuwe postzegel catalogusgeschiedenis',
        ]);

        $this->assertDatabaseCount(
            'stamp_translations',
            $originalTranslationCount
        );

        // Er mag geen nieuwe catalogusvermelding zijn aangemaakt.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-700',
        ]);

        // Er mogen geen nieuwe cataloguswaarden zijn aangemaakt.
        $this->assertDatabaseCount('stamp_catalog_values', 6);

        // Ook de geldige cataloguswaarde mag niet zijn opgeslagen.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'condition_id' => $this->postfris->id,
            'catalog_value' => 35.75,
            'currency' => 'USD',
        ]);

        // De ongeldige cataloguswaarde mag niet zijn opgeslagen.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => -12.50,
            'currency' => 'USD',
        ]);

        // Er mag geen geschiedenisbatch zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        expect(CatalogChangeBatch::query()->count())
            ->toBe(0);

        // Er mogen geen wijzigingslogs zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(CatalogChangeLog::query()->count())
            ->toBe(0);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // De oorspronkelijke postzegel moet volledig behouden blijven.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->getAttributes())
            ->toBe($originalStamp);

        // Alle oorspronkelijke postzegelvertalingen
        // moeten volledig ongewijzigd blijven.
        $freshTranslations = $freshStamp->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        expect($freshTranslations)
            ->toBe($originalTranslations);

        // Alle oorspronkelijke catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // Alle oorspronkelijke cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();
        }

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        foreach ($catalogs as $catalog) {
            $freshCatalog = $catalog->fresh();

            expect($freshCatalog)->not->toBeNull();

            expect($freshCatalog->getAttributes())
                ->toBe($originalCatalogs[$catalog->id]);
        }

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        foreach ($editions as $edition) {
            $freshEdition = $edition->fresh();

            expect($freshEdition)->not->toBeNull();

            expect($freshEdition->getAttributes())
                ->toBe($originalEditions[$edition->id]);
        }

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Controleer de oorspronkelijke catalogusrelaties.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Controleer dat de gebruiker aangemeld is gebleven.
        $this->assertAuthenticatedAs($this->user);

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect($this->history->getActiveBatch())
            ->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het verwijderen van een ongeldige catalogusvermelding na een landwijziging', function () {
    // Verwijder cataloguseditie A2 uit de testvoorbereiding,
    // zodat slechts één ongeldige catalogusvermelding overblijft.
    $this->valueA2Postfris->delete();

    $this->valueA2Gestempeld->delete();

    $this->entryA2->delete();

    // Controleer dat deze voorbereidende verwijderingen
    // geen wijzigingsgeschiedenis hebben veroorzaakt.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Bewaar de oorspronkelijke catalogusvermelding A1.
    $originalEntryA1 = $this->entryA1
        ->fresh()
        ->getAttributes();

    // Bewaar de oorspronkelijke cataloguswaarden van A1.
    $originalPostfrisA1 = $this->valueA1Postfris
        ->fresh()
        ->getAttributes();

    $originalGestempeldA1 = $this->valueA1Gestempeld
        ->fresh()
        ->getAttributes();

    // Bewaar de geldige catalogusvermelding B1.
    $originalEntryB1 = $this->entryB1
        ->fresh()
        ->getAttributes();

    // Bewaar de geldige cataloguswaarden van B1.
    $originalPostfrisB1 = $this->valueB1Postfris
        ->fresh()
        ->getAttributes();

    $originalGestempeldB1 = $this->valueB1Gestempeld
        ->fresh()
        ->getAttributes();

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    // Bewaar de oorspronkelijke catalogi.
    $catalogs = [
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ];

    $originalCatalogs = [];

    foreach ($catalogs as $catalog) {
        $originalCatalogs[$catalog->id] = $catalog
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogusedities.
    $editions = [
        $this->editionA1,
        $this->editionA2,
        $this->editionB1,
        $this->editionC1,
    ];

    $originalEditions = [];

    foreach ($editions as $edition) {
        $originalEditions[$edition->id] = $edition
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 2);

    $this->assertDatabaseCount('stamp_catalog_values', 4);

    expect($this->stamp->country_id)
        ->toBe($this->country->id);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(2);

    expect($this->stamp->catalogValues()->count())
        ->toBe(4);

    // Controleer dat catalogus A niet geldig is
    // voor het nieuwe land.
    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeFalse();

    // Controleer dat catalogus B wel geldig is
    // voor het nieuwe land.
    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['country_id'] = $this->otherCountry->id;

    // Er worden bewust geen catalogusgegevens ingediend.
    // De verwijdering moet uitsluitend via route A plaatsvinden.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan behouden records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig het land van de postzegel.
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

        // Controleer de gewijzigde postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->otherCountry->id);

        expect($freshStamp->country->id)
            ->toBe($this->otherCountry->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // Controleer dat catalogusvermelding A1 is verwijderd.
        expect($this->entryA1->fresh())
            ->toBeNull();

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryA1->id,
        ]);

        // Controleer dat beide cataloguswaarden van A1
        // daadwerkelijk zijn verwijderd.
        expect($this->valueA1Postfris->fresh())
            ->toBeNull();

        expect($this->valueA1Gestempeld->fresh())
            ->toBeNull();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueA1Postfris->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueA1Gestempeld->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryA1->id,
        ]);

        // Controleer dat catalogusvermelding B1 behouden blijft.
        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1)->not->toBeNull();

        expect($freshEntryB1->getAttributes())
            ->toBe($originalEntryB1);

        expect($freshEntryB1->values()->count())
            ->toBe(2);

        // Controleer dat beide cataloguswaarden van B1
        // volledig ongewijzigd blijven.
        $freshPostfrisB1 = $this->valueB1Postfris->fresh();

        $freshGestempeldB1 = $this->valueB1Gestempeld->fresh();

        expect($freshPostfrisB1)->not->toBeNull();

        expect($freshGestempeldB1)->not->toBeNull();

        expect($freshPostfrisB1->getAttributes())
            ->toBe($originalPostfrisB1);

        expect($freshGestempeldB1->getAttributes())
            ->toBe($originalGestempeldB1);

        expect($freshPostfrisB1->needs_review)
            ->toBeFalse();

        expect($freshGestempeldB1->needs_review)
            ->toBeFalse();

        // Er mag uitsluitend catalogusvermelding B1 overblijven.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(1);

        expect($freshStamp->catalogEntries()->sole()->id)
            ->toBe($this->entryB1->id);

        expect($freshStamp->catalogValues()->count())
            ->toBe(2);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogA->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionA1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionA1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogA->name,
            'catalog_edition_name' => $this->editionA1->edition_name,
        ]);

        // Er moeten precies drie wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 3);

        expect($batch->logs()->count())
            ->toBe(3);

        expect(
            $batch->logs()
                ->where('action', 'deleted')
                ->count()
        )->toBe(3);

        expect(
            $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->count()
        )->toBe(1);

        expect(
            $batch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(2);

        // Haal het wijzigingslog van de verwijderde
        // catalogusvermelding op.
        $entryLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->sole();

        // Haal beide wijzigingslogs van de verwijderde
        // cataloguswaarden op.
        $postfrisLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $gestempeldLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        // Controleer de gemeenschappelijke loggegevens.
        foreach ([
            $entryLog,
            $postfrisLog,
            $gestempeldLog,
        ] as $log) {
            expect($log)
                ->toBeInstanceOf(CatalogChangeLog::class);

            expect($log->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($log->catalog_edition_id)
                ->toBe($this->editionA1->id);

            expect($log->stamp_id)
                ->toBe($this->stamp->id);

            expect($log->stamp_series_id)
                ->toBeNull();

            expect($log->action)
                ->toBe('deleted');

            expect($log->old_values)
                ->toBeArray();

            expect($log->new_values)
                ->toBeNull();

            // Controleer de gemeenschappelijke relaties.
            expect($log->batch->id)
                ->toBe($batch->id);

            expect($log->catalogEdition->id)
                ->toBe($this->editionA1->id);

            expect($log->stamp->id)
                ->toBe($this->stamp->id);

            expect($log->stamp->country_id)
                ->toBe($this->otherCountry->id);

            expect($log->stampSeries)
                ->toBeNull();
        }

        // Controleer het wijzigingslog van de verwijderde
        // catalogusvermelding.
        expect($entryLog->record_type)
            ->toBe('stamp_catalog_entry');

        expect($entryLog->record_id)
            ->toBe($this->entryA1->id);

        expect($entryLog->condition_id)
            ->toBeNull();

        expect($entryLog->condition)
            ->toBeNull();

        // Controleer de historische identificatiegegevens.
        expect($entryLog->old_values['id'])
            ->toBe($this->entryA1->id);

        expect($entryLog->old_values['stamp_id'])
            ->toBe($this->stamp->id);

        expect($entryLog->old_values['catalog_edition_id'])
            ->toBe($this->editionA1->id);

        expect($entryLog->old_values['catalog_number'])
            ->toBe('A-100');

        // Controleer dat alle oorspronkelijke attributen
        // van de verwijderde catalogusvermelding aanwezig zijn.
        $loggedEntryAttributes = $entryLog->old_values;

        $expectedEntryAttributes = $originalEntryA1;

        ksort($loggedEntryAttributes);

        ksort($expectedEntryAttributes);

        expect($loggedEntryAttributes)
            ->toBe($expectedEntryAttributes);

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $entryLog->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionA1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => null,
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryA1->id,
            'action' => 'deleted',
            'new_values' => null,
        ]);

        // Definieer de verwachte wijzigingslogs
        // van beide verwijderde cataloguswaarden.
        $expectedValueLogs = [
            [
                $postfrisLog,
                $this->valueA1Postfris,
                $this->postfris,
                $originalPostfrisA1,
                15.00,
            ],
            [
                $gestempeldLog,
                $this->valueA1Gestempeld,
                $this->gestempeld,
                $originalGestempeldA1,
                7.50,
            ],
        ];

        // Controleer beide wijzigingslogs afzonderlijk.
        foreach ($expectedValueLogs as [
            $log,
            $deletedValue,
            $condition,
            $originalAttributes,
            $amount,
        ]) {
            expect($log->record_type)
                ->toBe('stamp_catalog_value');

            expect($log->record_id)
                ->toBe($deletedValue->id);

            expect($log->condition_id)
                ->toBe($condition->id);

            expect($log->condition->id)
                ->toBe($condition->id);

            // Controleer de historische identificatiegegevens.
            expect($log->old_values['id'])
                ->toBe($deletedValue->id);

            expect($log->old_values['stamp_catalog_entry_id'])
                ->toBe($this->entryA1->id);

            expect($log->old_values['condition_id'])
                ->toBe($condition->id);

            // Controleer de oorspronkelijke cataloguswaarde.
            expect($log->old_values)
                ->toHaveKey('catalog_value');

            expect(is_numeric($log->old_values['catalog_value']))
                ->toBeTrue();

            expect((float) $log->old_values['catalog_value'])
                ->toBe($amount);

            expect($log->old_values['currency'])
                ->toBe('EUR');

            // Controleer de oorspronkelijke controlemarkering.
            expect($log->old_values)
                ->toHaveKey('needs_review');

            expect(
                in_array(
                    $log->old_values['needs_review'],
                    [false, 0],
                    true
                )
            )->toBeTrue();

            // Controleer dat alle oorspronkelijke attributen
            // in het wijzigingslog aanwezig zijn.
            $loggedAttributes = $log->old_values;

            $expectedAttributes = $originalAttributes;

            // Normaliseer numerieke waarden vanwege
            // mogelijke verschillen tussen SQLite en JSON.
            $loggedAttributes['catalog_value'] =
                (float) $loggedAttributes['catalog_value'];

            $expectedAttributes['catalog_value'] =
                (float) $expectedAttributes['catalog_value'];

            $loggedAttributes['needs_review'] =
                (bool) $loggedAttributes['needs_review'];

            $expectedAttributes['needs_review'] =
                (bool) $expectedAttributes['needs_review'];

            // De volgorde van JSON-attributen is niet relevant.
            ksort($loggedAttributes);

            ksort($expectedAttributes);

            expect($loggedAttributes)
                ->toBe($expectedAttributes);

            // Controleer het opgeslagen wijzigingslog.
            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $log->id,
                'catalog_change_batch_id' => $batch->id,
                'catalog_edition_id' => $this->editionA1->id,
                'stamp_id' => $this->stamp->id,
                'stamp_series_id' => null,
                'condition_id' => $condition->id,
                'record_type' => 'stamp_catalog_value',
                'record_id' => $deletedValue->id,
                'action' => 'deleted',
                'new_values' => null,
            ]);

            // De historische identificatie moet behouden blijven,
            // ondanks het verwijderen van de cataloguswaarde.
            expect(
                StampCatalogValue::query()
                    ->whereKey($log->record_id)
                    ->exists()
            )->toBeFalse();

            // De cataloguswaarde moet vóór de catalogusvermelding
            // als verwijderd zijn geregistreerd.
            expect($log->id)
                ->toBeLessThan($entryLog->id);
        }

        // De historische identificatie van de verwijderde
        // catalogusvermelding moet eveneens behouden blijven.
        expect(
            StampCatalogEntry::query()
                ->whereKey($entryLog->record_id)
                ->exists()
        )->toBeFalse();

        // Beide verwijderde cataloguswaarden moeten
        // afzonderlijke wijzigingslogs hebben.
        expect($postfrisLog->id)
            ->not->toBe($gestempeldLog->id);

        // Er mogen geen wijzigingslogs voor de behouden
        // catalogusvermelding B1 bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
        ]);

        // Er mogen geen wijzigingslogs voor de behouden
        // cataloguswaarden van B1 bestaan.
        foreach ([
            $this->valueB1Postfris,
            $this->valueB1Gestempeld,
        ] as $value) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        foreach ($catalogs as $catalog) {
            $freshCatalog = $catalog->fresh();

            expect($freshCatalog)->not->toBeNull();

            expect($freshCatalog->getAttributes())
                ->toBe($originalCatalogs[$catalog->id]);
        }

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        foreach ($editions as $edition) {
            $freshEdition = $edition->fresh();

            expect($freshEdition)->not->toBeNull();

            expect($freshEdition->getAttributes())
                ->toBe($originalEditions[$edition->id]);
        }

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 1);

        $this->assertDatabaseCount('stamp_catalog_values', 2);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert afzonderlijke geschiedenisbatches bij het verwijderen van meerdere ongeldige catalogusvermeldingen na een landwijziging', function () {
    // Definieer de catalogusedities waarvan de vermeldingen
    // en cataloguswaarden moeten worden verwijderd.
    $invalidEditions = [
        [
            'edition' => $this->editionA1,
            'entry' => $this->entryA1,
            'values' => [
                $this->valueA1Postfris,
                $this->valueA1Gestempeld,
            ],
        ],
        [
            'edition' => $this->editionA2,
            'entry' => $this->entryA2,
            'values' => [
                $this->valueA2Postfris,
                $this->valueA2Gestempeld,
            ],
        ],
    ];

    // Bewaar alle oorspronkelijke catalogusvermeldingen
    // die moeten worden verwijderd.
    $originalEntries = [];

    // Bewaar alle oorspronkelijke cataloguswaarden
    // die moeten worden verwijderd.
    $originalValues = [];

    foreach ($invalidEditions as $data) {
        $entry = $data['entry'];

        $originalEntries[$entry->id] = $entry
            ->fresh()
            ->getAttributes();

        foreach ($data['values'] as $value) {
            $originalValues[$value->id] = $value
                ->fresh()
                ->getAttributes();
        }
    }

    // Bewaar de geldige catalogusvermelding B1.
    $originalEntryB1 = $this->entryB1
        ->fresh()
        ->getAttributes();

    // Bewaar beide geldige cataloguswaarden van B1.
    $originalPostfrisB1 = $this->valueB1Postfris
        ->fresh()
        ->getAttributes();

    $originalGestempeldB1 = $this->valueB1Gestempeld
        ->fresh()
        ->getAttributes();

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->stamp->country_id)
        ->toBe($this->country->id);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    expect($this->stamp->catalogValues()->count())
        ->toBe(6);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Controleer dat catalogus A niet geldig is
    // voor het nieuwe land.
    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeFalse();

    // Controleer dat catalogus B wel geldig is
    // voor het nieuwe land.
    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['country_id'] = $this->otherCountry->id;

    // Er worden bewust geen catalogusgegevens ingediend.
    // Alle verwijderingen moeten via route A plaatsvinden.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan behouden records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig het land van de postzegel.
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

        // Controleer de gewijzigde postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->otherCountry->id);

        expect($freshStamp->country->id)
            ->toBe($this->otherCountry->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // Controleer dat beide ongeldige catalogusvermeldingen
        // en alle vier bijbehorende waarden zijn verwijderd.
        foreach ($invalidEditions as $data) {
            $entry = $data['entry'];

            expect($entry->fresh())
                ->toBeNull();

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $entry->id,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $entry->id,
            ]);

            foreach ($data['values'] as $value) {
                expect($value->fresh())
                    ->toBeNull();

                $this->assertDatabaseMissing('stamp_catalog_values', [
                    'id' => $value->id,
                ]);
            }
        }

        // Controleer dat catalogusvermelding B1
        // volledig ongewijzigd blijft.
        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1)->not->toBeNull();

        expect($freshEntryB1->getAttributes())
            ->toBe($originalEntryB1);

        expect($freshEntryB1->values()->count())
            ->toBe(2);

        // Controleer beide behouden cataloguswaarden.
        $freshPostfrisB1 = $this->valueB1Postfris->fresh();

        $freshGestempeldB1 = $this->valueB1Gestempeld->fresh();

        expect($freshPostfrisB1)->not->toBeNull();

        expect($freshGestempeldB1)->not->toBeNull();

        expect($freshPostfrisB1->getAttributes())
            ->toBe($originalPostfrisB1);

        expect($freshGestempeldB1->getAttributes())
            ->toBe($originalGestempeldB1);

        expect($freshPostfrisB1->needs_review)
            ->toBeFalse();

        expect($freshGestempeldB1->needs_review)
            ->toBeFalse();

        // Er mag uitsluitend catalogusvermelding B1 overblijven.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(1);

        expect($freshStamp->catalogEntries()->sole()->id)
            ->toBe($this->entryB1->id);

        expect($freshStamp->catalogValues()->count())
            ->toBe(2);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moeten precies twee geschiedenisbatches bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 2);

        // Er moeten precies zes wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 6);

        // Haal beide afzonderlijke geschiedenisbatches op.
        $batchA1 = CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->editionA1->id)
            ->sole();

        $batchA2 = CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->editionA2->id)
            ->sole();

        // Beide catalogusedities moeten een eigen batch hebben.
        expect($batchA1->id)
            ->not->toBe($batchA2->id);

        // Koppel de verwachte batches aan de catalogusedities.
        $expectedBatches = [
            $this->editionA1->id => $batchA1,
            $this->editionA2->id => $batchA2,
        ];

        // Controleer beide geschiedenisbatches afzonderlijk.
        foreach ($invalidEditions as $data) {
            $edition = $data['edition'];

            $entry = $data['entry'];

            $batch = $expectedBatches[$edition->id];

            // Controleer de geschiedenisbatch.
            expect($batch->catalog_edition_id)
                ->toBe($edition->id);

            expect($batch->user_id)
                ->toBe($this->user->id);

            expect($batch->user_name)
                ->toBe($this->user->name);

            expect($batch->operation)
                ->toBe('stamp_update');

            expect($batch->description)
                ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

            expect($batch->catalog_name)
                ->toBe($this->catalogA->name);

            expect($batch->catalog_edition_name)
                ->toBe($edition->edition_name);

            // Controleer de relaties van de geschiedenisbatch.
            expect($batch->catalogEdition->id)
                ->toBe($edition->id);

            expect($batch->user->id)
                ->toBe($this->user->id);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $batch->id,
                'catalog_edition_id' => $edition->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogA->name,
                'catalog_edition_name' => $edition->edition_name,
            ]);

            // Iedere batch moet precies drie wijzigingslogs bevatten.
            expect($batch->logs()->count())
                ->toBe(3);

            expect(
                $batch->logs()
                    ->where('action', 'deleted')
                    ->count()
            )->toBe(3);

            expect(
                $batch->logs()
                    ->where('record_type', 'stamp_catalog_entry')
                    ->count()
            )->toBe(1);

            expect(
                $batch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->count()
            )->toBe(2);

            // Controleer de gemeenschappelijke gegevens
            // van alle wijzigingslogs binnen deze batch.
            foreach ($batch->logs()->get() as $log) {
                expect($log)
                    ->toBeInstanceOf(CatalogChangeLog::class);

                expect($log->catalog_change_batch_id)
                    ->toBe($batch->id);

                expect($log->catalog_edition_id)
                    ->toBe($edition->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('deleted');

                expect($log->old_values)
                    ->toBeArray();

                expect($log->new_values)
                    ->toBeNull();

                // Controleer de gemeenschappelijke relaties.
                expect($log->batch->id)
                    ->toBe($batch->id);

                expect($log->catalogEdition->id)
                    ->toBe($edition->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stamp->country_id)
                    ->toBe($this->otherCountry->id);

                expect($log->stampSeries)
                    ->toBeNull();
            }

            // Haal het wijzigingslog van de verwijderde
            // catalogusvermelding op.
            $entryLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            expect($entryLog->record_id)
                ->toBe($entry->id);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->condition)
                ->toBeNull();

            // Controleer de historische identificatiegegevens.
            expect($entryLog->old_values['id'])
                ->toBe($entry->id);

            expect($entryLog->old_values['stamp_id'])
                ->toBe($this->stamp->id);

            expect($entryLog->old_values['catalog_edition_id'])
                ->toBe($edition->id);

            expect($entryLog->old_values['catalog_number'])
                ->toBe($originalEntries[$entry->id]['catalog_number']);

            // Vergelijk alle oorspronkelijke attributen
            // van de verwijderde catalogusvermelding.
            $loggedEntryAttributes = $entryLog->old_values;

            $expectedEntryAttributes = $originalEntries[$entry->id];

            ksort($loggedEntryAttributes);

            ksort($expectedEntryAttributes);

            expect($loggedEntryAttributes)
                ->toBe($expectedEntryAttributes);

            // Controleer het opgeslagen wijzigingslog.
            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $entryLog->id,
                'catalog_change_batch_id' => $batch->id,
                'catalog_edition_id' => $edition->id,
                'stamp_id' => $this->stamp->id,
                'stamp_series_id' => null,
                'condition_id' => null,
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
                'action' => 'deleted',
                'new_values' => null,
            ]);

            // Het oorspronkelijke record bestaat niet meer,
            // maar de historische identificatie blijft behouden.
            expect(
                StampCatalogEntry::query()
                    ->whereKey($entryLog->record_id)
                    ->exists()
            )->toBeFalse();

            // Controleer beide verwijderde cataloguswaarden
            // binnen de huidige cataloguseditie.
            foreach ($data['values'] as $deletedValue) {
                $originalAttributes = $originalValues[$deletedValue->id];

                $valueLog = $batch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $deletedValue->id)
                    ->sole();

                expect($valueLog->record_id)
                    ->toBe($deletedValue->id);

                expect($valueLog->condition_id)
                    ->toBe($deletedValue->condition_id);

                expect($valueLog->condition->id)
                    ->toBe($deletedValue->condition_id);

                // Controleer de historische identificatiegegevens.
                expect($valueLog->old_values['id'])
                    ->toBe($deletedValue->id);

                expect($valueLog->old_values['stamp_catalog_entry_id'])
                    ->toBe($entry->id);

                expect($valueLog->old_values['condition_id'])
                    ->toBe($deletedValue->condition_id);

                // Controleer de oorspronkelijke cataloguswaarde.
                expect($valueLog->old_values)
                    ->toHaveKey('catalog_value');

                expect(is_numeric($valueLog->old_values['catalog_value']))
                    ->toBeTrue();

                expect((float) $valueLog->old_values['catalog_value'])
                    ->toBe((float) $originalAttributes['catalog_value']);

                expect($valueLog->old_values['currency'])
                    ->toBe('EUR');

                // Controleer de oorspronkelijke controlemarkering.
                expect($valueLog->old_values)
                    ->toHaveKey('needs_review');

                expect(
                    in_array(
                        $valueLog->old_values['needs_review'],
                        [false, 0],
                        true
                    )
                )->toBeTrue();

                // Vergelijk alle oorspronkelijke attributen.
                $loggedAttributes = $valueLog->old_values;

                $expectedAttributes = $originalAttributes;

                // Normaliseer numerieke databasewaarden.
                $loggedAttributes['catalog_value'] =
                    (float) $loggedAttributes['catalog_value'];

                $expectedAttributes['catalog_value'] =
                    (float) $expectedAttributes['catalog_value'];

                $loggedAttributes['needs_review'] =
                    (bool) $loggedAttributes['needs_review'];

                $expectedAttributes['needs_review'] =
                    (bool) $expectedAttributes['needs_review'];

                // De volgorde van JSON-attributen is niet relevant.
                ksort($loggedAttributes);

                ksort($expectedAttributes);

                expect($loggedAttributes)
                    ->toBe($expectedAttributes);

                // Controleer het opgeslagen wijzigingslog.
                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $valueLog->id,
                    'catalog_change_batch_id' => $batch->id,
                    'catalog_edition_id' => $edition->id,
                    'stamp_id' => $this->stamp->id,
                    'stamp_series_id' => null,
                    'condition_id' => $deletedValue->condition_id,
                    'record_type' => 'stamp_catalog_value',
                    'record_id' => $deletedValue->id,
                    'action' => 'deleted',
                    'new_values' => null,
                ]);

                // Het oorspronkelijke record bestaat niet meer.
                expect(
                    StampCatalogValue::query()
                        ->whereKey($valueLog->record_id)
                        ->exists()
                )->toBeFalse();

                // De cataloguswaarde moet vóór de catalogusvermelding
                // als verwijderd zijn geregistreerd.
                expect($valueLog->id)
                    ->toBeLessThan($entryLog->id);
            }

            // Er mogen geen logs van andere catalogusedities
            // binnen deze geschiedenisbatch bestaan.
            expect(
                $batch->logs()
                    ->where('catalog_edition_id', '!=', $edition->id)
                    ->count()
            )->toBe(0);
        }

        // Controleer de totale verdeling van de wijzigingslogs.
        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_entry')
                ->count()
        )->toBe(2);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(4);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'deleted')
                ->count()
        )->toBe(6);

        // Er mogen geen created- of updated-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'updated',
        ]);

        // Er mogen geen wijzigingslogs voor de behouden
        // catalogusvermelding B1 bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
        ]);

        // Er mogen geen wijzigingslogs voor de behouden
        // cataloguswaarden van B1 bestaan.
        foreach ([
            $this->valueB1Postfris,
            $this->valueB1Gestempeld,
        ] as $value) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor catalogusedities B1 en C1 bestaan.
        foreach ([
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 1);

        $this->assertDatabaseCount('stamp_catalog_values', 2);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 2);

        $this->assertDatabaseCount('catalog_change_logs', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend behoudt alle geldige catalogusvermeldingen zonder wijzigingsgeschiedenis na een landwijziging', function () {
    // Maak catalogus A ook geldig voor het nieuwe land.
    $this->catalogA->countries()->attach(
        $this->otherCountry->id
    );

    // Controleer dat beide catalogi geldig zijn
    // voor het oorspronkelijke en het nieuwe land.
    foreach ([
        $this->catalogA,
        $this->catalogB,
    ] as $catalog) {
        foreach ([
            $this->country,
            $this->otherCountry,
        ] as $country) {
            expect(
                $catalog->countries()
                    ->where('countries.id', $country->id)
                    ->exists()
            )->toBeTrue();
        }
    }

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    // Verzamel alle bestaande catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
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

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar alle oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar de bestaande landkoppelingen per catalogus.
    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] = $catalog
            ->countries()
            ->orderBy('countries.id')
            ->pluck('countries.id')
            ->all();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->stamp->country_id)
        ->toBe($this->country->id);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    expect($this->stamp->catalogValues()->count())
        ->toBe(6);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['country_id'] = $this->otherCountry->id;

    // Er worden bewust geen catalogusgegevens ingediend.
    // Alleen route A mag worden uitgevoerd.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan behouden records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig het land van de postzegel.
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

        // Controleer de gewijzigde postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->otherCountry->id);

        expect($freshStamp->country->id)
            ->toBe($this->otherCountry->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // Alle oorspronkelijke catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // De gekoppelde catalogus moet geldig zijn
            // voor het nieuwe land van de postzegel.
            expect(
                $freshEntry->catalogEdition->catalog
                    ->countries()
                    ->where('countries.id', $this->otherCountry->id)
                    ->exists()
            )->toBeTrue();

            // Er mag geen wijzigingslog voor deze
            // catalogusvermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle oorspronkelijke cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze
            // cataloguswaarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer dat alle drie catalogusvermeldingen
        // nog steeds aan dezelfde postzegel gekoppeld zijn.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Controleer dat alle zes cataloguswaarden behouden zijn.
        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Alle landkoppelingen van de catalogi
        // moeten volledig ongewijzigd blijven.
        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // Er mag geen geschiedenisbatch zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        expect(CatalogChangeBatch::query()->count())
            ->toBe(0);

        // Er mogen geen wijzigingslogs zijn aangemaakt.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(CatalogChangeLog::query()->count())
            ->toBe(0);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor een van de vier catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het aanmaken van een nieuwe catalogusvermelding via de oorspronkelijke invoerstructuur', function () {
    // Maak catalogus C ook geldig voor het huidige land.
    $this->catalogC->countries()->attach(
        $this->country->id
    );

    // Controleer dat alle drie catalogi geldig zijn
    // voor het huidige land van de postzegel.
    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        expect(
            $catalog->countries()
                ->where('countries.id', $this->country->id)
                ->exists()
        )->toBeTrue();
    }

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    // Verzamel alle bestaande catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
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

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar alle oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Er mag nog geen catalogusvermelding voor C1 bestaan.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    expect($this->stamp->catalogValues()->count())
        ->toBe(6);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_edition_id'] = $this->editionC1->id;

    $payload['catalog_number'] = 'C-100';

    // Er worden bewust geen cataloguswaarden ingediend.
    // De verwerking moet uitsluitend via route B plaatsvinden.
    expect($payload)
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    // Het land van de postzegel blijft ongewijzigd.
    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Voeg de nieuwe catalogusvermelding toe.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Er moet precies één nieuwe catalogusvermelding bestaan.
        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $newEntry = $freshStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionC1->id)
            ->sole();

        expect($newEntry)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect($newEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($newEntry->catalog_edition_id)
            ->toBe($this->editionC1->id);

        expect($newEntry->catalog_number)
            ->toBe('C-100');

        // Controleer de relaties van de nieuwe vermelding.
        expect($newEntry->stamp->id)
            ->toBe($this->stamp->id);

        expect($newEntry->catalogEdition->id)
            ->toBe($this->editionC1->id);

        expect($newEntry->catalogEdition->catalog->id)
            ->toBe($this->catalogC->id);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $newEntry->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
            'catalog_number' => 'C-100',
        ]);

        // De nieuwe vermelding mag geen bestaande vermelding zijn.
        expect(
            array_key_exists(
                $newEntry->id,
                $originalEntries
            )
        )->toBeFalse();

        // Er mogen geen nieuwe cataloguswaarden ontstaan.
        expect($newEntry->values()->count())
            ->toBe(0);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $newEntry->id,
        ]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(4);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Alle oorspronkelijke catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen geschiedenis voor deze vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle oorspronkelijke cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen geschiedenis voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionC1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogC->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionC1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionC1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionC1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogC->name,
            'catalog_edition_name' => $this->editionC1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de identificatiegegevens van het log.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionC1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBeNull();

        expect($log->record_type)
            ->toBe('stamp_catalog_entry');

        expect($log->record_id)
            ->toBe($newEntry->id);

        expect($log->action)
            ->toBe('created');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionC1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->stampSeries)
            ->toBeNull();

        expect($log->condition)
            ->toBeNull();

        // Bij een nieuw record bestaan geen oorspronkelijke waarden.
        expect($log->old_values)
            ->toBeNull();

        // De nieuwe waarden moeten als array zijn opgeslagen.
        expect($log->new_values)
            ->toBeArray();

        // Controleer alle verplichte attributen.
        expect($log->new_values)
            ->toHaveKeys([
                'id',
                'stamp_id',
                'catalog_edition_id',
                'catalog_number',
                'created_at',
                'updated_at',
            ]);

        expect($log->new_values['id'])
            ->toBe($newEntry->id);

        expect($log->new_values['stamp_id'])
            ->toBe($this->stamp->id);

        expect($log->new_values['catalog_edition_id'])
            ->toBe($this->editionC1->id);

        expect($log->new_values['catalog_number'])
            ->toBe('C-100');

        // Vergelijk alle geregistreerde attributen
        // met de daadwerkelijk opgeslagen catalogusvermelding.
        $freshEntryAttributes = $newEntry
            ->fresh()
            ->getAttributes();

        foreach ($log->new_values as $attribute => $value) {
            expect($freshEntryAttributes)
                ->toHaveKey($attribute);

            expect($value)
                ->toBe($freshEntryAttributes[$attribute]);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionC1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => null,
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $newEntry->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        // Er mogen geen wijzigingslogs voor cataloguswaarden bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
        ]);

        // Er mogen geen updated- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'updated',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het wijzigen van een bestaand catalogusnummer via de oorspronkelijke invoerstructuur', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    // Verzamel alle bestaande catalogusvermeldingen.
    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
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

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar alle oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryA1->catalog_number)
        ->toBe('A-100');

    expect($this->entryA1->values()->count())
        ->toBe(2);

    // Controleer dat de catalogus geldig is
    // voor het huidige land van de postzegel.
    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-150';

    // Er worden bewust geen cataloguswaarden ingediend.
    // Alleen het bestaande catalogusnummer wordt gewijzigd.
    expect($payload)
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om timestampwijzigingen
    // aan bestaande records te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig het bestaande catalogusnummer.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Controleer de gewijzigde catalogusvermelding A1.
        $freshEntryA1 = $this->entryA1->fresh();

        expect($freshEntryA1)->not->toBeNull();

        expect($freshEntryA1->id)
            ->toBe($this->entryA1->id);

        expect($freshEntryA1->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntryA1->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($freshEntryA1->catalog_number)
            ->toBe('A-150');

        expect($freshEntryA1->values()->count())
            ->toBe(2);

        // De oorspronkelijke aanmaaktijd moet behouden blijven.
        expect($freshEntryA1->getRawOriginal('created_at'))
            ->toBe(
                $originalEntries[$this->entryA1->id]['created_at']
            );

        // De wijzigingstijd moet daadwerkelijk zijn aangepast.
        expect($freshEntryA1->getRawOriginal('updated_at'))
            ->not->toBe(
                $originalEntries[$this->entryA1->id]['updated_at']
            );

        // Controleer dat uitsluitend het catalogusnummer
        // en de wijzigingstijd zijn aangepast.
        $expectedEntryAttributes =
            $originalEntries[$this->entryA1->id];

        $expectedEntryAttributes['catalog_number'] = 'A-150';

        $expectedEntryAttributes['updated_at'] =
            $freshEntryA1->getRawOriginal('updated_at');

        expect($freshEntryA1->getAttributes())
            ->toBe($expectedEntryAttributes);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryA1->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionA1->id,
            'catalog_number' => 'A-150',
        ]);

        // Het oorspronkelijke catalogusnummer mag niet meer
        // aanwezig zijn bij dezelfde catalogusvermelding.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryA1->id,
            'catalog_number' => 'A-100',
        ]);

        // Beide overige catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ([
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor deze vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle zes oorspronkelijke cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer dat alle oorspronkelijke vermeldingen
        // en waarden nog steeds aan de postzegel gekoppeld zijn.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogA->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionA1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionA1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogA->name,
            'catalog_edition_name' => $this->editionA1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de identificatiegegevens van het log.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBeNull();

        expect($log->record_type)
            ->toBe('stamp_catalog_entry');

        expect($log->record_id)
            ->toBe($this->entryA1->id);

        expect($log->action)
            ->toBe('updated');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->stampSeries)
            ->toBeNull();

        expect($log->condition)
            ->toBeNull();

        // Zowel de oorspronkelijke als de nieuwe waarden
        // moeten als array zijn opgeslagen.
        expect($log->old_values)
            ->toBeArray();

        expect($log->new_values)
            ->toBeArray();

        // Controleer de volledige oorspronkelijke waarden.
        // Uitsluitend het gewijzigde attribuut is toegestaan.
        expect($log->old_values)
            ->toBe([
                'catalog_number' => 'A-100',
            ]);

        // Controleer de volledige nieuwe waarden.
        expect($log->new_values)
            ->toBe([
                'catalog_number' => 'A-150',
            ]);

        // Beide arrays mogen slechts één attribuut bevatten.
        expect($log->old_values)
            ->toHaveCount(1);

        expect($log->new_values)
            ->toHaveCount(1);

        // Controleer dat de oorspronkelijke waarde
        // overeenkomt met de oorspronkelijke databasewaarde.
        expect($log->old_values['catalog_number'])
            ->toBe(
                $originalEntries[$this->entryA1->id]['catalog_number']
            );

        // Controleer dat de nieuwe waarde overeenkomt
        // met de daadwerkelijk opgeslagen databasewaarde.
        expect($log->new_values['catalog_number'])
            ->toBe($freshEntryA1->catalog_number);

        // Ongewijzigde attributen en timestamps
        // mogen niet in het wijzigingslog voorkomen.
        foreach ([
            'id',
            'stamp_id',
            'catalog_edition_id',
            'notes',
            'created_at',
            'updated_at',
        ] as $attribute) {
            expect($log->old_values)
                ->not->toHaveKey($attribute);

            expect($log->new_values)
                ->not->toHaveKey($attribute);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionA1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => null,
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryA1->id,
            'action' => 'updated',
        ]);

        // Er mogen geen wijzigingslogs voor cataloguswaarden bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
        ]);

        // Er mogen geen created- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het aanmaken van een nieuwe cataloguswaarde via de oorspronkelijke invoerstructuur', function () {
    // Verwijder de bestaande postfrisse cataloguswaarde van B1.
    // Hierdoor ontstaat een ontbrekende waarderingscategorie.
    $this->valueB1Postfris->delete();

    // De voorbereidende verwijdering mag geen geschiedenis veroorzaken.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel de vijf resterende cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 5);

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(1);

    expect($this->editionB1->currency)
        ->toBe('USD');

    // Er mag geen postfrisse cataloguswaarde voor B1 bestaan.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
    ]);

    // De gestempelde cataloguswaarde moet wel aanwezig zijn.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $this->valueB1Gestempeld->id,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 12.50,
        'currency' => 'USD',
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-100';

    $payload['catalog_values'] = [
        'postfris' => '32.75',
    ];

    // Er worden uitsluitend gegevens voor route B ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    expect($payload['catalog_values'])
        ->not->toHaveKey('gestempeld');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Voeg de ontbrekende cataloguswaarde toe.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Er mag geen nieuwe catalogusvermelding zijn aangemaakt.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        // Er moet precies één nieuwe cataloguswaarde bestaan.
        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $newValue = $this->entryB1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($newValue)
            ->toBeInstanceOf(StampCatalogValue::class);

        expect($newValue->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($newValue->condition_id)
            ->toBe($this->postfris->id);

        expect(is_numeric($newValue->catalog_value))
            ->toBeTrue();

        expect((float) $newValue->catalog_value)
            ->toBe(32.75);

        expect($newValue->currency)
            ->toBe('USD');

        expect($newValue->needs_review)
            ->toBeFalse();

        // Controleer de relaties van de nieuwe cataloguswaarde.
        expect($newValue->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($newValue->catalogEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($newValue->catalogEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($newValue->condition->id)
            ->toBe($this->postfris->id);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $newValue->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 32.75,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // De nieuwe waarde mag niet overeenkomen
        // met een van de vijf behouden cataloguswaarden.
        expect(
            array_key_exists($newValue->id, $originalValues)
        )->toBeFalse();

        // Alle catalogusvermeldingen moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // updateOrCreate kan de wijzigingstijd van B1 aanpassen,
            // ook wanneer het catalogusnummer ongewijzigd blijft.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vijf oorspronkelijke cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBe($this->postfris->id);

        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        expect($log->record_id)
            ->toBe($newValue->id);

        expect($log->action)
            ->toBe('created');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition->id)
            ->toBe($this->postfris->id);

        expect($log->stampSeries)
            ->toBeNull();

        // Bij een nieuw record bestaan geen oorspronkelijke waarden.
        expect($log->old_values)
            ->toBeNull();

        expect($log->new_values)
            ->toBeArray();

        // Controleer alle verplichte attributen.
        expect($log->new_values)
            ->toHaveKeys([
                'id',
                'stamp_catalog_entry_id',
                'condition_id',
                'catalog_value',
                'currency',
                'created_at',
                'updated_at',
            ]);

        expect($log->new_values['id'])
            ->toBe($newValue->id);

        expect($log->new_values['stamp_catalog_entry_id'])
            ->toBe($this->entryB1->id);

        expect($log->new_values['condition_id'])
            ->toBe($this->postfris->id);

        expect(is_numeric($log->new_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->new_values['catalog_value'])
            ->toBe(32.75);

        expect($log->new_values['currency'])
            ->toBe('USD');

        // Vergelijk alle geregistreerde attributen
        // met de daadwerkelijk opgeslagen cataloguswaarde.
        $freshValueAttributes = $newValue
            ->fresh()
            ->getAttributes();

        foreach ($log->new_values as $attribute => $value) {
            expect($freshValueAttributes)
                ->toHaveKey($attribute);

            $expectedValue = $freshValueAttributes[$attribute];

            if ($attribute === 'catalog_value') {
                expect(is_numeric($value))
                    ->toBeTrue();

                expect((float) $value)
                    ->toBe((float) $expectedValue);

                continue;
            }

            if ($attribute === 'needs_review') {
                expect(in_array($value, [false, 0], true))
                    ->toBeTrue();

                expect((bool) $value)
                    ->toBe((bool) $expectedValue);

                continue;
            }

            expect($value)
                ->toBe($expectedValue);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $newValue->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        // Er mogen geen wijzigingslogs voor vermeldingen bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // Er mogen geen updated- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'updated',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het wijzigen van een bestaande cataloguswaarde via de oorspronkelijke invoerstructuur', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->editionB1->currency)
        ->toBe('USD');

    expect((float) $this->valueB1Postfris->catalog_value)
        ->toBe(25.00);

    expect($this->valueB1Postfris->needs_review)
        ->toBeFalse();

    expect((float) $this->valueB1Gestempeld->catalog_value)
        ->toBe(12.50);

    expect($this->valueB1Gestempeld->needs_review)
        ->toBeFalse();

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-100';

    $payload['catalog_values'] = [
        'postfris' => '37.75',
    ];

    // Er worden uitsluitend gegevens voor route B ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    // De gestempelde cataloguswaarde wordt niet ingediend.
    expect($payload['catalog_values'])
        ->not->toHaveKey('gestempeld');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig de bestaande postfrisse cataloguswaarde.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Controleer de gewijzigde cataloguswaarde.
        $freshValue = $this->valueB1Postfris->fresh();

        expect($freshValue)->not->toBeNull();

        expect($freshValue->id)
            ->toBe($this->valueB1Postfris->id);

        expect($freshValue->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($freshValue->condition_id)
            ->toBe($this->postfris->id);

        expect(is_numeric($freshValue->catalog_value))
            ->toBeTrue();

        expect((float) $freshValue->catalog_value)
            ->toBe(37.75);

        expect($freshValue->currency)
            ->toBe('USD');

        // De gewijzigde cataloguswaarde moet opnieuw
        // worden gecontroleerd.
        expect($freshValue->needs_review)
            ->toBeTrue();

        // Controleer de relaties van de cataloguswaarde.
        expect($freshValue->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($freshValue->catalogEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshValue->catalogEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($freshValue->condition->id)
            ->toBe($this->postfris->id);

        // Controleer de oorspronkelijke attributen.
        $originalChangedValue =
            $originalValues[$this->valueB1Postfris->id];

        // De oorspronkelijke aanmaaktijd moet behouden blijven.
        expect($freshValue->getRawOriginal('created_at'))
            ->toBe($originalChangedValue['created_at']);

        // De wijzigingstijd moet daadwerkelijk zijn aangepast.
        expect($freshValue->getRawOriginal('updated_at'))
            ->not->toBe($originalChangedValue['updated_at']);

        // Controleer dat uitsluitend catalog_value,
        // needs_review en updated_at zijn gewijzigd.
        $expectedValueAttributes = $originalChangedValue;

        $expectedValueAttributes['catalog_value'] =
            $freshValue->getRawOriginal('catalog_value');

        $expectedValueAttributes['needs_review'] =
            $freshValue->getRawOriginal('needs_review');

        $expectedValueAttributes['updated_at'] =
            $freshValue->getRawOriginal('updated_at');

        expect($freshValue->getAttributes())
            ->toBe($expectedValueAttributes);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 37.75,
            'currency' => 'USD',
            'needs_review' => true,
        ]);

        // De oorspronkelijke waarde mag niet meer
        // bij hetzelfde record aanwezig zijn.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 25.00,
        ]);

        // De oorspronkelijke controlemarkering
        // mag niet meer aanwezig zijn.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'needs_review' => false,
        ]);

        // Alle catalogusvermeldingen moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // updateOrCreate kan de wijzigingstijd van B1
            // aanpassen zonder inhoudelijke wijziging.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vijf overige cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            if ($value->id === $this->valueB1Postfris->id) {
                continue;
            }

            $freshUnchangedValue = $value->fresh();

            expect($freshUnchangedValue)->not->toBeNull();

            expect($freshUnchangedValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshUnchangedValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de identificatiegegevens van het log.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBe($this->postfris->id);

        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        expect($log->record_id)
            ->toBe($this->valueB1Postfris->id);

        expect($log->action)
            ->toBe('updated');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition->id)
            ->toBe($this->postfris->id);

        expect($log->stampSeries)
            ->toBeNull();

        // Zowel old_values als new_values
        // moeten als array zijn opgeslagen.
        expect($log->old_values)
            ->toBeArray();

        expect($log->new_values)
            ->toBeArray();

        // Beide arrays mogen uitsluitend de twee
        // daadwerkelijk gewijzigde attributen bevatten.
        expect($log->old_values)
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        expect($log->new_values)
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        // Controleer de oorspronkelijke cataloguswaarde.
        expect(is_numeric($log->old_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->old_values['catalog_value'])
            ->toBe(25.00);

        // Controleer de nieuwe cataloguswaarde.
        expect(is_numeric($log->new_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->new_values['catalog_value'])
            ->toBe(37.75);

        // Controleer de oorspronkelijke controlemarkering.
        expect(
            in_array(
                $log->old_values['needs_review'],
                [false, 0],
                true
            )
        )->toBeTrue();

        // Controleer de nieuwe controlemarkering.
        expect(
            in_array(
                $log->new_values['needs_review'],
                [true, 1],
                true
            )
        )->toBeTrue();

        // Normaliseer de oorspronkelijke loggegevens.
        $loggedOldValues = $log->old_values;

        $loggedOldValues['catalog_value'] =
            (float) $loggedOldValues['catalog_value'];

        $loggedOldValues['needs_review'] =
            (bool) $loggedOldValues['needs_review'];

        // Normaliseer de nieuwe loggegevens.
        $loggedNewValues = $log->new_values;

        $loggedNewValues['catalog_value'] =
            (float) $loggedNewValues['catalog_value'];

        $loggedNewValues['needs_review'] =
            (bool) $loggedNewValues['needs_review'];

        // De volgorde van JSON-attributen is niet relevant.
        ksort($loggedOldValues);

        ksort($loggedNewValues);

        // Controleer de volledige oorspronkelijke loggegevens.
        expect($loggedOldValues)
            ->toBe([
                'catalog_value' => 25.00,
                'needs_review' => false,
            ]);

        // Controleer de volledige nieuwe loggegevens.
        expect($loggedNewValues)
            ->toBe([
                'catalog_value' => 37.75,
                'needs_review' => true,
            ]);

        // Vergelijk de historische waarden
        // met de oorspronkelijke databasewaarden.
        expect($loggedOldValues['catalog_value'])
            ->toBe(
                (float) $originalChangedValue['catalog_value']
            );

        expect($loggedOldValues['needs_review'])
            ->toBe(
                (bool) $originalChangedValue['needs_review']
            );

        // Vergelijk de nieuwe historische waarden
        // met de daadwerkelijk opgeslagen databasewaarden.
        expect($loggedNewValues['catalog_value'])
            ->toBe((float) $freshValue->catalog_value);

        expect($loggedNewValues['needs_review'])
            ->toBe($freshValue->needs_review);

        // Ongewijzigde attributen en timestamps
        // mogen niet in het wijzigingslog voorkomen.
        foreach ([
            'id',
            'stamp_catalog_entry_id',
            'condition_id',
            'currency',
            'notes',
            'created_at',
            'updated_at',
        ] as $attribute) {
            expect($log->old_values)
                ->not->toHaveKey($attribute);

            expect($log->new_values)
                ->not->toHaveKey($attribute);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Postfris->id,
            'action' => 'updated',
        ]);

        // Er mogen geen wijzigingslogs voor vermeldingen bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // Er mogen geen created- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het verwijderen van een bestaande cataloguswaarde via de oorspronkelijke invoerstructuur', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de identificatiegegevens en attributen
    // van de te verwijderen cataloguswaarde.
    $deletedValueId = $this->valueB1Postfris->id;

    $originalDeletedValue = $originalValues[$deletedValueId];

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(2);

    expect((float) $this->valueB1Postfris->catalog_value)
        ->toBe(25.00);

    expect($this->valueB1Postfris->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->needs_review)
        ->toBeFalse();

    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $deletedValueId,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 25.00,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-100';

    $payload['catalog_values'] = [
        'postfris' => '',
    ];

    // Er worden uitsluitend gegevens voor route B ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    // De gestempelde cataloguswaarde wordt niet ingediend.
    expect($payload['catalog_values'])
        ->not->toHaveKey('gestempeld');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Verwijder de bestaande postfrisse cataloguswaarde
        // door een leeg formulierveld in te dienen.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // De oorspronkelijke cataloguswaarde moet verwijderd zijn.
        expect($this->valueB1Postfris->fresh())
            ->toBeNull();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $deletedValueId,
        ]);

        // Er mag ook geen vervangende postfrisse waarde bestaan.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
        ]);

        expect(
            $this->entryB1->values()
                ->where('condition_id', $this->postfris->id)
                ->exists()
        )->toBeFalse();

        // Er mag geen catalogusvermelding zijn verwijderd.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        // Er moeten precies vijf cataloguswaarden overblijven.
        $this->assertDatabaseCount('stamp_catalog_values', 5);

        // Alle catalogusvermeldingen moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // updateOrCreate kan de wijzigingstijd van B1
            // aanpassen zonder inhoudelijke wijziging.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            $expectedValueCount =
                $entry->id === $this->entryB1->id ? 1 : 2;

            expect($freshEntry->values()->count())
                ->toBe($expectedValueCount);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vijf overige cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            if ($value->id === $deletedValueId) {
                continue;
            }

            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de behouden gestempelde waarde.
        $remainingValue = $this->entryB1->values()->sole();

        expect($remainingValue->id)
            ->toBe($this->valueB1Gestempeld->id);

        expect($remainingValue->condition_id)
            ->toBe($this->gestempeld->id);

        expect((float) $remainingValue->catalog_value)
            ->toBe(12.50);

        expect($remainingValue->currency)
            ->toBe('USD');

        expect($remainingValue->needs_review)
            ->toBeFalse();

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(5);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de historische identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBe($this->postfris->id);

        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        expect($log->record_id)
            ->toBe($deletedValueId);

        expect($log->action)
            ->toBe('deleted');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition->id)
            ->toBe($this->postfris->id);

        expect($log->stampSeries)
            ->toBeNull();

        // Bij een verwijderd record bestaan geen nieuwe waarden.
        expect($log->new_values)
            ->toBeNull();

        // De volledige oorspronkelijke attributen
        // moeten als array zijn opgeslagen.
        expect($log->old_values)
            ->toBeArray();

        expect($log->old_values)
            ->toHaveKeys([
                'id',
                'stamp_catalog_entry_id',
                'condition_id',
                'catalog_value',
                'currency',
                'needs_review',
                'created_at',
                'updated_at',
            ]);

        expect($log->old_values)
            ->toHaveCount(count($originalDeletedValue));

        // Controleer de historische record-ID.
        expect($log->old_values['id'])
            ->toBe($deletedValueId);

        expect($log->old_values['stamp_catalog_entry_id'])
            ->toBe($this->entryB1->id);

        expect($log->old_values['condition_id'])
            ->toBe($this->postfris->id);

        expect(is_numeric($log->old_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->old_values['catalog_value'])
            ->toBe(25.00);

        expect($log->old_values['currency'])
            ->toBe('USD');

        expect(
            in_array(
                $log->old_values['needs_review'],
                [false, 0],
                true
            )
        )->toBeTrue();

        // Controleer de oorspronkelijke timestamps.
        expect($log->old_values['created_at'])
            ->toBe($originalDeletedValue['created_at']);

        expect($log->old_values['updated_at'])
            ->toBe($originalDeletedValue['updated_at']);

        // Vergelijk alle oorspronkelijke attributen
        // met de geregistreerde historische attributen.
        foreach ($originalDeletedValue as $attribute => $value) {
            expect($log->old_values)
                ->toHaveKey($attribute);

            $loggedValue = $log->old_values[$attribute];

            if ($attribute === 'catalog_value') {
                expect(is_numeric($loggedValue))
                    ->toBeTrue();

                expect((float) $loggedValue)
                    ->toBe((float) $value);

                continue;
            }

            if ($attribute === 'needs_review') {
                expect(in_array($loggedValue, [false, 0], true))
                    ->toBeTrue();

                expect((bool) $loggedValue)
                    ->toBe((bool) $value);

                continue;
            }

            expect($loggedValue)
                ->toBe($value);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $deletedValueId,
            'action' => 'deleted',
            'new_values' => null,
        ]);

        // De historische record-ID moet behouden blijven,
        // hoewel het oorspronkelijke record niet meer bestaat.
        expect(StampCatalogValue::find($log->record_id))
            ->toBeNull();

        expect($log->fresh()->record_id)
            ->toBe($deletedValueId);

        // Er mogen geen wijzigingslogs voor vermeldingen bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // Er mogen geen created- of updated-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'updated',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert geen wijzigingsgeschiedenis bij het verwijderen van een niet bestaande cataloguswaarde via de oorspronkelijke invoerstructuur', function () {
    // Bewaar de ID van de vooraf te verwijderen cataloguswaarde.
    $missingValueId = $this->valueB1Postfris->id;

    // Verwijder de postfrisse cataloguswaarde uitsluitend
    // tijdens de testvoorbereiding.
    $this->valueB1Postfris->delete();

    // De voorbereidende verwijdering mag geen geschiedenis veroorzaken.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle vijf resterende cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 5);

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(1);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    expect($this->stamp->catalogValues()->count())
        ->toBe(5);

    // De vooraf verwijderde cataloguswaarde mag niet bestaan.
    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $missingValueId,
    ]);

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
    ]);

    // De gestempelde cataloguswaarde moet wel bestaan.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $this->valueB1Gestempeld->id,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 12.50,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-100';

    $payload['catalog_values'] = [
        'postfris' => '',
    ];

    // Er worden uitsluitend gegevens voor route B ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    // De bestaande gestempelde cataloguswaarde
    // wordt bewust niet ingediend.
    expect($payload['catalog_values'])
        ->not->toHaveKey('gestempeld');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Probeer een niet-bestaande cataloguswaarde te verwijderen.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // De ontbrekende cataloguswaarde mag niet zijn aangemaakt.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $missingValueId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
        ]);

        expect(
            $this->entryB1->values()
                ->where('condition_id', $this->postfris->id)
                ->exists()
        )->toBeFalse();

        // Er mogen geen catalogusvermeldingen
        // of cataloguswaarden zijn toegevoegd of verwijderd.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        // Alle bestaande catalogusvermeldingen
        // moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // updateOrCreate kan de wijzigingstijd van B1
            // aanpassen zonder inhoudelijke wijziging.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            $expectedValueCount =
                $entry->id === $this->entryB1->id ? 1 : 2;

            expect($freshEntry->values()->count())
                ->toBe($expectedValueCount);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vijf bestaande cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de behouden gestempelde waarde.
        $remainingValue = $this->entryB1->values()->sole();

        expect($remainingValue->id)
            ->toBe($this->valueB1Gestempeld->id);

        expect($remainingValue->condition_id)
            ->toBe($this->gestempeld->id);

        expect(is_numeric($remainingValue->catalog_value))
            ->toBeTrue();

        expect((float) $remainingValue->catalog_value)
            ->toBe(12.50);

        expect($remainingValue->currency)
            ->toBe('USD');

        expect($remainingValue->needs_review)
            ->toBeFalse();

        // Controleer alle catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(5);

        // Controleer dat exact dezelfde catalogusvermeldingen
        // aan de postzegel gekoppeld zijn gebleven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Controleer dat exact dezelfde cataloguswaarden
        // aan de postzegel gekoppeld zijn gebleven.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er mag geen blijvende geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        expect(CatalogChangeBatch::query()->count())
            ->toBe(0);

        // Er mogen geen wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(CatalogChangeLog::query()->count())
            ->toBe(0);

        // Er mag geen wijzigingslog bestaan voor
        // de niet-bestaande cataloguswaarde.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $missingValueId,
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor een van de vier catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mogen geen geschiedenisbatches
        // voor een van beide gebruikers bestaan.
        foreach ([
            $this->user,
            $this->otherUser,
        ] as $user) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'user_id' => $user->id,
            ]);
        }

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het wijzigen van een bestaande cataloguswaarde naar nul via de oorspronkelijke invoerstructuur', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke attributen
    // van de te wijzigen cataloguswaarde.
    $changedValueId = $this->valueB1Postfris->id;

    $originalChangedValue = $originalValues[$changedValueId];

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(2);

    expect($this->editionB1->currency)
        ->toBe('USD');

    expect((float) $this->valueB1Postfris->catalog_value)
        ->toBe(25.00);

    expect($this->valueB1Postfris->needs_review)
        ->toBeFalse();

    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $changedValueId,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 25.00,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-100';

    $payload['catalog_values'] = [
        'postfris' => '0.00',
    ];

    // Controleer dat daadwerkelijk een nulwaarde
    // en geen leeg formulierveld wordt ingediend.
    expect($payload['catalog_values']['postfris'])
        ->toBe('0.00')
        ->not->toBe('')
        ->not->toBeNull();

    // Er worden uitsluitend gegevens voor route B ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    // De gestempelde cataloguswaarde wordt niet ingediend.
    expect($payload['catalog_values'])
        ->not->toHaveKey('gestempeld');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig de bestaande cataloguswaarde naar nul.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // De oorspronkelijke cataloguswaarde
        // moet nog steeds bestaan.
        $freshValue = $this->valueB1Postfris->fresh();

        expect($freshValue)->not->toBeNull();

        expect($freshValue->id)
            ->toBe($changedValueId);

        expect($freshValue->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($freshValue->condition_id)
            ->toBe($this->postfris->id);

        // De cataloguswaarde moet daadwerkelijk nul zijn.
        expect($freshValue->catalog_value)
            ->not->toBeNull();

        expect(is_numeric($freshValue->catalog_value))
            ->toBeTrue();

        expect((float) $freshValue->catalog_value)
            ->toBe(0.00);

        expect($freshValue->catalog_value)
            ->toBe('0.00');

        expect($freshValue->currency)
            ->toBe('USD');

        // De gewijzigde cataloguswaarde
        // moet opnieuw worden gecontroleerd.
        expect($freshValue->needs_review)
            ->toBeTrue();

        // Controleer de relaties van de cataloguswaarde.
        expect($freshValue->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($freshValue->catalogEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshValue->catalogEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($freshValue->condition->id)
            ->toBe($this->postfris->id);

        // De oorspronkelijke aanmaaktijd moet behouden blijven.
        expect($freshValue->getRawOriginal('created_at'))
            ->toBe($originalChangedValue['created_at']);

        // De wijzigingstijd moet daadwerkelijk zijn aangepast.
        expect($freshValue->getRawOriginal('updated_at'))
            ->not->toBe($originalChangedValue['updated_at']);

        // Controleer dat uitsluitend catalog_value,
        // needs_review en updated_at zijn gewijzigd.
        $expectedValueAttributes = $originalChangedValue;

        $expectedValueAttributes['catalog_value'] =
            $freshValue->getRawOriginal('catalog_value');

        $expectedValueAttributes['needs_review'] =
            $freshValue->getRawOriginal('needs_review');

        $expectedValueAttributes['updated_at'] =
            $freshValue->getRawOriginal('updated_at');

        expect($freshValue->getAttributes())
            ->toBe($expectedValueAttributes);

        // Controleer de daadwerkelijk opgeslagen nulwaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $changedValueId,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 0.00,
            'currency' => 'USD',
            'needs_review' => true,
        ]);

        // De oorspronkelijke waarde mag niet meer bestaan
        // bij hetzelfde record.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $changedValueId,
            'catalog_value' => 25.00,
        ]);

        // De nulwaarde mag niet als null zijn opgeslagen.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $changedValueId,
            'catalog_value' => null,
        ]);

        // Er mag geen vervangend of dubbel record bestaan.
        $storedValue = $this->entryB1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($storedValue->id)
            ->toBe($changedValueId);

        // Alle catalogusvermeldingen moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // updateOrCreate kan de wijzigingstijd van B1
            // aanpassen zonder inhoudelijke wijziging.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vijf overige cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            if ($value->id === $changedValueId) {
                continue;
            }

            $freshUnchangedValue = $value->fresh();

            expect($freshUnchangedValue)->not->toBeNull();

            expect($freshUnchangedValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshUnchangedValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de behouden gestempelde waarde.
        $remainingGestempeld = $this->valueB1Gestempeld->fresh();

        expect((float) $remainingGestempeld->catalog_value)
            ->toBe(12.50);

        expect($remainingGestempeld->currency)
            ->toBe('USD');

        expect($remainingGestempeld->needs_review)
            ->toBeFalse();

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de historische identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBe($this->postfris->id);

        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        expect($log->record_id)
            ->toBe($changedValueId);

        expect($log->action)
            ->toBe('updated');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition->id)
            ->toBe($this->postfris->id);

        expect($log->stampSeries)
            ->toBeNull();

        // Beide arrays mogen uitsluitend de twee
        // daadwerkelijk gewijzigde attributen bevatten.
        expect($log->old_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        expect($log->new_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        // Controleer de oorspronkelijke cataloguswaarde.
        expect(is_numeric($log->old_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->old_values['catalog_value'])
            ->toBe(25.00);

        // Controleer dat de nieuwe historische waarde
        // daadwerkelijk nul is en niet null.
        expect($log->new_values['catalog_value'])
            ->not->toBeNull();

        expect(is_numeric($log->new_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->new_values['catalog_value'])
            ->toBe(0.00);

        // Controleer de oorspronkelijke controlemarkering.
        expect(
            in_array(
                $log->old_values['needs_review'],
                [false, 0],
                true
            )
        )->toBeTrue();

        // Controleer de nieuwe controlemarkering.
        expect(
            in_array(
                $log->new_values['needs_review'],
                [true, 1],
                true
            )
        )->toBeTrue();

        // Normaliseer de oorspronkelijke loggegevens.
        $loggedOldValues = $log->old_values;

        $loggedOldValues['catalog_value'] =
            (float) $loggedOldValues['catalog_value'];

        $loggedOldValues['needs_review'] =
            (bool) $loggedOldValues['needs_review'];

        // Normaliseer de nieuwe loggegevens.
        $loggedNewValues = $log->new_values;

        $loggedNewValues['catalog_value'] =
            (float) $loggedNewValues['catalog_value'];

        $loggedNewValues['needs_review'] =
            (bool) $loggedNewValues['needs_review'];

        // De volgorde van JSON-attributen is niet relevant.
        ksort($loggedOldValues);

        ksort($loggedNewValues);

        // Controleer de volledige oorspronkelijke loggegevens.
        expect($loggedOldValues)
            ->toBe([
                'catalog_value' => 25.00,
                'needs_review' => false,
            ]);

        // Controleer de volledige nieuwe loggegevens.
        expect($loggedNewValues)
            ->toBe([
                'catalog_value' => 0.00,
                'needs_review' => true,
            ]);

        // Vergelijk de oorspronkelijke historische waarden
        // met de oorspronkelijke databasewaarden.
        expect($loggedOldValues['catalog_value'])
            ->toBe(
                (float) $originalChangedValue['catalog_value']
            );

        expect($loggedOldValues['needs_review'])
            ->toBe(
                (bool) $originalChangedValue['needs_review']
            );

        // Vergelijk de nieuwe historische waarden
        // met de daadwerkelijk opgeslagen databasewaarden.
        expect($loggedNewValues['catalog_value'])
            ->toBe((float) $freshValue->catalog_value);

        expect($loggedNewValues['needs_review'])
            ->toBe($freshValue->needs_review);

        // Ongewijzigde attributen en timestamps
        // mogen niet in het wijzigingslog voorkomen.
        foreach ([
            'id',
            'stamp_catalog_entry_id',
            'condition_id',
            'currency',
            'notes',
            'created_at',
            'updated_at',
        ] as $attribute) {
            expect($log->old_values)
                ->not->toHaveKey($attribute);

            expect($log->new_values)
                ->not->toHaveKey($attribute);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $changedValueId,
            'action' => 'updated',
        ]);

        // Er mogen geen wijzigingslogs voor vermeldingen bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // De nulwaarde mag niet tot verwijdering
        // of het aanmaken van een nieuw record hebben geleid.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert geen wijzigingsgeschiedenis bij het opslaan van ongewijzigde catalogusgegevens via de oorspronkelijke invoerstructuur', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(2);

    expect($this->editionB1->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Postfris->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Gestempeld->currency)
        ->toBe('USD');

    expect($this->valueB1Gestempeld->needs_review)
        ->toBeFalse();

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-100';

    $payload['catalog_values'] = [
        'postfris' => '25.00',
        'gestempeld' => '12.50',
    ];

    // Er worden uitsluitend gegevens voor route B ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_entries')
        ->not->toHaveKey('catalog_editions');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Controleer dat alle ingediende catalogusgegevens
    // overeenkomen met de bestaande databasegegevens.
    expect($payload['catalog_number'])
        ->toBe($this->entryB1->catalog_number);

    expect($payload['catalog_values']['postfris'])
        ->toBe($this->valueB1Postfris->catalog_value);

    expect($payload['catalog_values']['gestempeld'])
        ->toBe($this->valueB1Gestempeld->catalog_value);

    // Verplaats de tijd om eventuele timestampwijzigingen
    // aan bestaande records te kunnen onderscheiden.
    $this->travel(1)->minutes();

    try {
        // Sla de ongewijzigde catalogusgegevens opnieuw op.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        // De algemene postzegelgegevens worden wel gewijzigd.
        // Dit mag geen catalogusgeschiedenis veroorzaken.
        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Alle catalogusvermeldingen moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // Alleen bij de ingediende catalogusvermelding B1
            // is een eventuele timestampwijziging toegestaan.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle cataloguswaarden moeten behouden blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            $expectedAttributes = $originalValues[$value->id];

            // Alleen bij de twee ingediende cataloguswaarden
            // is een eventuele timestampwijziging toegestaan.
            if (in_array($value->id, [
                $this->valueB1Postfris->id,
                $this->valueB1Gestempeld->id,
            ], true)) {
                $expectedAttributes['updated_at'] =
                    $freshValue->getRawOriginal('updated_at');
            }

            expect($freshValue->getAttributes())
                ->toBe($expectedAttributes);

            // Een ongewijzigde cataloguswaarde mag niet
            // opnieuw voor controle worden gemarkeerd.
            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek catalogusvermelding B1.
        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1->id)
            ->toBe($this->entryB1->id);

        expect($freshEntryB1->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntryB1->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($freshEntryB1->catalog_number)
            ->toBe('B-100');

        expect($freshEntryB1->values()->count())
            ->toBe(2);

        // Controleer specifiek de postfrisse cataloguswaarde.
        $freshPostfris = $freshEntryB1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($freshPostfris->id)
            ->toBe($this->valueB1Postfris->id);

        expect($freshPostfris->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($freshPostfris->condition_id)
            ->toBe($this->postfris->id);

        expect($freshPostfris->catalog_value)
            ->toBe('25.00');

        expect($freshPostfris->currency)
            ->toBe('USD');

        expect($freshPostfris->needs_review)
            ->toBeFalse();

        // Controleer specifiek de gestempelde cataloguswaarde.
        $freshGestempeld = $freshEntryB1->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($freshGestempeld->id)
            ->toBe($this->valueB1Gestempeld->id);

        expect($freshGestempeld->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($freshGestempeld->condition_id)
            ->toBe($this->gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('12.50');

        expect($freshGestempeld->currency)
            ->toBe('USD');

        expect($freshGestempeld->needs_review)
            ->toBeFalse();

        // Controleer de opgeslagen catalogusvermelding.
        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-100',
        ]);

        // Controleer de opgeslagen postfrisse cataloguswaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 25.00,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // Controleer de opgeslagen gestempelde cataloguswaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Gestempeld->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 12.50,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // Geen enkele cataloguswaarde mag opnieuw
        // voor controle zijn gemarkeerd.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'needs_review' => true,
        ]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Exact dezelfde catalogusvermeldingen
        // moeten aan de postzegel gekoppeld zijn gebleven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Exact dezelfde cataloguswaarden
        // moeten aan de postzegel gekoppeld zijn gebleven.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er mag geen blijvende geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        expect(CatalogChangeBatch::query()->count())
            ->toBe(0);

        // Er mogen geen wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(CatalogChangeLog::query()->count())
            ->toBe(0);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor een van de vier catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mogen geen geschiedenisbatches
        // voor een van beide gebruikers bestaan.
        foreach ([
            $this->user,
            $this->otherUser,
        ] as $user) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'user_id' => $user->id,
            ]);
        }

        // Geen enkele wijzigingsactie mag zijn geregistreerd.
        foreach ([
            'created',
            'updated',
            'deleted',
        ] as $action) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'action' => $action,
            ]);
        }

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het wijzigen van een bestaand catalogusnummer via catalog entries', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke attributen
    // van de te wijzigen catalogusvermelding.
    $changedEntryId = $this->entryA2->id;

    $originalChangedEntry = $originalEntries[$changedEntryId];

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryA2->catalog_number)
        ->toBe('A-200');

    expect($this->entryA2->catalog_edition_id)
        ->toBe($this->editionA2->id);

    expect($this->entryA2->values()->count())
        ->toBe(2);

    expect($this->editionA2->currency)
        ->toBe('EUR');

    expect($this->valueA2Postfris->catalog_value)
        ->toBe('20.00');

    expect($this->valueA2Gestempeld->catalog_value)
        ->toBe('10.00');

    expect($this->valueA2Postfris->needs_review)
        ->toBeFalse();

    expect($this->valueA2Gestempeld->needs_review)
        ->toBeFalse();

    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $changedEntryId,
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionA2->id,
        'catalog_number' => 'A-200',
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_entries'] = [
        $changedEntryId => [
            'catalog_number' => 'A-250',
        ],
    ];

    // Er worden uitsluitend gegevens voor route C ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_editions');

    expect($payload['catalog_entries'])
        ->toHaveCount(1);

    expect($payload['catalog_entries'][$changedEntryId])
        ->not->toHaveKey('values');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig het bestaande catalogusnummer via route C.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Controleer de gewijzigde catalogusvermelding.
        $freshEntry = $this->entryA2->fresh();

        expect($freshEntry)->not->toBeNull();

        expect($freshEntry->id)
            ->toBe($changedEntryId);

        expect($freshEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntry->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($freshEntry->catalog_number)
            ->toBe('A-250');

        // Controleer de relaties van de catalogusvermelding.
        expect($freshEntry->stamp->id)
            ->toBe($this->stamp->id);

        expect($freshEntry->catalogEdition->id)
            ->toBe($this->editionA2->id);

        expect($freshEntry->catalogEdition->catalog->id)
            ->toBe($this->catalogA->id);

        expect($freshEntry->values()->count())
            ->toBe(2);

        // De oorspronkelijke aanmaaktijd moet behouden blijven.
        expect($freshEntry->getRawOriginal('created_at'))
            ->toBe($originalChangedEntry['created_at']);

        // De wijzigingstijd moet daadwerkelijk zijn aangepast.
        expect($freshEntry->getRawOriginal('updated_at'))
            ->not->toBe($originalChangedEntry['updated_at']);

        // Uitsluitend catalog_number en updated_at
        // mogen zijn gewijzigd.
        $expectedEntryAttributes = $originalChangedEntry;

        $expectedEntryAttributes['catalog_number'] = 'A-250';

        $expectedEntryAttributes['updated_at'] =
            $freshEntry->getRawOriginal('updated_at');

        expect($freshEntry->getAttributes())
            ->toBe($expectedEntryAttributes);

        // Controleer de opgeslagen catalogusvermelding.
        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $changedEntryId,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionA2->id,
            'catalog_number' => 'A-250',
        ]);

        // Het oorspronkelijke catalogusnummer
        // mag niet meer bij hetzelfde record bestaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $changedEntryId,
            'catalog_number' => 'A-200',
        ]);

        // Er mag geen vervangende of dubbele vermelding bestaan.
        $storedEntry = $freshStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionA2->id)
            ->sole();

        expect($storedEntry->id)
            ->toBe($changedEntryId);

        // Alle overige catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            if ($entry->id === $changedEntryId) {
                continue;
            }

            $freshUnchangedEntry = $entry->fresh();

            expect($freshUnchangedEntry)->not->toBeNull();

            expect($freshUnchangedEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshUnchangedEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor deze vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle zes cataloguswaarden moeten volledig
        // ongewijzigd blijven, inclusief timestamps.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de behouden waarden van A2.
        $freshPostfris = $freshEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($freshPostfris->id)
            ->toBe($this->valueA2Postfris->id);

        expect($freshPostfris->catalog_value)
            ->toBe('20.00');

        expect($freshPostfris->currency)
            ->toBe('EUR');

        expect($freshPostfris->needs_review)
            ->toBeFalse();

        $freshGestempeld = $freshEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($freshGestempeld->id)
            ->toBe($this->valueA2Gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('10.00');

        expect($freshGestempeld->currency)
            ->toBe('EUR');

        expect($freshGestempeld->needs_review)
            ->toBeFalse();

        // Geen enkele cataloguswaarde mag opnieuw
        // voor controle zijn gemarkeerd.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'needs_review' => true,
        ]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogA->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionA2->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionA2->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionA2->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogA->name,
            'catalog_edition_name' => $this->editionA2->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de historische identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBeNull();

        expect($log->record_type)
            ->toBe('stamp_catalog_entry');

        expect($log->record_id)
            ->toBe($changedEntryId);

        expect($log->action)
            ->toBe('updated');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionA2->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition)
            ->toBeNull();

        expect($log->stampSeries)
            ->toBeNull();

        // Controleer de volledige oorspronkelijke loggegevens.
        expect($log->old_values)
            ->toBeArray()
            ->toHaveCount(1)
            ->toBe([
                'catalog_number' => 'A-200',
            ]);

        // Controleer de volledige nieuwe loggegevens.
        expect($log->new_values)
            ->toBeArray()
            ->toHaveCount(1)
            ->toBe([
                'catalog_number' => 'A-250',
            ]);

        // Vergelijk de oorspronkelijke historische waarde
        // met de oorspronkelijke databasewaarde.
        expect($log->old_values['catalog_number'])
            ->toBe($originalChangedEntry['catalog_number']);

        // Vergelijk de nieuwe historische waarde
        // met de daadwerkelijk opgeslagen databasewaarde.
        expect($log->new_values['catalog_number'])
            ->toBe($freshEntry->catalog_number);

        // Ongewijzigde attributen en timestamps
        // mogen niet in het wijzigingslog voorkomen.
        foreach ([
            'id',
            'stamp_id',
            'catalog_edition_id',
            'notes',
            'created_at',
            'updated_at',
        ] as $attribute) {
            expect($log->old_values)
                ->not->toHaveKey($attribute);

            expect($log->new_values)
                ->not->toHaveKey($attribute);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionA2->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => null,
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $changedEntryId,
            'action' => 'updated',
        ]);

        // Er mogen geen wijzigingslogs voor cataloguswaarden bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
        ]);

        // Er mogen geen created- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het wijzigen van een bestaande cataloguswaarde via catalog entries', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke attributen
    // van de te wijzigen cataloguswaarde.
    $changedValueId = $this->valueB1Postfris->id;

    $originalChangedValue = $originalValues[$changedValueId];

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(2);

    expect($this->editionB1->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Postfris->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Gestempeld->needs_review)
        ->toBeFalse();

    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $changedValueId,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 25.00,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-100',
            'values' => [
                'postfris' => '38.75',
            ],
        ],
    ];

    // Er worden uitsluitend gegevens voor route C ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_editions');

    expect($payload['catalog_entries'])
        ->toHaveCount(1);

    expect($payload['catalog_entries'][$this->entryB1->id]['values'])
        ->toHaveCount(1)
        ->not->toHaveKey('gestempeld');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig de bestaande cataloguswaarde via route C.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Controleer de gewijzigde cataloguswaarde.
        $freshValue = $this->valueB1Postfris->fresh();

        expect($freshValue)->not->toBeNull();

        expect($freshValue->id)
            ->toBe($changedValueId);

        expect($freshValue->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($freshValue->condition_id)
            ->toBe($this->postfris->id);

        expect($freshValue->catalog_value)
            ->toBe('38.75');

        expect((float) $freshValue->catalog_value)
            ->toBe(38.75);

        expect($freshValue->currency)
            ->toBe('USD');

        // De gewijzigde cataloguswaarde
        // moet opnieuw worden gecontroleerd.
        expect($freshValue->needs_review)
            ->toBeTrue();

        // Controleer de relaties van de cataloguswaarde.
        expect($freshValue->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($freshValue->catalogEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshValue->catalogEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($freshValue->catalogEntry->catalogEdition->catalog->id)
            ->toBe($this->catalogB->id);

        expect($freshValue->condition->id)
            ->toBe($this->postfris->id);

        // De oorspronkelijke aanmaaktijd moet behouden blijven.
        expect($freshValue->getRawOriginal('created_at'))
            ->toBe($originalChangedValue['created_at']);

        // De wijzigingstijd moet daadwerkelijk zijn aangepast.
        expect($freshValue->getRawOriginal('updated_at'))
            ->not->toBe($originalChangedValue['updated_at']);

        // Controleer dat uitsluitend catalog_value,
        // needs_review en updated_at zijn gewijzigd.
        $expectedValueAttributes = $originalChangedValue;

        $expectedValueAttributes['catalog_value'] =
            $freshValue->getRawOriginal('catalog_value');

        $expectedValueAttributes['needs_review'] =
            $freshValue->getRawOriginal('needs_review');

        $expectedValueAttributes['updated_at'] =
            $freshValue->getRawOriginal('updated_at');

        expect($freshValue->getAttributes())
            ->toBe($expectedValueAttributes);

        // Controleer de daadwerkelijk opgeslagen cataloguswaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $changedValueId,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 38.75,
            'currency' => 'USD',
            'needs_review' => true,
        ]);

        // De oorspronkelijke waarde mag niet meer bestaan
        // bij hetzelfde record.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $changedValueId,
            'catalog_value' => 25.00,
        ]);

        // Er mag geen vervangend of dubbel record bestaan.
        $storedValue = $this->entryB1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($storedValue->id)
            ->toBe($changedValueId);

        // Alle catalogusvermeldingen moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // Alleen bij de ingediende catalogusvermelding B1
            // is een eventuele timestampwijziging toegestaan.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vijf overige cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            if ($value->id === $changedValueId) {
                continue;
            }

            $freshUnchangedValue = $value->fresh();

            expect($freshUnchangedValue)->not->toBeNull();

            expect($freshUnchangedValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshUnchangedValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek het behouden catalogusnummer.
        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1->catalog_number)
            ->toBe('B-100');

        expect($freshEntryB1->values()->count())
            ->toBe(2);

        // Controleer specifiek de behouden gestempelde waarde.
        $freshGestempeld = $freshEntryB1->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($freshGestempeld->id)
            ->toBe($this->valueB1Gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('12.50');

        expect($freshGestempeld->currency)
            ->toBe('USD');

        expect($freshGestempeld->needs_review)
            ->toBeFalse();

        // Uitsluitend de gewijzigde cataloguswaarde
        // mag voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->pluck('id')
                ->all()
        )->toBe([$changedValueId]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de historische identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBe($this->postfris->id);

        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        expect($log->record_id)
            ->toBe($changedValueId);

        expect($log->action)
            ->toBe('updated');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition->id)
            ->toBe($this->postfris->id);

        expect($log->stampSeries)
            ->toBeNull();

        // Beide arrays mogen uitsluitend de twee
        // daadwerkelijk gewijzigde attributen bevatten.
        expect($log->old_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        expect($log->new_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        // Controleer de oorspronkelijke cataloguswaarde.
        expect(is_numeric($log->old_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->old_values['catalog_value'])
            ->toBe(25.00);

        // Controleer de nieuwe cataloguswaarde.
        expect(is_numeric($log->new_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->new_values['catalog_value'])
            ->toBe(38.75);

        // Controleer de oorspronkelijke controlemarkering.
        expect(
            in_array(
                $log->old_values['needs_review'],
                [false, 0],
                true
            )
        )->toBeTrue();

        // Controleer de nieuwe controlemarkering.
        expect(
            in_array(
                $log->new_values['needs_review'],
                [true, 1],
                true
            )
        )->toBeTrue();

        // Normaliseer de oorspronkelijke loggegevens.
        $loggedOldValues = $log->old_values;

        $loggedOldValues['catalog_value'] =
            (float) $loggedOldValues['catalog_value'];

        $loggedOldValues['needs_review'] =
            (bool) $loggedOldValues['needs_review'];

        // Normaliseer de nieuwe loggegevens.
        $loggedNewValues = $log->new_values;

        $loggedNewValues['catalog_value'] =
            (float) $loggedNewValues['catalog_value'];

        $loggedNewValues['needs_review'] =
            (bool) $loggedNewValues['needs_review'];

        // De volgorde van JSON-attributen is niet relevant.
        ksort($loggedOldValues);

        ksort($loggedNewValues);

        // Controleer de volledige oorspronkelijke loggegevens.
        expect($loggedOldValues)
            ->toBe([
                'catalog_value' => 25.00,
                'needs_review' => false,
            ]);

        // Controleer de volledige nieuwe loggegevens.
        expect($loggedNewValues)
            ->toBe([
                'catalog_value' => 38.75,
                'needs_review' => true,
            ]);

        // Vergelijk de oorspronkelijke historische waarden
        // met de oorspronkelijke databasewaarden.
        expect($loggedOldValues['catalog_value'])
            ->toBe(
                (float) $originalChangedValue['catalog_value']
            );

        expect($loggedOldValues['needs_review'])
            ->toBe(
                (bool) $originalChangedValue['needs_review']
            );

        // Vergelijk de nieuwe historische waarden
        // met de daadwerkelijk opgeslagen databasewaarden.
        expect($loggedNewValues['catalog_value'])
            ->toBe((float) $freshValue->catalog_value);

        expect($loggedNewValues['needs_review'])
            ->toBe($freshValue->needs_review);

        // Ongewijzigde attributen en timestamps
        // mogen niet in het wijzigingslog voorkomen.
        foreach ([
            'id',
            'stamp_catalog_entry_id',
            'condition_id',
            'currency',
            'notes',
            'created_at',
            'updated_at',
        ] as $attribute) {
            expect($log->old_values)
                ->not->toHaveKey($attribute);

            expect($log->new_values)
                ->not->toHaveKey($attribute);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $changedValueId,
            'action' => 'updated',
        ]);

        // Er mogen geen wijzigingslogs voor vermeldingen bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // Er mogen geen created- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het aanmaken van een ontbrekende cataloguswaarde via catalog entries', function () {
    // Verwijder de bestaande postfrisse cataloguswaarde
    // uitsluitend tijdens de testvoorbereiding.
    $this->valueB1Postfris->delete();

    // De voorbereidende verwijdering mag geen geschiedenis veroorzaken.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle vijf resterende cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 5);

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(1);

    expect($this->editionB1->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->fresh())
        ->toBeNull();

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
    ]);

    expect(
        $this->entryB1->values()
            ->where('condition_id', $this->postfris->id)
            ->exists()
    )->toBeFalse();

    // De gestempelde cataloguswaarde moet behouden zijn gebleven.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $this->valueB1Gestempeld->id,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 12.50,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-100',
            'values' => [
                'postfris' => '32.75',
            ],
        ],
    ];

    // Er worden uitsluitend gegevens voor route C ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_editions');

    expect($payload['catalog_entries'])
        ->toHaveCount(1);

    expect($payload['catalog_entries'][$this->entryB1->id]['values'])
        ->toHaveCount(1)
        ->not->toHaveKey('gestempeld');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Maak de ontbrekende cataloguswaarde aan via route C.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Er mag geen nieuwe catalogusvermelding zijn aangemaakt.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        // Er moet precies één cataloguswaarde zijn toegevoegd.
        $this->assertDatabaseCount('stamp_catalog_values', 6);

        // Haal de nieuw aangemaakte cataloguswaarde op.
        $newValue = $this->entryB1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($newValue)
            ->toBeInstanceOf(StampCatalogValue::class);

        expect($newValue->id)
            ->not->toBeNull();

        // De nieuwe record-ID mag niet overeenkomen
        // met een van de vijf behouden cataloguswaarden.
        expect(array_key_exists($newValue->id, $originalValues))
            ->toBeFalse();

        expect($newValue->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($newValue->condition_id)
            ->toBe($this->postfris->id);

        expect($newValue->catalog_value)
            ->toBe('32.75');

        expect((float) $newValue->catalog_value)
            ->toBe(32.75);

        // De valuta moet afkomstig zijn van cataloguseditie B1.
        expect($newValue->currency)
            ->toBe('USD');

        // Een nieuw aangemaakte cataloguswaarde krijgt
        // de bestaande standaardwaarde voor needs_review.
        expect($newValue->needs_review)
            ->toBeFalse();

        // Controleer de timestamps van de nieuwe cataloguswaarde.
        expect($newValue->created_at)
            ->not->toBeNull();

        expect($newValue->updated_at)
            ->not->toBeNull();

        expect($newValue->getRawOriginal('created_at'))
            ->toBe($newValue->getRawOriginal('updated_at'));

        // Controleer de relaties van de nieuwe cataloguswaarde.
        expect($newValue->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($newValue->catalogEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($newValue->catalogEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($newValue->catalogEntry->catalogEdition->catalog->id)
            ->toBe($this->catalogB->id);

        expect($newValue->condition->id)
            ->toBe($this->postfris->id);

        // Controleer de daadwerkelijk opgeslagen cataloguswaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $newValue->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 32.75,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // Er mag geen dubbele postfrisse cataloguswaarde bestaan.
        expect(
            $this->entryB1->values()
                ->where('condition_id', $this->postfris->id)
                ->count()
        )->toBe(1);

        // Alle bestaande catalogusvermeldingen
        // moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // Alleen bij de ingediende catalogusvermelding B1
            // is een eventuele timestampwijziging toegestaan.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vijf oorspronkelijke cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de behouden catalogusvermelding B1.
        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1->catalog_number)
            ->toBe('B-100');

        expect($freshEntryB1->values()->count())
            ->toBe(2);

        // Controleer specifiek de behouden gestempelde waarde.
        $freshGestempeld = $freshEntryB1->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($freshGestempeld->id)
            ->toBe($this->valueB1Gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('12.50');

        expect($freshGestempeld->currency)
            ->toBe('USD');

        expect($freshGestempeld->needs_review)
            ->toBeFalse();

        // Geen enkele cataloguswaarde mag voor controle
        // zijn gemarkeerd.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'needs_review' => true,
        ]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de historische identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBe($this->postfris->id);

        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        expect($log->record_id)
            ->toBe($newValue->id);

        expect($log->action)
            ->toBe('created');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition->id)
            ->toBe($this->postfris->id);

        expect($log->stampSeries)
            ->toBeNull();

        // Bij een nieuw record bestaan geen oorspronkelijke waarden.
        expect($log->old_values)
            ->toBeNull();

        // Controleer de geregistreerde nieuwe attributen.
        expect($log->new_values)
            ->toBeArray()
            ->toHaveKeys([
                'id',
                'stamp_catalog_entry_id',
                'condition_id',
                'catalog_value',
                'currency',
                'created_at',
                'updated_at',
            ]);

        expect($log->new_values['id'])
            ->toBe($newValue->id);

        expect($log->new_values['stamp_catalog_entry_id'])
            ->toBe($this->entryB1->id);

        expect($log->new_values['condition_id'])
            ->toBe($this->postfris->id);

        expect(is_numeric($log->new_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->new_values['catalog_value'])
            ->toBe(32.75);

        expect($log->new_values['currency'])
            ->toBe('USD');

        expect($log->new_values['created_at'])
            ->toBe($newValue->getRawOriginal('created_at'));

        expect($log->new_values['updated_at'])
            ->toBe($newValue->getRawOriginal('updated_at'));

        // Vergelijk alle geregistreerde nieuwe attributen
        // met de daadwerkelijk opgeslagen databaseattributen.
        $storedAttributes = $newValue->getAttributes();

        foreach ($log->new_values as $attribute => $loggedValue) {
            expect($storedAttributes)
                ->toHaveKey($attribute);

            if ($attribute === 'catalog_value') {
                expect(is_numeric($loggedValue))
                    ->toBeTrue();

                expect((float) $loggedValue)
                    ->toBe((float) $storedAttributes[$attribute]);

                continue;
            }

            if ($attribute === 'needs_review') {
                expect(in_array($loggedValue, [false, 0], true))
                    ->toBeTrue();

                expect((bool) $loggedValue)
                    ->toBe((bool) $storedAttributes[$attribute]);

                continue;
            }

            expect($loggedValue)
                ->toBe($storedAttributes[$attribute]);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $newValue->id,
            'action' => 'created',
            'old_values' => null,
        ]);

        // De historische record-ID moet verwijzen
        // naar de daadwerkelijk aangemaakte cataloguswaarde.
        expect(StampCatalogValue::find($log->record_id)?->id)
            ->toBe($newValue->id);

        // Er mogen geen wijzigingslogs voor vermeldingen bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // Er mogen geen updated- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'updated',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert geen wijzigingsgeschiedenis bij lege cataloguswaarden via catalog entries', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(2);

    expect($this->editionB1->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Postfris->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Gestempeld->currency)
        ->toBe('USD');

    expect($this->valueB1Gestempeld->needs_review)
        ->toBeFalse();

    // Controleer de oorspronkelijke databasewaarden.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $this->valueB1Postfris->id,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 25.00,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $this->valueB1Gestempeld->id,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 12.50,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-100',
            'values' => [
                'postfris' => '',
                'gestempeld' => null,
            ],
        ],
    ];

    // Er worden uitsluitend gegevens voor route C ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_editions');

    expect($payload['catalog_entries'])
        ->toHaveCount(1);

    expect($payload['catalog_entries'][$this->entryB1->id]['values'])
        ->toHaveCount(2)
        ->toHaveKeys([
            'postfris',
            'gestempeld',
        ]);

    expect(
        $payload['catalog_entries'][$this->entryB1->id]['values']['postfris']
    )->toBe('');

    expect(
        $payload['catalog_entries'][$this->entryB1->id]['values']['gestempeld']
    )->toBeNull();

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Dien lege cataloguswaarden in via route C.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        // De algemene postzegelgegevens worden wel gewijzigd.
        // Dit mag geen catalogusgeschiedenis veroorzaken.
        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Alle bestaande catalogusvermeldingen
        // moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // Alleen bij de ingediende catalogusvermelding B1
            // is een eventuele timestampwijziging toegestaan.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle zes cataloguswaarden moeten volledig
        // ongewijzigd blijven, inclusief timestamps.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            expect($freshValue->getRawOriginal('created_at'))
                ->toBe($originalValues[$value->id]['created_at']);

            expect($freshValue->getRawOriginal('updated_at'))
                ->toBe($originalValues[$value->id]['updated_at']);

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek catalogusvermelding B1.
        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1->id)
            ->toBe($this->entryB1->id);

        expect($freshEntryB1->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntryB1->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($freshEntryB1->catalog_number)
            ->toBe('B-100');

        expect($freshEntryB1->values()->count())
            ->toBe(2);

        // Controleer de behouden postfrisse cataloguswaarde.
        $freshPostfris = $freshEntryB1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($freshPostfris->id)
            ->toBe($this->valueB1Postfris->id);

        expect($freshPostfris->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($freshPostfris->condition_id)
            ->toBe($this->postfris->id);

        expect($freshPostfris->catalog_value)
            ->toBe('25.00');

        expect($freshPostfris->currency)
            ->toBe('USD');

        expect($freshPostfris->needs_review)
            ->toBeFalse();

        expect($freshPostfris->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($freshPostfris->condition->id)
            ->toBe($this->postfris->id);

        // Controleer de behouden gestempelde cataloguswaarde.
        $freshGestempeld = $freshEntryB1->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($freshGestempeld->id)
            ->toBe($this->valueB1Gestempeld->id);

        expect($freshGestempeld->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($freshGestempeld->condition_id)
            ->toBe($this->gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('12.50');

        expect($freshGestempeld->currency)
            ->toBe('USD');

        expect($freshGestempeld->needs_review)
            ->toBeFalse();

        expect($freshGestempeld->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($freshGestempeld->condition->id)
            ->toBe($this->gestempeld->id);

        // Controleer de behouden catalogusvermelding.
        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-100',
        ]);

        // Controleer de behouden postfrisse databasewaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 25.00,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // Controleer de behouden gestempelde databasewaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Gestempeld->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 12.50,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // Lege invoervelden mogen niet worden omgezet
        // naar cataloguswaarden van nul.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'catalog_value' => 0.00,
        ]);

        // Lege invoervelden mogen niet worden opgeslagen
        // als cataloguswaarden met null.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'catalog_value' => null,
        ]);

        // Geen enkele cataloguswaarde mag opnieuw
        // voor controle zijn gemarkeerd.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'needs_review' => true,
        ]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Exact dezelfde catalogusvermeldingen
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Exact dezelfde cataloguswaarden
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er mag geen blijvende geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        expect(CatalogChangeBatch::query()->count())
            ->toBe(0);

        // Er mogen geen wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(CatalogChangeLog::query()->count())
            ->toBe(0);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor een van de vier catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mogen geen geschiedenisbatches
        // voor een van beide gebruikers bestaan.
        foreach ([
            $this->user,
            $this->otherUser,
        ] as $user) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'user_id' => $user->id,
            ]);
        }

        // Geen enkele wijzigingsactie mag zijn geregistreerd.
        foreach ([
            'created',
            'updated',
            'deleted',
        ] as $action) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'action' => $action,
            ]);
        }

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het aanmaken van een nieuwe catalogusvermelding met cataloguswaarden via catalog editions', function () {
    // Maak catalogus C beschikbaar voor het huidige land
    // van de bestaande postzegel.
    $this->catalogC->countries()->attach($this->country->id);

    // De voorbereidende landkoppeling mag geen
    // catalogusgeschiedenis veroorzaken.
    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar de landkoppelingen van catalogus C.
    $originalCountryIds = $this->catalogC->countries()
        ->orderBy('countries.id')
        ->pluck('countries.id')
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    expect($this->editionC1->currency)
        ->toBe('GBP');

    expect($originalCountryIds)
        ->toContain($this->country->id)
        ->toContain($this->otherCountry->id)
        ->toHaveCount(2);

    // Cataloguseditie C1 mag nog niet gekoppeld zijn.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    expect(
        $this->stamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionC1->id)
            ->exists()
    )->toBeFalse();

    // Controleer de beschikbare waarderingsregels.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'catalog_id' => $this->catalogC->id,
        'condition_id' => $this->postfris->id,
        'country_id' => null,
    ]);

    $this->assertDatabaseHas('catalog_condition_rules', [
        'catalog_id' => $this->catalogC->id,
        'condition_id' => $this->gestempeld->id,
        'country_id' => null,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_editions'] = [
        $this->editionC1->id => [
            'catalog_number' => 'C-300',
            'values' => [
                'postfris' => '45.75',
                'gestempeld' => '22.50',
            ],
        ],
    ];

    // Er worden uitsluitend gegevens voor route D ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries');

    expect($payload['catalog_editions'])
        ->toHaveCount(1);

    expect($payload['catalog_editions'][$this->editionC1->id]['values'])
        ->toHaveCount(2)
        ->toHaveKeys([
            'postfris',
            'gestempeld',
        ]);

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Maak de nieuwe catalogusvermelding
        // met cataloguswaarden aan via route D.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Er moet precies één catalogusvermelding
        // zijn toegevoegd.
        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        // Er moeten precies twee cataloguswaarden
        // zijn toegevoegd.
        $this->assertDatabaseCount('stamp_catalog_values', 8);

        // Haal de nieuwe catalogusvermelding op.
        $newEntry = $freshStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionC1->id)
            ->sole();

        expect($newEntry)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect($newEntry->id)
            ->not->toBeNull();

        expect(array_key_exists($newEntry->id, $originalEntries))
            ->toBeFalse();

        expect($newEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($newEntry->catalog_edition_id)
            ->toBe($this->editionC1->id);

        expect($newEntry->catalog_number)
            ->toBe('C-300');

        expect($newEntry->created_at)
            ->not->toBeNull();

        expect($newEntry->updated_at)
            ->not->toBeNull();

        expect($newEntry->getRawOriginal('created_at'))
            ->toBe($newEntry->getRawOriginal('updated_at'));

        // Controleer de relaties van de nieuwe vermelding.
        expect($newEntry->stamp->id)
            ->toBe($this->stamp->id);

        expect($newEntry->catalogEdition->id)
            ->toBe($this->editionC1->id);

        expect($newEntry->catalogEdition->catalog->id)
            ->toBe($this->catalogC->id);

        expect($newEntry->values()->count())
            ->toBe(2);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $newEntry->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
            'catalog_number' => 'C-300',
        ]);

        // Haal de nieuwe postfrisse cataloguswaarde op.
        $newPostfris = $newEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Haal de nieuwe gestempelde cataloguswaarde op.
        $newGestempeld = $newEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        // De nieuwe cataloguswaarden moeten verschillende
        // record-ID's hebben.
        expect($newPostfris->id)
            ->not->toBe($newGestempeld->id);

        // Controleer beide nieuwe cataloguswaarden.
        $newValues = [
            [
                'value' => $newPostfris,
                'condition' => $this->postfris,
                'amount' => '45.75',
            ],
            [
                'value' => $newGestempeld,
                'condition' => $this->gestempeld,
                'amount' => '22.50',
            ],
        ];

        foreach ($newValues as $newValueData) {
            $newValue = $newValueData['value'];

            $condition = $newValueData['condition'];

            $amount = $newValueData['amount'];

            expect($newValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($newValue->id)
                ->not->toBeNull();

            expect(array_key_exists($newValue->id, $originalValues))
                ->toBeFalse();

            expect($newValue->stamp_catalog_entry_id)
                ->toBe($newEntry->id);

            expect($newValue->condition_id)
                ->toBe($condition->id);

            expect($newValue->catalog_value)
                ->toBe($amount);

            expect((float) $newValue->catalog_value)
                ->toBe((float) $amount);

            // Beide waarden moeten de valuta van C1 overnemen.
            expect($newValue->currency)
                ->toBe('GBP');

            expect($newValue->needs_review)
                ->toBeFalse();

            expect($newValue->created_at)
                ->not->toBeNull();

            expect($newValue->updated_at)
                ->not->toBeNull();

            expect($newValue->getRawOriginal('created_at'))
                ->toBe($newValue->getRawOriginal('updated_at'));

            // Controleer de relaties.
            expect($newValue->catalogEntry->id)
                ->toBe($newEntry->id);

            expect($newValue->catalogEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($newValue->catalogEntry->catalog_edition_id)
                ->toBe($this->editionC1->id);

            expect($newValue->condition->id)
                ->toBe($condition->id);

            // Controleer de opgeslagen databasewaarde.
            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $newValue->id,
                'stamp_catalog_entry_id' => $newEntry->id,
                'condition_id' => $condition->id,
                'catalog_value' => (float) $amount,
                'currency' => 'GBP',
                'needs_review' => false,
            ]);
        }

        // Alle drie oorspronkelijke catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor deze vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle zes oorspronkelijke cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Geen enkele cataloguswaarde mag voor controle
        // zijn gemarkeerd.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'needs_review' => true,
        ]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(4);

        expect($freshStamp->catalogValues()->count())
            ->toBe(8);

        // Controleer de exacte gekoppelde catalogusvermeldingen.
        $expectedEntryIds = collect($entries)
            ->pluck('id')
            ->push($newEntry->id)
            ->sort()
            ->values()
            ->all();

        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe($expectedEntryIds);

        // Controleer de exacte gekoppelde cataloguswaarden.
        $expectedValueIds = collect($values)
            ->pluck('id')
            ->push($newPostfris->id)
            ->push($newGestempeld->id)
            ->sort()
            ->values()
            ->all();

        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe($expectedValueIds);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionC1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogC->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionC1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionC1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionC1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogC->name,
            'catalog_edition_name' => $this->editionC1->edition_name,
        ]);

        // Er moeten precies drie wijzigingslogs bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 3);

        expect($batch->logs()->count())
            ->toBe(3);

        // Haal de wijzigingslogs op in registratievolgorde.
        $logs = $batch->logs()
            ->orderBy('id')
            ->get();

        expect($logs)->toHaveCount(3);

        // De catalogusvermelding moet als eerste
        // zijn geregistreerd.
        $entryLog = $logs[0];

        expect($entryLog->record_type)
            ->toBe('stamp_catalog_entry');

        expect($entryLog->record_id)
            ->toBe($newEntry->id);

        expect($entryLog->condition_id)
            ->toBeNull();

        expect($entryLog->condition)
            ->toBeNull();

        // De postfrisse cataloguswaarde moet als tweede
        // zijn geregistreerd.
        $postfrisLog = $logs[1];

        expect($postfrisLog->record_type)
            ->toBe('stamp_catalog_value');

        expect($postfrisLog->record_id)
            ->toBe($newPostfris->id);

        expect($postfrisLog->condition_id)
            ->toBe($this->postfris->id);

        expect($postfrisLog->condition->id)
            ->toBe($this->postfris->id);

        // De gestempelde cataloguswaarde moet als derde
        // zijn geregistreerd.
        $gestempeldLog = $logs[2];

        expect($gestempeldLog->record_type)
            ->toBe('stamp_catalog_value');

        expect($gestempeldLog->record_id)
            ->toBe($newGestempeld->id);

        expect($gestempeldLog->condition_id)
            ->toBe($this->gestempeld->id);

        expect($gestempeldLog->condition->id)
            ->toBe($this->gestempeld->id);

        // Controleer de gemeenschappelijke historische gegevens
        // van alle drie wijzigingslogs.
        foreach ($logs as $log) {
            expect($log)
                ->toBeInstanceOf(CatalogChangeLog::class);

            expect($log->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($log->catalog_edition_id)
                ->toBe($this->editionC1->id);

            expect($log->stamp_id)
                ->toBe($this->stamp->id);

            expect($log->stamp_series_id)
                ->toBeNull();

            expect($log->action)
                ->toBe('created');

            expect($log->old_values)
                ->toBeNull();

            expect($log->new_values)
                ->toBeArray();

            // Controleer de relaties van het wijzigingslog.
            expect($log->batch->id)
                ->toBe($batch->id);

            expect($log->catalogEdition->id)
                ->toBe($this->editionC1->id);

            expect($log->stamp->id)
                ->toBe($this->stamp->id);

            expect($log->stampSeries)
                ->toBeNull();

            // Controleer het opgeslagen wijzigingslog.
            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $log->id,
                'catalog_change_batch_id' => $batch->id,
                'catalog_edition_id' => $this->editionC1->id,
                'stamp_id' => $this->stamp->id,
                'stamp_series_id' => null,
                'condition_id' => $log->condition_id,
                'record_type' => $log->record_type,
                'record_id' => $log->record_id,
                'action' => 'created',
                'old_values' => null,
            ]);
        }

        // Controleer de geregistreerde attributen
        // van de nieuwe catalogusvermelding.
        expect($entryLog->new_values)
            ->toHaveKeys([
                'id',
                'stamp_id',
                'catalog_edition_id',
                'catalog_number',
                'created_at',
                'updated_at',
            ]);

        expect($entryLog->new_values['id'])
            ->toBe($newEntry->id);

        expect($entryLog->new_values['stamp_id'])
            ->toBe($this->stamp->id);

        expect($entryLog->new_values['catalog_edition_id'])
            ->toBe($this->editionC1->id);

        expect($entryLog->new_values['catalog_number'])
            ->toBe('C-300');

        // Vergelijk alle geregistreerde attributen
        // met de opgeslagen catalogusvermelding.
        $storedEntryAttributes = $newEntry->getAttributes();

        foreach ($entryLog->new_values as $attribute => $loggedValue) {
            expect($storedEntryAttributes)
                ->toHaveKey($attribute);

            expect($loggedValue)
                ->toBe($storedEntryAttributes[$attribute]);
        }

        // Controleer de geregistreerde attributen
        // van beide nieuwe cataloguswaarden.
        $valueLogs = [
            [
                'log' => $postfrisLog,
                'value' => $newPostfris,
                'condition' => $this->postfris,
                'amount' => 45.75,
            ],
            [
                'log' => $gestempeldLog,
                'value' => $newGestempeld,
                'condition' => $this->gestempeld,
                'amount' => 22.50,
            ],
        ];

        foreach ($valueLogs as $valueLogData) {
            $log = $valueLogData['log'];

            $value = $valueLogData['value'];

            $condition = $valueLogData['condition'];

            $amount = $valueLogData['amount'];

            expect($log->new_values)
                ->toHaveKeys([
                    'id',
                    'stamp_catalog_entry_id',
                    'condition_id',
                    'catalog_value',
                    'currency',
                    'created_at',
                    'updated_at',
                ]);

            expect($log->new_values['id'])
                ->toBe($value->id);

            expect($log->new_values['stamp_catalog_entry_id'])
                ->toBe($newEntry->id);

            expect($log->new_values['condition_id'])
                ->toBe($condition->id);

            expect(is_numeric($log->new_values['catalog_value']))
                ->toBeTrue();

            expect((float) $log->new_values['catalog_value'])
                ->toBe($amount);

            expect($log->new_values['currency'])
                ->toBe('GBP');

            expect($log->new_values['created_at'])
                ->toBe($value->getRawOriginal('created_at'));

            expect($log->new_values['updated_at'])
                ->toBe($value->getRawOriginal('updated_at'));

            // Vergelijk alle geregistreerde attributen
            // met de daadwerkelijk opgeslagen databasegegevens.
            $storedAttributes = $value->getAttributes();

            foreach ($log->new_values as $attribute => $loggedValue) {
                expect($storedAttributes)
                    ->toHaveKey($attribute);

                if ($attribute === 'catalog_value') {
                    expect(is_numeric($loggedValue))
                        ->toBeTrue();

                    expect((float) $loggedValue)
                        ->toBe((float) $storedAttributes[$attribute]);

                    continue;
                }

                if ($attribute === 'needs_review') {
                    expect(in_array($loggedValue, [false, 0], true))
                        ->toBeTrue();

                    expect((bool) $loggedValue)
                        ->toBe((bool) $storedAttributes[$attribute]);

                    continue;
                }

                expect($loggedValue)
                    ->toBe($storedAttributes[$attribute]);
            }

            // De historische record-ID moet verwijzen
            // naar de daadwerkelijk aangemaakte cataloguswaarde.
            expect(StampCatalogValue::find($log->record_id)?->id)
                ->toBe($value->id);
        }

        // De historische record-ID van de catalogusvermelding
        // moet verwijzen naar het daadwerkelijk aangemaakte record.
        expect(StampCatalogEntry::find($entryLog->record_id)?->id)
            ->toBe($newEntry->id);

        // Controleer de exacte verdeling van de wijzigingslogs.
        expect(
            $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->where('action', 'created')
                ->count()
        )->toBe(1);

        expect(
            $batch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->where('action', 'created')
                ->count()
        )->toBe(2);

        // Er mogen geen updated- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'updated',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // De landkoppelingen van catalogus C
        // moeten ongewijzigd blijven.
        expect(
            $this->catalogC->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all()
        )->toBe($originalCountryIds);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het wijzigen van een bestaand catalogusnummer via catalog editions', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke attributen
    // van de te wijzigen catalogusvermelding.
    $changedEntryId = $this->entryA1->id;

    $originalChangedEntry = $originalEntries[$changedEntryId];

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryA1->catalog_number)
        ->toBe('A-100');

    expect($this->entryA1->catalog_edition_id)
        ->toBe($this->editionA1->id);

    expect($this->entryA1->values()->count())
        ->toBe(2);

    expect($this->editionA1->currency)
        ->toBe('EUR');

    expect($this->valueA1Postfris->catalog_value)
        ->toBe('15.00');

    expect($this->valueA1Gestempeld->catalog_value)
        ->toBe('7.50');

    expect($this->valueA1Postfris->needs_review)
        ->toBeFalse();

    expect($this->valueA1Gestempeld->needs_review)
        ->toBeFalse();

    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $changedEntryId,
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionA1->id,
        'catalog_number' => 'A-100',
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_editions'] = [
        $this->editionA1->id => [
            'catalog_number' => 'A-175',
        ],
    ];

    // Er worden uitsluitend gegevens voor route D ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries');

    expect($payload['catalog_editions'])
        ->toHaveCount(1);

    expect($payload['catalog_editions'][$this->editionA1->id])
        ->not->toHaveKey('values');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig het bestaande catalogusnummer via route D.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Controleer de gewijzigde catalogusvermelding.
        $freshEntry = $this->entryA1->fresh();

        expect($freshEntry)->not->toBeNull();

        expect($freshEntry->id)
            ->toBe($changedEntryId);

        expect($freshEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntry->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($freshEntry->catalog_number)
            ->toBe('A-175');

        // Controleer de relaties van de catalogusvermelding.
        expect($freshEntry->stamp->id)
            ->toBe($this->stamp->id);

        expect($freshEntry->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($freshEntry->catalogEdition->catalog->id)
            ->toBe($this->catalogA->id);

        expect($freshEntry->values()->count())
            ->toBe(2);

        // De oorspronkelijke aanmaaktijd moet behouden blijven.
        expect($freshEntry->getRawOriginal('created_at'))
            ->toBe($originalChangedEntry['created_at']);

        // De wijzigingstijd moet daadwerkelijk zijn aangepast.
        expect($freshEntry->getRawOriginal('updated_at'))
            ->not->toBe($originalChangedEntry['updated_at']);

        // Uitsluitend catalog_number en updated_at
        // mogen zijn gewijzigd.
        $expectedEntryAttributes = $originalChangedEntry;

        $expectedEntryAttributes['catalog_number'] = 'A-175';

        $expectedEntryAttributes['updated_at'] =
            $freshEntry->getRawOriginal('updated_at');

        expect($freshEntry->getAttributes())
            ->toBe($expectedEntryAttributes);

        // Controleer de opgeslagen catalogusvermelding.
        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $changedEntryId,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionA1->id,
            'catalog_number' => 'A-175',
        ]);

        // Het oorspronkelijke catalogusnummer
        // mag niet meer bij hetzelfde record bestaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $changedEntryId,
            'catalog_number' => 'A-100',
        ]);

        // Er mag geen vervangende of dubbele vermelding bestaan.
        $storedEntry = $freshStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionA1->id)
            ->sole();

        expect($storedEntry->id)
            ->toBe($changedEntryId);

        // Alle overige catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            if ($entry->id === $changedEntryId) {
                continue;
            }

            $freshUnchangedEntry = $entry->fresh();

            expect($freshUnchangedEntry)->not->toBeNull();

            expect($freshUnchangedEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshUnchangedEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor deze vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle zes cataloguswaarden moeten volledig
        // ongewijzigd blijven, inclusief timestamps.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de behouden postfrisse waarde.
        $freshPostfris = $freshEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($freshPostfris->id)
            ->toBe($this->valueA1Postfris->id);

        expect($freshPostfris->catalog_value)
            ->toBe('15.00');

        expect($freshPostfris->currency)
            ->toBe('EUR');

        expect($freshPostfris->needs_review)
            ->toBeFalse();

        // Controleer specifiek de behouden gestempelde waarde.
        $freshGestempeld = $freshEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($freshGestempeld->id)
            ->toBe($this->valueA1Gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('7.50');

        expect($freshGestempeld->currency)
            ->toBe('EUR');

        expect($freshGestempeld->needs_review)
            ->toBeFalse();

        // Geen enkele cataloguswaarde mag opnieuw
        // voor controle zijn gemarkeerd.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'needs_review' => true,
        ]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Exact dezelfde catalogusvermeldingen
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Exact dezelfde cataloguswaarden
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogA->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionA1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionA1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogA->name,
            'catalog_edition_name' => $this->editionA1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de historische identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBeNull();

        expect($log->record_type)
            ->toBe('stamp_catalog_entry');

        expect($log->record_id)
            ->toBe($changedEntryId);

        expect($log->action)
            ->toBe('updated');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition)
            ->toBeNull();

        expect($log->stampSeries)
            ->toBeNull();

        // Controleer de volledige oorspronkelijke loggegevens.
        expect($log->old_values)
            ->toBeArray()
            ->toHaveCount(1)
            ->toBe([
                'catalog_number' => 'A-100',
            ]);

        // Controleer de volledige nieuwe loggegevens.
        expect($log->new_values)
            ->toBeArray()
            ->toHaveCount(1)
            ->toBe([
                'catalog_number' => 'A-175',
            ]);

        // Vergelijk de oorspronkelijke historische waarde
        // met de oorspronkelijke databasewaarde.
        expect($log->old_values['catalog_number'])
            ->toBe($originalChangedEntry['catalog_number']);

        // Vergelijk de nieuwe historische waarde
        // met de daadwerkelijk opgeslagen databasewaarde.
        expect($log->new_values['catalog_number'])
            ->toBe($freshEntry->catalog_number);

        // Ongewijzigde attributen en timestamps
        // mogen niet in het wijzigingslog voorkomen.
        foreach ([
            'id',
            'stamp_id',
            'catalog_edition_id',
            'notes',
            'created_at',
            'updated_at',
        ] as $attribute) {
            expect($log->old_values)
                ->not->toHaveKey($attribute);

            expect($log->new_values)
                ->not->toHaveKey($attribute);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionA1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => null,
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $changedEntryId,
            'action' => 'updated',
        ]);

        // De historische record-ID moet verwijzen
        // naar de oorspronkelijke catalogusvermelding.
        expect(StampCatalogEntry::find($log->record_id)?->id)
            ->toBe($changedEntryId);

        // Er mogen geen wijzigingslogs voor cataloguswaarden bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
        ]);

        // Er mogen geen created- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het wijzigen van een bestaande cataloguswaarde via catalog editions', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke attributen
    // van de te wijzigen cataloguswaarde.
    $changedValueId = $this->valueA2Postfris->id;

    $originalChangedValue = $originalValues[$changedValueId];

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryA2->catalog_number)
        ->toBe('A-200');

    expect($this->entryA2->values()->count())
        ->toBe(2);

    expect($this->editionA2->currency)
        ->toBe('EUR');

    expect($this->valueA2Postfris->catalog_value)
        ->toBe('20.00');

    expect($this->valueA2Postfris->currency)
        ->toBe('EUR');

    expect($this->valueA2Postfris->needs_review)
        ->toBeFalse();

    expect($this->valueA2Gestempeld->catalog_value)
        ->toBe('10.00');

    expect($this->valueA2Gestempeld->needs_review)
        ->toBeFalse();

    // Controleer de oorspronkelijke databasewaarde.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $changedValueId,
        'stamp_catalog_entry_id' => $this->entryA2->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 20.00,
        'currency' => 'EUR',
        'needs_review' => false,
    ]);

    // Controleer de beschikbare waarderingsregel.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'catalog_id' => $this->catalogA->id,
        'condition_id' => $this->postfris->id,
        'country_id' => null,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_editions'] = [
        $this->editionA2->id => [
            'catalog_number' => 'A-200',
            'values' => [
                'postfris' => '28.75',
            ],
        ],
    ];

    // Er worden uitsluitend gegevens voor route D ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries');

    expect($payload['catalog_editions'])
        ->toHaveCount(1);

    expect($payload['catalog_editions'][$this->editionA2->id]['values'])
        ->toHaveCount(1)
        ->not->toHaveKey('gestempeld');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig de bestaande cataloguswaarde via route D.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Controleer de gewijzigde cataloguswaarde.
        $freshValue = $this->valueA2Postfris->fresh();

        expect($freshValue)->not->toBeNull();

        expect($freshValue->id)
            ->toBe($changedValueId);

        expect($freshValue->stamp_catalog_entry_id)
            ->toBe($this->entryA2->id);

        expect($freshValue->condition_id)
            ->toBe($this->postfris->id);

        expect($freshValue->catalog_value)
            ->toBe('28.75');

        expect((float) $freshValue->catalog_value)
            ->toBe(28.75);

        expect($freshValue->currency)
            ->toBe('EUR');

        // De gewijzigde cataloguswaarde
        // moet opnieuw worden gecontroleerd.
        expect($freshValue->needs_review)
            ->toBeTrue();

        // Controleer de relaties van de cataloguswaarde.
        expect($freshValue->catalogEntry->id)
            ->toBe($this->entryA2->id);

        expect($freshValue->catalogEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshValue->catalogEntry->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($freshValue->catalogEntry->catalogEdition->catalog->id)
            ->toBe($this->catalogA->id);

        expect($freshValue->condition->id)
            ->toBe($this->postfris->id);

        // De oorspronkelijke aanmaaktijd moet behouden blijven.
        expect($freshValue->getRawOriginal('created_at'))
            ->toBe($originalChangedValue['created_at']);

        // De wijzigingstijd moet daadwerkelijk zijn aangepast.
        expect($freshValue->getRawOriginal('updated_at'))
            ->not->toBe($originalChangedValue['updated_at']);

        // Uitsluitend catalog_value, needs_review
        // en updated_at mogen zijn gewijzigd.
        $expectedValueAttributes = $originalChangedValue;

        $expectedValueAttributes['catalog_value'] =
            $freshValue->getRawOriginal('catalog_value');

        $expectedValueAttributes['needs_review'] =
            $freshValue->getRawOriginal('needs_review');

        $expectedValueAttributes['updated_at'] =
            $freshValue->getRawOriginal('updated_at');

        expect($freshValue->getAttributes())
            ->toBe($expectedValueAttributes);

        // Controleer de opgeslagen cataloguswaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $changedValueId,
            'stamp_catalog_entry_id' => $this->entryA2->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 28.75,
            'currency' => 'EUR',
            'needs_review' => true,
        ]);

        // De oorspronkelijke waarde mag niet meer bestaan
        // bij hetzelfde record.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $changedValueId,
            'catalog_value' => 20.00,
        ]);

        // Er mag geen vervangend of dubbel record bestaan.
        $storedValue = $this->entryA2->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($storedValue->id)
            ->toBe($changedValueId);

        // Alle bestaande catalogusvermeldingen
        // moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // Alleen bij de ingediende catalogusvermelding A2
            // is een eventuele timestampwijziging toegestaan.
            if ($entry->id === $this->entryA2->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vijf overige cataloguswaarden
        // moeten volledig ongewijzigd blijven.
        foreach ($values as $value) {
            if ($value->id === $changedValueId) {
                continue;
            }

            $freshUnchangedValue = $value->fresh();

            expect($freshUnchangedValue)->not->toBeNull();

            expect($freshUnchangedValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshUnchangedValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de behouden catalogusvermelding A2.
        $freshEntryA2 = $this->entryA2->fresh();

        expect($freshEntryA2->catalog_number)
            ->toBe('A-200');

        expect($freshEntryA2->values()->count())
            ->toBe(2);

        // Controleer specifiek de behouden gestempelde waarde.
        $freshGestempeld = $freshEntryA2->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($freshGestempeld->id)
            ->toBe($this->valueA2Gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('10.00');

        expect($freshGestempeld->currency)
            ->toBe('EUR');

        expect($freshGestempeld->needs_review)
            ->toBeFalse();

        // Uitsluitend de gewijzigde cataloguswaarde
        // mag voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->pluck('id')
                ->all()
        )->toBe([$changedValueId]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Exact dezelfde catalogusvermeldingen
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Exact dezelfde cataloguswaarden
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogA->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionA2->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionA2->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionA2->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogA->name,
            'catalog_edition_name' => $this->editionA2->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de historische identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBe($this->postfris->id);

        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        expect($log->record_id)
            ->toBe($changedValueId);

        expect($log->action)
            ->toBe('updated');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionA2->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition->id)
            ->toBe($this->postfris->id);

        expect($log->stampSeries)
            ->toBeNull();

        // Beide arrays mogen uitsluitend de twee
        // daadwerkelijk gewijzigde attributen bevatten.
        expect($log->old_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        expect($log->new_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        // Controleer de oorspronkelijke cataloguswaarde.
        expect(is_numeric($log->old_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->old_values['catalog_value'])
            ->toBe(20.00);

        // Controleer de nieuwe cataloguswaarde.
        expect(is_numeric($log->new_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->new_values['catalog_value'])
            ->toBe(28.75);

        // Controleer de oorspronkelijke controlemarkering.
        expect(
            in_array(
                $log->old_values['needs_review'],
                [false, 0],
                true
            )
        )->toBeTrue();

        // Controleer de nieuwe controlemarkering.
        expect(
            in_array(
                $log->new_values['needs_review'],
                [true, 1],
                true
            )
        )->toBeTrue();

        // Normaliseer de oorspronkelijke loggegevens.
        $loggedOldValues = $log->old_values;

        $loggedOldValues['catalog_value'] =
            (float) $loggedOldValues['catalog_value'];

        $loggedOldValues['needs_review'] =
            (bool) $loggedOldValues['needs_review'];

        // Normaliseer de nieuwe loggegevens.
        $loggedNewValues = $log->new_values;

        $loggedNewValues['catalog_value'] =
            (float) $loggedNewValues['catalog_value'];

        $loggedNewValues['needs_review'] =
            (bool) $loggedNewValues['needs_review'];

        // De volgorde van JSON-attributen is niet relevant.
        ksort($loggedOldValues);

        ksort($loggedNewValues);

        // Controleer de volledige oorspronkelijke loggegevens.
        expect($loggedOldValues)
            ->toBe([
                'catalog_value' => 20.00,
                'needs_review' => false,
            ]);

        // Controleer de volledige nieuwe loggegevens.
        expect($loggedNewValues)
            ->toBe([
                'catalog_value' => 28.75,
                'needs_review' => true,
            ]);

        // Vergelijk de oorspronkelijke historische waarden
        // met de oorspronkelijke databasewaarden.
        expect($loggedOldValues['catalog_value'])
            ->toBe(
                (float) $originalChangedValue['catalog_value']
            );

        expect($loggedOldValues['needs_review'])
            ->toBe(
                (bool) $originalChangedValue['needs_review']
            );

        // Vergelijk de nieuwe historische waarden
        // met de daadwerkelijk opgeslagen databasewaarden.
        expect($loggedNewValues['catalog_value'])
            ->toBe((float) $freshValue->catalog_value);

        expect($loggedNewValues['needs_review'])
            ->toBe($freshValue->needs_review);

        // Ongewijzigde attributen en timestamps
        // mogen niet in het wijzigingslog voorkomen.
        foreach ([
            'id',
            'stamp_catalog_entry_id',
            'condition_id',
            'currency',
            'notes',
            'created_at',
            'updated_at',
        ] as $attribute) {
            expect($log->old_values)
                ->not->toHaveKey($attribute);

            expect($log->new_values)
                ->not->toHaveKey($attribute);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionA2->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $changedValueId,
            'action' => 'updated',
        ]);

        // De historische record-ID moet verwijzen
        // naar de oorspronkelijke cataloguswaarde.
        expect(StampCatalogValue::find($log->record_id)?->id)
            ->toBe($changedValueId);

        // Er mogen geen wijzigingslogs voor vermeldingen bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // Er mogen geen created- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert wijzigingsgeschiedenis bij het verwijderen van een bestaande cataloguswaarde via catalog editions', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke attributen
    // van de te verwijderen cataloguswaarde.
    $deletedValueId = $this->valueB1Postfris->id;

    $originalDeletedValue = $originalValues[$deletedValueId];

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(2);

    expect($this->editionB1->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Postfris->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Gestempeld->currency)
        ->toBe('USD');

    expect($this->valueB1Gestempeld->needs_review)
        ->toBeFalse();

    // Controleer de oorspronkelijke databasewaarde.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $deletedValueId,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 25.00,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-100',
            'values' => [
                'postfris' => '',
            ],
        ],
    ];

    // Er worden uitsluitend gegevens voor route D ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries');

    expect($payload['catalog_editions'])
        ->toHaveCount(1);

    expect($payload['catalog_editions'][$this->editionB1->id]['values'])
        ->toHaveCount(1)
        ->not->toHaveKey('gestempeld');

    expect(
        $payload['catalog_editions'][$this->editionB1->id]['values']['postfris']
    )->toBe('');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Verwijder de bestaande cataloguswaarde via route D.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // De oorspronkelijke cataloguswaarde
        // mag niet meer bestaan.
        expect($this->valueB1Postfris->fresh())
            ->toBeNull();

        expect(StampCatalogValue::find($deletedValueId))
            ->toBeNull();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $deletedValueId,
        ]);

        // Er mag geen vervangende postfrisse waarde bestaan.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
        ]);

        expect(
            $this->entryB1->values()
                ->where('condition_id', $this->postfris->id)
                ->exists()
        )->toBeFalse();

        // Er moet precies één cataloguswaarde zijn verwijderd.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        // Alle bestaande catalogusvermeldingen
        // moeten behouden blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            $expectedAttributes = $originalEntries[$entry->id];

            // Alleen bij de ingediende catalogusvermelding B1
            // is een eventuele timestampwijziging toegestaan.
            if ($entry->id === $this->entryB1->id) {
                $expectedAttributes['updated_at'] =
                    $freshEntry->getRawOriginal('updated_at');
            }

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            $expectedValueCount =
                $entry->id === $this->entryB1->id ? 1 : 2;

            expect($freshEntry->values()->count())
                ->toBe($expectedValueCount);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vijf overige cataloguswaarden moeten volledig
        // ongewijzigd blijven, inclusief timestamps.
        foreach ($values as $value) {
            if ($value->id === $deletedValueId) {
                continue;
            }

            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de behouden catalogusvermelding B1.
        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1->id)
            ->toBe($this->entryB1->id);

        expect($freshEntryB1->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntryB1->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($freshEntryB1->catalog_number)
            ->toBe('B-100');

        expect($freshEntryB1->values()->count())
            ->toBe(1);

        expect($freshEntryB1->stamp->id)
            ->toBe($this->stamp->id);

        expect($freshEntryB1->catalogEdition->id)
            ->toBe($this->editionB1->id);

        // Controleer specifiek de behouden gestempelde waarde.
        $freshGestempeld = $freshEntryB1->values()->sole();

        expect($freshGestempeld->id)
            ->toBe($this->valueB1Gestempeld->id);

        expect($freshGestempeld->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($freshGestempeld->condition_id)
            ->toBe($this->gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('12.50');

        expect($freshGestempeld->currency)
            ->toBe('USD');

        expect($freshGestempeld->needs_review)
            ->toBeFalse();

        expect($freshGestempeld->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($freshGestempeld->condition->id)
            ->toBe($this->gestempeld->id);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Gestempeld->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 12.50,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // De lege invoer mag niet zijn opgeslagen
        // als een cataloguswaarde van nul.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'catalog_value' => 0.00,
        ]);

        // Geen enkele resterende cataloguswaarde
        // mag opnieuw voor controle zijn gemarkeerd.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'needs_review' => true,
        ]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(5);

        // Exact dezelfde catalogusvermeldingen
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Uitsluitend de vijf overige cataloguswaarden
        // mogen nog aan de postzegel gekoppeld zijn.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->reject(fn ($id) => $id === $deletedValueId)
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de historische identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBe($this->postfris->id);

        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        expect($log->record_id)
            ->toBe($deletedValueId);

        expect($log->action)
            ->toBe('deleted');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition->id)
            ->toBe($this->postfris->id);

        expect($log->stampSeries)
            ->toBeNull();

        // De oorspronkelijke attributen moeten volledig
        // in de wijzigingsgeschiedenis behouden blijven.
        expect($log->old_values)
            ->toBeArray()
            ->toHaveCount(count($originalDeletedValue))
            ->toHaveKeys([
                'id',
                'stamp_catalog_entry_id',
                'condition_id',
                'catalog_value',
                'currency',
                'needs_review',
                'created_at',
                'updated_at',
            ]);

        // Bij een verwijdering bestaan geen nieuwe attributen.
        expect($log->new_values)
            ->toBeNull();

        // Controleer de belangrijkste historische attributen.
        expect($log->old_values['id'])
            ->toBe($deletedValueId);

        expect($log->old_values['stamp_catalog_entry_id'])
            ->toBe($this->entryB1->id);

        expect($log->old_values['condition_id'])
            ->toBe($this->postfris->id);

        expect(is_numeric($log->old_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->old_values['catalog_value'])
            ->toBe(25.00);

        expect($log->old_values['currency'])
            ->toBe('USD');

        expect(
            in_array(
                $log->old_values['needs_review'],
                [false, 0],
                true
            )
        )->toBeTrue();

        expect($log->old_values['created_at'])
            ->toBe($originalDeletedValue['created_at']);

        expect($log->old_values['updated_at'])
            ->toBe($originalDeletedValue['updated_at']);

        // Normaliseer de historische attributen.
        $loggedOldValues = $log->old_values;

        $loggedOldValues['catalog_value'] =
            (float) $loggedOldValues['catalog_value'];

        $loggedOldValues['needs_review'] =
            (bool) $loggedOldValues['needs_review'];

        // Normaliseer de oorspronkelijke databaseattributen.
        $expectedOldValues = $originalDeletedValue;

        $expectedOldValues['catalog_value'] =
            (float) $expectedOldValues['catalog_value'];

        $expectedOldValues['needs_review'] =
            (bool) $expectedOldValues['needs_review'];

        // De volgorde van JSON-attributen is niet relevant.
        ksort($loggedOldValues);

        ksort($expectedOldValues);

        // Alle oorspronkelijke attributen moeten exact
        // overeenkomen met de historische attributen.
        expect($loggedOldValues)
            ->toBe($expectedOldValues);

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $deletedValueId,
            'action' => 'deleted',
            'new_values' => null,
        ]);

        // De historische record-ID moet behouden blijven,
        // terwijl het oorspronkelijke record niet meer bestaat.
        expect(StampCatalogValue::find($log->record_id))
            ->toBeNull();

        expect($log->fresh()->record_id)
            ->toBe($deletedValueId);

        // Er mogen geen wijzigingslogs voor vermeldingen bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // Er mogen geen created- of updated-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'updated',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend behoudt een bestaand catalogusnummer bij lege nummerinvoer en registreert uitsluitend de gewijzigde cataloguswaarde via catalog editions', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke attributen
    // van de te wijzigen cataloguswaarde.
    $changedValueId = $this->valueB1Postfris->id;

    $originalChangedValue = $originalValues[$changedValueId];

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(2);

    expect($this->editionB1->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Postfris->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Gestempeld->needs_review)
        ->toBeFalse();

    // Controleer de oorspronkelijke catalogusvermelding.
    $this->assertDatabaseHas('stamp_catalog_entries', [
        'id' => $this->entryB1->id,
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionB1->id,
        'catalog_number' => 'B-100',
    ]);

    // Controleer de oorspronkelijke cataloguswaarde.
    $this->assertDatabaseHas('stamp_catalog_values', [
        'id' => $changedValueId,
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->postfris->id,
        'catalog_value' => 25.00,
        'currency' => 'USD',
        'needs_review' => false,
    ]);

    // Controleer de beschikbare waarderingsregel.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'catalog_id' => $this->catalogB->id,
        'condition_id' => $this->postfris->id,
        'country_id' => null,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => '',
            'values' => [
                'postfris' => '34.75',
            ],
        ],
    ];

    // Er worden uitsluitend gegevens voor route D ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries');

    expect($payload['catalog_editions'])
        ->toHaveCount(1);

    expect(
        $payload['catalog_editions'][$this->editionB1->id]['catalog_number']
    )->toBe('');

    expect($payload['catalog_editions'][$this->editionB1->id]['values'])
        ->toHaveCount(1)
        ->not->toHaveKey('gestempeld');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Dien een leeg catalogusnummer in,
        // gecombineerd met een gewijzigde cataloguswaarde.
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

        // Controleer de bestaande postzegel.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Alle drie bestaande catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog voor de vermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Controleer specifiek de behouden catalogusvermelding B1.
        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1->id)
            ->toBe($this->entryB1->id);

        expect($freshEntryB1->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntryB1->catalog_edition_id)
            ->toBe($this->editionB1->id);

        // Het oorspronkelijke catalogusnummer
        // moet behouden blijven.
        expect($freshEntryB1->catalog_number)
            ->toBe('B-100');

        expect($freshEntryB1->values()->count())
            ->toBe(2);

        // Ook de oorspronkelijke timestamps
        // van de catalogusvermelding moeten behouden blijven.
        expect($freshEntryB1->getRawOriginal('created_at'))
            ->toBe(
                $originalEntries[$this->entryB1->id]['created_at']
            );

        expect($freshEntryB1->getRawOriginal('updated_at'))
            ->toBe(
                $originalEntries[$this->entryB1->id]['updated_at']
            );

        // Controleer de relaties van de catalogusvermelding.
        expect($freshEntryB1->stamp->id)
            ->toBe($this->stamp->id);

        expect($freshEntryB1->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($freshEntryB1->catalogEdition->catalog->id)
            ->toBe($this->catalogB->id);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-100',
        ]);

        // Het catalogusnummer mag niet zijn leeggemaakt.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => '',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => null,
        ]);

        // Er mag geen vervangende of dubbele vermelding bestaan.
        $storedEntry = $freshStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionB1->id)
            ->sole();

        expect($storedEntry->id)
            ->toBe($this->entryB1->id);

        // Controleer de gewijzigde cataloguswaarde.
        $freshValue = $this->valueB1Postfris->fresh();

        expect($freshValue)->not->toBeNull();

        expect($freshValue->id)
            ->toBe($changedValueId);

        expect($freshValue->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($freshValue->condition_id)
            ->toBe($this->postfris->id);

        expect($freshValue->catalog_value)
            ->toBe('34.75');

        expect((float) $freshValue->catalog_value)
            ->toBe(34.75);

        expect($freshValue->currency)
            ->toBe('USD');

        expect($freshValue->needs_review)
            ->toBeTrue();

        // Controleer de relaties van de gewijzigde cataloguswaarde.
        expect($freshValue->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($freshValue->catalogEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshValue->catalogEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($freshValue->condition->id)
            ->toBe($this->postfris->id);

        // De oorspronkelijke aanmaaktijd moet behouden blijven.
        expect($freshValue->getRawOriginal('created_at'))
            ->toBe($originalChangedValue['created_at']);

        // De wijzigingstijd moet daadwerkelijk zijn aangepast.
        expect($freshValue->getRawOriginal('updated_at'))
            ->not->toBe($originalChangedValue['updated_at']);

        // Uitsluitend catalog_value, needs_review
        // en updated_at mogen zijn gewijzigd.
        $expectedValueAttributes = $originalChangedValue;

        $expectedValueAttributes['catalog_value'] =
            $freshValue->getRawOriginal('catalog_value');

        $expectedValueAttributes['needs_review'] =
            $freshValue->getRawOriginal('needs_review');

        $expectedValueAttributes['updated_at'] =
            $freshValue->getRawOriginal('updated_at');

        expect($freshValue->getAttributes())
            ->toBe($expectedValueAttributes);

        // Controleer de opgeslagen cataloguswaarde.
        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $changedValueId,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 34.75,
            'currency' => 'USD',
            'needs_review' => true,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $changedValueId,
            'catalog_value' => 25.00,
        ]);

        // Er mag geen vervangende of dubbele cataloguswaarde bestaan.
        $storedValue = $freshEntryB1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        expect($storedValue->id)
            ->toBe($changedValueId);

        // Alle vijf overige cataloguswaarden moeten volledig
        // ongewijzigd blijven, inclusief timestamps.
        foreach ($values as $value) {
            if ($value->id === $changedValueId) {
                continue;
            }

            $freshUnchangedValue = $value->fresh();

            expect($freshUnchangedValue)->not->toBeNull();

            expect($freshUnchangedValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshUnchangedValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog voor deze waarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de behouden gestempelde waarde.
        $freshGestempeld = $freshEntryB1->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($freshGestempeld->id)
            ->toBe($this->valueB1Gestempeld->id);

        expect($freshGestempeld->catalog_value)
            ->toBe('12.50');

        expect($freshGestempeld->currency)
            ->toBe('USD');

        expect($freshGestempeld->needs_review)
            ->toBeFalse();

        // Uitsluitend de gewijzigde cataloguswaarde
        // mag voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->pluck('id')
                ->all()
        )->toBe([$changedValueId]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Exact dezelfde catalogusvermeldingen
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Exact dezelfde cataloguswaarden
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen automatische koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er moet precies één geschiedenisbatch bestaan.
        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe('Catalogusgegevens bij het wijzigen van een postzegel');

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        // Controleer de relaties van de geschiedenisbatch.
        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogB->name,
            'catalog_edition_name' => $this->editionB1->edition_name,
        ]);

        // Er moet precies één wijzigingslog bestaan.
        $this->assertDatabaseCount('catalog_change_logs', 1);

        expect($batch->logs()->count())
            ->toBe(1);

        $log = $batch->logs()->sole();

        expect($log)
            ->toBeInstanceOf(CatalogChangeLog::class);

        // Controleer de historische identificatiegegevens.
        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($this->stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->condition_id)
            ->toBe($this->postfris->id);

        expect($log->record_type)
            ->toBe('stamp_catalog_value');

        expect($log->record_id)
            ->toBe($changedValueId);

        expect($log->action)
            ->toBe('updated');

        // Controleer de relaties van het wijzigingslog.
        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($log->stamp->id)
            ->toBe($this->stamp->id);

        expect($log->condition->id)
            ->toBe($this->postfris->id);

        expect($log->stampSeries)
            ->toBeNull();

        // Beide arrays mogen uitsluitend de twee
        // daadwerkelijk gewijzigde attributen bevatten.
        expect($log->old_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        expect($log->new_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        // Controleer de oorspronkelijke cataloguswaarde.
        expect(is_numeric($log->old_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->old_values['catalog_value'])
            ->toBe(25.00);

        // Controleer de nieuwe cataloguswaarde.
        expect(is_numeric($log->new_values['catalog_value']))
            ->toBeTrue();

        expect((float) $log->new_values['catalog_value'])
            ->toBe(34.75);

        // Controleer de oorspronkelijke controlemarkering.
        expect(
            in_array(
                $log->old_values['needs_review'],
                [false, 0],
                true
            )
        )->toBeTrue();

        // Controleer de nieuwe controlemarkering.
        expect(
            in_array(
                $log->new_values['needs_review'],
                [true, 1],
                true
            )
        )->toBeTrue();

        // Normaliseer de oorspronkelijke loggegevens.
        $loggedOldValues = $log->old_values;

        $loggedOldValues['catalog_value'] =
            (float) $loggedOldValues['catalog_value'];

        $loggedOldValues['needs_review'] =
            (bool) $loggedOldValues['needs_review'];

        // Normaliseer de nieuwe loggegevens.
        $loggedNewValues = $log->new_values;

        $loggedNewValues['catalog_value'] =
            (float) $loggedNewValues['catalog_value'];

        $loggedNewValues['needs_review'] =
            (bool) $loggedNewValues['needs_review'];

        // De volgorde van JSON-attributen is niet relevant.
        ksort($loggedOldValues);

        ksort($loggedNewValues);

        // Controleer de volledige oorspronkelijke loggegevens.
        expect($loggedOldValues)
            ->toBe([
                'catalog_value' => 25.00,
                'needs_review' => false,
            ]);

        // Controleer de volledige nieuwe loggegevens.
        expect($loggedNewValues)
            ->toBe([
                'catalog_value' => 34.75,
                'needs_review' => true,
            ]);

        // Vergelijk de oorspronkelijke historische waarden
        // met de oorspronkelijke databasewaarden.
        expect($loggedOldValues['catalog_value'])
            ->toBe(
                (float) $originalChangedValue['catalog_value']
            );

        expect($loggedOldValues['needs_review'])
            ->toBe(
                (bool) $originalChangedValue['needs_review']
            );

        // Vergelijk de nieuwe historische waarden
        // met de daadwerkelijk opgeslagen databasewaarden.
        expect($loggedNewValues['catalog_value'])
            ->toBe((float) $freshValue->catalog_value);

        expect($loggedNewValues['needs_review'])
            ->toBe($freshValue->needs_review);

        // Ongewijzigde attributen en timestamps
        // mogen niet in het wijzigingslog voorkomen.
        foreach ([
            'id',
            'stamp_catalog_entry_id',
            'condition_id',
            'catalog_number',
            'currency',
            'notes',
            'created_at',
            'updated_at',
        ] as $attribute) {
            expect($log->old_values)
                ->not->toHaveKey($attribute);

            expect($log->new_values)
                ->not->toHaveKey($attribute);
        }

        // Controleer het opgeslagen wijzigingslog.
        $this->assertDatabaseHas('catalog_change_logs', [
            'id' => $log->id,
            'catalog_change_batch_id' => $batch->id,
            'catalog_edition_id' => $this->editionB1->id,
            'stamp_id' => $this->stamp->id,
            'stamp_series_id' => null,
            'condition_id' => $this->postfris->id,
            'record_type' => 'stamp_catalog_value',
            'record_id' => $changedValueId,
            'action' => 'updated',
        ]);

        // De historische record-ID moet verwijzen
        // naar de oorspronkelijke cataloguswaarde.
        expect(StampCatalogValue::find($log->record_id)?->id)
            ->toBe($changedValueId);

        // Het lege catalogusnummer mag geen
        // wijzigingslog voor een vermelding veroorzaken.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
        ]);

        // Er mogen geen created- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 1);
    } finally {
        $this->travelBack();
    }
});

test('de backend slaat volledig lege invoer voor een niet gekoppelde cataloguseditie over zonder wijzigingsgeschiedenis via catalog editions', function () {
    // Maak cataloguseditie C1 beschikbaar
    // voor het huidige land van de postzegel.
    $this->catalogC->countries()->attach(
        $this->country->id
    );

    // Controleer of catalogus C daadwerkelijk
    // aan het huidige land is gekoppeld.
    expect(
        $this->catalogC->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    // Controleer of ook de oorspronkelijke landkoppeling
    // van catalogus C behouden is gebleven.
    expect(
        $this->catalogC->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke landkoppelingen.
    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Controleer de beschikbare cataloguseditie C1.
    expect($this->editionC1->catalog_id)
        ->toBe($this->catalogC->id);

    expect($this->editionC1->currency)
        ->toBe('GBP');

    // Controleer of beide waarderingscategorieën
    // voor catalogus C beschikbaar zijn.
    $this->assertDatabaseHas('catalog_condition_rules', [
        'catalog_id' => $this->catalogC->id,
        'condition_id' => $this->postfris->id,
        'country_id' => null,
    ]);

    $this->assertDatabaseHas('catalog_condition_rules', [
        'catalog_id' => $this->catalogC->id,
        'condition_id' => $this->gestempeld->id,
        'country_id' => null,
    ]);

    // De postzegel mag nog niet aan C1 gekoppeld zijn.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    expect(
        $this->stamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionC1->id)
            ->exists()
    )->toBeFalse();

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_editions'] = [
        $this->editionC1->id => [
            'catalog_number' => '',
            'values' => [
                'postfris' => '',
                'gestempeld' => null,
            ],
        ],
    ];

    // Er worden uitsluitend gegevens voor route D ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries');

    expect($payload['catalog_editions'])
        ->toHaveCount(1);

    expect(
        $payload['catalog_editions'][$this->editionC1->id]['catalog_number']
    )->toBe('');

    expect(
        $payload['catalog_editions'][$this->editionC1->id]['values']
    )->toBe([
        'postfris' => '',
        'gestempeld' => null,
    ]);

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Dien volledig lege catalogusgegevens in
        // voor de nog niet gekoppelde cataloguseditie C1.
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

        // Controleer of de algemene postzegelgegevens
        // daadwerkelijk zijn opgeslagen.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->name)
            ->not->toBe($originalStamp['name']);

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Er mag geen nieuwe catalogusvermelding
        // voor cataloguseditie C1 zijn aangemaakt.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        expect(
            $freshStamp->catalogEntries()
                ->where('catalog_edition_id', $this->editionC1->id)
                ->exists()
        )->toBeFalse();

        // Er mag geen catalogusvermelding
        // met een leeg catalogusnummer bestaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_number' => '',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_number' => null,
        ]);

        // Er mogen geen nieuwe catalogusvermeldingen
        // of cataloguswaarden zijn aangemaakt.
        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        // Alle drie bestaande catalogusvermeldingen
        // moeten volledig ongewijzigd blijven.
        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->stamp->id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalogEdition->id)
                ->toBe($entry->catalog_edition_id);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen wijzigingslog
            // voor deze catalogusvermelding bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle zes bestaande cataloguswaarden moeten
        // volledig ongewijzigd blijven, inclusief timestamps.
        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->catalogEntry->id)
                ->toBe($value->stamp_catalog_entry_id);

            expect($freshValue->condition->id)
                ->toBe($value->condition_id);

            expect($freshValue->needs_review)
                ->toBeFalse();

            // Er mag geen wijzigingslog
            // voor deze cataloguswaarde bestaan.
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer specifiek de oorspronkelijke catalogusnummers.
        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // Controleer de oorspronkelijke cataloguswaarden.
        expect($this->valueA1Postfris->fresh()->catalog_value)
            ->toBe('15.00');

        expect($this->valueA1Gestempeld->fresh()->catalog_value)
            ->toBe('7.50');

        expect($this->valueA2Postfris->fresh()->catalog_value)
            ->toBe('20.00');

        expect($this->valueA2Gestempeld->fresh()->catalog_value)
            ->toBe('10.00');

        expect($this->valueB1Postfris->fresh()->catalog_value)
            ->toBe('25.00');

        expect($this->valueB1Gestempeld->fresh()->catalog_value)
            ->toBe('12.50');

        // Er mogen geen lege cataloguswaarden
        // als numerieke nul zijn opgeslagen.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'catalog_value' => 0.00,
        ]);

        // Er mogen geen nieuwe GBP-waarden zijn aangemaakt.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'currency' => 'GBP',
        ]);

        // Geen enkele bestaande cataloguswaarde
        // mag opnieuw voor controle zijn gemarkeerd.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'needs_review' => true,
        ]);

        // Controleer de catalogusrelaties van de postzegel.
        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Exact dezelfde catalogusvermeldingen
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Exact dezelfde cataloguswaarden
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Alle oorspronkelijke catalogi
        // moeten volledig ongewijzigd blijven.
        $freshCatalogs = Catalog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($catalog) => $catalog->getAttributes())
            ->all();

        expect($freshCatalogs)
            ->toBe($originalCatalogs);

        // Alle oorspronkelijke catalogusedities
        // moeten volledig ongewijzigd blijven.
        $freshEditions = CatalogEdition::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($edition) => $edition->getAttributes())
            ->all();

        expect($freshEditions)
            ->toBe($originalEditions);

        // Alle oorspronkelijke waarderingsregels
        // moeten volledig ongewijzigd blijven.
        $freshConditionRules = CatalogConditionRule::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($rule) => $rule->getAttributes())
            ->all();

        expect($freshConditionRules)
            ->toBe($originalConditionRules);

        // Alle oorspronkelijke landkoppelingen
        // moeten volledig behouden blijven.
        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // Cataloguseditie C1 moet beschikbaar blijven,
        // ondanks het overslaan van de lege invoer.
        expect(
            $this->catalogC->countries()
                ->where('countries.id', $this->country->id)
                ->exists()
        )->toBeTrue();

        expect($this->editionC1->fresh())
            ->not->toBeNull();

        // Er mag geen geschiedenisbatch zijn opgeslagen.
        $this->assertDatabaseCount('catalog_change_batches', 0);

        expect(CatalogChangeBatch::query()->exists())
            ->toBeFalse();

        // Er mag geen wijzigingslog zijn opgeslagen.
        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(CatalogChangeLog::query()->exists())
            ->toBeFalse();

        // Er mogen voor geen enkele cataloguseditie
        // geschiedenisbatches of wijzigingslogs bestaan.
        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // Er mogen voor geen van beide gebruikers
        // geschiedenisbatches bestaan.
        foreach ([
            $this->user,
            $this->otherUser,
        ] as $user) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'user_id' => $user->id,
            ]);
        }

        // Er mogen geen created-, updated-
        // of deleted-logs bestaan.
        foreach ([
            'created',
            'updated',
            'deleted',
        ] as $action) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'action' => $action,
            ]);
        }

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert afzonderlijke geschiedenisbatches voor meerdere gewijzigde catalogusedities binnen een aanvraag via catalog editions', function () {
    // Maak cataloguseditie C1 beschikbaar
    // voor het huidige land van de postzegel.
    $this->catalogC->countries()->attach(
        $this->country->id
    );

    expect(
        $this->catalogC->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de historische record-ID's.
    $changedEntryId = $this->entryA1->id;

    $changedValueId = $this->valueA1Postfris->id;

    $deletedValueId = $this->valueB1Postfris->id;

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke landkoppelingen.
    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryA1->catalog_number)
        ->toBe('A-100');

    expect($this->entryA2->catalog_number)
        ->toBe('A-200');

    expect($this->entryB1->catalog_number)
        ->toBe('B-100');

    expect($this->valueA1Postfris->catalog_value)
        ->toBe('15.00');

    expect($this->valueB1Postfris->catalog_value)
        ->toBe('25.00');

    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    // C1 mag nog niet aan de postzegel gekoppeld zijn.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    $payload['catalog_editions'] = [
        // A1: bestaand nummer en bestaande waarde wijzigen.
        $this->editionA1->id => [
            'catalog_number' => 'A-150',
            'values' => [
                'postfris' => '18.75',
            ],
        ],

        // A2: volledig ongewijzigde gegevens indienen.
        $this->editionA2->id => [
            'catalog_number' => 'A-200',
            'values' => [
                'postfris' => '20.00',
                'gestempeld' => '10.00',
            ],
        ],

        // B1: bestaande postfrisse waarde verwijderen.
        $this->editionB1->id => [
            'catalog_number' => 'B-100',
            'values' => [
                'postfris' => '',
            ],
        ],

        // C1: nieuwe vermelding met twee waarden aanmaken.
        $this->editionC1->id => [
            'catalog_number' => 'C-400',
            'values' => [
                'postfris' => '42.50',
                'gestempeld' => '21.25',
            ],
        ],
    ];

    // Er worden uitsluitend gegevens voor route D ingediend.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries');

    expect($payload['catalog_editions'])
        ->toHaveCount(4);

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Verwerk alle vier catalogusedities
        // binnen dezelfde HTTP-aanvraag.
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

        // Controleer de algemene postzegelgegevens.
        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Cataloguseditie A1: bestaande gegevens wijzigen.
        // -------------------------------------------------

        $freshEntryA1 = $this->entryA1->fresh();

        expect($freshEntryA1)->not->toBeNull();

        expect($freshEntryA1->id)
            ->toBe($changedEntryId);

        expect($freshEntryA1->catalog_number)
            ->toBe('A-150');

        expect($freshEntryA1->getRawOriginal('updated_at'))
            ->not->toBe(
                $originalEntries[$changedEntryId]['updated_at']
            );

        // Alleen catalog_number en updated_at mogen wijzigen.
        $expectedEntryA1 = $originalEntries[$changedEntryId];

        $expectedEntryA1['catalog_number'] = 'A-150';

        $expectedEntryA1['updated_at'] =
            $freshEntryA1->getRawOriginal('updated_at');

        expect($freshEntryA1->getAttributes())
            ->toBe($expectedEntryA1);

        expect($freshEntryA1->values()->count())
            ->toBe(2);

        $freshA1Postfris = $this->valueA1Postfris->fresh();

        expect($freshA1Postfris)->not->toBeNull();

        expect($freshA1Postfris->id)
            ->toBe($changedValueId);

        expect($freshA1Postfris->catalog_value)
            ->toBe('18.75');

        expect($freshA1Postfris->currency)
            ->toBe('EUR');

        expect($freshA1Postfris->needs_review)
            ->toBeTrue();

        expect($freshA1Postfris->getRawOriginal('updated_at'))
            ->not->toBe(
                $originalValues[$changedValueId]['updated_at']
            );

        // Alleen de waarde, controlemarkering en timestamp
        // mogen bij de postfrisse waarde wijzigen.
        $expectedA1Postfris = $originalValues[$changedValueId];

        foreach ([
            'catalog_value',
            'needs_review',
            'updated_at',
        ] as $attribute) {
            $expectedA1Postfris[$attribute] =
                $freshA1Postfris->getRawOriginal($attribute);
        }

        expect($freshA1Postfris->getAttributes())
            ->toBe($expectedA1Postfris);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $changedEntryId,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionA1->id,
            'catalog_number' => 'A-150',
        ]);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $changedValueId,
            'stamp_catalog_entry_id' => $this->entryA1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 18.75,
            'currency' => 'EUR',
            'needs_review' => true,
        ]);

        // De niet-ingediende gestempelde waarde
        // moet volledig ongewijzigd blijven.
        expect(
            $this->valueA1Gestempeld->fresh()->getAttributes()
        )->toBe(
            $originalValues[$this->valueA1Gestempeld->id]
        );

        // -------------------------------------------------
        // Cataloguseditie A2: ongewijzigde gegevens.
        // -------------------------------------------------

        $freshEntryA2 = $this->entryA2->fresh();

        expect($freshEntryA2)->not->toBeNull();

        expect($freshEntryA2->catalog_number)
            ->toBe('A-200');

        expect($freshEntryA2->values()->count())
            ->toBe(2);

        // Alleen een eventuele wijziging van updated_at
        // is toegestaan bij de ingediende vermelding.
        $expectedEntryA2 =
            $originalEntries[$this->entryA2->id];

        $expectedEntryA2['updated_at'] =
            $freshEntryA2->getRawOriginal('updated_at');

        expect($freshEntryA2->getAttributes())
            ->toBe($expectedEntryA2);

        // Beide ingediende waarden moeten inhoudelijk
        // volledig ongewijzigd blijven.
        foreach ([
            $this->valueA2Postfris,
            $this->valueA2Gestempeld,
        ] as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            $expectedAttributes =
                $originalValues[$value->id];

            $expectedAttributes['updated_at'] =
                $freshValue->getRawOriginal('updated_at');

            expect($freshValue->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshValue->needs_review)
                ->toBeFalse();
        }

        expect($this->valueA2Postfris->fresh()->catalog_value)
            ->toBe('20.00');

        expect($this->valueA2Gestempeld->fresh()->catalog_value)
            ->toBe('10.00');

        // -------------------------------------------------
        // Cataloguseditie B1: bestaande waarde verwijderen.
        // -------------------------------------------------

        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1)->not->toBeNull();

        expect($freshEntryB1->catalog_number)
            ->toBe('B-100');

        expect($freshEntryB1->values()->count())
            ->toBe(1);

        // Alleen een eventuele wijziging van updated_at
        // is toegestaan bij de ingediende vermelding.
        $expectedEntryB1 =
            $originalEntries[$this->entryB1->id];

        $expectedEntryB1['updated_at'] =
            $freshEntryB1->getRawOriginal('updated_at');

        expect($freshEntryB1->getAttributes())
            ->toBe($expectedEntryB1);

        // De oorspronkelijke postfrisse waarde
        // mag niet meer bestaan.
        expect(StampCatalogValue::find($deletedValueId))
            ->toBeNull();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $deletedValueId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
        ]);

        // De gestempelde waarde moet volledig behouden blijven.
        $freshB1Gestempeld = $freshEntryB1->values()->sole();

        expect($freshB1Gestempeld->id)
            ->toBe($this->valueB1Gestempeld->id);

        expect($freshB1Gestempeld->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Gestempeld->id]
            );

        expect($freshB1Gestempeld->catalog_value)
            ->toBe('12.50');

        expect($freshB1Gestempeld->currency)
            ->toBe('USD');

        expect($freshB1Gestempeld->needs_review)
            ->toBeFalse();

        // -------------------------------------------------
        // Cataloguseditie C1: nieuwe gegevens aanmaken.
        // -------------------------------------------------

        $newEntryC1 = $freshStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionC1->id)
            ->sole();

        expect($newEntryC1)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect(array_keys($originalEntries))
            ->not->toContain($newEntryC1->id);

        expect($newEntryC1->stamp_id)
            ->toBe($this->stamp->id);

        expect($newEntryC1->catalog_edition_id)
            ->toBe($this->editionC1->id);

        expect($newEntryC1->catalog_number)
            ->toBe('C-400');

        expect($newEntryC1->stamp->id)
            ->toBe($this->stamp->id);

        expect($newEntryC1->catalogEdition->id)
            ->toBe($this->editionC1->id);

        expect($newEntryC1->catalogEdition->catalog->id)
            ->toBe($this->catalogC->id);

        expect($newEntryC1->values()->count())
            ->toBe(2);

        $newC1Postfris = $newEntryC1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $newC1Gestempeld = $newEntryC1->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        // Verzamel de ID's van de vijf behouden waarden.
        $remainingValueIds = collect($values)
            ->pluck('id')
            ->reject(fn ($id) => $id === $deletedValueId)
            ->values()
            ->all();

        // Nieuwe waarden mogen geen bestaande waarden vervangen.
        expect($remainingValueIds)
            ->not->toContain($newC1Postfris->id)
            ->not->toContain($newC1Gestempeld->id);

        expect($newC1Postfris->id)
            ->not->toBe($newC1Gestempeld->id);

        foreach ([
            [$newC1Postfris, $this->postfris, '42.50'],
            [$newC1Gestempeld, $this->gestempeld, '21.25'],
        ] as [$value, $condition, $amount]) {
            expect($value)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($value->stamp_catalog_entry_id)
                ->toBe($newEntryC1->id);

            expect($value->condition_id)
                ->toBe($condition->id);

            expect($value->catalog_value)
                ->toBe($amount);

            expect($value->currency)
                ->toBe('GBP');

            expect($value->needs_review)
                ->toBeFalse();

            expect($value->catalogEntry->id)
                ->toBe($newEntryC1->id);

            expect($value->condition->id)
                ->toBe($condition->id);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $value->id,
                'stamp_catalog_entry_id' => $newEntryC1->id,
                'condition_id' => $condition->id,
                'catalog_value' => (float) $amount,
                'currency' => 'GBP',
                'needs_review' => false,
            ]);
        }

        // Controleer de nieuwe catalogusvermelding.
        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $newEntryC1->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
            'catalog_number' => 'C-400',
        ]);

        // -------------------------------------------------
        // Controleer alle definitieve catalogusrelaties.
        // -------------------------------------------------

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 7);

        expect($freshStamp->catalogEntries()->count())
            ->toBe(4);

        expect($freshStamp->catalogValues()->count())
            ->toBe(7);

        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect([
                $this->entryA1->id,
                $this->entryA2->id,
                $this->entryB1->id,
                $newEntryC1->id,
            ])->sort()->values()->all()
        );

        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect([
                ...$remainingValueIds,
                $newC1Postfris->id,
                $newC1Gestempeld->id,
            ])->sort()->values()->all()
        );

        // Uitsluitend de gewijzigde postfrisse waarde van A1
        // mag opnieuw voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->pluck('id')
                ->all()
        )->toBe([$changedValueId]);

        // -------------------------------------------------
        // Controleer de geschiedenisbatchverdeling.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 6);

        $batches = CatalogChangeBatch::query()
            ->get()
            ->keyBy('catalog_edition_id');

        expect($batches)->toHaveCount(3);

        expect($batches->keys()->sort()->values()->all())
            ->toBe(
                collect([
                    $this->editionA1->id,
                    $this->editionB1->id,
                    $this->editionC1->id,
                ])->sort()->values()->all()
            );

        $batchA1 = $batches->get($this->editionA1->id);

        $batchB1 = $batches->get($this->editionB1->id);

        $batchC1 = $batches->get($this->editionC1->id);

        expect($batchA1)->not->toBeNull();

        expect($batchB1)->not->toBeNull();

        expect($batchC1)->not->toBeNull();

        // Iedere gewijzigde cataloguseditie
        // moet een eigen geschiedenisbatch hebben.
        expect(
            collect([
                $batchA1->id,
                $batchB1->id,
                $batchC1->id,
            ])->unique()->count()
        )->toBe(3);

        // Controleer alle geschiedenisbatchgegevens.
        foreach ([
            [$batchA1, $this->editionA1, $this->catalogA, 2],
            [$batchB1, $this->editionB1, $this->catalogB, 1],
            [$batchC1, $this->editionC1, $this->catalogC, 3],
        ] as [$batch, $edition, $catalog, $logCount]) {
            expect($batch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($batch->catalog_edition_id)
                ->toBe($edition->id);

            expect($batch->user_id)
                ->toBe($this->user->id);

            expect($batch->user_name)
                ->toBe($this->user->name);

            expect($batch->operation)
                ->toBe('stamp_update');

            expect($batch->description)
                ->toBe(
                    'Catalogusgegevens bij het wijzigen van een postzegel'
                );

            expect($batch->catalog_name)
                ->toBe($catalog->name);

            expect($batch->catalog_edition_name)
                ->toBe($edition->edition_name);

            expect($batch->catalogEdition->id)
                ->toBe($edition->id);

            expect($batch->user->id)
                ->toBe($this->user->id);

            expect($batch->logs()->count())
                ->toBe($logCount);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $batch->id,
                'catalog_edition_id' => $edition->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $catalog->name,
                'catalog_edition_name' => $edition->edition_name,
            ]);

            // Alle logs moeten bij de juiste batch,
            // cataloguseditie en postzegel horen.
            foreach ($batch->logs()->get() as $log) {
                expect($log)
                    ->toBeInstanceOf(CatalogChangeLog::class);

                expect($log->catalog_change_batch_id)
                    ->toBe($batch->id);

                expect($log->catalog_edition_id)
                    ->toBe($edition->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->batch->id)
                    ->toBe($batch->id);

                expect($log->catalogEdition->id)
                    ->toBe($edition->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();
            }
        }

        // -------------------------------------------------
        // Controleer de twee wijzigingslogs van A1.
        // -------------------------------------------------

        $logsA1 = $batchA1->logs()
            ->orderBy('id')
            ->get();

        expect($logsA1)->toHaveCount(2);

        $entryLogA1 = $logsA1[0];

        $valueLogA1 = $logsA1[1];

        // Eerste log: gewijzigd catalogusnummer.
        expect($entryLogA1->record_type)
            ->toBe('stamp_catalog_entry');

        expect($entryLogA1->record_id)
            ->toBe($changedEntryId);

        expect($entryLogA1->action)
            ->toBe('updated');

        expect($entryLogA1->condition_id)
            ->toBeNull();

        expect($entryLogA1->condition)
            ->toBeNull();

        expect($entryLogA1->old_values)
            ->toBe([
                'catalog_number' => 'A-100',
            ]);

        expect($entryLogA1->new_values)
            ->toBe([
                'catalog_number' => 'A-150',
            ]);

        // Tweede log: gewijzigde postfrisse waarde.
        expect($valueLogA1->record_type)
            ->toBe('stamp_catalog_value');

        expect($valueLogA1->record_id)
            ->toBe($changedValueId);

        expect($valueLogA1->action)
            ->toBe('updated');

        expect($valueLogA1->condition_id)
            ->toBe($this->postfris->id);

        expect($valueLogA1->condition->id)
            ->toBe($this->postfris->id);

        expect($valueLogA1->old_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        expect($valueLogA1->new_values)
            ->toBeArray()
            ->toHaveCount(2)
            ->toHaveKeys([
                'catalog_value',
                'needs_review',
            ]);

        expect(is_numeric($valueLogA1->old_values['catalog_value']))
            ->toBeTrue();

        expect(is_numeric($valueLogA1->new_values['catalog_value']))
            ->toBeTrue();

        expect(
            in_array(
                $valueLogA1->old_values['needs_review'],
                [false, 0],
                true
            )
        )->toBeTrue();

        expect(
            in_array(
                $valueLogA1->new_values['needs_review'],
                [true, 1],
                true
            )
        )->toBeTrue();

        $loggedOldA1 = $valueLogA1->old_values;

        $loggedOldA1['catalog_value'] =
            (float) $loggedOldA1['catalog_value'];

        $loggedOldA1['needs_review'] =
            (bool) $loggedOldA1['needs_review'];

        $loggedNewA1 = $valueLogA1->new_values;

        $loggedNewA1['catalog_value'] =
            (float) $loggedNewA1['catalog_value'];

        $loggedNewA1['needs_review'] =
            (bool) $loggedNewA1['needs_review'];

        ksort($loggedOldA1);

        ksort($loggedNewA1);

        expect($loggedOldA1)
            ->toBe([
                'catalog_value' => 15.00,
                'needs_review' => false,
            ]);

        expect($loggedNewA1)
            ->toBe([
                'catalog_value' => 18.75,
                'needs_review' => true,
            ]);

        expect($loggedOldA1['catalog_value'])
            ->toBe(
                (float) $originalValues[$changedValueId]['catalog_value']
            );

        expect($loggedNewA1['catalog_value'])
            ->toBe((float) $freshA1Postfris->catalog_value);

        // -------------------------------------------------
        // Controleer dat A2 geen geschiedenis bevat.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->editionA2->id,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_edition_id' => $this->editionA2->id,
        ]);

        // -------------------------------------------------
        // Controleer het verwijderingslog van B1.
        // -------------------------------------------------

        $deletedLogB1 = $batchB1->logs()->sole();

        expect($deletedLogB1->record_type)
            ->toBe('stamp_catalog_value');

        expect($deletedLogB1->record_id)
            ->toBe($deletedValueId);

        expect($deletedLogB1->action)
            ->toBe('deleted');

        expect($deletedLogB1->condition_id)
            ->toBe($this->postfris->id);

        expect($deletedLogB1->condition->id)
            ->toBe($this->postfris->id);

        expect($deletedLogB1->new_values)
            ->toBeNull();

        expect($deletedLogB1->old_values)
            ->toBeArray()
            ->toHaveCount(
                count($originalValues[$deletedValueId])
            )
            ->toHaveKeys([
                'id',
                'stamp_catalog_entry_id',
                'condition_id',
                'catalog_value',
                'currency',
                'needs_review',
                'created_at',
                'updated_at',
            ]);

        expect(is_numeric($deletedLogB1->old_values['catalog_value']))
            ->toBeTrue();

        expect(
            in_array(
                $deletedLogB1->old_values['needs_review'],
                [false, 0],
                true
            )
        )->toBeTrue();

        // Vergelijk alle historische attributen
        // met de oorspronkelijke databaseattributen.
        $loggedDeletedValues = $deletedLogB1->old_values;

        $expectedDeletedValues =
            $originalValues[$deletedValueId];

        $loggedDeletedValues['catalog_value'] =
            (float) $loggedDeletedValues['catalog_value'];

        $expectedDeletedValues['catalog_value'] =
            (float) $expectedDeletedValues['catalog_value'];

        $loggedDeletedValues['needs_review'] =
            (bool) $loggedDeletedValues['needs_review'];

        $expectedDeletedValues['needs_review'] =
            (bool) $expectedDeletedValues['needs_review'];

        ksort($loggedDeletedValues);

        ksort($expectedDeletedValues);

        expect($loggedDeletedValues)
            ->toBe($expectedDeletedValues);

        expect($loggedDeletedValues['catalog_value'])
            ->toBe(25.00);

        expect($loggedDeletedValues['currency'])
            ->toBe('USD');

        expect($deletedLogB1->fresh()->record_id)
            ->toBe($deletedValueId);

        // -------------------------------------------------
        // Controleer de drie aanmaaklogs van C1.
        // -------------------------------------------------

        $logsC1 = $batchC1->logs()
            ->orderBy('id')
            ->get();

        expect($logsC1)->toHaveCount(3);

        $entryLogC1 = $logsC1[0];

        $postfrisLogC1 = $logsC1[1];

        $gestempeldLogC1 = $logsC1[2];

        // Eerste log: nieuwe catalogusvermelding.
        expect($entryLogC1->record_type)
            ->toBe('stamp_catalog_entry');

        expect($entryLogC1->record_id)
            ->toBe($newEntryC1->id);

        expect($entryLogC1->action)
            ->toBe('created');

        expect($entryLogC1->condition_id)
            ->toBeNull();

        expect($entryLogC1->condition)
            ->toBeNull();

        expect($entryLogC1->old_values)
            ->toBeNull();

        expect($entryLogC1->new_values)
            ->toBeArray()
            ->toHaveKeys([
                'id',
                'stamp_id',
                'catalog_edition_id',
                'catalog_number',
                'created_at',
                'updated_at',
            ]);

        expect($entryLogC1->new_values['id'])
            ->toBe($newEntryC1->id);

        expect($entryLogC1->new_values['stamp_id'])
            ->toBe($this->stamp->id);

        expect($entryLogC1->new_values['catalog_edition_id'])
            ->toBe($this->editionC1->id);

        expect($entryLogC1->new_values['catalog_number'])
            ->toBe('C-400');

        // Alle geregistreerde attributen moeten overeenkomen
        // met de daadwerkelijk opgeslagen catalogusvermelding.
        foreach ($entryLogC1->new_values as $attribute => $value) {
            expect($newEntryC1->getAttributes())
                ->toHaveKey($attribute);

            expect($value)
                ->toBe($newEntryC1->getRawOriginal($attribute));
        }

        // Tweede en derde log: nieuwe cataloguswaarden.
        foreach ([
            [$postfrisLogC1, $newC1Postfris, $this->postfris, 42.50],
            [$gestempeldLogC1, $newC1Gestempeld, $this->gestempeld, 21.25],
        ] as [$log, $value, $condition, $amount]) {
            expect($log->record_type)
                ->toBe('stamp_catalog_value');

            expect($log->record_id)
                ->toBe($value->id);

            expect($log->action)
                ->toBe('created');

            expect($log->condition_id)
                ->toBe($condition->id);

            expect($log->condition->id)
                ->toBe($condition->id);

            expect($log->old_values)
                ->toBeNull();

            expect($log->new_values)
                ->toBeArray()
                ->toHaveKeys([
                    'id',
                    'stamp_catalog_entry_id',
                    'condition_id',
                    'catalog_value',
                    'currency',
                    'created_at',
                    'updated_at',
                ]);

            expect($log->new_values['id'])
                ->toBe($value->id);

            expect($log->new_values['stamp_catalog_entry_id'])
                ->toBe($newEntryC1->id);

            expect($log->new_values['condition_id'])
                ->toBe($condition->id);

            expect(is_numeric($log->new_values['catalog_value']))
                ->toBeTrue();

            expect((float) $log->new_values['catalog_value'])
                ->toBe($amount);

            expect($log->new_values['currency'])
                ->toBe('GBP');

            // Vergelijk alle geregistreerde attributen
            // met de daadwerkelijk opgeslagen waarde.
            foreach ($log->new_values as $attribute => $loggedValue) {
                expect($value->getAttributes())
                    ->toHaveKey($attribute);

                $storedValue = $value->getRawOriginal($attribute);

                if ($attribute === 'catalog_value') {
                    expect((float) $loggedValue)
                        ->toBe((float) $storedValue);

                    continue;
                }

                if ($attribute === 'needs_review') {
                    expect((bool) $loggedValue)
                        ->toBe((bool) $storedValue);

                    continue;
                }

                expect($loggedValue)
                    ->toBe($storedValue);
            }
        }

        // -------------------------------------------------
        // Controleer de totale verdeling van de logs.
        // -------------------------------------------------

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(2);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'deleted')
                ->count()
        )->toBe(1);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'created')
                ->count()
        )->toBe(3);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_entry')
                ->count()
        )->toBe(2);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(4);

        // Voor de behouden catalogusvermeldingen A2 en B1
        // mogen geen wijzigingslogs bestaan.
        foreach ([
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Voor de vier ongewijzigde cataloguswaarden
        // mogen geen wijzigingslogs bestaan.
        foreach ([
            $this->valueA1Gestempeld,
            $this->valueA2Postfris,
            $this->valueA2Gestempeld,
            $this->valueB1Gestempeld,
        ] as $value) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Er mag geen geschiedenisbatch
        // voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Er mogen geen lege geschiedenisbatches achterblijven.
        expect(
            CatalogChangeBatch::query()
                ->whereDoesntHave('logs')
                ->exists()
        )->toBeFalse();

        // -------------------------------------------------
        // Controleer alle overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        // Alle oorspronkelijke landkoppelingen
        // moeten behouden blijven.
        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 7);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 6);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert alle wijzigingen via routes B C en D voor dezelfde cataloguseditie binnen een geschiedenisbatch', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke record-ID's.
    $changedEntryId = $this->entryA1->id;

    $changedPostfrisId = $this->valueA1Postfris->id;

    $changedGestempeldId = $this->valueA1Gestempeld->id;

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke landkoppelingen.
    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryA1->catalog_number)
        ->toBe('A-100');

    expect($this->valueA1Postfris->catalog_value)
        ->toBe('15.00');

    expect($this->valueA1Gestempeld->catalog_value)
        ->toBe('7.50');

    expect($this->editionA1->currency)
        ->toBe('EUR');

    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    // -------------------------------------------------
    // Route B: oorspronkelijke invoerstructuur.
    // -------------------------------------------------

    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-101';

    $payload['catalog_values'] = [
        'postfris' => '16.00',
        'gestempeld' => '8.00',
    ];

    // -------------------------------------------------
    // Route C: bestaande catalogusvermeldingen.
    // -------------------------------------------------

    $payload['catalog_entries'] = [
        $this->entryA1->id => [
            'catalog_number' => 'A-102',
            'values' => [
                'postfris' => '17.00',
                'gestempeld' => '9.00',
            ],
        ],
    ];

    // -------------------------------------------------
    // Route D: beschikbare catalogusedities.
    // -------------------------------------------------

    $payload['catalog_editions'] = [
        $this->editionA1->id => [
            'catalog_number' => 'A-103',
            'values' => [
                'postfris' => '18.00',
                'gestempeld' => '10.00',
            ],
        ],
    ];

    // Controleer of alle drie routes zijn opgenomen.
    expect($payload)
        ->toHaveKeys([
            'catalog_edition_id',
            'catalog_number',
            'catalog_values',
            'catalog_entries',
            'catalog_editions',
        ]);

    expect($payload['catalog_edition_id'])
        ->toBe($this->editionA1->id);

    expect($payload['catalog_entries'])
        ->toHaveCount(1)
        ->toHaveKey($this->entryA1->id);

    expect($payload['catalog_editions'])
        ->toHaveCount(1)
        ->toHaveKey($this->editionA1->id);

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan bestaande records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Verwerk alle drie routes binnen dezelfde aanvraag.
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

        // -------------------------------------------------
        // Controleer de algemene postzegelgegevens.
        // -------------------------------------------------

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer de definitieve catalogusvermelding A1.
        // -------------------------------------------------

        $freshEntryA1 = $this->entryA1->fresh();

        expect($freshEntryA1)->not->toBeNull();

        expect($freshEntryA1->id)
            ->toBe($changedEntryId);

        expect($freshEntryA1->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntryA1->catalog_edition_id)
            ->toBe($this->editionA1->id);

        // Route D moet de definitieve waarde bepalen.
        expect($freshEntryA1->catalog_number)
            ->toBe('A-103');

        expect($freshEntryA1->getRawOriginal('created_at'))
            ->toBe(
                $originalEntries[$changedEntryId]['created_at']
            );

        expect($freshEntryA1->getRawOriginal('updated_at'))
            ->not->toBe(
                $originalEntries[$changedEntryId]['updated_at']
            );

        // Alleen catalog_number en updated_at mogen wijzigen.
        $expectedEntryA1 = $originalEntries[$changedEntryId];

        $expectedEntryA1['catalog_number'] = 'A-103';

        $expectedEntryA1['updated_at'] =
            $freshEntryA1->getRawOriginal('updated_at');

        expect($freshEntryA1->getAttributes())
            ->toBe($expectedEntryA1);

        expect($freshEntryA1->stamp->id)
            ->toBe($this->stamp->id);

        expect($freshEntryA1->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($freshEntryA1->catalogEdition->catalog->id)
            ->toBe($this->catalogA->id);

        expect($freshEntryA1->values()->count())
            ->toBe(2);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $changedEntryId,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionA1->id,
            'catalog_number' => 'A-103',
        ]);

        // Er mag geen dubbele catalogusvermelding bestaan.
        expect(
            $freshStamp->catalogEntries()
                ->where('catalog_edition_id', $this->editionA1->id)
                ->sole()
                ->id
        )->toBe($changedEntryId);

        // -------------------------------------------------
        // Controleer de definitieve cataloguswaarden.
        // -------------------------------------------------

        $freshPostfris = $this->valueA1Postfris->fresh();

        $freshGestempeld = $this->valueA1Gestempeld->fresh();

        foreach ([
            [$freshPostfris, $changedPostfrisId, $this->postfris, '18.00'],
            [$freshGestempeld, $changedGestempeldId, $this->gestempeld, '10.00'],
        ] as [$value, $recordId, $condition, $amount]) {
            expect($value)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($value->id)
                ->toBe($recordId);

            expect($value->stamp_catalog_entry_id)
                ->toBe($changedEntryId);

            expect($value->condition_id)
                ->toBe($condition->id);

            expect($value->catalog_value)
                ->toBe($amount);

            expect($value->currency)
                ->toBe('EUR');

            expect($value->needs_review)
                ->toBeTrue();

            expect($value->catalogEntry->id)
                ->toBe($changedEntryId);

            expect($value->condition->id)
                ->toBe($condition->id);

            expect($value->getRawOriginal('created_at'))
                ->toBe(
                    $originalValues[$recordId]['created_at']
                );

            expect($value->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalValues[$recordId]['updated_at']
                );

            // Alleen deze drie attributen mogen wijzigen.
            $expectedAttributes = $originalValues[$recordId];

            foreach ([
                'catalog_value',
                'needs_review',
                'updated_at',
            ] as $attribute) {
                $expectedAttributes[$attribute] =
                    $value->getRawOriginal($attribute);
            }

            expect($value->getAttributes())
                ->toBe($expectedAttributes);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $recordId,
                'stamp_catalog_entry_id' => $changedEntryId,
                'condition_id' => $condition->id,
                'catalog_value' => (float) $amount,
                'currency' => 'EUR',
                'needs_review' => true,
            ]);

            // Er mag geen dubbele cataloguswaarde bestaan.
            expect(
                $freshEntryA1->values()
                    ->where('condition_id', $condition->id)
                    ->sole()
                    ->id
            )->toBe($recordId);
        }

        // -------------------------------------------------
        // Controleer de niet-ingediende catalogusvermeldingen.
        // -------------------------------------------------

        foreach ([
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)->not->toBeNull();

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Alle vier overige cataloguswaarden moeten
        // volledig ongewijzigd blijven, inclusief timestamps.
        foreach ([
            $this->valueA2Postfris,
            $this->valueA2Gestempeld,
            $this->valueB1Postfris,
            $this->valueB1Gestempeld,
        ] as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)->not->toBeNull();

            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Uitsluitend de twee gewijzigde waarden
        // mogen voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect([
                $changedPostfrisId,
                $changedGestempeldId,
            ])->sort()->values()->all()
        );

        // Exact dezelfde catalogusvermeldingen
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Exact dezelfde cataloguswaarden
        // moeten aan de postzegel gekoppeld blijven.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen nieuwe koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // -------------------------------------------------
        // Controleer de gezamenlijke geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch)
            ->toBeInstanceOf(CatalogChangeBatch::class);

        expect($batch->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe(
                'Catalogusgegevens bij het wijzigen van een postzegel'
            );

        expect($batch->catalog_name)
            ->toBe($this->catalogA->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionA1->edition_name);

        expect($batch->catalogEdition->id)
            ->toBe($this->editionA1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionA1->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogA->name,
            'catalog_edition_name' => $this->editionA1->edition_name,
        ]);

        // Alle negen wijzigingen moeten in dezelfde batch staan.
        $this->assertDatabaseCount('catalog_change_logs', 9);

        expect($batch->logs()->count())
            ->toBe(9);

        $logs = $batch->logs()
            ->orderBy('id')
            ->get();

        expect($logs)->toHaveCount(9);

        // -------------------------------------------------
        // Definieer alle negen verwachte wijzigingslogs.
        // -------------------------------------------------

        $expectedLogs = [
            // Route B: catalogusnummer wijzigen.
            [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $changedEntryId,
                'condition_id' => null,
                'old_values' => [
                    'catalog_number' => 'A-100',
                ],
                'new_values' => [
                    'catalog_number' => 'A-101',
                ],
            ],

            // Route B: postfrisse waarde wijzigen.
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $changedPostfrisId,
                'condition_id' => $this->postfris->id,
                'old_values' => [
                    'catalog_value' => 15.00,
                    'needs_review' => false,
                ],
                'new_values' => [
                    'catalog_value' => 16.00,
                    'needs_review' => true,
                ],
            ],

            // Route B: gestempelde waarde wijzigen.
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $changedGestempeldId,
                'condition_id' => $this->gestempeld->id,
                'old_values' => [
                    'catalog_value' => 7.50,
                    'needs_review' => false,
                ],
                'new_values' => [
                    'catalog_value' => 8.00,
                    'needs_review' => true,
                ],
            ],

            // Route C: catalogusnummer opnieuw wijzigen.
            [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $changedEntryId,
                'condition_id' => null,
                'old_values' => [
                    'catalog_number' => 'A-101',
                ],
                'new_values' => [
                    'catalog_number' => 'A-102',
                ],
            ],

            // Route C: postfrisse waarde opnieuw wijzigen.
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $changedPostfrisId,
                'condition_id' => $this->postfris->id,
                'old_values' => [
                    'catalog_value' => 16.00,
                ],
                'new_values' => [
                    'catalog_value' => 17.00,
                ],
            ],

            // Route C: gestempelde waarde opnieuw wijzigen.
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $changedGestempeldId,
                'condition_id' => $this->gestempeld->id,
                'old_values' => [
                    'catalog_value' => 8.00,
                ],
                'new_values' => [
                    'catalog_value' => 9.00,
                ],
            ],

            // Route D: definitief catalogusnummer.
            [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $changedEntryId,
                'condition_id' => null,
                'old_values' => [
                    'catalog_number' => 'A-102',
                ],
                'new_values' => [
                    'catalog_number' => 'A-103',
                ],
            ],

            // Route D: definitieve postfrisse waarde.
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $changedPostfrisId,
                'condition_id' => $this->postfris->id,
                'old_values' => [
                    'catalog_value' => 17.00,
                ],
                'new_values' => [
                    'catalog_value' => 18.00,
                ],
            ],

            // Route D: definitieve gestempelde waarde.
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $changedGestempeldId,
                'condition_id' => $this->gestempeld->id,
                'old_values' => [
                    'catalog_value' => 9.00,
                ],
                'new_values' => [
                    'catalog_value' => 10.00,
                ],
            ],
        ];

        // -------------------------------------------------
        // Controleer ieder wijzigingslog afzonderlijk.
        // -------------------------------------------------

        foreach ($expectedLogs as $index => $expectedLog) {
            $log = $logs[$index];

            expect($log)
                ->toBeInstanceOf(CatalogChangeLog::class);

            expect($log->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($log->catalog_edition_id)
                ->toBe($this->editionA1->id);

            expect($log->stamp_id)
                ->toBe($this->stamp->id);

            expect($log->stamp_series_id)
                ->toBeNull();

            expect($log->record_type)
                ->toBe($expectedLog['record_type']);

            expect($log->record_id)
                ->toBe($expectedLog['record_id']);

            expect($log->condition_id)
                ->toBe($expectedLog['condition_id']);

            expect($log->action)
                ->toBe('updated');

            // Controleer de relaties van het wijzigingslog.
            expect($log->batch->id)
                ->toBe($batch->id);

            expect($log->catalogEdition->id)
                ->toBe($this->editionA1->id);

            expect($log->stamp->id)
                ->toBe($this->stamp->id);

            expect($log->stampSeries)
                ->toBeNull();

            if ($expectedLog['condition_id'] === null) {
                expect($log->condition)
                    ->toBeNull();
            } else {
                expect($log->condition->id)
                    ->toBe($expectedLog['condition_id']);
            }

            // Controleer de volledige oude en nieuwe loggegevens.
            foreach ([
                'old_values',
                'new_values',
            ] as $attribute) {
                $loggedValues = $log->{$attribute};

                $expectedValues = $expectedLog[$attribute];

                expect($loggedValues)
                    ->toBeArray()
                    ->toHaveCount(count($expectedValues))
                    ->toHaveKeys(array_keys($expectedValues));

                // Normaliseer numerieke cataloguswaarden.
                if (array_key_exists('catalog_value', $expectedValues)) {
                    expect(is_numeric($loggedValues['catalog_value']))
                        ->toBeTrue();

                    $loggedValues['catalog_value'] =
                        (float) $loggedValues['catalog_value'];
                }

                // Normaliseer de controlemarkering.
                if (array_key_exists('needs_review', $expectedValues)) {
                    expect(
                        in_array(
                            $loggedValues['needs_review'],
                            [false, true, 0, 1],
                            true
                        )
                    )->toBeTrue();

                    $loggedValues['needs_review'] =
                        (bool) $loggedValues['needs_review'];
                }

                // De volgorde van JSON-attributen is niet relevant.
                ksort($loggedValues);

                ksort($expectedValues);

                expect($loggedValues)
                    ->toBe($expectedValues);

                // Ongewijzigde identificatiegegevens en timestamps
                // mogen niet in het wijzigingslog voorkomen.
                foreach ([
                    'id',
                    'stamp_id',
                    'stamp_catalog_entry_id',
                    'catalog_edition_id',
                    'condition_id',
                    'currency',
                    'notes',
                    'created_at',
                    'updated_at',
                ] as $unchangedAttribute) {
                    expect($loggedValues)
                        ->not->toHaveKey($unchangedAttribute);
                }
            }

            // Controleer het daadwerkelijk opgeslagen wijzigingslog.
            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $log->id,
                'catalog_change_batch_id' => $batch->id,
                'catalog_edition_id' => $this->editionA1->id,
                'stamp_id' => $this->stamp->id,
                'stamp_series_id' => null,
                'condition_id' => $expectedLog['condition_id'],
                'record_type' => $expectedLog['record_type'],
                'record_id' => $expectedLog['record_id'],
                'action' => 'updated',
            ]);
        }

        // -------------------------------------------------
        // Controleer de historische wijzigingsketens.
        // -------------------------------------------------

        $entryLogs = $logs
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $changedEntryId)
            ->values();

        $postfrisLogs = $logs
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $changedPostfrisId)
            ->values();

        $gestempeldLogs = $logs
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $changedGestempeldId)
            ->values();

        expect($entryLogs)->toHaveCount(3);

        expect($postfrisLogs)->toHaveCount(3);

        expect($gestempeldLogs)->toHaveCount(3);

        // De nieuwe waarde van iedere route moet overeenkomen
        // met de oorspronkelijke waarde van de volgende route.
        for ($index = 0; $index < 2; $index++) {
            expect(
                $entryLogs[$index]->new_values['catalog_number']
            )->toBe(
                $entryLogs[$index + 1]->old_values['catalog_number']
            );

            expect(
                (float) $postfrisLogs[$index]->new_values['catalog_value']
            )->toBe(
                (float) $postfrisLogs[$index + 1]->old_values['catalog_value']
            );

            expect(
                (float) $gestempeldLogs[$index]->new_values['catalog_value']
            )->toBe(
                (float) $gestempeldLogs[$index + 1]->old_values['catalog_value']
            );
        }

        // De eerste historische waarden moeten overeenkomen
        // met de oorspronkelijke databasewaarden.
        expect($entryLogs[0]->old_values['catalog_number'])
            ->toBe(
                $originalEntries[$changedEntryId]['catalog_number']
            );

        expect((float) $postfrisLogs[0]->old_values['catalog_value'])
            ->toBe(
                (float) $originalValues[$changedPostfrisId]['catalog_value']
            );

        expect((float) $gestempeldLogs[0]->old_values['catalog_value'])
            ->toBe(
                (float) $originalValues[$changedGestempeldId]['catalog_value']
            );

        // De laatste historische waarden moeten overeenkomen
        // met de definitieve databasewaarden.
        expect($entryLogs[2]->new_values['catalog_number'])
            ->toBe($freshEntryA1->catalog_number);

        expect((float) $postfrisLogs[2]->new_values['catalog_value'])
            ->toBe((float) $freshPostfris->catalog_value);

        expect((float) $gestempeldLogs[2]->new_values['catalog_value'])
            ->toBe((float) $freshGestempeld->catalog_value);

        // -------------------------------------------------
        // Controleer de historische controlemarkeringen.
        // -------------------------------------------------

        $reviewLogs = $logs->filter(
            fn ($log) => array_key_exists(
                'needs_review',
                $log->new_values
            )
        )->values();

        // Alleen de eerste wijziging van iedere waarde
        // mag een gewijzigde controlemarkering bevatten.
        expect($reviewLogs)->toHaveCount(2);

        expect($reviewLogs->pluck('id')->all())
            ->toBe([
                $logs[1]->id,
                $logs[2]->id,
            ]);

        foreach ($reviewLogs as $log) {
            expect((bool) $log->old_values['needs_review'])
                ->toBeFalse();

            expect((bool) $log->new_values['needs_review'])
                ->toBeTrue();
        }

        // Bij de volgende routes blijft needs_review ongewijzigd.
        foreach ([
            $logs[4],
            $logs[5],
            $logs[7],
            $logs[8],
        ] as $log) {
            expect($log->old_values)
                ->not->toHaveKey('needs_review');

            expect($log->new_values)
                ->not->toHaveKey('needs_review');
        }

        // -------------------------------------------------
        // Controleer de totale geschiedenisverdeling.
        // -------------------------------------------------

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(9);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_entry')
                ->count()
        )->toBe(3);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(6);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // Er mogen geen geschiedenisbatches of wijzigingslogs
        // voor andere catalogusedities bestaan.
        foreach ([
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Er mogen geen lege geschiedenisbatches achterblijven.
        expect(
            CatalogChangeBatch::query()
                ->whereDoesntHave('logs')
                ->exists()
        )->toBeFalse();

        // -------------------------------------------------
        // Controleer alle overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        // Alle oorspronkelijke landkoppelingen
        // moeten volledig behouden blijven.
        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 9);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert afzonderlijke geschiedenisbatches voor verschillende catalogusedities via routes B C en D', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
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

    // Verzamel alle bestaande cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke landkoppelingen.
    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Alle oorspronkelijke waarden zijn gecontroleerd.
    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    // A1 en A2 behoren tot dezelfde catalogus.
    expect($this->editionA1->catalog_id)
        ->toBe($this->catalogA->id);

    expect($this->editionA2->catalog_id)
        ->toBe($this->catalogA->id);

    expect($this->editionA1->id)
        ->not->toBe($this->editionA2->id);

    // B1 behoort tot een andere catalogus.
    expect($this->editionB1->catalog_id)
        ->toBe($this->catalogB->id);

    expect($this->catalogA->id)
        ->not->toBe($this->catalogB->id);

    // Controleer de oorspronkelijke valuta's.
    expect($this->editionA1->currency)
        ->toBe('EUR');

    expect($this->editionA2->currency)
        ->toBe('EUR');

    expect($this->editionB1->currency)
        ->toBe('USD');

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    // -------------------------------------------------
    // Route B: cataloguseditie A1.
    // -------------------------------------------------

    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-101';

    $payload['catalog_values'] = [
        'postfris' => '16.50',
        'gestempeld' => '8.25',
    ];

    // -------------------------------------------------
    // Route C: cataloguseditie A2.
    // -------------------------------------------------

    $payload['catalog_entries'] = [
        $this->entryA2->id => [
            'catalog_number' => 'A-201',
            'values' => [
                'postfris' => '22.50',
                'gestempeld' => '11.25',
            ],
        ],
    ];

    // -------------------------------------------------
    // Route D: cataloguseditie B1.
    // -------------------------------------------------

    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-101',
            'values' => [
                'postfris' => '30.75',
                'gestempeld' => '15.50',
            ],
        ],
    ];

    // Controleer of alle drie invoerstructuren aanwezig zijn.
    expect($payload)
        ->toHaveKeys([
            'catalog_edition_id',
            'catalog_number',
            'catalog_values',
            'catalog_entries',
            'catalog_editions',
        ]);

    expect($payload['catalog_edition_id'])
        ->toBe($this->editionA1->id);

    expect($payload['catalog_entries'])
        ->toHaveCount(1)
        ->toHaveKey($this->entryA2->id);

    expect($payload['catalog_editions'])
        ->toHaveCount(1)
        ->toHaveKey($this->editionB1->id);

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // -------------------------------------------------
    // Definieer de verwachte gegevens per cataloguseditie.
    // -------------------------------------------------

    $expectedEditions = [
        [
            'edition' => $this->editionA1,
            'catalog' => $this->catalogA,
            'entry' => $this->entryA1,
            'old_number' => 'A-100',
            'new_number' => 'A-101',
            'currency' => 'EUR',
            'values' => [
                [
                    'model' => $this->valueA1Postfris,
                    'condition' => $this->postfris,
                    'old_amount' => '15.00',
                    'new_amount' => '16.50',
                ],
                [
                    'model' => $this->valueA1Gestempeld,
                    'condition' => $this->gestempeld,
                    'old_amount' => '7.50',
                    'new_amount' => '8.25',
                ],
            ],
        ],
        [
            'edition' => $this->editionA2,
            'catalog' => $this->catalogA,
            'entry' => $this->entryA2,
            'old_number' => 'A-200',
            'new_number' => 'A-201',
            'currency' => 'EUR',
            'values' => [
                [
                    'model' => $this->valueA2Postfris,
                    'condition' => $this->postfris,
                    'old_amount' => '20.00',
                    'new_amount' => '22.50',
                ],
                [
                    'model' => $this->valueA2Gestempeld,
                    'condition' => $this->gestempeld,
                    'old_amount' => '10.00',
                    'new_amount' => '11.25',
                ],
            ],
        ],
        [
            'edition' => $this->editionB1,
            'catalog' => $this->catalogB,
            'entry' => $this->entryB1,
            'old_number' => 'B-100',
            'new_number' => 'B-101',
            'currency' => 'USD',
            'values' => [
                [
                    'model' => $this->valueB1Postfris,
                    'condition' => $this->postfris,
                    'old_amount' => '25.00',
                    'new_amount' => '30.75',
                ],
                [
                    'model' => $this->valueB1Gestempeld,
                    'condition' => $this->gestempeld,
                    'old_amount' => '12.50',
                    'new_amount' => '15.50',
                ],
            ],
        ],
    ];

    // Controleer alle oorspronkelijke catalogusgegevens.
    foreach ($expectedEditions as $expectedEdition) {
        expect($expectedEdition['entry']->catalog_number)
            ->toBe($expectedEdition['old_number']);

        foreach ($expectedEdition['values'] as $expectedValue) {
            expect($expectedValue['model']->catalog_value)
                ->toBe($expectedValue['old_amount']);

            expect($expectedValue['model']->currency)
                ->toBe($expectedEdition['currency']);

            expect($expectedValue['model']->needs_review)
                ->toBeFalse();
        }
    }

    // Verplaats de tijd om timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        // Verwerk alle drie routes binnen dezelfde aanvraag.
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

        // -------------------------------------------------
        // Controleer de algemene postzegelgegevens.
        // -------------------------------------------------

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)->not->toBeNull();

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer alle definitieve catalogusgegevens.
        // -------------------------------------------------

        foreach ($expectedEditions as $expectedEdition) {
            $edition = $expectedEdition['edition'];

            $catalog = $expectedEdition['catalog'];

            $entry = $expectedEdition['entry'];

            $currency = $expectedEdition['currency'];

            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            expect($freshEntry->id)
                ->toBe($entry->id);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalog_edition_id)
                ->toBe($edition->id);

            expect($freshEntry->catalog_number)
                ->toBe($expectedEdition['new_number']);

            expect($freshEntry->getRawOriginal('created_at'))
                ->toBe(
                    $originalEntries[$entry->id]['created_at']
                );

            expect($freshEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$entry->id]['updated_at']
                );

            // Alleen catalog_number en updated_at mogen wijzigen.
            $expectedEntryAttributes =
                $originalEntries[$entry->id];

            $expectedEntryAttributes['catalog_number'] =
                $expectedEdition['new_number'];

            $expectedEntryAttributes['updated_at'] =
                $freshEntry->getRawOriginal('updated_at');

            expect($freshEntry->getAttributes())
                ->toBe($expectedEntryAttributes);

            // Controleer de relaties.
            expect($freshEntry->stamp->id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalogEdition->id)
                ->toBe($edition->id);

            expect($freshEntry->catalogEdition->catalog->id)
                ->toBe($catalog->id);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Er mag geen dubbele catalogusvermelding bestaan.
            expect(
                $freshStamp->catalogEntries()
                    ->where('catalog_edition_id', $edition->id)
                    ->sole()
                    ->id
            )->toBe($entry->id);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $entry->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $edition->id,
                'catalog_number' => $expectedEdition['new_number'],
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $entry->id,
                'catalog_number' => $expectedEdition['old_number'],
            ]);

            // Controleer beide gewijzigde cataloguswaarden.
            foreach ($expectedEdition['values'] as $expectedValue) {
                $value = $expectedValue['model'];

                $condition = $expectedValue['condition'];

                $freshValue = $value->fresh();

                expect($freshValue)
                    ->toBeInstanceOf(StampCatalogValue::class);

                expect($freshValue->id)
                    ->toBe($value->id);

                expect($freshValue->stamp_catalog_entry_id)
                    ->toBe($entry->id);

                expect($freshValue->condition_id)
                    ->toBe($condition->id);

                expect($freshValue->catalog_value)
                    ->toBe($expectedValue['new_amount']);

                expect($freshValue->currency)
                    ->toBe($currency);

                expect($freshValue->needs_review)
                    ->toBeTrue();

                expect($freshValue->getRawOriginal('created_at'))
                    ->toBe(
                        $originalValues[$value->id]['created_at']
                    );

                expect($freshValue->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalValues[$value->id]['updated_at']
                    );

                // Alleen catalog_value, needs_review
                // en updated_at mogen wijzigen.
                $expectedValueAttributes =
                    $originalValues[$value->id];

                foreach ([
                    'catalog_value',
                    'needs_review',
                    'updated_at',
                ] as $attribute) {
                    $expectedValueAttributes[$attribute] =
                        $freshValue->getRawOriginal($attribute);
                }

                expect($freshValue->getAttributes())
                    ->toBe($expectedValueAttributes);

                // Controleer de relaties.
                expect($freshValue->catalogEntry->id)
                    ->toBe($entry->id);

                expect($freshValue->catalogEntry->catalog_edition_id)
                    ->toBe($edition->id);

                expect($freshValue->condition->id)
                    ->toBe($condition->id);

                // Er mag geen dubbele cataloguswaarde bestaan.
                expect(
                    $freshEntry->values()
                        ->where('condition_id', $condition->id)
                        ->sole()
                        ->id
                )->toBe($value->id);

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $value->id,
                    'stamp_catalog_entry_id' => $entry->id,
                    'condition_id' => $condition->id,
                    'catalog_value' => (float) $expectedValue['new_amount'],
                    'currency' => $currency,
                    'needs_review' => true,
                ]);

                $this->assertDatabaseMissing('stamp_catalog_values', [
                    'id' => $value->id,
                    'catalog_value' => (float) $expectedValue['old_amount'],
                ]);
            }
        }

        // -------------------------------------------------
        // Controleer alle definitieve catalogusrelaties.
        // -------------------------------------------------

        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Alle oorspronkelijke record-ID's moeten behouden blijven.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Alle zes waarden moeten voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen nieuwe koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // -------------------------------------------------
        // Controleer de geschiedenisbatchverdeling.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 9);

        $batches = CatalogChangeBatch::query()
            ->orderBy('id')
            ->get();

        expect($batches)->toHaveCount(3);

        // De batches moeten in de volgorde B, C en D
        // zijn aangemaakt.
        expect($batches->pluck('catalog_edition_id')->all())
            ->toBe([
                $this->editionA1->id,
                $this->editionA2->id,
                $this->editionB1->id,
            ]);

        expect($batches->pluck('id')->unique()->count())
            ->toBe(3);

        // Iedere cataloguseditie mag precies één batch hebben.
        foreach ($expectedEditions as $expectedEdition) {
            expect(
                CatalogChangeBatch::query()
                    ->where(
                        'catalog_edition_id',
                        $expectedEdition['edition']->id
                    )
                    ->count()
            )->toBe(1);
        }

        // -------------------------------------------------
        // Controleer iedere geschiedenisbatch en alle logs.
        // -------------------------------------------------

        foreach ($expectedEditions as $index => $expectedEdition) {
            $batch = $batches[$index];

            $edition = $expectedEdition['edition'];

            $catalog = $expectedEdition['catalog'];

            $entry = $expectedEdition['entry'];

            expect($batch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($batch->catalog_edition_id)
                ->toBe($edition->id);

            expect($batch->user_id)
                ->toBe($this->user->id);

            expect($batch->user_name)
                ->toBe($this->user->name);

            expect($batch->operation)
                ->toBe('stamp_update');

            expect($batch->description)
                ->toBe(
                    'Catalogusgegevens bij het wijzigen van een postzegel'
                );

            expect($batch->catalog_name)
                ->toBe($catalog->name);

            expect($batch->catalog_edition_name)
                ->toBe($edition->edition_name);

            // Controleer de relaties van de geschiedenisbatch.
            expect($batch->catalogEdition->id)
                ->toBe($edition->id);

            expect($batch->catalogEdition->catalog->id)
                ->toBe($catalog->id);

            expect($batch->user->id)
                ->toBe($this->user->id);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $batch->id,
                'catalog_edition_id' => $edition->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $catalog->name,
                'catalog_edition_name' => $edition->edition_name,
            ]);

            // Iedere batch moet precies drie logs bevatten.
            expect($batch->logs()->count())
                ->toBe(3);

            $logs = $batch->logs()
                ->orderBy('id')
                ->get();

            expect($logs)->toHaveCount(3);

            // Definieer de verwachte wijzigingslogs.
            $expectedLogs = [
                [
                    'record_type' => 'stamp_catalog_entry',
                    'record_id' => $entry->id,
                    'condition_id' => null,
                    'old_values' => [
                        'catalog_number' => $expectedEdition['old_number'],
                    ],
                    'new_values' => [
                        'catalog_number' => $expectedEdition['new_number'],
                    ],
                ],
            ];

            // Voeg de twee verwachte cataloguswaardelogs toe.
            foreach ($expectedEdition['values'] as $expectedValue) {
                $expectedLogs[] = [
                    'record_type' => 'stamp_catalog_value',
                    'record_id' => $expectedValue['model']->id,
                    'condition_id' => $expectedValue['condition']->id,
                    'old_values' => [
                        'catalog_value' => (float) $expectedValue['old_amount'],
                        'needs_review' => false,
                    ],
                    'new_values' => [
                        'catalog_value' => (float) $expectedValue['new_amount'],
                        'needs_review' => true,
                    ],
                ];
            }

            // Controleer alle drie logs afzonderlijk.
            foreach ($expectedLogs as $logIndex => $expectedLog) {
                $log = $logs[$logIndex];

                expect($log)
                    ->toBeInstanceOf(CatalogChangeLog::class);

                expect($log->catalog_change_batch_id)
                    ->toBe($batch->id);

                expect($log->catalog_edition_id)
                    ->toBe($edition->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->record_type)
                    ->toBe($expectedLog['record_type']);

                expect($log->record_id)
                    ->toBe($expectedLog['record_id']);

                expect($log->condition_id)
                    ->toBe($expectedLog['condition_id']);

                expect($log->action)
                    ->toBe('updated');

                // Controleer de relaties van het wijzigingslog.
                expect($log->batch->id)
                    ->toBe($batch->id);

                expect($log->catalogEdition->id)
                    ->toBe($edition->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                if ($expectedLog['condition_id'] === null) {
                    expect($log->condition)
                        ->toBeNull();
                } else {
                    expect($log->condition->id)
                        ->toBe($expectedLog['condition_id']);
                }

                // Controleer de volledige oude en nieuwe loggegevens.
                foreach ([
                    'old_values',
                    'new_values',
                ] as $attribute) {
                    $loggedValues = $log->{$attribute};

                    $expectedValues = $expectedLog[$attribute];

                    expect($loggedValues)
                        ->toBeArray()
                        ->toHaveCount(count($expectedValues))
                        ->toHaveKeys(array_keys($expectedValues));

                    // Normaliseer numerieke cataloguswaarden.
                    if (array_key_exists('catalog_value', $expectedValues)) {
                        expect(is_numeric($loggedValues['catalog_value']))
                            ->toBeTrue();

                        $loggedValues['catalog_value'] =
                            (float) $loggedValues['catalog_value'];
                    }

                    // Normaliseer de controlemarkering.
                    if (array_key_exists('needs_review', $expectedValues)) {
                        expect(
                            in_array(
                                $loggedValues['needs_review'],
                                [false, true, 0, 1],
                                true
                            )
                        )->toBeTrue();

                        $loggedValues['needs_review'] =
                            (bool) $loggedValues['needs_review'];
                    }

                    // De volgorde van JSON-attributen is niet relevant.
                    ksort($loggedValues);

                    ksort($expectedValues);

                    expect($loggedValues)
                        ->toBe($expectedValues);

                    // Ongewijzigde attributen mogen niet
                    // in het wijzigingslog voorkomen.
                    foreach ([
                        'id',
                        'stamp_id',
                        'stamp_catalog_entry_id',
                        'catalog_edition_id',
                        'condition_id',
                        'currency',
                        'notes',
                        'created_at',
                        'updated_at',
                    ] as $unchangedAttribute) {
                        expect($loggedValues)
                            ->not->toHaveKey($unchangedAttribute);
                    }
                }

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $batch->id,
                    'catalog_edition_id' => $edition->id,
                    'stamp_id' => $this->stamp->id,
                    'stamp_series_id' => null,
                    'condition_id' => $expectedLog['condition_id'],
                    'record_type' => $expectedLog['record_type'],
                    'record_id' => $expectedLog['record_id'],
                    'action' => 'updated',
                ]);
            }

            // -------------------------------------------------
            // Vergelijk de geschiedenis met de databasegegevens.
            // -------------------------------------------------

            $entryLog = $logs[0];

            expect($entryLog->old_values['catalog_number'])
                ->toBe(
                    $originalEntries[$entry->id]['catalog_number']
                );

            expect($entryLog->new_values['catalog_number'])
                ->toBe($entry->fresh()->catalog_number);

            foreach ($expectedEdition['values'] as $valueIndex => $expectedValue) {
                $value = $expectedValue['model'];

                $valueLog = $logs[$valueIndex + 1];

                $freshValue = $value->fresh();

                expect((float) $valueLog->old_values['catalog_value'])
                    ->toBe(
                        (float) $originalValues[$value->id]['catalog_value']
                    );

                expect((float) $valueLog->new_values['catalog_value'])
                    ->toBe((float) $freshValue->catalog_value);

                expect((bool) $valueLog->old_values['needs_review'])
                    ->toBe(
                        (bool) $originalValues[$value->id]['needs_review']
                    );

                expect((bool) $valueLog->new_values['needs_review'])
                    ->toBe($freshValue->needs_review);
            }

            // Er mag geen log van een andere cataloguseditie
            // binnen deze geschiedenisbatch aanwezig zijn.
            expect(
                $batch->logs()
                    ->where('catalog_edition_id', '!=', $edition->id)
                    ->exists()
            )->toBeFalse();
        }

        // -------------------------------------------------
        // Controleer de scheiding tussen A1 en A2.
        // -------------------------------------------------

        $batchA1 = $batches[0];

        $batchA2 = $batches[1];

        $batchB1 = $batches[2];

        expect($batchA1->id)
            ->not->toBe($batchA2->id);

        expect($batchA1->catalog_name)
            ->toBe($batchA2->catalog_name);

        expect($batchA1->catalog_edition_id)
            ->not->toBe($batchA2->catalog_edition_id);

        expect($batchB1->catalog_name)
            ->not->toBe($batchA1->catalog_name);

        // Controleer de globale volgorde van de negen logs.
        expect(
            CatalogChangeLog::query()
                ->orderBy('id')
                ->pluck('catalog_change_batch_id')
                ->all()
        )->toBe([
            $batchA1->id,
            $batchA1->id,
            $batchA1->id,
            $batchA2->id,
            $batchA2->id,
            $batchA2->id,
            $batchB1->id,
            $batchB1->id,
            $batchB1->id,
        ]);

        // -------------------------------------------------
        // Controleer de totale geschiedenisverdeling.
        // -------------------------------------------------

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(9);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_entry')
                ->count()
        )->toBe(3);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(6);

        // Iedere catalogusvermelding moet precies één log hebben.
        foreach ($entries as $entry) {
            expect(
                CatalogChangeLog::query()
                    ->where('record_type', 'stamp_catalog_entry')
                    ->where('record_id', $entry->id)
                    ->count()
            )->toBe(1);
        }

        // Iedere cataloguswaarde moet precies één log hebben.
        foreach ($values as $value) {
            expect(
                CatalogChangeLog::query()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $value->id)
                    ->count()
            )->toBe(1);
        }

        // Alle zes waardelogs moeten een gewijzigde
        // controlemarkering bevatten.
        $reviewLogs = CatalogChangeLog::query()
            ->get()
            ->filter(
                fn ($log) => array_key_exists(
                    'needs_review',
                    $log->new_values
                )
            );

        expect($reviewLogs)->toHaveCount(6);

        foreach ($reviewLogs as $log) {
            expect((bool) $log->old_values['needs_review'])
                ->toBeFalse();

            expect((bool) $log->new_values['needs_review'])
                ->toBeTrue();
        }

        // Er mogen geen created- of deleted-logs bestaan.
        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        // C1 mag geen wijzigingsgeschiedenis bevatten.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er mag geen batch voor de andere gebruiker bestaan.
        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Er mogen geen lege geschiedenisbatches achterblijven.
        expect(
            CatalogChangeBatch::query()
                ->whereDoesntHave('logs')
                ->exists()
        )->toBeFalse();

        // -------------------------------------------------
        // Controleer alle overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        // Alle oorspronkelijke landkoppelingen
        // moeten volledig behouden blijven.
        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 9);
    } finally {
        $this->travelBack();
    }
});

test('de backend combineert automatische verwijderingen en cataloguswijzigingen bij een landwijziging binnen een aanvraag', function () {
    // Bewaar de oorspronkelijke postzegel.
    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    // Verzamel de oorspronkelijke catalogusvermeldingen.
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

    // Verzamel de oorspronkelijke cataloguswaarden.
    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] = $value
            ->fresh()
            ->getAttributes();
    }

    // Bewaar alle overige catalogusgegevens.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // Controleer de uitgangssituatie.
    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->stamp->country_id)
        ->toBe($this->country->id);

    expect($this->country->id)
        ->not->toBe($this->otherCountry->id);

    // Catalogus A is uitsluitend geldig voor het oude land.
    expect($originalCatalogCountries[$this->catalogA->id])
        ->toBe([$this->country->id]);

    // Catalogus B is geldig voor beide landen.
    expect($originalCatalogCountries[$this->catalogB->id])
        ->toBe(
            collect([
                $this->country->id,
                $this->otherCountry->id,
            ])->sort()->values()->all()
        );

    // Catalogus C is uitsluitend geldig voor het nieuwe land.
    expect($originalCatalogCountries[$this->catalogC->id])
        ->toBe([$this->otherCountry->id]);

    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    // Stel de formuliergegevens samen.
    $payload = $this->updatePayload;

    // -------------------------------------------------
    // Route A: landwijziging.
    // -------------------------------------------------

    $payload['country_id'] = $this->otherCountry->id;

    // -------------------------------------------------
    // Route B: bestaande cataloguseditie B1 wijzigen.
    // -------------------------------------------------

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-101';

    $payload['catalog_values'] = [
        'postfris' => '30.75',
    ];

    // -------------------------------------------------
    // Route C: dezelfde catalogusvermelding B1 wijzigen.
    // -------------------------------------------------

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-102',
            'values' => [
                'gestempeld' => '15.50',
            ],
        ],
    ];

    // -------------------------------------------------
    // Route D: nieuwe catalogusvermelding C1 aanmaken.
    // -------------------------------------------------

    $payload['catalog_editions'] = [
        $this->editionC1->id => [
            'catalog_number' => 'C-300',
            'values' => [
                'postfris' => '45.75',
                'gestempeld' => '22.50',
            ],
        ],
    ];

    // Verplaats de tijd om timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        // Verwerk alle vier routes binnen dezelfde aanvraag.
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // -------------------------------------------------
        // Controleer de gewijzigde postzegel.
        // -------------------------------------------------

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)
            ->toBeInstanceOf(Stamp::class);

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->otherCountry->id);

        expect($freshStamp->country->id)
            ->toBe($this->otherCountry->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Route A: controleer de verwijderde gegevens.
        // -------------------------------------------------

        $deletedEditions = [
            [
                'edition' => $this->editionA1,
                'entry' => $this->entryA1,
                'values' => [
                    $this->valueA1Postfris,
                    $this->valueA1Gestempeld,
                ],
            ],
            [
                'edition' => $this->editionA2,
                'entry' => $this->entryA2,
                'values' => [
                    $this->valueA2Postfris,
                    $this->valueA2Gestempeld,
                ],
            ],
        ];

        foreach ($deletedEditions as $deletedEdition) {
            $edition = $deletedEdition['edition'];

            $entry = $deletedEdition['entry'];

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $entry->id,
            ]);

            expect($entry->fresh())
                ->toBeNull();

            expect(
                $freshStamp->catalogEntries()
                    ->where('catalog_edition_id', $edition->id)
                    ->exists()
            )->toBeFalse();

            foreach ($deletedEdition['values'] as $value) {
                $this->assertDatabaseMissing('stamp_catalog_values', [
                    'id' => $value->id,
                ]);

                expect($value->fresh())
                    ->toBeNull();
            }

            // De cataloguseditie zelf mag niet worden verwijderd.
            expect($edition->fresh())
                ->toBeInstanceOf(CatalogEdition::class);
        }

        // -------------------------------------------------
        // Routes B en C: controleer catalogusvermelding B1.
        // -------------------------------------------------

        $freshEntryB1 = $this->entryB1->fresh();

        expect($freshEntryB1)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect($freshEntryB1->id)
            ->toBe($this->entryB1->id);

        expect($freshEntryB1->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntryB1->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($freshEntryB1->catalog_number)
            ->toBe('B-102');

        expect($freshEntryB1->getRawOriginal('created_at'))
            ->toBe(
                $originalEntries[$this->entryB1->id]['created_at']
            );

        expect($freshEntryB1->getRawOriginal('updated_at'))
            ->not->toBe(
                $originalEntries[$this->entryB1->id]['updated_at']
            );

        // Alleen catalog_number en updated_at mogen wijzigen.
        $expectedEntryB1 =
            $originalEntries[$this->entryB1->id];

        $expectedEntryB1['catalog_number'] = 'B-102';

        $expectedEntryB1['updated_at'] =
            $freshEntryB1->getRawOriginal('updated_at');

        expect($freshEntryB1->getAttributes())
            ->toBe($expectedEntryB1);

        expect($freshEntryB1->stamp->id)
            ->toBe($this->stamp->id);

        expect($freshEntryB1->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($freshEntryB1->values()->count())
            ->toBe(2);

        // Controleer beide bestaande cataloguswaarden van B1.
        foreach ([
            [
                $this->valueB1Postfris,
                $this->postfris,
                '30.75',
            ],
            [
                $this->valueB1Gestempeld,
                $this->gestempeld,
                '15.50',
            ],
        ] as [$value, $condition, $amount]) {
            $freshValue = $value->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($value->id);

            expect($freshValue->stamp_catalog_entry_id)
                ->toBe($this->entryB1->id);

            expect($freshValue->condition_id)
                ->toBe($condition->id);

            expect($freshValue->catalog_value)
                ->toBe($amount);

            expect($freshValue->currency)
                ->toBe('USD');

            expect($freshValue->needs_review)
                ->toBeTrue();

            expect($freshValue->catalogEntry->id)
                ->toBe($this->entryB1->id);

            expect($freshValue->condition->id)
                ->toBe($condition->id);

            expect($freshValue->getRawOriginal('created_at'))
                ->toBe(
                    $originalValues[$value->id]['created_at']
                );

            expect($freshValue->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalValues[$value->id]['updated_at']
                );

            $expectedAttributes = $originalValues[$value->id];

            foreach ([
                'catalog_value',
                'needs_review',
                'updated_at',
            ] as $attribute) {
                $expectedAttributes[$attribute] =
                    $freshValue->getRawOriginal($attribute);
            }

            expect($freshValue->getAttributes())
                ->toBe($expectedAttributes);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $value->id,
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $condition->id,
                'catalog_value' => (float) $amount,
                'currency' => 'USD',
                'needs_review' => true,
            ]);
        }

        // -------------------------------------------------
        // Route D: controleer de nieuwe catalogusvermelding C1.
        // -------------------------------------------------

        $entryC1 = $freshStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionC1->id)
            ->sole();

        expect($entryC1)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect($entryC1->id)
            ->not->toBe($this->entryB1->id);

        expect($entryC1->stamp_id)
            ->toBe($this->stamp->id);

        expect($entryC1->catalog_edition_id)
            ->toBe($this->editionC1->id);

        expect($entryC1->catalog_number)
            ->toBe('C-300');

        expect($entryC1->stamp->id)
            ->toBe($this->stamp->id);

        expect($entryC1->catalogEdition->id)
            ->toBe($this->editionC1->id);

        expect($entryC1->values()->count())
            ->toBe(2);

        $valueC1Postfris = $entryC1->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $valueC1Gestempeld = $entryC1->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        foreach ([
            [
                $valueC1Postfris,
                $this->postfris,
                '45.75',
            ],
            [
                $valueC1Gestempeld,
                $this->gestempeld,
                '22.50',
            ],
        ] as [$value, $condition, $amount]) {
            expect($value)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($value->stamp_catalog_entry_id)
                ->toBe($entryC1->id);

            expect($value->condition_id)
                ->toBe($condition->id);

            expect($value->catalog_value)
                ->toBe($amount);

            expect($value->currency)
                ->toBe('GBP');

            // Nieuwe waarden zijn geen gewijzigde bestaande waarden.
            expect($value->needs_review)
                ->toBeFalse();

            expect($value->catalogEntry->id)
                ->toBe($entryC1->id);

            expect($value->condition->id)
                ->toBe($condition->id);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $value->id,
                'stamp_catalog_entry_id' => $entryC1->id,
                'condition_id' => $condition->id,
                'catalog_value' => (float) $amount,
                'currency' => 'GBP',
                'needs_review' => false,
            ]);
        }

        // -------------------------------------------------
        // Controleer de definitieve catalogusrelaties.
        // -------------------------------------------------

        expect($freshStamp->catalogEntries()->count())
            ->toBe(2);

        expect($freshStamp->catalogValues()->count())
            ->toBe(4);

        expect(
            $freshStamp->catalogEntries()
                ->orderBy('catalog_edition_id')
                ->pluck('catalog_edition_id')
                ->all()
        )->toBe([
            $this->editionB1->id,
            $this->editionC1->id,
        ]);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect([
                $this->valueB1Postfris->id,
                $this->valueB1Gestempeld->id,
            ])->sort()->values()->all()
        );

        // -------------------------------------------------
        // Controleer de geschiedenisbatchverdeling.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 4);

        $this->assertDatabaseCount('catalog_change_logs', 13);

        $expectedBatches = [
            [
                'edition' => $this->editionA1,
                'catalog' => $this->catalogA,
                'log_count' => 3,
            ],
            [
                'edition' => $this->editionA2,
                'catalog' => $this->catalogA,
                'log_count' => 3,
            ],
            [
                'edition' => $this->editionB1,
                'catalog' => $this->catalogB,
                'log_count' => 4,
            ],
            [
                'edition' => $this->editionC1,
                'catalog' => $this->catalogC,
                'log_count' => 3,
            ],
        ];

        $batches = [];

        foreach ($expectedBatches as $expectedBatch) {
            $edition = $expectedBatch['edition'];

            $catalog = $expectedBatch['catalog'];

            $batch = CatalogChangeBatch::query()
                ->where('catalog_edition_id', $edition->id)
                ->sole();

            $batches[$edition->id] = $batch;

            expect($batch->catalog_edition_id)
                ->toBe($edition->id);

            expect($batch->user_id)
                ->toBe($this->user->id);

            expect($batch->user_name)
                ->toBe($this->user->name);

            expect($batch->operation)
                ->toBe('stamp_update');

            expect($batch->description)
                ->toBe(
                    'Catalogusgegevens bij het wijzigen van een postzegel'
                );

            expect($batch->catalog_name)
                ->toBe($catalog->name);

            expect($batch->catalog_edition_name)
                ->toBe($edition->edition_name);

            expect($batch->catalogEdition->id)
                ->toBe($edition->id);

            expect($batch->catalogEdition->catalog->id)
                ->toBe($catalog->id);

            expect($batch->user->id)
                ->toBe($this->user->id);

            expect($batch->logs()->count())
                ->toBe($expectedBatch['log_count']);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $batch->id,
                'catalog_edition_id' => $edition->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $catalog->name,
                'catalog_edition_name' => $edition->edition_name,
            ]);

            // Controleer de gemeenschappelijke loggegevens.
            foreach ($batch->logs()->get() as $log) {
                expect($log)
                    ->toBeInstanceOf(CatalogChangeLog::class);

                expect($log->catalog_change_batch_id)
                    ->toBe($batch->id);

                expect($log->catalog_edition_id)
                    ->toBe($edition->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->batch->id)
                    ->toBe($batch->id);

                expect($log->catalogEdition->id)
                    ->toBe($edition->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                if ($log->condition_id === null) {
                    expect($log->condition)
                        ->toBeNull();
                } else {
                    expect($log->condition->id)
                        ->toBe($log->condition_id);
                }
            }
        }

        expect(
            collect($batches)
                ->pluck('id')
                ->unique()
                ->count()
        )->toBe(4);

        // -------------------------------------------------
        // Route A: controleer alle zes verwijderingslogs.
        // -------------------------------------------------

        foreach ($deletedEditions as $deletedEdition) {
            $edition = $deletedEdition['edition'];

            $entry = $deletedEdition['entry'];

            $batch = $batches[$edition->id];

            $logs = $batch->logs()
                ->orderBy('id')
                ->get();

            expect($logs)->toHaveCount(3);

            // De twee waarden moeten vóór de vermelding
            // worden verwijderd en geregistreerd.
            $expectedDeletedRecords = [
                [
                    'record_type' => 'stamp_catalog_value',
                    'record_id' => $deletedEdition['values'][0]->id,
                    'condition_id' => $this->postfris->id,
                    'attributes' => $originalValues[
                        $deletedEdition['values'][0]->id
                    ],
                ],
                [
                    'record_type' => 'stamp_catalog_value',
                    'record_id' => $deletedEdition['values'][1]->id,
                    'condition_id' => $this->gestempeld->id,
                    'attributes' => $originalValues[
                        $deletedEdition['values'][1]->id
                    ],
                ],
                [
                    'record_type' => 'stamp_catalog_entry',
                    'record_id' => $entry->id,
                    'condition_id' => null,
                    'attributes' => $originalEntries[$entry->id],
                ],
            ];

            foreach ($expectedDeletedRecords as $index => $expectedRecord) {
                $log = $logs[$index];

                expect($log->record_type)
                    ->toBe($expectedRecord['record_type']);

                expect($log->record_id)
                    ->toBe($expectedRecord['record_id']);

                expect($log->condition_id)
                    ->toBe($expectedRecord['condition_id']);

                expect($log->action)
                    ->toBe('deleted');

                expect($log->new_values)
                    ->toBeNull();

                $loggedValues = $log->old_values;

                $expectedValues = $expectedRecord['attributes'];

                expect($loggedValues)
                    ->toBeArray()
                    ->toHaveCount(count($expectedValues))
                    ->toHaveKeys(array_keys($expectedValues));

                // Normaliseer de database- en JSON-representaties.
                if (array_key_exists('catalog_value', $expectedValues)) {
                    expect(is_numeric($loggedValues['catalog_value']))
                        ->toBeTrue();

                    $loggedValues['catalog_value'] =
                        (float) $loggedValues['catalog_value'];

                    $expectedValues['catalog_value'] =
                        (float) $expectedValues['catalog_value'];
                }

                if (array_key_exists('needs_review', $expectedValues)) {
                    expect(
                        in_array(
                            $loggedValues['needs_review'],
                            [false, true, 0, 1],
                            true
                        )
                    )->toBeTrue();

                    $loggedValues['needs_review'] =
                        (bool) $loggedValues['needs_review'];

                    $expectedValues['needs_review'] =
                        (bool) $expectedValues['needs_review'];
                }

                ksort($loggedValues);

                ksort($expectedValues);

                expect($loggedValues)
                    ->toBe($expectedValues);

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $batch->id,
                    'catalog_edition_id' => $edition->id,
                    'stamp_id' => $this->stamp->id,
                    'stamp_series_id' => null,
                    'condition_id' => $expectedRecord['condition_id'],
                    'record_type' => $expectedRecord['record_type'],
                    'record_id' => $expectedRecord['record_id'],
                    'action' => 'deleted',
                ]);
            }
        }

        // -------------------------------------------------
        // Routes B en C: controleer de vier wijzigingslogs.
        // -------------------------------------------------

        $batchB1 = $batches[$this->editionB1->id];

        $logsB1 = $batchB1->logs()
            ->orderBy('id')
            ->get();

        expect($logsB1)->toHaveCount(4);

        $expectedUpdatedLogs = [
            // Route B: catalogusnummer.
            [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $this->entryB1->id,
                'condition_id' => null,
                'old_values' => [
                    'catalog_number' => 'B-100',
                ],
                'new_values' => [
                    'catalog_number' => 'B-101',
                ],
            ],

            // Route B: postfrisse waarde.
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Postfris->id,
                'condition_id' => $this->postfris->id,
                'old_values' => [
                    'catalog_value' => 25.00,
                    'needs_review' => false,
                ],
                'new_values' => [
                    'catalog_value' => 30.75,
                    'needs_review' => true,
                ],
            ],

            // Route C: catalogusnummer.
            [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $this->entryB1->id,
                'condition_id' => null,
                'old_values' => [
                    'catalog_number' => 'B-101',
                ],
                'new_values' => [
                    'catalog_number' => 'B-102',
                ],
            ],

            // Route C: gestempelde waarde.
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Gestempeld->id,
                'condition_id' => $this->gestempeld->id,
                'old_values' => [
                    'catalog_value' => 12.50,
                    'needs_review' => false,
                ],
                'new_values' => [
                    'catalog_value' => 15.50,
                    'needs_review' => true,
                ],
            ],
        ];

        foreach ($expectedUpdatedLogs as $index => $expectedLog) {
            $log = $logsB1[$index];

            expect($log->record_type)
                ->toBe($expectedLog['record_type']);

            expect($log->record_id)
                ->toBe($expectedLog['record_id']);

            expect($log->condition_id)
                ->toBe($expectedLog['condition_id']);

            expect($log->action)
                ->toBe('updated');

            foreach ([
                'old_values',
                'new_values',
            ] as $attribute) {
                $loggedValues = $log->{$attribute};

                $expectedValues = $expectedLog[$attribute];

                expect($loggedValues)
                    ->toBeArray()
                    ->toHaveCount(count($expectedValues))
                    ->toHaveKeys(array_keys($expectedValues));

                if (array_key_exists('catalog_value', $expectedValues)) {
                    expect(is_numeric($loggedValues['catalog_value']))
                        ->toBeTrue();

                    $loggedValues['catalog_value'] =
                        (float) $loggedValues['catalog_value'];
                }

                if (array_key_exists('needs_review', $expectedValues)) {
                    expect(
                        in_array(
                            $loggedValues['needs_review'],
                            [false, true, 0, 1],
                            true
                        )
                    )->toBeTrue();

                    $loggedValues['needs_review'] =
                        (bool) $loggedValues['needs_review'];
                }

                ksort($loggedValues);

                ksort($expectedValues);

                expect($loggedValues)
                    ->toBe($expectedValues);
            }

            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $log->id,
                'catalog_change_batch_id' => $batchB1->id,
                'catalog_edition_id' => $this->editionB1->id,
                'stamp_id' => $this->stamp->id,
                'condition_id' => $expectedLog['condition_id'],
                'record_type' => $expectedLog['record_type'],
                'record_id' => $expectedLog['record_id'],
                'action' => 'updated',
            ]);
        }

        // Controleer de historische wijzigingsketen van B1.
        expect($logsB1[0]->new_values['catalog_number'])
            ->toBe($logsB1[2]->old_values['catalog_number']);

        expect($logsB1[2]->new_values['catalog_number'])
            ->toBe($freshEntryB1->catalog_number);

        // -------------------------------------------------
        // Route D: controleer de drie aanmaaklogs.
        // -------------------------------------------------

        $batchC1 = $batches[$this->editionC1->id];

        $logsC1 = $batchC1->logs()
            ->orderBy('id')
            ->get();

        expect($logsC1)->toHaveCount(3);

        $expectedCreatedLogs = [
            [
                'record_type' => 'stamp_catalog_entry',
                'model' => $entryC1,
                'condition_id' => null,
                'required_attributes' => [
                    'id',
                    'stamp_id',
                    'catalog_edition_id',
                    'catalog_number',
                    'created_at',
                    'updated_at',
                ],
            ],
            [
                'record_type' => 'stamp_catalog_value',
                'model' => $valueC1Postfris,
                'condition_id' => $this->postfris->id,
                'required_attributes' => [
                    'id',
                    'stamp_catalog_entry_id',
                    'condition_id',
                    'catalog_value',
                    'currency',
                    'created_at',
                    'updated_at',
                ],
            ],
            [
                'record_type' => 'stamp_catalog_value',
                'model' => $valueC1Gestempeld,
                'condition_id' => $this->gestempeld->id,
                'required_attributes' => [
                    'id',
                    'stamp_catalog_entry_id',
                    'condition_id',
                    'catalog_value',
                    'currency',
                    'created_at',
                    'updated_at',
                ],
            ],
        ];

        foreach ($expectedCreatedLogs as $index => $expectedLog) {
            $log = $logsC1[$index];

            $model = $expectedLog['model']->fresh();

            expect($log->record_type)
                ->toBe($expectedLog['record_type']);

            expect($log->record_id)
                ->toBe($model->id);

            expect($log->condition_id)
                ->toBe($expectedLog['condition_id']);

            expect($log->action)
                ->toBe('created');

            expect($log->old_values)
                ->toBeNull();

            expect($log->new_values)
                ->toBeArray()
                ->toHaveKeys($expectedLog['required_attributes']);

            $modelAttributes = $model->getAttributes();

            // Controleer ieder geregistreerd attribuut.
            foreach ($log->new_values as $attribute => $loggedValue) {
                expect($modelAttributes)
                    ->toHaveKey($attribute);

                $expectedValue = $modelAttributes[$attribute];

                if ($attribute === 'catalog_value') {
                    expect(is_numeric($loggedValue))
                        ->toBeTrue();

                    $loggedValue = (float) $loggedValue;

                    $expectedValue = (float) $expectedValue;
                }

                if ($attribute === 'needs_review') {
                    expect(
                        in_array(
                            $loggedValue,
                            [false, true, 0, 1],
                            true
                        )
                    )->toBeTrue();

                    $loggedValue = (bool) $loggedValue;

                    $expectedValue = (bool) $expectedValue;
                }

                expect($loggedValue)
                    ->toBe($expectedValue);
            }

            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $log->id,
                'catalog_change_batch_id' => $batchC1->id,
                'catalog_edition_id' => $this->editionC1->id,
                'stamp_id' => $this->stamp->id,
                'condition_id' => $expectedLog['condition_id'],
                'record_type' => $expectedLog['record_type'],
                'record_id' => $model->id,
                'action' => 'created',
            ]);
        }

        // -------------------------------------------------
        // Controleer de totale geschiedenisverdeling.
        // -------------------------------------------------

        expect(
            CatalogChangeLog::query()
                ->where('action', 'deleted')
                ->count()
        )->toBe(6);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(4);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'created')
                ->count()
        )->toBe(3);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_entry')
                ->count()
        )->toBe(5);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(8);

        // Alle verwijderingen via route A moeten vóór
        // de wijzigingen via routes B en C plaatsvinden.
        $deletedLogs = CatalogChangeLog::query()
            ->where('action', 'deleted')
            ->get();

        expect($deletedLogs->max('id'))
            ->toBeLessThan($logsB1->min('id'));

        // De aanmaaklogs van route D moeten daarna volgen.
        expect($logsB1->max('id'))
            ->toBeLessThan($logsC1->min('id'));

        // Er mogen geen lege geschiedenisbatches bestaan.
        expect(
            CatalogChangeBatch::query()
                ->whereDoesntHave('logs')
                ->exists()
        )->toBeFalse();

        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // -------------------------------------------------
        // Controleer alle overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        // Controleer de definitieve databaseaantallen.
        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 2);

        $this->assertDatabaseCount('stamp_catalog_values', 4);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 4);

        $this->assertDatabaseCount('catalog_change_logs', 13);
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert uitsluitend wijzigingsgeschiedenis voor de gewijzigde postzegel en behoudt alle gegevens van een andere postzegel', function () {
    // -------------------------------------------------
    // Maak een tweede postzegel aan.
    // -------------------------------------------------

    $otherStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2020,
    ]);

    expect($otherStamp->id)
        ->not->toBe($this->stamp->id);

    expect($otherStamp->country_id)
        ->toBe($this->stamp->country_id);

    expect($otherStamp->issue_year)
        ->toBe($this->stamp->issue_year);

    // Maak catalogusvermeldingen voor dezelfde edities aan.
    $otherEntryA1 = $otherStamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionA1->id,
        'catalog_number' => 'A-900',
        'notes' => 'Andere postzegel cataloguseditie A1',
    ]);

    $otherEntryA2 = $otherStamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionA2->id,
        'catalog_number' => 'A-901',
        'notes' => 'Andere postzegel cataloguseditie A2',
    ]);

    $otherEntryB1 = $otherStamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionB1->id,
        'catalog_number' => 'B-900',
        'notes' => 'Andere postzegel cataloguseditie B1',
    ]);

    // Maak afwijkende cataloguswaarden aan.
    $otherValueA1Postfris = $otherEntryA1->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 115.00,
        'currency' => 'EUR',
        'notes' => 'Andere postzegel A1 postfris',
        'needs_review' => true,
    ]);

    $otherValueA1Gestempeld = $otherEntryA1->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 57.50,
        'currency' => 'EUR',
        'notes' => 'Andere postzegel A1 gestempeld',
        'needs_review' => false,
    ]);

    $otherValueA2Postfris = $otherEntryA2->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 120.00,
        'currency' => 'EUR',
        'notes' => 'Andere postzegel A2 postfris',
        'needs_review' => false,
    ]);

    $otherValueA2Gestempeld = $otherEntryA2->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 60.00,
        'currency' => 'EUR',
        'notes' => 'Andere postzegel A2 gestempeld',
        'needs_review' => true,
    ]);

    $otherValueB1Postfris = $otherEntryB1->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 125.00,
        'currency' => 'USD',
        'notes' => 'Andere postzegel B1 postfris',
        'needs_review' => true,
    ]);

    $otherValueB1Gestempeld = $otherEntryB1->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 62.50,
        'currency' => 'USD',
        'notes' => 'Andere postzegel B1 gestempeld',
        'needs_review' => false,
    ]);

    // -------------------------------------------------
    // Bewaar alle oorspronkelijke gegevens.
    // -------------------------------------------------

    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalOtherStamp = $otherStamp
        ->fresh()
        ->getAttributes();

    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $otherEntries = [
        $otherEntryA1,
        $otherEntryA2,
        $otherEntryB1,
    ];

    $otherValues = [
        $otherValueA1Postfris,
        $otherValueA1Gestempeld,
        $otherValueA2Postfris,
        $otherValueA2Gestempeld,
        $otherValueB1Postfris,
        $otherValueB1Gestempeld,
    ];

    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] =
            $entry->fresh()->getAttributes();
    }

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] =
            $value->fresh()->getAttributes();
    }

    $originalOtherEntries = [];

    foreach ($otherEntries as $entry) {
        $originalOtherEntries[$entry->id] =
            $entry->fresh()->getAttributes();
    }

    $originalOtherValues = [];

    foreach ($otherValues as $value) {
        $originalOtherValues[$value->id] =
            $value->fresh()->getAttributes();
    }

    // Bewaar ook de vertalingen van de andere postzegel.
    $originalOtherTranslations = $otherStamp->translations()
        ->orderBy('id')
        ->get()
        ->map(fn ($translation) => $translation->getAttributes())
        ->all();

    // Bewaar de themakoppelingen.
    $originalOtherThemes = $otherStamp->themes()
        ->orderBy('themes.id')
        ->pluck('themes.id')
        ->all();

    // Bewaar de algemene catalogusgegevens.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 2);

    $this->assertDatabaseCount('stamp_catalog_entries', 6);

    $this->assertDatabaseCount('stamp_catalog_values', 12);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    expect($this->stamp->catalogValues()->count())
        ->toBe(6);

    expect($otherStamp->catalogEntries()->count())
        ->toBe(3);

    expect($otherStamp->catalogValues()->count())
        ->toBe(6);

    // Beide postzegels gebruiken dezelfde catalogusedities.
    expect(
        $this->stamp->catalogEntries()
            ->orderBy('catalog_edition_id')
            ->pluck('catalog_edition_id')
            ->all()
    )->toBe(
        $otherStamp->catalogEntries()
            ->orderBy('catalog_edition_id')
            ->pluck('catalog_edition_id')
            ->all()
    );

    // De oorspronkelijke controlemarkeringen moeten verschillen.
    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    expect($otherValueA1Postfris->needs_review)
        ->toBeTrue();

    expect($otherValueA1Gestempeld->needs_review)
        ->toBeFalse();

    expect($otherValueA2Postfris->needs_review)
        ->toBeFalse();

    expect($otherValueA2Gestempeld->needs_review)
        ->toBeTrue();

    expect($otherValueB1Postfris->needs_review)
        ->toBeTrue();

    expect($otherValueB1Gestempeld->needs_review)
        ->toBeFalse();

    // -------------------------------------------------
    // Stel de formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

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

    // Definieer de verwachte wijzigingen.
    $expectedEditions = [
        [
            'edition' => $this->editionA1,
            'catalog' => $this->catalogA,
            'entry' => $this->entryA1,
            'old_number' => 'A-100',
            'new_number' => 'A-101',
            'currency' => 'EUR',
            'values' => [
                [
                    'model' => $this->valueA1Postfris,
                    'condition' => $this->postfris,
                    'old_amount' => '15.00',
                    'new_amount' => '16.50',
                ],
                [
                    'model' => $this->valueA1Gestempeld,
                    'condition' => $this->gestempeld,
                    'old_amount' => '7.50',
                    'new_amount' => '8.25',
                ],
            ],
        ],
        [
            'edition' => $this->editionA2,
            'catalog' => $this->catalogA,
            'entry' => $this->entryA2,
            'old_number' => 'A-200',
            'new_number' => 'A-201',
            'currency' => 'EUR',
            'values' => [
                [
                    'model' => $this->valueA2Postfris,
                    'condition' => $this->postfris,
                    'old_amount' => '20.00',
                    'new_amount' => '22.50',
                ],
                [
                    'model' => $this->valueA2Gestempeld,
                    'condition' => $this->gestempeld,
                    'old_amount' => '10.00',
                    'new_amount' => '11.25',
                ],
            ],
        ],
        [
            'edition' => $this->editionB1,
            'catalog' => $this->catalogB,
            'entry' => $this->entryB1,
            'old_number' => 'B-100',
            'new_number' => 'B-101',
            'currency' => 'USD',
            'values' => [
                [
                    'model' => $this->valueB1Postfris,
                    'condition' => $this->postfris,
                    'old_amount' => '25.00',
                    'new_amount' => '30.75',
                ],
                [
                    'model' => $this->valueB1Gestempeld,
                    'condition' => $this->gestempeld,
                    'old_amount' => '12.50',
                    'new_amount' => '15.50',
                ],
            ],
        ],
    ];

    // Verplaats de tijd om ongewenste wijzigingen
    // van timestamps te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // Wijzig uitsluitend de eerste postzegel.
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // -------------------------------------------------
        // Controleer de gewijzigde postzegel.
        // -------------------------------------------------

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)
            ->toBeInstanceOf(Stamp::class);

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer de gewijzigde catalogusgegevens.
        // -------------------------------------------------

        foreach ($expectedEditions as $expectedEdition) {
            $edition = $expectedEdition['edition'];

            $entry = $expectedEdition['entry'];

            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            expect($freshEntry->id)
                ->toBe($entry->id);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalog_edition_id)
                ->toBe($edition->id);

            expect($freshEntry->catalog_number)
                ->toBe($expectedEdition['new_number']);

            expect($freshEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$entry->id]['updated_at']
                );

            // Alleen catalog_number en updated_at mogen wijzigen.
            $expectedAttributes = $originalEntries[$entry->id];

            $expectedAttributes['catalog_number'] =
                $expectedEdition['new_number'];

            $expectedAttributes['updated_at'] =
                $freshEntry->getRawOriginal('updated_at');

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->stamp->id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalogEdition->id)
                ->toBe($edition->id);

            expect($freshEntry->values()->count())
                ->toBe(2);

            expect(
                $freshStamp->catalogEntries()
                    ->where('catalog_edition_id', $edition->id)
                    ->sole()
                    ->id
            )->toBe($entry->id);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $entry->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $edition->id,
                'catalog_number' => $expectedEdition['new_number'],
            ]);

            foreach ($expectedEdition['values'] as $expectedValue) {
                $value = $expectedValue['model'];

                $condition = $expectedValue['condition'];

                $freshValue = $value->fresh();

                expect($freshValue)
                    ->toBeInstanceOf(StampCatalogValue::class);

                expect($freshValue->id)
                    ->toBe($value->id);

                expect($freshValue->stamp_catalog_entry_id)
                    ->toBe($entry->id);

                expect($freshValue->condition_id)
                    ->toBe($condition->id);

                expect($freshValue->catalog_value)
                    ->toBe($expectedValue['new_amount']);

                expect($freshValue->currency)
                    ->toBe($expectedEdition['currency']);

                expect($freshValue->needs_review)
                    ->toBeTrue();

                expect($freshValue->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalValues[$value->id]['updated_at']
                    );

                // Alleen de verwachte attributen mogen wijzigen.
                $expectedAttributes =
                    $originalValues[$value->id];

                foreach ([
                    'catalog_value',
                    'needs_review',
                    'updated_at',
                ] as $attribute) {
                    $expectedAttributes[$attribute] =
                        $freshValue->getRawOriginal($attribute);
                }

                expect($freshValue->getAttributes())
                    ->toBe($expectedAttributes);

                expect($freshValue->catalogEntry->id)
                    ->toBe($entry->id);

                expect($freshValue->catalogEntry->stamp_id)
                    ->toBe($this->stamp->id);

                expect($freshValue->condition->id)
                    ->toBe($condition->id);

                expect(
                    $freshEntry->values()
                        ->where('condition_id', $condition->id)
                        ->sole()
                        ->id
                )->toBe($value->id);

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $value->id,
                    'stamp_catalog_entry_id' => $entry->id,
                    'condition_id' => $condition->id,
                    'catalog_value' => (float) $expectedValue['new_amount'],
                    'currency' => $expectedEdition['currency'],
                    'needs_review' => true,
                ]);
            }
        }

        // -------------------------------------------------
        // Controleer de andere postzegel.
        // -------------------------------------------------

        $freshOtherStamp = $otherStamp->fresh();

        expect($freshOtherStamp)
            ->toBeInstanceOf(Stamp::class);

        // Alle attributen moeten volledig ongewijzigd blijven.
        expect($freshOtherStamp->getAttributes())
            ->toBe($originalOtherStamp);

        expect($freshOtherStamp->id)
            ->toBe($otherStamp->id);

        expect($freshOtherStamp->country_id)
            ->toBe($this->country->id);

        expect($freshOtherStamp->issue_year)
            ->toBe(2020);

        // Controleer de vertalingen.
        expect(
            $freshOtherStamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalOtherTranslations);

        // Controleer de themakoppelingen.
        expect(
            $freshOtherStamp->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalOtherThemes);

        // -------------------------------------------------
        // Controleer de andere catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($otherEntries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            // Inclusief catalogusnummer, notities en timestamps.
            expect($freshEntry->getAttributes())
                ->toBe($originalOtherEntries[$entry->id]);

            expect($freshEntry->stamp_id)
                ->toBe($otherStamp->id);

            expect($freshEntry->stamp->id)
                ->toBe($otherStamp->id);

            expect($freshEntry->catalog_edition_id)
                ->toBe(
                    $originalOtherEntries[$entry->id]['catalog_edition_id']
                );

            expect($freshEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer de andere cataloguswaarden.
        // -------------------------------------------------

        foreach ($otherValues as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            // Inclusief bedrag, valuta, notities,
            // controlemarkering en timestamps.
            expect($freshValue->getAttributes())
                ->toBe($originalOtherValues[$value->id]);

            expect($freshValue->catalogEntry->stamp_id)
                ->toBe($otherStamp->id);

            expect($freshValue->needs_review)
                ->toBe(
                    (bool) $originalOtherValues[$value->id]['needs_review']
                );

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer de gegevensisolatie van beide postzegels.
        // -------------------------------------------------

        foreach ([
            [$freshStamp, $entries, $values],
            [$freshOtherStamp, $otherEntries, $otherValues],
        ] as [$stamp, $stampEntries, $stampValues]) {
            expect($stamp->catalogEntries()->count())
                ->toBe(3);

            expect($stamp->catalogValues()->count())
                ->toBe(6);

            expect(
                $stamp->catalogEntries()
                    ->orderBy('id')
                    ->pluck('id')
                    ->all()
            )->toBe(
                collect($stampEntries)
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all()
            );

            expect(
                $stamp->catalogValues()
                    ->orderBy('stamp_catalog_values.id')
                    ->pluck('stamp_catalog_values.id')
                    ->all()
            )->toBe(
                collect($stampValues)
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all()
            );

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $stamp->id,
                'catalog_edition_id' => $this->editionC1->id,
            ]);
        }

        // Zes gewijzigde waarden van de eerste postzegel
        // en drie oorspronkelijke controlemarkeringen
        // van de andere postzegel moeten aanwezig zijn.
        $expectedReviewIds = collect([
            ...$values,
            $otherValueA1Postfris,
            $otherValueA2Gestempeld,
            $otherValueB1Postfris,
        ])->pluck('id')->sort()->values()->all();

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe($expectedReviewIds);

        // -------------------------------------------------
        // Controleer de geschiedenisbatchverdeling.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 9);

        $batches = CatalogChangeBatch::query()
            ->orderBy('id')
            ->get();

        expect($batches)->toHaveCount(3);

        expect($batches->pluck('catalog_edition_id')->all())
            ->toBe([
                $this->editionA1->id,
                $this->editionA2->id,
                $this->editionB1->id,
            ]);

        // -------------------------------------------------
        // Controleer iedere geschiedenisbatch.
        // -------------------------------------------------

        foreach ($expectedEditions as $index => $expectedEdition) {
            $batch = $batches[$index];

            $edition = $expectedEdition['edition'];

            $catalog = $expectedEdition['catalog'];

            $entry = $expectedEdition['entry'];

            expect($batch->catalog_edition_id)
                ->toBe($edition->id);

            expect($batch->user_id)
                ->toBe($this->user->id);

            expect($batch->user_name)
                ->toBe($this->user->name);

            expect($batch->operation)
                ->toBe('stamp_update');

            expect($batch->description)
                ->toBe(
                    'Catalogusgegevens bij het wijzigen van een postzegel'
                );

            expect($batch->catalog_name)
                ->toBe($catalog->name);

            expect($batch->catalog_edition_name)
                ->toBe($edition->edition_name);

            expect($batch->catalogEdition->id)
                ->toBe($edition->id);

            expect($batch->user->id)
                ->toBe($this->user->id);

            $logs = $batch->logs()
                ->orderBy('id')
                ->get();

            expect($logs)->toHaveCount(3);

            // Definieer het verwachte vermeldingslog.
            $expectedLogs = [
                [
                    'record_type' => 'stamp_catalog_entry',
                    'record_id' => $entry->id,
                    'condition_id' => null,
                    'old_values' => [
                        'catalog_number' => $expectedEdition['old_number'],
                    ],
                    'new_values' => [
                        'catalog_number' => $expectedEdition['new_number'],
                    ],
                ],
            ];

            // Definieer de verwachte waardelogs.
            foreach ($expectedEdition['values'] as $expectedValue) {
                $expectedLogs[] = [
                    'record_type' => 'stamp_catalog_value',
                    'record_id' => $expectedValue['model']->id,
                    'condition_id' => $expectedValue['condition']->id,
                    'old_values' => [
                        'catalog_value' => (float) $expectedValue['old_amount'],
                        'needs_review' => false,
                    ],
                    'new_values' => [
                        'catalog_value' => (float) $expectedValue['new_amount'],
                        'needs_review' => true,
                    ],
                ];
            }

            // Controleer ieder wijzigingslog afzonderlijk.
            foreach ($expectedLogs as $logIndex => $expectedLog) {
                $log = $logs[$logIndex];

                expect($log)
                    ->toBeInstanceOf(CatalogChangeLog::class);

                expect($log->catalog_change_batch_id)
                    ->toBe($batch->id);

                expect($log->catalog_edition_id)
                    ->toBe($edition->id);

                // Uitsluitend de eerste postzegel.
                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_id)
                    ->not->toBe($otherStamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->record_type)
                    ->toBe($expectedLog['record_type']);

                expect($log->record_id)
                    ->toBe($expectedLog['record_id']);

                expect($log->condition_id)
                    ->toBe($expectedLog['condition_id']);

                expect($log->action)
                    ->toBe('updated');

                // Controleer de relaties.
                expect($log->batch->id)
                    ->toBe($batch->id);

                expect($log->catalogEdition->id)
                    ->toBe($edition->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                if ($expectedLog['condition_id'] === null) {
                    expect($log->condition)
                        ->toBeNull();
                } else {
                    expect($log->condition->id)
                        ->toBe($expectedLog['condition_id']);
                }

                // Controleer de volledige historische waarden.
                foreach ([
                    'old_values',
                    'new_values',
                ] as $attribute) {
                    $loggedValues = $log->{$attribute};

                    $expectedValues = $expectedLog[$attribute];

                    expect($loggedValues)
                        ->toBeArray()
                        ->toHaveCount(count($expectedValues))
                        ->toHaveKeys(array_keys($expectedValues));

                    if (array_key_exists('catalog_value', $expectedValues)) {
                        expect(is_numeric($loggedValues['catalog_value']))
                            ->toBeTrue();

                        $loggedValues['catalog_value'] =
                            (float) $loggedValues['catalog_value'];
                    }

                    if (array_key_exists('needs_review', $expectedValues)) {
                        expect(
                            in_array(
                                $loggedValues['needs_review'],
                                [false, true, 0, 1],
                                true
                            )
                        )->toBeTrue();

                        $loggedValues['needs_review'] =
                            (bool) $loggedValues['needs_review'];
                    }

                    ksort($loggedValues);

                    ksort($expectedValues);

                    expect($loggedValues)
                        ->toBe($expectedValues);
                }

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $batch->id,
                    'catalog_edition_id' => $edition->id,
                    'stamp_id' => $this->stamp->id,
                    'stamp_series_id' => null,
                    'condition_id' => $expectedLog['condition_id'],
                    'record_type' => $expectedLog['record_type'],
                    'record_id' => $expectedLog['record_id'],
                    'action' => 'updated',
                ]);
            }

            // Geen enkel log binnen deze batch mag
            // aan de andere postzegel gekoppeld zijn.
            expect(
                $batch->logs()
                    ->where('stamp_id', $otherStamp->id)
                    ->exists()
            )->toBeFalse();
        }

        // -------------------------------------------------
        // Controleer de volledige historische gegevensisolatie.
        // -------------------------------------------------

        expect(
            CatalogChangeLog::query()
                ->where('stamp_id', $this->stamp->id)
                ->count()
        )->toBe(9);

        expect(
            CatalogChangeLog::query()
                ->where('stamp_id', $otherStamp->id)
                ->count()
        )->toBe(0);

        expect(
            CatalogChangeLog::query()
                ->distinct()
                ->pluck('stamp_id')
                ->all()
        )->toBe([$this->stamp->id]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $otherStamp->id,
        ]);

        // Exact de drie gewijzigde vermeldingen.
        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_entry')
                ->orderBy('record_id')
                ->pluck('record_id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Exact de zes gewijzigde waarden.
        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_value')
                ->orderBy('record_id')
                ->pluck('record_id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(9);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        expect(
            CatalogChangeBatch::query()
                ->whereDoesntHave('logs')
                ->exists()
        )->toBeFalse();

        // -------------------------------------------------
        // Controleer de overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 6);

        $this->assertDatabaseCount('stamp_catalog_values', 12);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 9);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert uitsluitend wijzigingsgeschiedenis voor de ingediende cataloguseditie en behoudt alle niet ingediende catalogusgegevens', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke postzegel.
    // -------------------------------------------------

    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] =
            $entry->fresh()->getAttributes();
    }

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] =
            $value->fresh()->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke landkoppelingen.
    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // Bewaar de relevante record-ID's.
    $changedEntryId = $this->entryA2->id;

    $changedValueId = $this->valueA2Postfris->id;

    $deletedValueId = $this->valueA2Gestempeld->id;

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryA2->catalog_number)
        ->toBe('A-200');

    expect($this->valueA2Postfris->catalog_value)
        ->toBe('20.00');

    expect($this->valueA2Gestempeld->catalog_value)
        ->toBe('10.00');

    // A1 en A2 behoren tot dezelfde catalogus.
    expect($this->editionA1->catalog_id)
        ->toBe($this->catalogA->id);

    expect($this->editionA2->catalog_id)
        ->toBe($this->catalogA->id);

    expect($this->editionA1->id)
        ->not->toBe($this->editionA2->id);

    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    // -------------------------------------------------
    // Stel de formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    // Dien uitsluitend cataloguseditie A2 in via route D.
    $payload['catalog_editions'] = [
        $this->editionA2->id => [
            'catalog_number' => 'A-250',
            'values' => [
                'postfris' => '24.75',
                'gestempeld' => '',
            ],
        ],
    ];

    // De overige invoerstructuren mogen niet aanwezig zijn.
    expect($payload)
        ->not->toHaveKey('catalog_edition_id')
        ->not->toHaveKey('catalog_number')
        ->not->toHaveKey('catalog_values')
        ->not->toHaveKey('catalog_entries');

    expect($payload['catalog_editions'])
        ->toHaveCount(1)
        ->toHaveKey($this->editionA2->id);

    foreach ([
        $this->editionA1,
        $this->editionB1,
        $this->editionC1,
    ] as $edition) {
        expect($payload['catalog_editions'])
            ->not->toHaveKey($edition->id);
    }

    expect($payload['country_id'])
        ->toBe($this->country->id);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // aan niet-ingediende records te detecteren.
    $this->travel(1)->minutes();

    try {
        // Verwerk uitsluitend de ingediende cataloguseditie.
        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // -------------------------------------------------
        // Controleer de algemene postzegelgegevens.
        // -------------------------------------------------

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)
            ->toBeInstanceOf(Stamp::class);

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer de gewijzigde catalogusvermelding A2.
        // -------------------------------------------------

        $freshEntryA2 = $this->entryA2->fresh();

        expect($freshEntryA2)
            ->toBeInstanceOf(StampCatalogEntry::class);

        expect($freshEntryA2->id)
            ->toBe($changedEntryId);

        expect($freshEntryA2->stamp_id)
            ->toBe($this->stamp->id);

        expect($freshEntryA2->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($freshEntryA2->catalog_number)
            ->toBe('A-250');

        expect($freshEntryA2->getRawOriginal('updated_at'))
            ->not->toBe(
                $originalEntries[$changedEntryId]['updated_at']
            );

        // Alleen catalog_number en updated_at mogen wijzigen.
        $expectedEntryAttributes =
            $originalEntries[$changedEntryId];

        $expectedEntryAttributes['catalog_number'] = 'A-250';

        $expectedEntryAttributes['updated_at'] =
            $freshEntryA2->getRawOriginal('updated_at');

        expect($freshEntryA2->getAttributes())
            ->toBe($expectedEntryAttributes);

        expect($freshEntryA2->stamp->id)
            ->toBe($this->stamp->id);

        expect($freshEntryA2->catalogEdition->id)
            ->toBe($this->editionA2->id);

        expect($freshEntryA2->values()->count())
            ->toBe(1);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $changedEntryId,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionA2->id,
            'catalog_number' => 'A-250',
        ]);

        // -------------------------------------------------
        // Controleer de gewijzigde postfrisse waarde.
        // -------------------------------------------------

        $freshPostfris = $this->valueA2Postfris->fresh();

        expect($freshPostfris)
            ->toBeInstanceOf(StampCatalogValue::class);

        expect($freshPostfris->id)
            ->toBe($changedValueId);

        expect($freshPostfris->stamp_catalog_entry_id)
            ->toBe($changedEntryId);

        expect($freshPostfris->condition_id)
            ->toBe($this->postfris->id);

        expect($freshPostfris->catalog_value)
            ->toBe('24.75');

        expect($freshPostfris->currency)
            ->toBe('EUR');

        expect($freshPostfris->needs_review)
            ->toBeTrue();

        expect($freshPostfris->getRawOriginal('updated_at'))
            ->not->toBe(
                $originalValues[$changedValueId]['updated_at']
            );

        // Alleen de verwachte attributen mogen wijzigen.
        $expectedValueAttributes =
            $originalValues[$changedValueId];

        foreach ([
            'catalog_value',
            'needs_review',
            'updated_at',
        ] as $attribute) {
            $expectedValueAttributes[$attribute] =
                $freshPostfris->getRawOriginal($attribute);
        }

        expect($freshPostfris->getAttributes())
            ->toBe($expectedValueAttributes);

        expect($freshPostfris->catalogEntry->id)
            ->toBe($changedEntryId);

        expect($freshPostfris->condition->id)
            ->toBe($this->postfris->id);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $changedValueId,
            'stamp_catalog_entry_id' => $changedEntryId,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 24.75,
            'currency' => 'EUR',
            'needs_review' => true,
        ]);

        // -------------------------------------------------
        // Controleer de verwijderde gestempelde waarde.
        // -------------------------------------------------

        expect($this->valueA2Gestempeld->fresh())
            ->toBeNull();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $deletedValueId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $changedEntryId,
            'condition_id' => $this->gestempeld->id,
        ]);

        // Alleen de postfrisse waarde mag overblijven.
        expect(
            $freshEntryA2->values()->sole()->id
        )->toBe($changedValueId);

        // -------------------------------------------------
        // Controleer de niet-ingediende catalogusvermeldingen.
        // -------------------------------------------------

        foreach ([
            $this->entryA1,
            $this->entryB1,
        ] as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            // Alle attributen moeten volledig ongewijzigd blijven.
            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer de niet-ingediende cataloguswaarden.
        // -------------------------------------------------

        foreach ([
            $this->valueA1Postfris,
            $this->valueA1Gestempeld,
            $this->valueB1Postfris,
            $this->valueB1Gestempeld,
        ] as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            // Inclusief bedrag, valuta, notities,
            // controlemarkering en timestamps.
            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            expect($freshValue->catalogEntry->stamp_id)
                ->toBe($this->stamp->id);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer de definitieve catalogusrelaties.
        // -------------------------------------------------

        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(5);

        // Alle drie oorspronkelijke vermeldingen blijven bestaan.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Alleen de verwijderde waarde mag ontbreken.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->reject(fn ($id) => $id === $deletedValueId)
                ->sort()
                ->values()
                ->all()
        );

        // Alleen de gewijzigde postfrisse waarde
        // mag voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe([$changedValueId]);

        // Er mag geen nieuwe koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // -------------------------------------------------
        // Controleer de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);

        $batch = CatalogChangeBatch::query()->sole();

        expect($batch)
            ->toBeInstanceOf(CatalogChangeBatch::class);

        expect($batch->catalog_edition_id)
            ->toBe($this->editionA2->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe('stamp_update');

        expect($batch->description)
            ->toBe(
                'Catalogusgegevens bij het wijzigen van een postzegel'
            );

        expect($batch->catalog_name)
            ->toBe($this->catalogA->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionA2->edition_name);

        expect($batch->catalogEdition->id)
            ->toBe($this->editionA2->id);

        expect($batch->catalogEdition->catalog->id)
            ->toBe($this->catalogA->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        expect($batch->logs()->count())
            ->toBe(3);

        $this->assertDatabaseHas('catalog_change_batches', [
            'id' => $batch->id,
            'catalog_edition_id' => $this->editionA2->id,
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'operation' => 'stamp_update',
            'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
            'catalog_name' => $this->catalogA->name,
            'catalog_edition_name' => $this->editionA2->edition_name,
        ]);

        // -------------------------------------------------
        // Definieer de drie verwachte wijzigingslogs.
        // -------------------------------------------------

        $expectedLogs = [
            [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $changedEntryId,
                'condition_id' => null,
                'action' => 'updated',
                'old_values' => [
                    'catalog_number' => 'A-200',
                ],
                'new_values' => [
                    'catalog_number' => 'A-250',
                ],
            ],
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $changedValueId,
                'condition_id' => $this->postfris->id,
                'action' => 'updated',
                'old_values' => [
                    'catalog_value' => 20.00,
                    'needs_review' => false,
                ],
                'new_values' => [
                    'catalog_value' => 24.75,
                    'needs_review' => true,
                ],
            ],
            [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $deletedValueId,
                'condition_id' => $this->gestempeld->id,
                'action' => 'deleted',
                'old_values' => $originalValues[$deletedValueId],
                'new_values' => null,
            ],
        ];

        $logs = $batch->logs()
            ->orderBy('id')
            ->get();

        expect($logs)->toHaveCount(3);

        // -------------------------------------------------
        // Controleer ieder wijzigingslog afzonderlijk.
        // -------------------------------------------------

        foreach ($expectedLogs as $index => $expectedLog) {
            $log = $logs[$index];

            expect($log)
                ->toBeInstanceOf(CatalogChangeLog::class);

            expect($log->catalog_change_batch_id)
                ->toBe($batch->id);

            expect($log->catalog_edition_id)
                ->toBe($this->editionA2->id);

            expect($log->stamp_id)
                ->toBe($this->stamp->id);

            expect($log->stamp_series_id)
                ->toBeNull();

            expect($log->record_type)
                ->toBe($expectedLog['record_type']);

            expect($log->record_id)
                ->toBe($expectedLog['record_id']);

            expect($log->condition_id)
                ->toBe($expectedLog['condition_id']);

            expect($log->action)
                ->toBe($expectedLog['action']);

            // Controleer de relaties.
            expect($log->batch->id)
                ->toBe($batch->id);

            expect($log->catalogEdition->id)
                ->toBe($this->editionA2->id);

            expect($log->stamp->id)
                ->toBe($this->stamp->id);

            expect($log->stampSeries)
                ->toBeNull();

            if ($expectedLog['condition_id'] === null) {
                expect($log->condition)
                    ->toBeNull();
            } else {
                expect($log->condition->id)
                    ->toBe($expectedLog['condition_id']);
            }

            // Controleer alle historische attributen.
            foreach ([
                'old_values',
                'new_values',
            ] as $attribute) {
                $loggedValues = $log->{$attribute};

                $expectedValues = $expectedLog[$attribute];

                // Een verwijderingslog heeft geen nieuwe waarden.
                if ($expectedValues === null) {
                    expect($loggedValues)->toBeNull();

                    continue;
                }

                expect($loggedValues)
                    ->toBeArray()
                    ->toHaveCount(count($expectedValues))
                    ->toHaveKeys(array_keys($expectedValues));

                // Normaliseer numerieke cataloguswaarden.
                if (array_key_exists('catalog_value', $expectedValues)) {
                    expect(is_numeric($loggedValues['catalog_value']))
                        ->toBeTrue();

                    $loggedValues['catalog_value'] =
                        (float) $loggedValues['catalog_value'];

                    $expectedValues['catalog_value'] =
                        (float) $expectedValues['catalog_value'];
                }

                // Normaliseer de controlemarkering.
                if (array_key_exists('needs_review', $expectedValues)) {
                    expect(
                        in_array(
                            $loggedValues['needs_review'],
                            [false, true, 0, 1],
                            true
                        )
                    )->toBeTrue();

                    $loggedValues['needs_review'] =
                        (bool) $loggedValues['needs_review'];

                    $expectedValues['needs_review'] =
                        (bool) $expectedValues['needs_review'];
                }

                // De volgorde van JSON-attributen is niet relevant.
                ksort($loggedValues);

                ksort($expectedValues);

                expect($loggedValues)
                    ->toBe($expectedValues);
            }

            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $log->id,
                'catalog_change_batch_id' => $batch->id,
                'catalog_edition_id' => $this->editionA2->id,
                'stamp_id' => $this->stamp->id,
                'stamp_series_id' => null,
                'condition_id' => $expectedLog['condition_id'],
                'record_type' => $expectedLog['record_type'],
                'record_id' => $expectedLog['record_id'],
                'action' => $expectedLog['action'],
            ]);
        }

        // -------------------------------------------------
        // Controleer de historische verwijderingsgegevens.
        // -------------------------------------------------

        $deletedLog = $logs[2];

        expect($deletedLog->record_id)
            ->toBe($deletedValueId);

        expect($deletedLog->old_values['id'])
            ->toBe($deletedValueId);

        expect($deletedLog->old_values['stamp_catalog_entry_id'])
            ->toBe($changedEntryId);

        expect($deletedLog->old_values['condition_id'])
            ->toBe($this->gestempeld->id);

        expect((float) $deletedLog->old_values['catalog_value'])
            ->toBe(10.00);

        expect($deletedLog->old_values['currency'])
            ->toBe('EUR');

        expect($deletedLog->new_values)
            ->toBeNull();

        // Het historische record-ID blijft behouden,
        // hoewel het oorspronkelijke record niet meer bestaat.
        expect(
            StampCatalogValue::query()
                ->whereKey($deletedLog->record_id)
                ->exists()
        )->toBeFalse();

        // -------------------------------------------------
        // Controleer de totale geschiedenisverdeling.
        // -------------------------------------------------

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(2);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'deleted')
                ->count()
        )->toBe(1);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_entry')
                ->count()
        )->toBe(1);

        expect(
            CatalogChangeLog::query()
                ->where('record_type', 'stamp_catalog_value')
                ->count()
        )->toBe(2);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        // Uitsluitend A2 mag wijzigingsgeschiedenis bevatten.
        expect(
            CatalogChangeLog::query()
                ->distinct()
                ->pluck('catalog_edition_id')
                ->all()
        )->toBe([$this->editionA2->id]);

        foreach ([
            $this->editionA1,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Er mogen geen lege geschiedenisbatches achterblijven.
        expect(
            CatalogChangeBatch::query()
                ->whereDoesntHave('logs')
                ->exists()
        )->toBeFalse();

        // -------------------------------------------------
        // Controleer de overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('de backend wijzigt algemene postzegelgegevens zonder catalogusgeschiedenis te registreren', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke postzegelgegevens.
    // -------------------------------------------------

    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] =
            $entry->fresh()->getAttributes();
    }

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] =
            $value->fresh()->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke condities.
    $originalConditions = Condition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($condition) => $condition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke landkoppelingen.
    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    expect($this->stamp->catalogValues()->count())
        ->toBe(6);

    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    // -------------------------------------------------
    // Stel uitsluitend algemene postzegelgegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde algemene postzegelgegevens',
        'description' => 'Nieuwe algemene beschrijving van de postzegel',
    ];

    $payload['face_value'] = '2.50';

    $payload['currency'] = 'EUR';

    $payload['designer'] = 'Nieuwe testontwerper';

    $payload['printing_method'] = 'Offsetdruk';

    $payload['notes'] = 'Gewijzigde algemene postzegelnotities';

    // Het land en uitgiftejaar blijven ongewijzigd.
    expect($payload['country_id'])
        ->toBe($this->country->id);

    expect($payload['issue_year'])
        ->toBe($this->stamp->issue_year);

    // Geen enkele catalogusinvoerstructuur mag aanwezig zijn.
    foreach ([
        'catalog_id',
        'catalog_edition_id',
        'catalog_number',
        'catalog_values',
        'catalog_entries',
        'catalog_editions',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // Controleer dat daadwerkelijk een naamwijziging plaatsvindt.
    expect($originalStamp['name'])
        ->not->toBe($payload['translations']['nl']['name']);

    // Verplaats de tijd om timestampwijzigingen te detecteren.
    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Verwerk de algemene postzegelgegevens.
        // -------------------------------------------------

        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        $response->assertSessionHasNoErrors();

        $response->assertRedirect(
            route('backend.stamps.index')
        );

        $response->assertSessionHas('success');

        // -------------------------------------------------
        // Controleer de gewijzigde postzegel.
        // -------------------------------------------------

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)
            ->toBeInstanceOf(Stamp::class);

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->country->id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->toBe('Gewijzigde algemene postzegelgegevens');

        expect($freshStamp->description)
            ->toBe('Nieuwe algemene beschrijving van de postzegel');

        expect($freshStamp->face_value)
            ->toBe('2.50');

        expect($freshStamp->currency)
            ->toBe('EUR');

        expect($freshStamp->designer)
            ->toBe('Nieuwe testontwerper');

        expect($freshStamp->printing_method)
            ->toBe('Offsetdruk');

        expect($freshStamp->notes)
            ->toBe('Gewijzigde algemene postzegelnotities');

        expect($freshStamp->getRawOriginal('created_at'))
            ->toBe($originalStamp['created_at']);

        expect($freshStamp->getRawOriginal('updated_at'))
            ->not->toBe($originalStamp['updated_at']);

        // Controleer de opgeslagen algemene postzegelgegevens.
        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
            'issue_year' => 2020,
            'name' => 'Gewijzigde algemene postzegelgegevens',
            'description' => 'Nieuwe algemene beschrijving van de postzegel',
            'face_value' => '2.50',
            'currency' => 'EUR',
            'designer' => 'Nieuwe testontwerper',
            'printing_method' => 'Offsetdruk',
            'notes' => 'Gewijzigde algemene postzegelnotities',
        ]);

        // -------------------------------------------------
        // Controleer de Nederlandse vertaling.
        // -------------------------------------------------

        $translation = $freshStamp->translations()
            ->where('locale', 'nl')
            ->sole();

        expect($translation->stamp_id)
            ->toBe($this->stamp->id);

        expect($translation->locale)
            ->toBe('nl');

        expect($translation->name)
            ->toBe('Gewijzigde algemene postzegelgegevens');

        expect($translation->description)
            ->toBe('Nieuwe algemene beschrijving van de postzegel');

        $this->assertDatabaseHas('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => 'Gewijzigde algemene postzegelgegevens',
            'description' => 'Nieuwe algemene beschrijving van de postzegel',
        ]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            expect($freshEntry->id)
                ->toBe($entry->id);

            // Alle attributen moeten volledig ongewijzigd blijven.
            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->stamp->id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalogEdition->id)
                ->toBe(
                    $originalEntries[$entry->id]['catalog_edition_id']
                );

            expect($freshEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($value->id);

            // Inclusief bedrag, valuta, notities,
            // controlemarkering en timestamps.
            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            expect($freshValue->catalogEntry->id)
                ->toBe(
                    $originalValues[$value->id]['stamp_catalog_entry_id']
                );

            expect($freshValue->catalogEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshValue->condition->id)
                ->toBe(
                    $originalValues[$value->id]['condition_id']
                );

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer de definitieve catalogusrelaties.
        // -------------------------------------------------

        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        // Exact dezelfde catalogusvermeldingen.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Exact dezelfde cataloguswaarden.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen nieuwe koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Geen enkele cataloguswaarde mag voor controle
        // worden gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer dat geen wijzigingsgeschiedenis bestaat.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            CatalogChangeBatch::query()->exists()
        )->toBeFalse();

        expect(
            CatalogChangeLog::query()->exists()
        )->toBeFalse();

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        $this->assertDatabaseMissing('catalog_change_batches', [
            'operation' => 'stamp_update',
        ]);

        // -------------------------------------------------
        // Controleer de overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        expect(
            Condition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($condition) => $condition->getAttributes())
                ->all()
        )->toBe($originalConditions);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('de backend registreert geen dubbele wijzigingsgeschiedenis bij het herhaald opslaan van dezelfde catalogusgegevens via routes B C en D', function () {
    // -------------------------------------------------
    // Verzamel de oorspronkelijke catalogusvermeldingen.
    // -------------------------------------------------

    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] =
            $entry->fresh()->getAttributes();
    }

    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] =
            $value->fresh()->getAttributes();
    }

    // Bewaar de oorspronkelijke algemene catalogusgegevens.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    $originalConditions = Condition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($condition) => $condition->getAttributes())
        ->all();

    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    // -------------------------------------------------
    // Stel de formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

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

    // Definieer de verwachte wijzigingen.
    $expectedEditions = [
        [
            'edition' => $this->editionA1,
            'catalog' => $this->catalogA,
            'entry' => $this->entryA1,
            'old_number' => 'A-100',
            'new_number' => 'A-101',
            'currency' => 'EUR',
            'values' => [
                [
                    'model' => $this->valueA1Postfris,
                    'condition' => $this->postfris,
                    'old_amount' => '15.00',
                    'new_amount' => '16.50',
                ],
                [
                    'model' => $this->valueA1Gestempeld,
                    'condition' => $this->gestempeld,
                    'old_amount' => '7.50',
                    'new_amount' => '8.25',
                ],
            ],
        ],
        [
            'edition' => $this->editionA2,
            'catalog' => $this->catalogA,
            'entry' => $this->entryA2,
            'old_number' => 'A-200',
            'new_number' => 'A-201',
            'currency' => 'EUR',
            'values' => [
                [
                    'model' => $this->valueA2Postfris,
                    'condition' => $this->postfris,
                    'old_amount' => '20.00',
                    'new_amount' => '22.50',
                ],
                [
                    'model' => $this->valueA2Gestempeld,
                    'condition' => $this->gestempeld,
                    'old_amount' => '10.00',
                    'new_amount' => '11.25',
                ],
            ],
        ],
        [
            'edition' => $this->editionB1,
            'catalog' => $this->catalogB,
            'entry' => $this->entryB1,
            'old_number' => 'B-100',
            'new_number' => 'B-101',
            'currency' => 'USD',
            'values' => [
                [
                    'model' => $this->valueB1Postfris,
                    'condition' => $this->postfris,
                    'old_amount' => '25.00',
                    'new_amount' => '30.75',
                ],
                [
                    'model' => $this->valueB1Gestempeld,
                    'condition' => $this->gestempeld,
                    'old_amount' => '12.50',
                    'new_amount' => '15.50',
                ],
            ],
        ],
    ];

    // Verplaats de tijd voor de eerste aanvraag.
    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Eerste HTTP-aanvraag: daadwerkelijke wijzigingen.
        // -------------------------------------------------

        $firstResponse = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        $firstResponse->assertSessionHasNoErrors();

        $firstResponse->assertRedirect(
            route('backend.stamps.index')
        );

        $firstResponse->assertSessionHas('success');

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp->name)
            ->toBe('Gewijzigde postzegel catalogusgeschiedenis');

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        // -------------------------------------------------
        // Controleer de eerste geschiedenisregistratie.
        // -------------------------------------------------

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 9);

        expect($this->history->getActiveBatch())
            ->toBeNull();

        foreach ($expectedEditions as $expectedEdition) {
            $edition = $expectedEdition['edition'];

            $catalog = $expectedEdition['catalog'];

            $entry = $expectedEdition['entry'];

            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            expect($freshEntry->id)
                ->toBe($entry->id);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalog_edition_id)
                ->toBe($edition->id);

            expect($freshEntry->catalog_number)
                ->toBe($expectedEdition['new_number']);

            expect($freshEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$entry->id]['updated_at']
                );

            // Alleen catalog_number en updated_at mogen wijzigen.
            $expectedAttributes = $originalEntries[$entry->id];

            $expectedAttributes['catalog_number'] =
                $expectedEdition['new_number'];

            $expectedAttributes['updated_at'] =
                $freshEntry->getRawOriginal('updated_at');

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->values()->count())
                ->toBe(2);

            // Controleer de gewijzigde cataloguswaarden.
            foreach ($expectedEdition['values'] as $expectedValue) {
                $value = $expectedValue['model'];

                $condition = $expectedValue['condition'];

                $freshValue = $value->fresh();

                expect($freshValue)
                    ->toBeInstanceOf(StampCatalogValue::class);

                expect($freshValue->id)
                    ->toBe($value->id);

                expect($freshValue->stamp_catalog_entry_id)
                    ->toBe($entry->id);

                expect($freshValue->condition_id)
                    ->toBe($condition->id);

                expect($freshValue->catalog_value)
                    ->toBe($expectedValue['new_amount']);

                expect($freshValue->currency)
                    ->toBe($expectedEdition['currency']);

                expect($freshValue->needs_review)
                    ->toBeTrue();

                expect($freshValue->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalValues[$value->id]['updated_at']
                    );

                $expectedAttributes =
                    $originalValues[$value->id];

                foreach ([
                    'catalog_value',
                    'needs_review',
                    'updated_at',
                ] as $attribute) {
                    $expectedAttributes[$attribute] =
                        $freshValue->getRawOriginal($attribute);
                }

                expect($freshValue->getAttributes())
                    ->toBe($expectedAttributes);
            }

            // -------------------------------------------------
            // Controleer de geschiedenisbatch per editie.
            // -------------------------------------------------

            $batch = CatalogChangeBatch::query()
                ->where('catalog_edition_id', $edition->id)
                ->sole();

            expect($batch->catalog_edition_id)
                ->toBe($edition->id);

            expect($batch->user_id)
                ->toBe($this->user->id);

            expect($batch->user_name)
                ->toBe($this->user->name);

            expect($batch->operation)
                ->toBe('stamp_update');

            expect($batch->description)
                ->toBe(
                    'Catalogusgegevens bij het wijzigen van een postzegel'
                );

            expect($batch->catalog_name)
                ->toBe($catalog->name);

            expect($batch->catalog_edition_name)
                ->toBe($edition->edition_name);

            expect($batch->catalogEdition->id)
                ->toBe($edition->id);

            expect($batch->user->id)
                ->toBe($this->user->id);

            $logs = $batch->logs()
                ->orderBy('id')
                ->get();

            expect($logs)->toHaveCount(3);

            // Definieer het verwachte vermeldingslog.
            $expectedLogs = [
                [
                    'record_type' => 'stamp_catalog_entry',
                    'record_id' => $entry->id,
                    'condition_id' => null,
                    'old_values' => [
                        'catalog_number' => $expectedEdition['old_number'],
                    ],
                    'new_values' => [
                        'catalog_number' => $expectedEdition['new_number'],
                    ],
                ],
            ];

            // Definieer de twee verwachte waardelogs.
            foreach ($expectedEdition['values'] as $expectedValue) {
                $expectedLogs[] = [
                    'record_type' => 'stamp_catalog_value',
                    'record_id' => $expectedValue['model']->id,
                    'condition_id' => $expectedValue['condition']->id,
                    'old_values' => [
                        'catalog_value' => (float) $expectedValue['old_amount'],
                        'needs_review' => false,
                    ],
                    'new_values' => [
                        'catalog_value' => (float) $expectedValue['new_amount'],
                        'needs_review' => true,
                    ],
                ];
            }

            // Controleer ieder wijzigingslog.
            foreach ($expectedLogs as $index => $expectedLog) {
                $log = $logs[$index];

                expect($log)
                    ->toBeInstanceOf(CatalogChangeLog::class);

                expect($log->catalog_change_batch_id)
                    ->toBe($batch->id);

                expect($log->catalog_edition_id)
                    ->toBe($edition->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->record_type)
                    ->toBe($expectedLog['record_type']);

                expect($log->record_id)
                    ->toBe($expectedLog['record_id']);

                expect($log->condition_id)
                    ->toBe($expectedLog['condition_id']);

                expect($log->action)
                    ->toBe('updated');

                expect($log->batch->id)
                    ->toBe($batch->id);

                expect($log->catalogEdition->id)
                    ->toBe($edition->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                if ($expectedLog['condition_id'] === null) {
                    expect($log->condition)
                        ->toBeNull();
                } else {
                    expect($log->condition->id)
                        ->toBe($expectedLog['condition_id']);
                }

                foreach ([
                    'old_values',
                    'new_values',
                ] as $attribute) {
                    $loggedValues = $log->{$attribute};

                    $expectedValues = $expectedLog[$attribute];

                    expect($loggedValues)
                        ->toBeArray()
                        ->toHaveCount(count($expectedValues))
                        ->toHaveKeys(array_keys($expectedValues));

                    if (array_key_exists('catalog_value', $expectedValues)) {
                        expect(is_numeric($loggedValues['catalog_value']))
                            ->toBeTrue();

                        $loggedValues['catalog_value'] =
                            (float) $loggedValues['catalog_value'];
                    }

                    if (array_key_exists('needs_review', $expectedValues)) {
                        expect(
                            in_array(
                                $loggedValues['needs_review'],
                                [false, true, 0, 1],
                                true
                            )
                        )->toBeTrue();

                        $loggedValues['needs_review'] =
                            (bool) $loggedValues['needs_review'];
                    }

                    ksort($loggedValues);

                    ksort($expectedValues);

                    expect($loggedValues)
                        ->toBe($expectedValues);
                }
            }
        }

        // -------------------------------------------------
        // Bewaar de volledige toestand na de eerste aanvraag.
        // -------------------------------------------------

        $firstStamp = $this->stamp
            ->fresh()
            ->getAttributes();

        $firstTranslations = $this->stamp->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $firstEntries = [];

        foreach ($entries as $entry) {
            $firstEntries[$entry->id] =
                $entry->fresh()->getAttributes();
        }

        $firstValues = [];

        foreach ($values as $value) {
            $firstValues[$value->id] =
                $value->fresh()->getAttributes();
        }

        // Bewaar alle batches, inclusief timestamps en snapshots.
        $firstBatches = CatalogChangeBatch::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($batch) => $batch->getAttributes())
            ->all();

        // Bewaar alle logs, inclusief historische JSON-gegevens.
        $firstLogs = CatalogChangeLog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($log) => $log->getAttributes())
            ->all();

        expect($firstBatches)->toHaveCount(3);

        expect($firstLogs)->toHaveCount(9);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(6);

        // Verplaats de tijd opnieuw om eventuele wijzigingen
        // aan bestaande geschiedenisrecords te detecteren.
        $this->travel(2)->minutes();

        // -------------------------------------------------
        // Tweede HTTP-aanvraag: exact dezelfde formuliergegevens.
        // -------------------------------------------------

        $secondResponse = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        $secondResponse->assertSessionHasNoErrors();

        $secondResponse->assertRedirect(
            route('backend.stamps.index')
        );

        $secondResponse->assertSessionHas('success');

        // -------------------------------------------------
        // Controleer de algemene postzegelgegevens.
        // -------------------------------------------------

        $secondStamp = $this->stamp->fresh();

        $expectedStamp = $firstStamp;

        // Een normale save mag updated_at opnieuw aanpassen.
        $expectedStamp['updated_at'] =
            $secondStamp->getRawOriginal('updated_at');

        expect($secondStamp->getAttributes())
            ->toBe($expectedStamp);

        // Controleer de vertalingen, afgezien van updated_at.
        $secondTranslations = $secondStamp->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        expect($secondTranslations)
            ->toHaveCount(count($firstTranslations));

        foreach ($secondTranslations as $index => $translation) {
            $expectedTranslation = $firstTranslations[$index];

            $expectedTranslation['updated_at'] =
                $translation['updated_at'];

            expect($translation)
                ->toBe($expectedTranslation);
        }

        // -------------------------------------------------
        // Controleer alle catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            expect($freshEntry->id)
                ->toBe($entry->id);

            $expectedAttributes = $firstEntries[$entry->id];

            // updated_at mag door een normale save wijzigen.
            $expectedAttributes['updated_at'] =
                $freshEntry->getRawOriginal('updated_at');

            expect($freshEntry->getAttributes())
                ->toBe($expectedAttributes);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        // -------------------------------------------------
        // Controleer alle cataloguswaarden.
        // -------------------------------------------------

        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($value->id);

            $expectedAttributes = $firstValues[$value->id];

            // updated_at mag door een normale save wijzigen.
            $expectedAttributes['updated_at'] =
                $freshValue->getRawOriginal('updated_at');

            expect($freshValue->getAttributes())
                ->toBe($expectedAttributes);

            // De bestaande controlemarkering moet behouden blijven.
            expect($freshValue->needs_review)
                ->toBeTrue();

            expect($freshValue->catalogEntry->stamp_id)
                ->toBe($this->stamp->id);
        }

        // -------------------------------------------------
        // Controleer dat geen dubbele geschiedenis bestaat.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 9);

        $secondBatches = CatalogChangeBatch::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($batch) => $batch->getAttributes())
            ->all();

        $secondLogs = CatalogChangeLog::query()
            ->orderBy('id')
            ->get()
            ->map(fn ($log) => $log->getAttributes())
            ->all();

        // Alle oorspronkelijke batches moeten exact gelijk zijn.
        // Ook de timestamps mogen niet zijn gewijzigd.
        expect($secondBatches)
            ->toBe($firstBatches);

        // Alle oorspronkelijke logs moeten exact gelijk zijn.
        // Inclusief record-ID's en historische JSON-gegevens.
        expect($secondLogs)
            ->toBe($firstLogs);

        // -------------------------------------------------
        // Controleer de definitieve geschiedenisverdeling.
        // -------------------------------------------------

        foreach ($expectedEditions as $expectedEdition) {
            $edition = $expectedEdition['edition'];

            $batch = CatalogChangeBatch::query()
                ->where('catalog_edition_id', $edition->id)
                ->sole();

            expect($batch->logs()->count())
                ->toBe(3);

            expect(
                $batch->logs()
                    ->where('action', 'updated')
                    ->count()
            )->toBe(3);

            expect(
                $batch->logs()
                    ->where('record_type', 'stamp_catalog_entry')
                    ->count()
            )->toBe(1);

            expect(
                $batch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->count()
            )->toBe(2);
        }

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(9);

        expect(
            CatalogChangeLog::query()
                ->where('stamp_id', $this->stamp->id)
                ->count()
        )->toBe(9);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'created',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'action' => 'deleted',
        ]);

        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('catalog_change_batches', [
            'user_id' => $this->otherUser->id,
        ]);

        // Er mogen geen lege geschiedenisbatches achterblijven.
        expect(
            CatalogChangeBatch::query()
                ->whereDoesntHave('logs')
                ->exists()
        )->toBeFalse();

        // -------------------------------------------------
        // Controleer de definitieve catalogusrelaties.
        // -------------------------------------------------

        expect($secondStamp->catalogEntries()->count())
            ->toBe(3);

        expect($secondStamp->catalogValues()->count())
            ->toBe(6);

        expect(
            $secondStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        expect(
            $secondStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // -------------------------------------------------
        // Controleer alle overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        expect(
            Condition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($condition) => $condition->getAttributes())
                ->all()
        )->toBe($originalConditions);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 9);

        // Ook na de tweede aanvraag mag geen actieve
        // geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('de backend weigert een ongeldige cataloguswaarde zonder gegevens te wijzigen of wijzigingsgeschiedenis te registreren', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke postzegelgegevens.
    // -------------------------------------------------

    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalTranslations = $this->stamp->translations()
        ->orderBy('id')
        ->get()
        ->map(fn ($translation) => $translation->getAttributes())
        ->all();

    $originalThemes = $this->stamp->themes()
        ->orderBy('themes.id')
        ->pluck('themes.id')
        ->all();

    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] =
            $entry->fresh()->getAttributes();
    }

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] =
            $value->fresh()->getAttributes();
    }

    // Bewaar de oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke condities.
    $originalConditions = Condition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($condition) => $condition->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke landkoppelingen.
    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    // -------------------------------------------------
    // Stel de formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    // Dien ook gewijzigde algemene postzegelgegevens in.
    $payload['translations']['nl'] = [
        'name' => 'Ongeldige aanvraag catalogusgeschiedenis',
        'description' => 'Deze beschrijving mag niet worden opgeslagen',
    ];

    $payload['designer'] = 'Deze ontwerper mag niet worden opgeslagen';

    $payload['notes'] = 'Deze notities mogen niet worden opgeslagen';

    // Route B: uitsluitend geldige catalogusgegevens.
    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-101';

    $payload['catalog_values'] = [
        'postfris' => '16.50',
        'gestempeld' => '8.25',
    ];

    // Route C: uitsluitend geldige catalogusgegevens.
    $payload['catalog_entries'] = [
        $this->entryA2->id => [
            'catalog_number' => 'A-201',
            'values' => [
                'postfris' => '22.50',
                'gestempeld' => '11.25',
            ],
        ],
    ];

    // Route D: één ongeldige negatieve cataloguswaarde.
    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-101',
            'values' => [
                'postfris' => '30.75',
                'gestempeld' => '-15.50',
            ],
        ],
    ];

    $invalidField =
        'catalog_editions.'.$this->editionB1->id.'.values.gestempeld';

    // Controleer dat de aanvraag daadwerkelijk wijzigingen bevat.
    expect($payload['translations']['nl']['name'])
        ->not->toBe($originalStamp['name']);

    expect($payload['catalog_number'])
        ->not->toBe($this->entryA1->catalog_number);

    expect(
        $payload['catalog_entries'][$this->entryA2->id]['catalog_number']
    )->not->toBe($this->entryA2->catalog_number);

    expect(
        $payload['catalog_editions'][$this->editionB1->id]['catalog_number']
    )->not->toBe($this->entryB1->catalog_number);

    expect(
        (float) $payload['catalog_editions'][$this->editionB1->id]['values']['gestempeld']
    )->toBeLessThan(0);

    $editUrl = route('backend.stamps.edit', $this->stamp);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Verwerk de ongeldige HTTP-aanvraag.
        // -------------------------------------------------

        $response = $this
            ->from($editUrl)
            ->put(
                route('backend.stamps.update', $this->stamp),
                $payload
            );

        // De gebruiker moet terugkeren naar het bewerkformulier.
        $response->assertRedirect($editUrl);

        // Uitsluitend de negatieve cataloguswaarde is ongeldig.
        $response->assertSessionHasErrors([
            $invalidField,
        ]);

        expect(session('errors')->getBag('default')->keys())
            ->toBe([$invalidField]);

        $response->assertSessionMissing('success');

        // -------------------------------------------------
        // Controleer de algemene postzegelgegevens.
        // -------------------------------------------------

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)
            ->toBeInstanceOf(Stamp::class);

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        // Alle attributen moeten exact gelijk zijn gebleven.
        // Ook created_at en updated_at mogen niet wijzigen.
        expect($freshStamp->getAttributes())
            ->toBe($originalStamp);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->not->toBe($payload['translations']['nl']['name']);

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer de oorspronkelijke vertalingen.
        // -------------------------------------------------

        expect(
            $freshStamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalTranslations);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // Controleer de oorspronkelijke themakoppelingen.
        expect(
            $freshStamp->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalThemes);

        // -------------------------------------------------
        // Controleer alle catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            expect($freshEntry->id)
                ->toBe($entry->id);

            // Inclusief catalogusnummer, notities en timestamps.
            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->stamp->id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalogEdition->id)
                ->toBe(
                    $originalEntries[$entry->id]['catalog_edition_id']
                );

            expect($freshEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Controleer expliciet de oorspronkelijke catalogusnummers.
        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // Geen enkel ingediend catalogusnummer mag zijn opgeslagen.
        foreach ([
            'A-101',
            'A-201',
            'B-101',
        ] as $catalogNumber) {
            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $this->stamp->id,
                'catalog_number' => $catalogNumber,
            ]);
        }

        // -------------------------------------------------
        // Controleer alle cataloguswaarden.
        // -------------------------------------------------

        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($value->id);

            // Inclusief bedrag, valuta, notities,
            // controlemarkering en timestamps.
            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            expect($freshValue->catalogEntry->id)
                ->toBe(
                    $originalValues[$value->id]['stamp_catalog_entry_id']
                );

            expect($freshValue->catalogEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshValue->condition->id)
                ->toBe(
                    $originalValues[$value->id]['condition_id']
                );

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer expliciet alle oorspronkelijke bedragen.
        expect($this->valueA1Postfris->fresh()->catalog_value)
            ->toBe('15.00');

        expect($this->valueA1Gestempeld->fresh()->catalog_value)
            ->toBe('7.50');

        expect($this->valueA2Postfris->fresh()->catalog_value)
            ->toBe('20.00');

        expect($this->valueA2Gestempeld->fresh()->catalog_value)
            ->toBe('10.00');

        expect($this->valueB1Postfris->fresh()->catalog_value)
            ->toBe('25.00');

        expect($this->valueB1Gestempeld->fresh()->catalog_value)
            ->toBe('12.50');

        // Er mogen geen negatieve cataloguswaarden bestaan.
        expect(
            StampCatalogValue::query()
                ->where('catalog_value', '<', 0)
                ->exists()
        )->toBeFalse();

        // Geen enkele waarde mag voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de definitieve catalogusrelaties.
        // -------------------------------------------------

        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // Er mag geen nieuwe catalogusvermelding ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // -------------------------------------------------
        // Controleer dat geen wijzigingsgeschiedenis bestaat.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            CatalogChangeBatch::query()->exists()
        )->toBeFalse();

        expect(
            CatalogChangeLog::query()->exists()
        )->toBeFalse();

        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        $this->assertDatabaseMissing('catalog_change_batches', [
            'operation' => 'stamp_update',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Controleer de overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        expect(
            Condition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($condition) => $condition->getAttributes())
                ->all()
        )->toBe($originalConditions);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('de backend weigert een ongeldige cataloguseditie zonder gegevens te wijzigen of wijzigingsgeschiedenis te registreren', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke postzegelgegevens.
    // -------------------------------------------------

    $originalStamp = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalTranslations = $this->stamp->translations()
        ->orderBy('id')
        ->get()
        ->map(fn ($translation) => $translation->getAttributes())
        ->all();

    $originalThemes = $this->stamp->themes()
        ->orderBy('themes.id')
        ->pluck('themes.id')
        ->all();

    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ];

    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
    ];

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] =
            $entry->fresh()->getAttributes();
    }

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] =
            $value->fresh()->getAttributes();
    }

    // Bewaar alle oorspronkelijke catalogi.
    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke catalogusedities.
    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke waarderingsregels.
    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke condities.
    $originalConditions = Condition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($condition) => $condition->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke landkoppelingen.
    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Controleer de geldigheid van de catalogusrelaties.
    // -------------------------------------------------

    expect($this->stamp->country_id)
        ->toBe($this->country->id);

    expect($this->country->id)
        ->not->toBe($this->otherCountry->id);

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    // Catalogus C is niet beschikbaar voor het huidige land.
    expect(
        $this->catalogC->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeFalse();

    // Catalogus C is wel beschikbaar voor het andere land.
    expect(
        $this->catalogC->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    expect($this->editionC1->catalog_id)
        ->toBe($this->catalogC->id);

    expect($this->editionC1->catalog->id)
        ->toBe($this->catalogC->id);

    // De ongeldige cataloguseditie bestaat daadwerkelijk.
    $this->assertDatabaseHas('catalog_editions', [
        'id' => $this->editionC1->id,
        'catalog_id' => $this->catalogC->id,
    ]);

    // Er bestaat nog geen koppeling met deze cataloguseditie.
    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    foreach ($values as $value) {
        expect($value->needs_review)
            ->toBeFalse();
    }

    // -------------------------------------------------
    // Stel de formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['translations']['nl'] = [
        'name' => 'Aanvraag met ongeldige cataloguseditie',
        'description' => 'Deze beschrijving mag niet worden opgeslagen',
    ];

    $payload['designer'] = 'Deze ontwerper mag niet worden opgeslagen';

    $payload['notes'] = 'Deze notities mogen niet worden opgeslagen';

    // Het land en uitgiftejaar blijven ongewijzigd.
    expect($payload['country_id'])
        ->toBe($this->country->id);

    expect($payload['issue_year'])
        ->toBe(2020);

    // Route B: geldige cataloguseditie A1.
    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-101';

    $payload['catalog_values'] = [
        'postfris' => '16.50',
        'gestempeld' => '8.25',
    ];

    // Route C: geldige cataloguseditie A2.
    $payload['catalog_entries'] = [
        $this->entryA2->id => [
            'catalog_number' => 'A-201',
            'values' => [
                'postfris' => '22.50',
                'gestempeld' => '11.25',
            ],
        ],
    ];

    // Route D: een geldige en een ongeldige cataloguseditie.
    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-101',
            'values' => [
                'postfris' => '30.75',
                'gestempeld' => '15.50',
            ],
        ],
        $this->editionC1->id => [
            'catalog_number' => 'C-300',
            'values' => [
                'postfris' => '45.75',
                'gestempeld' => '22.50',
            ],
        ],
    ];

    expect($payload['catalog_editions'])
        ->toHaveCount(2);

    expect($payload['translations']['nl']['name'])
        ->not->toBe($originalStamp['name']);

    $editUrl = route('backend.stamps.edit', $this->stamp);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Verwerk de ongeldige HTTP-aanvraag.
        // -------------------------------------------------

        $response = $this
            ->from($editUrl)
            ->put(
                route('backend.stamps.update', $this->stamp),
                $payload
            );

        $response->assertRedirect($editUrl);

        // De catalogusedities worden als geheel geweigerd.
        $response->assertSessionHasErrors([
            'catalog_editions',
        ]);

        // Controleer dat uitsluitend de catalogusedities
        // een validatiefout veroorzaken.
        expect(session('errors')->getBag('default')->keys())
            ->toBe(['catalog_editions']);

        $response->assertSessionMissing('success');

        // -------------------------------------------------
        // Controleer de oorspronkelijke postzegelgegevens.
        // -------------------------------------------------

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)
            ->toBeInstanceOf(Stamp::class);

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        // Alle attributen moeten volledig ongewijzigd blijven.
        expect($freshStamp->getAttributes())
            ->toBe($originalStamp);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->not->toBe($payload['translations']['nl']['name']);

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer de oorspronkelijke vertalingen.
        // -------------------------------------------------

        expect(
            $freshStamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalTranslations);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // Controleer de oorspronkelijke themakoppelingen.
        expect(
            $freshStamp->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalThemes);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            expect($freshEntry->id)
                ->toBe($entry->id);

            // Inclusief catalogusnummer, notities en timestamps.
            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->stamp->id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalogEdition->id)
                ->toBe(
                    $originalEntries[$entry->id]['catalog_edition_id']
                );

            expect($freshEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Controleer expliciet de oorspronkelijke catalogusnummers.
        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // Geen enkel ingediend catalogusnummer mag zijn opgeslagen.
        foreach ([
            'A-101',
            'A-201',
            'B-101',
            'C-300',
        ] as $catalogNumber) {
            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $this->stamp->id,
                'catalog_number' => $catalogNumber,
            ]);
        }

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($value->id);

            // Inclusief bedrag, valuta, notities,
            // controlemarkering en timestamps.
            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->needs_review)
                ->toBeFalse();

            expect($freshValue->catalogEntry->id)
                ->toBe(
                    $originalValues[$value->id]['stamp_catalog_entry_id']
                );

            expect($freshValue->catalogEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshValue->condition->id)
                ->toBe(
                    $originalValues[$value->id]['condition_id']
                );

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer expliciet de oorspronkelijke bedragen.
        expect($this->valueA1Postfris->fresh()->catalog_value)
            ->toBe('15.00');

        expect($this->valueA1Gestempeld->fresh()->catalog_value)
            ->toBe('7.50');

        expect($this->valueA2Postfris->fresh()->catalog_value)
            ->toBe('20.00');

        expect($this->valueA2Gestempeld->fresh()->catalog_value)
            ->toBe('10.00');

        expect($this->valueB1Postfris->fresh()->catalog_value)
            ->toBe('25.00');

        expect($this->valueB1Gestempeld->fresh()->catalog_value)
            ->toBe('12.50');

        // Geen enkele waarde mag voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de ongeldige cataloguseditie.
        // -------------------------------------------------

        // De cataloguseditie zelf moet behouden blijven.
        $this->assertDatabaseHas('catalog_editions', [
            'id' => $this->editionC1->id,
            'catalog_id' => $this->catalogC->id,
        ]);

        // Er mag geen catalogusvermelding zijn aangemaakt.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // Er mogen geen waarden voor deze editie zijn aangemaakt.
        expect(
            StampCatalogValue::query()
                ->whereHas('catalogEntry', function ($query) {
                    $query->where(
                        'catalog_edition_id',
                        $this->editionC1->id
                    );
                })
                ->exists()
        )->toBeFalse();

        // De catalogus mag niet automatisch aan het land
        // van de postzegel zijn gekoppeld.
        expect(
            $this->catalogC->countries()
                ->where('countries.id', $this->country->id)
                ->exists()
        )->toBeFalse();

        // De oorspronkelijke landkoppeling moet behouden blijven.
        expect(
            $this->catalogC->countries()
                ->where('countries.id', $this->otherCountry->id)
                ->exists()
        )->toBeTrue();

        // -------------------------------------------------
        // Controleer de definitieve catalogusrelaties.
        // -------------------------------------------------

        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect($entries)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect($values)
                ->pluck('id')
                ->sort()
                ->values()
                ->all()
        );

        // -------------------------------------------------
        // Controleer dat geen wijzigingsgeschiedenis bestaat.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            CatalogChangeBatch::query()->exists()
        )->toBeFalse();

        expect(
            CatalogChangeLog::query()->exists()
        )->toBeFalse();

        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        $this->assertDatabaseMissing('catalog_change_batches', [
            'operation' => 'stamp_update',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Controleer alle overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        expect(
            Condition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($condition) => $condition->getAttributes())
                ->all()
        )->toBe($originalConditions);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('de backend weigert een catalogusvermelding van een andere postzegel zonder gegevens te wijzigen of wijzigingsgeschiedenis te registreren', function () {
    // -------------------------------------------------
    // Maak een tweede postzegel aan.
    // -------------------------------------------------

    $otherStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2020,
    ]);

    $otherStamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Andere postzegel catalogusgeschiedenis',
            'description' => 'Oorspronkelijke beschrijving andere postzegel',
        ]
    );

    // Maak een catalogusvermelding voor de tweede postzegel.
    $otherEntry = $otherStamp->catalogEntries()->create([
        'catalog_edition_id' => $this->editionA2->id,
        'catalog_number' => 'A-900',
        'notes' => 'Oorspronkelijke notities andere catalogusvermelding',
    ]);

    $otherValuePostfris = $otherEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => 115.00,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse waarde andere postzegel',
        'needs_review' => true,
    ]);

    $otherValueGestempeld = $otherEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => 57.50,
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde waarde andere postzegel',
        'needs_review' => false,
    ]);

    // -------------------------------------------------
    // Verzamel beide postzegels en alle catalogusrecords.
    // -------------------------------------------------

    $stamps = [
        $this->stamp,
        $otherStamp,
    ];

    $entries = [
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
        $otherEntry,
    ];

    $values = [
        $this->valueA1Postfris,
        $this->valueA1Gestempeld,
        $this->valueA2Postfris,
        $this->valueA2Gestempeld,
        $this->valueB1Postfris,
        $this->valueB1Gestempeld,
        $otherValuePostfris,
        $otherValueGestempeld,
    ];

    // -------------------------------------------------
    // Bewaar alle oorspronkelijke postzegelgegevens.
    // -------------------------------------------------

    $originalStamps = [];

    $originalTranslations = [];

    $originalThemes = [];

    foreach ($stamps as $stamp) {
        $originalStamps[$stamp->id] =
            $stamp->fresh()->getAttributes();

        $originalTranslations[$stamp->id] =
            $stamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all();

        $originalThemes[$stamp->id] =
            $stamp->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();
    }

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
    $originalEntries = [];

    foreach ($entries as $entry) {
        $originalEntries[$entry->id] =
            $entry->fresh()->getAttributes();
    }

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = [];

    foreach ($values as $value) {
        $originalValues[$value->id] =
            $value->fresh()->getAttributes();
    }

    // -------------------------------------------------
    // Bewaar alle overige catalogusgegevens.
    // -------------------------------------------------

    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    $originalConditions = Condition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($condition) => $condition->getAttributes())
        ->all();

    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 2);

    $this->assertDatabaseCount('stamp_catalog_entries', 4);

    $this->assertDatabaseCount('stamp_catalog_values', 8);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // Beide postzegels moeten verschillende records zijn.
    expect($otherStamp->id)
        ->not->toBe($this->stamp->id);

    // Beide postzegels hebben hetzelfde land en uitgiftejaar.
    expect($otherStamp->country_id)
        ->toBe($this->stamp->country_id);

    expect($otherStamp->issue_year)
        ->toBe($this->stamp->issue_year);

    // De catalogusvermeldingen behoren tot dezelfde editie.
    expect($otherEntry->catalog_edition_id)
        ->toBe($this->entryA2->catalog_edition_id);

    // Maar de catalogusvermeldingen behoren tot andere postzegels.
    expect($otherEntry->stamp_id)
        ->toBe($otherStamp->id);

    expect($otherEntry->stamp_id)
        ->not->toBe($this->stamp->id);

    expect($otherEntry->id)
        ->not->toBe($this->entryA2->id);

    expect($this->stamp->catalogEntries()->count())
        ->toBe(3);

    expect($this->stamp->catalogValues()->count())
        ->toBe(6);

    expect($otherStamp->catalogEntries()->count())
        ->toBe(1);

    expect($otherStamp->catalogValues()->count())
        ->toBe(2);

    expect($otherValuePostfris->needs_review)
        ->toBeTrue();

    expect($otherValueGestempeld->needs_review)
        ->toBeFalse();

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(1);

    // -------------------------------------------------
    // Stel de formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['translations']['nl'] = [
        'name' => 'Aanvraag met catalogusvermelding andere postzegel',
        'description' => 'Deze beschrijving mag niet worden opgeslagen',
    ];

    $payload['designer'] =
        'Deze ontwerper mag niet worden opgeslagen';

    $payload['notes'] =
        'Deze notities mogen niet worden opgeslagen';

    // Route B: geldige catalogusvermelding A1.
    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-101';

    $payload['catalog_values'] = [
        'postfris' => '16.50',
        'gestempeld' => '8.25',
    ];

    // Route C: een geldige catalogusvermelding en
    // een catalogusvermelding van de andere postzegel.
    $payload['catalog_entries'] = [
        $this->entryA2->id => [
            'catalog_number' => 'A-201',
            'values' => [
                'postfris' => '22.50',
                'gestempeld' => '11.25',
            ],
        ],
        $otherEntry->id => [
            'catalog_number' => 'A-999',
            'values' => [
                'postfris' => '150.00',
                'gestempeld' => '75.00',
            ],
        ],
    ];

    // Route D: geldige catalogusvermelding B1.
    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-101',
            'values' => [
                'postfris' => '30.75',
                'gestempeld' => '15.50',
            ],
        ],
    ];

    // Controleer dat de aanvraag daadwerkelijk de
    // catalogusvermelding van de andere postzegel bevat.
    expect($payload['catalog_entries'])
        ->toHaveCount(2);

    expect(
        $payload['catalog_entries'][$otherEntry->id]['catalog_number']
    )->toBe('A-999');

    expect(
        $this->stamp->catalogEntries()
            ->whereKey($otherEntry->id)
            ->exists()
    )->toBeFalse();

    expect(
        $otherStamp->catalogEntries()
            ->whereKey($otherEntry->id)
            ->exists()
    )->toBeTrue();

    $editUrl = route('backend.stamps.edit', $this->stamp);

    // Verplaats de tijd om ongewenste timestampwijzigingen
    // te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Verwerk de ongeldige HTTP-aanvraag.
        // -------------------------------------------------

        $response = $this
            ->from($editUrl)
            ->put(
                route('backend.stamps.update', $this->stamp),
                $payload
            );

        $response->assertRedirect($editUrl);

        $response->assertSessionHasErrors([
            'catalog_entries',
        ]);

        // Uitsluitend catalog_entries mag een fout veroorzaken.
        expect(session('errors')->getBag('default')->keys())
            ->toBe(['catalog_entries']);

        $response->assertSessionMissing('success');

        // -------------------------------------------------
        // Controleer beide oorspronkelijke postzegels.
        // -------------------------------------------------

        foreach ($stamps as $stamp) {
            $freshStamp = $stamp->fresh();

            expect($freshStamp)
                ->toBeInstanceOf(Stamp::class);

            expect($freshStamp->id)
                ->toBe($stamp->id);

            // Alle attributen, inclusief timestamps,
            // moeten volledig ongewijzigd blijven.
            expect($freshStamp->getAttributes())
                ->toBe($originalStamps[$stamp->id]);

            expect(
                $freshStamp->translations()
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($translation) => $translation->getAttributes())
                    ->all()
            )->toBe(
                $originalTranslations[$stamp->id]
            );

            expect(
                $freshStamp->themes()
                    ->orderBy('themes.id')
                    ->pluck('themes.id')
                    ->all()
            )->toBe(
                $originalThemes[$stamp->id]
            );

            $this->assertDatabaseMissing('catalog_change_logs', [
                'stamp_id' => $stamp->id,
            ]);
        }

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'name' => $payload['translations']['nl']['name'],
        ]);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer alle vier catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($entries as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            expect($freshEntry->id)
                ->toBe($entry->id);

            expect($freshEntry->getAttributes())
                ->toBe($originalEntries[$entry->id]);

            expect($freshEntry->stamp_id)
                ->toBe(
                    $originalEntries[$entry->id]['stamp_id']
                );

            expect($freshEntry->stamp->id)
                ->toBe(
                    $originalEntries[$entry->id]['stamp_id']
                );

            expect($freshEntry->catalogEdition->id)
                ->toBe(
                    $originalEntries[$entry->id]['catalog_edition_id']
                );

            expect($freshEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Controleer expliciet alle oorspronkelijke nummers.
        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        expect($otherEntry->fresh()->catalog_number)
            ->toBe('A-900');

        // Geen enkel ingediend nummer mag zijn opgeslagen.
        foreach ([
            'A-101',
            'A-201',
            'A-999',
            'B-101',
        ] as $catalogNumber) {
            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'catalog_number' => $catalogNumber,
            ]);
        }

        // -------------------------------------------------
        // Controleer alle acht cataloguswaarden.
        // -------------------------------------------------

        foreach ($values as $value) {
            $freshValue = $value->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($value->id);

            // Inclusief bedrag, valuta, notities,
            // controlemarkering en timestamps.
            expect($freshValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($freshValue->catalogEntry->id)
                ->toBe(
                    $originalValues[$value->id]['stamp_catalog_entry_id']
                );

            expect($freshValue->condition->id)
                ->toBe(
                    $originalValues[$value->id]['condition_id']
                );

            expect($freshValue->needs_review)
                ->toBe(
                    (bool) $originalValues[$value->id]['needs_review']
                );

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $value->id,
            ]);
        }

        // Controleer expliciet de oorspronkelijke bedragen.
        expect($this->valueA1Postfris->fresh()->catalog_value)
            ->toBe('15.00');

        expect($this->valueA1Gestempeld->fresh()->catalog_value)
            ->toBe('7.50');

        expect($this->valueA2Postfris->fresh()->catalog_value)
            ->toBe('20.00');

        expect($this->valueA2Gestempeld->fresh()->catalog_value)
            ->toBe('10.00');

        expect($this->valueB1Postfris->fresh()->catalog_value)
            ->toBe('25.00');

        expect($this->valueB1Gestempeld->fresh()->catalog_value)
            ->toBe('12.50');

        expect($otherValuePostfris->fresh()->catalog_value)
            ->toBe('115.00');

        expect($otherValueGestempeld->fresh()->catalog_value)
            ->toBe('57.50');

        // -------------------------------------------------
        // Controleer de controlemarkeringen.
        // -------------------------------------------------

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe([
            $otherValuePostfris->id,
        ]);

        expect($otherValuePostfris->fresh()->needs_review)
            ->toBeTrue();

        expect($otherValueGestempeld->fresh()->needs_review)
            ->toBeFalse();

        // -------------------------------------------------
        // Controleer de definitieve catalogusrelaties.
        // -------------------------------------------------

        $freshStamp = $this->stamp->fresh();

        $freshOtherStamp = $otherStamp->fresh();

        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        expect($freshOtherStamp->catalogEntries()->count())
            ->toBe(1);

        expect($freshOtherStamp->catalogValues()->count())
            ->toBe(2);

        // De eerste postzegel behoudt exact dezelfde vermeldingen.
        expect(
            $freshStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe(
            collect([
                $this->entryA1->id,
                $this->entryA2->id,
                $this->entryB1->id,
            ])
                ->sort()
                ->values()
                ->all()
        );

        // De tweede postzegel behoudt zijn eigen vermelding.
        expect(
            $freshOtherStamp->catalogEntries()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        )->toBe([
            $otherEntry->id,
        ]);

        // De eerste postzegel behoudt exact dezelfde waarden.
        expect(
            $freshStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect([
                $this->valueA1Postfris->id,
                $this->valueA1Gestempeld->id,
                $this->valueA2Postfris->id,
                $this->valueA2Gestempeld->id,
                $this->valueB1Postfris->id,
                $this->valueB1Gestempeld->id,
            ])
                ->sort()
                ->values()
                ->all()
        );

        // De tweede postzegel behoudt exact zijn eigen waarden.
        expect(
            $freshOtherStamp->catalogValues()
                ->orderBy('stamp_catalog_values.id')
                ->pluck('stamp_catalog_values.id')
                ->all()
        )->toBe(
            collect([
                $otherValuePostfris->id,
                $otherValueGestempeld->id,
            ])
                ->sort()
                ->values()
                ->all()
        );

        // De catalogusvermelding mag niet zijn overgenomen
        // door de eerste postzegel.
        expect(
            $freshStamp->catalogEntries()
                ->whereKey($otherEntry->id)
                ->exists()
        )->toBeFalse();

        expect(
            $freshOtherStamp->catalogEntries()
                ->whereKey($otherEntry->id)
                ->exists()
        )->toBeTrue();

        // Er mag voor geen van beide postzegels
        // een nieuwe koppeling met C1 ontstaan.
        foreach ($stamps as $stamp) {
            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $stamp->id,
                'catalog_edition_id' => $this->editionC1->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer dat geen wijzigingsgeschiedenis bestaat.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            CatalogChangeBatch::query()->exists()
        )->toBeFalse();

        expect(
            CatalogChangeLog::query()->exists()
        )->toBeFalse();

        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        $this->assertDatabaseMissing('catalog_change_batches', [
            'operation' => 'stamp_update',
        ]);

        // -------------------------------------------------
        // Controleer alle overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        expect(
            Condition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($condition) => $condition->getAttributes())
                ->all()
        )->toBe($originalConditions);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('de backend weigert het aanmaken van een postzegel door een niet ingelogde gebruiker zonder wijzigingsgeschiedenis te registreren', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $originalStamps = Stamp::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($stamp) => $stamp->getAttributes())
        ->all();

    $originalTranslations = $this->stamp->translations()
        ->orderBy('id')
        ->get()
        ->map(fn ($translation) => $translation->getAttributes())
        ->all();

    $originalThemes = $this->stamp->themes()
        ->orderBy('themes.id')
        ->pluck('themes.id')
        ->all();

    $originalEntries = StampCatalogEntry::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    $originalConditions = Condition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($condition) => $condition->getAttributes())
        ->all();

    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    // -------------------------------------------------
    // Stel volledig geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->storePayload;

    $payload['translations']['nl'] = [
        'name' => 'Ongeautoriseerde nieuwe postzegel',
        'description' => 'Deze postzegel mag niet worden aangemaakt',
    ];

    $payload['face_value'] = '2.50';

    $payload['currency'] = 'EUR';

    $payload['designer'] = 'Ongeautoriseerde testontwerper';

    $payload['printing_method'] = 'Offsetdruk';

    $payload['notes'] = 'Deze notities mogen niet worden opgeslagen';

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-999';

    $payload['catalog_values'] = [
        'postfris' => '45.75',
        'gestempeld' => '22.50',
    ];

    // Controleer dat de catalogus beschikbaar is
    // voor het land van de nieuwe postzegel.
    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect($this->editionB1->catalog_id)
        ->toBe($this->catalogB->id);

    expect($this->editionB1->currency)
        ->toBe('USD');

    expect($payload['country_id'])
        ->toBe($this->country->id);

    expect($payload['issue_year'])
        ->toBe(2020);

    // Controleer dat beide waarderingscategorieën bestaan.
    foreach ([
        $this->postfris,
        $this->gestempeld,
    ] as $condition) {
        $this->assertDatabaseHas('catalog_condition_rules', [
            'catalog_id' => $this->catalogB->id,
            'condition_id' => $condition->id,
        ]);
    }

    $this->assertDatabaseMissing('stamps', [
        'name' => $payload['translations']['nl']['name'],
    ]);

    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'catalog_edition_id' => $this->editionB1->id,
        'catalog_number' => 'B-999',
    ]);

    // -------------------------------------------------
    // Maak de gebruiker niet-ingelogd.
    // -------------------------------------------------

    auth()->guard('web')->logout();

    $this->assertGuest('web');

    // Verplaats de tijd om eventuele ongewenste
    // timestampwijzigingen te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Verstuur de ongeautoriseerde HTTP-aanvraag.
        // -------------------------------------------------

        $response = $this->post(
            route('backend.stamps.store'),
            $payload
        );

        // De authenticatiemiddleware moet de aanvraag weigeren.
        $response->assertRedirect(route('login'));

        $response->assertSessionMissing('success');

        $this->assertGuest('web');

        // -------------------------------------------------
        // Controleer alle oorspronkelijke postzegelgegevens.
        // -------------------------------------------------

        expect(
            Stamp::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($stamp) => $stamp->getAttributes())
                ->all()
        )->toBe($originalStamps);

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)
            ->toBeInstanceOf(Stamp::class);

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect(
            $freshStamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalTranslations);

        expect(
            $freshStamp->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalThemes);

        // De ongeautoriseerde postzegel mag niet bestaan.
        $this->assertDatabaseMissing('stamps', [
            'name' => $payload['translations']['nl']['name'],
        ]);

        $this->assertDatabaseMissing('stamp_translations', [
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        expect(
            StampCatalogEntry::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($entry) => $entry->getAttributes())
                ->all()
        )->toBe($originalEntries);

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // De ongeautoriseerde catalogusvermelding
        // mag niet zijn aangemaakt.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-999',
        ]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        expect(
            StampCatalogValue::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($value) => $value->getAttributes())
                ->all()
        )->toBe($originalValues);

        expect($this->valueA1Postfris->fresh()->catalog_value)
            ->toBe('15.00');

        expect($this->valueA1Gestempeld->fresh()->catalog_value)
            ->toBe('7.50');

        expect($this->valueA2Postfris->fresh()->catalog_value)
            ->toBe('20.00');

        expect($this->valueA2Gestempeld->fresh()->catalog_value)
            ->toBe('10.00');

        expect($this->valueB1Postfris->fresh()->catalog_value)
            ->toBe('25.00');

        expect($this->valueB1Gestempeld->fresh()->catalog_value)
            ->toBe('12.50');

        // De ongeautoriseerde waarden mogen niet bestaan.
        $this->assertDatabaseMissing('stamp_catalog_values', [
            'catalog_value' => 45.75,
            'currency' => 'USD',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'catalog_value' => 22.50,
            'currency' => 'USD',
        ]);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de definitieve catalogusrelaties.
        // -------------------------------------------------

        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        foreach ([
            $this->entryA1,
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            expect($entry->fresh()->stamp_id)
                ->toBe($this->stamp->id);

            expect($entry->fresh()->values()->count())
                ->toBe(2);
        }

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // -------------------------------------------------
        // Controleer dat geen wijzigingsgeschiedenis bestaat.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            CatalogChangeBatch::query()->exists()
        )->toBeFalse();

        expect(
            CatalogChangeLog::query()->exists()
        )->toBeFalse();

        $this->assertDatabaseMissing('catalog_change_batches', [
            'operation' => 'stamp_create',
        ]);

        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer alle overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        expect(
            Condition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($condition) => $condition->getAttributes())
                ->all()
        )->toBe($originalConditions);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('de backend weigert het wijzigen van een postzegel door een niet ingelogde gebruiker zonder wijzigingsgeschiedenis te registreren', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $originalStamps = Stamp::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($stamp) => $stamp->getAttributes())
        ->all();

    $originalTranslations = $this->stamp->translations()
        ->orderBy('id')
        ->get()
        ->map(fn ($translation) => $translation->getAttributes())
        ->all();

    $originalThemes = $this->stamp->themes()
        ->orderBy('themes.id')
        ->pluck('themes.id')
        ->all();

    $originalEntries = StampCatalogEntry::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    $originalCatalogs = Catalog::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($catalog) => $catalog->getAttributes())
        ->all();

    $originalEditions = CatalogEdition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($edition) => $edition->getAttributes())
        ->all();

    $originalConditionRules = CatalogConditionRule::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($rule) => $rule->getAttributes())
        ->all();

    $originalConditions = Condition::query()
        ->orderBy('id')
        ->get()
        ->map(fn ($condition) => $condition->getAttributes())
        ->all();

    $originalCatalogCountries = [];

    foreach ([
        $this->catalogA,
        $this->catalogB,
        $this->catalogC,
    ] as $catalog) {
        $originalCatalogCountries[$catalog->id] =
            $catalog->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all();
    }

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    // -------------------------------------------------
    // Stel volledig geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    // Gewijzigde algemene postzegelgegevens.
    $payload['translations']['nl'] = [
        'name' => 'Ongeautoriseerde wijziging bestaande postzegel',
        'description' => 'Deze beschrijving mag niet worden opgeslagen',
    ];

    $payload['face_value'] = '5.50';

    $payload['currency'] = 'EUR';

    $payload['designer'] = 'Ongeautoriseerde testontwerper';

    $payload['printing_method'] = 'Offsetdruk';

    $payload['notes'] = 'Deze notities mogen niet worden opgeslagen';

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

    // -------------------------------------------------
    // Controleer de geldigheid van de formuliergegevens.
    // -------------------------------------------------

    expect($payload['country_id'])
        ->toBe($this->country->id);

    expect($payload['issue_year'])
        ->toBe(2020);

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect($this->entryA2->stamp_id)
        ->toBe($this->stamp->id);

    expect($this->entryA1->catalog_edition_id)
        ->toBe($this->editionA1->id);

    expect($this->entryA2->catalog_edition_id)
        ->toBe($this->editionA2->id);

    expect($this->entryB1->catalog_edition_id)
        ->toBe($this->editionB1->id);

    // Beide waarderingscategorieën moeten beschikbaar zijn.
    foreach ([
        $this->catalogA,
        $this->catalogB,
    ] as $catalog) {
        foreach ([
            $this->postfris,
            $this->gestempeld,
        ] as $condition) {
            $this->assertDatabaseHas('catalog_condition_rules', [
                'catalog_id' => $catalog->id,
                'condition_id' => $condition->id,
            ]);
        }
    }

    // Controleer dat daadwerkelijk wijzigingen worden ingediend.
    expect($payload['translations']['nl']['name'])
        ->not->toBe($this->stamp->fresh()->name);

    expect($payload['catalog_number'])
        ->not->toBe($this->entryA1->catalog_number);

    expect(
        $payload['catalog_entries'][$this->entryA2->id]['catalog_number']
    )->not->toBe($this->entryA2->catalog_number);

    expect(
        $payload['catalog_editions'][$this->editionB1->id]['catalog_number']
    )->not->toBe($this->entryB1->catalog_number);

    // -------------------------------------------------
    // Maak de gebruiker niet-ingelogd.
    // -------------------------------------------------

    auth()->guard('web')->logout();

    $this->assertGuest('web');

    // Verplaats de tijd om eventuele ongewenste
    // timestampwijzigingen te kunnen detecteren.
    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Verstuur de ongeautoriseerde HTTP-aanvraag.
        // -------------------------------------------------

        $response = $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        );

        // De authenticatiemiddleware moet de aanvraag weigeren.
        $response->assertRedirect(route('login'));

        $response->assertSessionMissing('success');

        $this->assertGuest('web');

        // -------------------------------------------------
        // Controleer alle oorspronkelijke postzegelgegevens.
        // -------------------------------------------------

        expect(
            Stamp::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($stamp) => $stamp->getAttributes())
                ->all()
        )->toBe($originalStamps);

        $freshStamp = $this->stamp->fresh();

        expect($freshStamp)
            ->toBeInstanceOf(Stamp::class);

        expect($freshStamp->id)
            ->toBe($this->stamp->id);

        expect($freshStamp->country_id)
            ->toBe($this->country->id);

        expect($freshStamp->issue_year)
            ->toBe(2020);

        expect($freshStamp->name)
            ->not->toBe($payload['translations']['nl']['name']);

        // De ongeautoriseerde naamwijziging mag niet bestaan.
        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer de oorspronkelijke vertalingen.
        // -------------------------------------------------

        expect(
            $freshStamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalTranslations);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // Controleer de oorspronkelijke themakoppelingen.
        expect(
            $freshStamp->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalThemes);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        // Alle attributen moeten exact gelijk zijn gebleven.
        // Inclusief catalogusnummers, notities en timestamps.
        expect(
            StampCatalogEntry::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($entry) => $entry->getAttributes())
                ->all()
        )->toBe($originalEntries);

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // Geen enkel ingediend catalogusnummer mag bestaan.
        foreach ([
            'A-101',
            'A-201',
            'B-101',
        ] as $catalogNumber) {
            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $this->stamp->id,
                'catalog_number' => $catalogNumber,
            ]);
        }

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        // Inclusief bedragen, valuta, notities,
        // controlemarkeringen en timestamps.
        expect(
            StampCatalogValue::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($value) => $value->getAttributes())
                ->all()
        )->toBe($originalValues);

        $expectedValues = [
            [
                'model' => $this->valueA1Postfris,
                'entry' => $this->entryA1,
                'condition' => $this->postfris,
                'amount' => '15.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA1Gestempeld,
                'entry' => $this->entryA1,
                'condition' => $this->gestempeld,
                'amount' => '7.50',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA2Postfris,
                'entry' => $this->entryA2,
                'condition' => $this->postfris,
                'amount' => '20.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA2Gestempeld,
                'entry' => $this->entryA2,
                'condition' => $this->gestempeld,
                'amount' => '10.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueB1Postfris,
                'entry' => $this->entryB1,
                'condition' => $this->postfris,
                'amount' => '25.00',
                'currency' => 'USD',
            ],
            [
                'model' => $this->valueB1Gestempeld,
                'entry' => $this->entryB1,
                'condition' => $this->gestempeld,
                'amount' => '12.50',
                'currency' => 'USD',
            ],
        ];

        foreach ($expectedValues as $expectedValue) {
            $freshValue = $expectedValue['model']->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($expectedValue['model']->id);

            expect($freshValue->catalog_value)
                ->toBe($expectedValue['amount']);

            expect($freshValue->currency)
                ->toBe($expectedValue['currency']);

            expect($freshValue->needs_review)
                ->toBeFalse();

            expect($freshValue->catalogEntry->id)
                ->toBe($expectedValue['entry']->id);

            expect($freshValue->catalogEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshValue->condition->id)
                ->toBe($expectedValue['condition']->id);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $freshValue->id,
            ]);
        }

        // Geen enkele waarde mag voor controle zijn gemarkeerd.
        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de definitieve catalogusrelaties.
        // -------------------------------------------------

        expect($freshStamp->catalogEntries()->count())
            ->toBe(3);

        expect($freshStamp->catalogValues()->count())
            ->toBe(6);

        foreach ([
            $this->entryA1,
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry)
                ->toBeInstanceOf(StampCatalogEntry::class);

            expect($freshEntry->id)
                ->toBe($entry->id);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->catalogEdition->id)
                ->toBe($entry->catalog_edition_id);

            expect($freshEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $entry->id,
            ]);
        }

        // Er mag geen nieuwe koppeling met C1 ontstaan.
        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        // -------------------------------------------------
        // Controleer dat geen wijzigingsgeschiedenis bestaat.
        // -------------------------------------------------

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            CatalogChangeBatch::query()->exists()
        )->toBeFalse();

        expect(
            CatalogChangeLog::query()->exists()
        )->toBeFalse();

        $this->assertDatabaseMissing('catalog_change_batches', [
            'operation' => 'stamp_update',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        foreach ([
            $this->editionA1,
            $this->editionA2,
            $this->editionB1,
            $this->editionC1,
        ] as $edition) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $edition->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $edition->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer alle overige catalogusgegevens.
        // -------------------------------------------------

        expect(
            Catalog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($catalog) => $catalog->getAttributes())
                ->all()
        )->toBe($originalCatalogs);

        expect(
            CatalogEdition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($edition) => $edition->getAttributes())
                ->all()
        )->toBe($originalEditions);

        expect(
            CatalogConditionRule::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($rule) => $rule->getAttributes())
                ->all()
        )->toBe($originalConditionRules);

        expect(
            Condition::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($condition) => $condition->getAttributes())
                ->all()
        )->toBe($originalConditions);

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            expect(
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all()
            )->toBe(
                $originalCatalogCountries[$catalog->id]
            );
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // Er mag geen actieve geschiedenisbatch achterblijven.
        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        // De gebruiker moet nog steeds niet-ingelogd zijn.
        $this->assertGuest('web');
    } finally {
        $this->travelBack();
    }
});
