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
    app()->setLocale('nl');

    /*
    |--------------------------------------------------------------------------
    | Gebruikers
    |--------------------------------------------------------------------------
    */

    $this->user = User::factory()->create([
        'name' => 'Testgebruiker catalogusgeschiedenis context',
        'is_admin' => false,
    ]);

    $this->otherUser = User::factory()->create([
        'name' => 'Andere testgebruiker context',
        'is_admin' => false,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Postzegel en landen
    |--------------------------------------------------------------------------
    */

    $this->stamp = Stamp::factory()->create([
        'issue_year' => 2020,
    ]);

    $this->country = $this->stamp->country;

    $this->otherCountry = Country::factory()->create();

    /*
    |--------------------------------------------------------------------------
    | Waarderingscategorieën
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Catalogi
    |--------------------------------------------------------------------------
    */

    $this->catalogA = Catalog::create([
        'name' => 'Testcatalogus A',
        'abbreviation' => 'TCA',
    ]);

    $this->catalogA->countries()->attach(
        $this->country->id
    );

    $this->catalogB = Catalog::create([
        'name' => 'Testcatalogus B',
        'abbreviation' => 'TCB',
    ]);

    $this->catalogB->countries()->attach([
        $this->country->id,
        $this->otherCountry->id,
    ]);

    $this->catalogC = Catalog::create([
        'name' => 'Testcatalogus C',
        'abbreviation' => 'TCC',
    ]);

    $this->catalogC->countries()->attach(
        $this->otherCountry->id
    );

    /*
    |--------------------------------------------------------------------------
    | Catalogusedities
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Waarderingsregels
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Bestaande catalogusvermeldingen
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Cataloguswaarden A1
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Cataloguswaarden A2
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Cataloguswaarden B1
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Gemeenschappelijke store-payload
    |--------------------------------------------------------------------------
    */

    $this->storePayload = [
        'country_id' => $this->country->id,
        'issue_year' => 2020,
        'translations' => [
            'nl' => [
                'name' => 'Nieuwe postzegel contexttest',
                'description' => 'Beschrijving nieuwe postzegel contexttest',
            ],
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Gemeenschappelijke update-payload
    |--------------------------------------------------------------------------
    */

    $this->updatePayload = [
        'country_id' => $this->country->id,
        'issue_year' => $this->stamp->issue_year,
        'translations' => [
            'nl' => [
                'name' => 'Gewijzigde postzegel contexttest',
                'description' => 'Gewijzigde beschrijving contexttest',
            ],
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Geschiedenisservice
    |--------------------------------------------------------------------------
    */

    $this->history = app(
        CatalogChangeHistoryService::class
    );

    /*
    |--------------------------------------------------------------------------
    | Controle uitgangssituatie
    |--------------------------------------------------------------------------
    */

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    /*
    |--------------------------------------------------------------------------
    | Authenticatie
    |--------------------------------------------------------------------------
    */

    $this->actingAs($this->user);
});

test('de actieve geschiedenisbatch wordt na een succesvolle store volledig opgeruimd', function () {

    /*
    |--------------------------------------------------------------------------
    | Uitgangssituatie
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    /*
    |--------------------------------------------------------------------------
    | Store-payload
    |--------------------------------------------------------------------------
    */

    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-1100';

    $payload['catalog_values'] = [
        'postfris' => '55.75',
        'gestempeld' => '27.50',
    ];

    /*
    |--------------------------------------------------------------------------
    | Succesvolle store-aanvraag
    |--------------------------------------------------------------------------
    */

    $response = $this->post(
        route('backend.stamps.store'),
        $payload
    );

    $response->assertRedirect();

    $response->assertSessionHasNoErrors();

    $response->assertSessionHas('success');

    /*
    |--------------------------------------------------------------------------
    | Aangemaakte postzegel
    |--------------------------------------------------------------------------
    */

    $newStamp = Stamp::query()
        ->where('id', '!=', $this->stamp->id)
        ->sole();

    expect($newStamp->country_id)
        ->toBe($this->country->id);

    expect($newStamp->name)
        ->toBe('Nieuwe postzegel contexttest');

    /*
    |--------------------------------------------------------------------------
    | Aangemaakte catalogusvermelding
    |--------------------------------------------------------------------------
    */

    $newEntry = $newStamp->catalogEntries()
        ->where('catalog_edition_id', $this->editionB1->id)
        ->sole();

    expect($newEntry->catalog_number)
        ->toBe('B-1100');

    /*
    |--------------------------------------------------------------------------
    | Aangemaakte cataloguswaarden
    |--------------------------------------------------------------------------
    */

    $newPostfris = $newEntry->values()
        ->where('condition_id', $this->postfris->id)
        ->sole();

    $newGestempeld = $newEntry->values()
        ->where('condition_id', $this->gestempeld->id)
        ->sole();

    expect($newPostfris->catalog_value)
        ->toBe('55.75');

    expect($newPostfris->currency)
        ->toBe('USD');

    expect($newPostfris->needs_review)
        ->toBeFalse();

    expect($newGestempeld->catalog_value)
        ->toBe('27.50');

    expect($newGestempeld->currency)
        ->toBe('USD');

    expect($newGestempeld->needs_review)
        ->toBeFalse();

    /*
    |--------------------------------------------------------------------------
    | Databaseaantallen na store
    |--------------------------------------------------------------------------
    */

    $this->assertDatabaseCount('stamps', 2);

    $this->assertDatabaseCount('stamp_catalog_entries', 4);

    $this->assertDatabaseCount('stamp_catalog_values', 8);

    $this->assertDatabaseCount('catalog_change_batches', 1);

    $this->assertDatabaseCount('catalog_change_logs', 3);

    /*
    |--------------------------------------------------------------------------
    | Geschiedenisbatch controleren
    |--------------------------------------------------------------------------
    */

    $batch = CatalogChangeBatch::query()->sole();

    expect($batch->catalog_edition_id)
        ->toBe($this->editionB1->id);

    expect($batch->user_id)
        ->toBe($this->user->id);

    expect($batch->user_name)
        ->toBe($this->user->name);

    expect($batch->operation)
        ->toBe('stamp_create');

    expect($batch->description)
        ->toBe(
            'Catalogusgegevens bij het aanmaken van een postzegel'
        );

    expect($batch->catalog_name)
        ->toBe($this->catalogB->name);

    expect($batch->catalog_edition_name)
        ->toBe($this->editionB1->edition_name);

    expect($batch->logs()->count())
        ->toBe(3);

    /*
    |--------------------------------------------------------------------------
    | Geschiedenislogs controleren
    |--------------------------------------------------------------------------
    */

    $entryLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_entry')
        ->where('record_id', $newEntry->id)
        ->sole();

    expect($entryLog->action)
        ->toBe('created');

    expect($entryLog->stamp_id)
        ->toBe($newStamp->id);

    expect($entryLog->catalog_edition_id)
        ->toBe($this->editionB1->id);

    expect($entryLog->old_values)
        ->toBeNull();

    expect($entryLog->new_values['catalog_number'])
        ->toBe('B-1100');

    $postfrisLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $newPostfris->id)
        ->sole();

    expect($postfrisLog->action)
        ->toBe('created');

    expect($postfrisLog->stamp_id)
        ->toBe($newStamp->id);

    expect($postfrisLog->condition_id)
        ->toBe($this->postfris->id);

    expect($postfrisLog->catalog_edition_id)
        ->toBe($this->editionB1->id);

    expect($postfrisLog->old_values)
        ->toBeNull();

    expect((float) $postfrisLog->new_values['catalog_value'])
        ->toBe(55.75);

    expect($postfrisLog->new_values['currency'])
        ->toBe('USD');

    $gestempeldLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $newGestempeld->id)
        ->sole();

    expect($gestempeldLog->action)
        ->toBe('created');

    expect($gestempeldLog->stamp_id)
        ->toBe($newStamp->id);

    expect($gestempeldLog->condition_id)
        ->toBe($this->gestempeld->id);

    expect($gestempeldLog->catalog_edition_id)
        ->toBe($this->editionB1->id);

    expect($gestempeldLog->old_values)
        ->toBeNull();

    expect((float) $gestempeldLog->new_values['catalog_value'])
        ->toBe(27.50);

    expect($gestempeldLog->new_values['currency'])
        ->toBe('USD');

    /*
    |--------------------------------------------------------------------------
    | Geschiedeniscontext na succesvolle store
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($this->history);

    expect(
        app(CatalogChangeHistoryService::class)
            ->getActiveBatch()
    )->toBeNull();

    /*
    |--------------------------------------------------------------------------
    | Volledige geschiedenisgegevens vastleggen
    |--------------------------------------------------------------------------
    */

    $historySnapshot = function () {
        return [
            'batches' => CatalogChangeBatch::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'logs' => CatalogChangeLog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    $originalHistory = $historySnapshot();

    /*
    |--------------------------------------------------------------------------
    | Bestaande catalogusgegevens vastleggen
    |--------------------------------------------------------------------------
    */

    $originalEntries = StampCatalogEntry::query()
        ->where('stamp_id', $this->stamp->id)
        ->orderBy('id')
        ->get()
        ->map(fn ($record) => $record->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->whereIn('stamp_catalog_entry_id', [
            $this->entryA1->id,
            $this->entryA2->id,
            $this->entryB1->id,
        ])
        ->orderBy('id')
        ->get()
        ->map(fn ($record) => $record->getAttributes())
        ->all();

    /*
    |--------------------------------------------------------------------------
    | Rechtstreekse modelbewerkingen buiten geschiedeniscontext
    |--------------------------------------------------------------------------
    */

    $this->travel(1)->minutes();

    try {

        /*
        |--------------------------------------------------------------------------
        | 1. Catalogusnummer rechtstreeks wijzigen
        |--------------------------------------------------------------------------
        */

        $newEntry->update([
            'catalog_number' => 'B-1200',
        ]);

        expect($newEntry->fresh()->catalog_number)
            ->toBe('B-1200');

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $newEntry->id,
            'stamp_id' => $newStamp->id,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-1200',
        ]);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($this->history->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 2. Cataloguswaarde rechtstreeks wijzigen
        |--------------------------------------------------------------------------
        */

        $newPostfris->update([
            'catalog_value' => '65.75',
        ]);

        expect($newPostfris->fresh()->catalog_value)
            ->toBe('65.75');

        expect($newPostfris->fresh()->currency)
            ->toBe('USD');

        expect($newPostfris->fresh()->needs_review)
            ->toBeTrue();

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $newPostfris->id,
            'stamp_catalog_entry_id' => $newEntry->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 65.75,
            'currency' => 'USD',
            'needs_review' => true,
        ]);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($this->history->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 3. Cataloguswaarde rechtstreeks verwijderen
        |--------------------------------------------------------------------------
        */

        $deletedValueId = $newGestempeld->id;

        $newGestempeld->delete();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $deletedValueId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $newEntry->id,
            'condition_id' => $this->gestempeld->id,
        ]);

        $this->assertDatabaseCount('stamp_catalog_values', 7);

        expect($newEntry->values()->count())
            ->toBe(1);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($this->history->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 4. Cataloguswaarde rechtstreeks opnieuw aanmaken
        |--------------------------------------------------------------------------
        */

        $replacementValue = $newEntry->values()->create([
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => '32.50',
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        expect($replacementValue->exists)
            ->toBeTrue();

        expect($replacementValue->fresh()->catalog_value)
            ->toBe('32.50');

        expect($replacementValue->fresh()->currency)
            ->toBe('USD');

        expect($replacementValue->fresh()->needs_review)
            ->toBeFalse();

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $replacementValue->id,
            'stamp_catalog_entry_id' => $newEntry->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 32.50,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        expect($newEntry->values()->count())
            ->toBe(2);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($this->history->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | Definitieve databaseaantallen
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(1);

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke postzegelgegevens blijven behouden
        |--------------------------------------------------------------------------
        */

        expect(
            StampCatalogEntry::query()
                ->where('stamp_id', $this->stamp->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($originalEntries);

        expect(
            StampCatalogValue::query()
                ->whereIn('stamp_catalog_entry_id', [
                    $this->entryA1->id,
                    $this->entryA2->id,
                    $this->entryB1->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($originalValues);

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke geschiedenis blijft volledig behouden
        |--------------------------------------------------------------------------
        */

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($batch->fresh()->logs()->count())
            ->toBe(3);

        expect($entryLog->fresh()->new_values['catalog_number'])
            ->toBe('B-1100');

        expect(
            (float) $postfrisLog->fresh()->new_values['catalog_value']
        )->toBe(55.75);

        expect(
            (float) $gestempeldLog->fresh()->new_values['catalog_value']
        )->toBe(27.50);

        /*
        |--------------------------------------------------------------------------
        | Definitieve controle geschiedeniscontext
        |--------------------------------------------------------------------------
        */

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history);

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

    } finally {

        $this->travelBack();
    }
});

test('de actieve geschiedenisbatch wordt na een succesvolle update volledig opgeruimd', function () {

    /*
    |--------------------------------------------------------------------------
    | Uitgangssituatie
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Gestempeld->fresh()->catalog_value)
        ->toBe('12.50');

    /*
    |--------------------------------------------------------------------------
    | Niet-betrokken catalogusgegevens vastleggen
    |--------------------------------------------------------------------------
    */

    $unaffectedEntriesSnapshot = function () {
        return StampCatalogEntry::query()
            ->whereIn('id', [
                $this->entryA1->id,
                $this->entryA2->id,
            ])
            ->orderBy('id')
            ->get()
            ->map(fn ($record) => $record->getAttributes())
            ->all();
    };

    $unaffectedValuesSnapshot = function () {
        return StampCatalogValue::query()
            ->whereIn('stamp_catalog_entry_id', [
                $this->entryA1->id,
                $this->entryA2->id,
            ])
            ->orderBy('id')
            ->get()
            ->map(fn ($record) => $record->getAttributes())
            ->all();
    };

    $originalUnaffectedEntries = $unaffectedEntriesSnapshot();

    $originalUnaffectedValues = $unaffectedValuesSnapshot();

    /*
    |--------------------------------------------------------------------------
    | Update-payload
    |--------------------------------------------------------------------------
    */

    $payload = $this->updatePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-2100';

    $payload['catalog_values'] = [
        'postfris' => '75.75',
        'gestempeld' => '37.50',
    ];

    /*
    |--------------------------------------------------------------------------
    | Succesvolle update-aanvraag
    |--------------------------------------------------------------------------
    */

    $response = $this->put(
        route('backend.stamps.update', $this->stamp),
        $payload
    );

    $response->assertRedirect();

    $response->assertSessionHasNoErrors();

    $response->assertSessionHas('success');

    /*
    |--------------------------------------------------------------------------
    | Gewijzigde postzegel controleren
    |--------------------------------------------------------------------------
    */

    $updatedStamp = $this->stamp->fresh();

    expect($updatedStamp->name)
        ->toBe('Gewijzigde postzegel contexttest');

    expect($updatedStamp->country_id)
        ->toBe($this->country->id);

    /*
    |--------------------------------------------------------------------------
    | Gewijzigde catalogusvermelding
    |--------------------------------------------------------------------------
    */

    $updatedEntry = $this->entryB1->fresh();

    expect($updatedEntry->id)
        ->toBe($this->entryB1->id);

    expect($updatedEntry->stamp_id)
        ->toBe($this->stamp->id);

    expect($updatedEntry->catalog_edition_id)
        ->toBe($this->editionB1->id);

    expect($updatedEntry->catalog_number)
        ->toBe('B-2100');

    /*
    |--------------------------------------------------------------------------
    | Gewijzigde cataloguswaarden
    |--------------------------------------------------------------------------
    */

    $updatedPostfris = $this->valueB1Postfris->fresh();

    $updatedGestempeld = $this->valueB1Gestempeld->fresh();

    expect($updatedPostfris->id)
        ->toBe($this->valueB1Postfris->id);

    expect($updatedPostfris->catalog_value)
        ->toBe('75.75');

    expect($updatedPostfris->currency)
        ->toBe('USD');

    expect($updatedPostfris->needs_review)
        ->toBeTrue();

    expect($updatedGestempeld->id)
        ->toBe($this->valueB1Gestempeld->id);

    expect($updatedGestempeld->catalog_value)
        ->toBe('37.50');

    expect($updatedGestempeld->currency)
        ->toBe('USD');

    expect($updatedGestempeld->needs_review)
        ->toBeTrue();

    /*
    |--------------------------------------------------------------------------
    | Databaseaantallen na update
    |--------------------------------------------------------------------------
    */

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 1);

    $this->assertDatabaseCount('catalog_change_logs', 3);

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(2);

    /*
    |--------------------------------------------------------------------------
    | Geschiedenisbatch controleren
    |--------------------------------------------------------------------------
    */

    $batch = CatalogChangeBatch::query()->sole();

    expect($batch->catalog_edition_id)
        ->toBe($this->editionB1->id);

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
        ->toBe($this->catalogB->name);

    expect($batch->catalog_edition_name)
        ->toBe($this->editionB1->edition_name);

    expect($batch->logs()->count())
        ->toBe(3);

    /*
    |--------------------------------------------------------------------------
    | Geschiedenislog catalogusvermelding
    |--------------------------------------------------------------------------
    */

    $entryLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_entry')
        ->where('record_id', $updatedEntry->id)
        ->sole();

    expect($entryLog->action)
        ->toBe('updated');

    expect($entryLog->stamp_id)
        ->toBe($this->stamp->id);

    expect($entryLog->catalog_edition_id)
        ->toBe($this->editionB1->id);

    expect($entryLog->condition_id)
        ->toBeNull();

    expect($entryLog->old_values)
        ->toBe([
            'catalog_number' => 'B-100',
        ]);

    expect($entryLog->new_values)
        ->toBe([
            'catalog_number' => 'B-2100',
        ]);

    /*
    |--------------------------------------------------------------------------
    | Geschiedenislog postfrisse cataloguswaarde
    |--------------------------------------------------------------------------
    */

    $postfrisLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $updatedPostfris->id)
        ->sole();

    expect($postfrisLog->action)
        ->toBe('updated');

    expect($postfrisLog->stamp_id)
        ->toBe($this->stamp->id);

    expect($postfrisLog->condition_id)
        ->toBe($this->postfris->id);

    expect($postfrisLog->catalog_edition_id)
        ->toBe($this->editionB1->id);

    expect((float) $postfrisLog->old_values['catalog_value'])
        ->toBe(25.00);

    expect((float) $postfrisLog->new_values['catalog_value'])
        ->toBe(75.75);

    expect((bool) $postfrisLog->old_values['needs_review'])
        ->toBeFalse();

    expect((bool) $postfrisLog->new_values['needs_review'])
        ->toBeTrue();

    expect($postfrisLog->old_values)
        ->toHaveCount(2);

    expect($postfrisLog->new_values)
        ->toHaveCount(2);

    /*
    |--------------------------------------------------------------------------
    | Geschiedenislog gestempelde cataloguswaarde
    |--------------------------------------------------------------------------
    */

    $gestempeldLog = $batch->logs()
        ->where('record_type', 'stamp_catalog_value')
        ->where('record_id', $updatedGestempeld->id)
        ->sole();

    expect($gestempeldLog->action)
        ->toBe('updated');

    expect($gestempeldLog->stamp_id)
        ->toBe($this->stamp->id);

    expect($gestempeldLog->condition_id)
        ->toBe($this->gestempeld->id);

    expect($gestempeldLog->catalog_edition_id)
        ->toBe($this->editionB1->id);

    expect((float) $gestempeldLog->old_values['catalog_value'])
        ->toBe(12.50);

    expect((float) $gestempeldLog->new_values['catalog_value'])
        ->toBe(37.50);

    expect((bool) $gestempeldLog->old_values['needs_review'])
        ->toBeFalse();

    expect((bool) $gestempeldLog->new_values['needs_review'])
        ->toBeTrue();

    expect($gestempeldLog->old_values)
        ->toHaveCount(2);

    expect($gestempeldLog->new_values)
        ->toHaveCount(2);

    /*
    |--------------------------------------------------------------------------
    | Geschiedeniscontext na succesvolle update
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($this->history);

    expect(
        app(CatalogChangeHistoryService::class)
            ->getActiveBatch()
    )->toBeNull();

    /*
    |--------------------------------------------------------------------------
    | Volledige geschiedenisgegevens vastleggen
    |--------------------------------------------------------------------------
    */

    $historySnapshot = function () {
        return [
            'batches' => CatalogChangeBatch::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'logs' => CatalogChangeLog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    $originalHistory = $historySnapshot();

    /*
    |--------------------------------------------------------------------------
    | Niet-betrokken catalogusgegevens na update
    |--------------------------------------------------------------------------
    */

    expect($unaffectedEntriesSnapshot())
        ->toBe($originalUnaffectedEntries);

    expect($unaffectedValuesSnapshot())
        ->toBe($originalUnaffectedValues);

    /*
    |--------------------------------------------------------------------------
    | Rechtstreekse modelbewerkingen buiten geschiedeniscontext
    |--------------------------------------------------------------------------
    */

    $this->travel(1)->minutes();

    try {

        /*
        |--------------------------------------------------------------------------
        | 1. Catalogusnummer rechtstreeks wijzigen
        |--------------------------------------------------------------------------
        */

        $updatedEntry->update([
            'catalog_number' => 'B-2200',
        ]);

        expect($updatedEntry->fresh()->catalog_number)
            ->toBe('B-2200');

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $updatedEntry->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-2200',
        ]);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($this->history->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 2. Cataloguswaarde rechtstreeks wijzigen
        |--------------------------------------------------------------------------
        */

        $updatedPostfris->update([
            'catalog_value' => '85.75',
        ]);

        expect($updatedPostfris->fresh()->catalog_value)
            ->toBe('85.75');

        expect($updatedPostfris->fresh()->currency)
            ->toBe('USD');

        expect($updatedPostfris->fresh()->needs_review)
            ->toBeTrue();

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $updatedPostfris->id,
            'stamp_catalog_entry_id' => $updatedEntry->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 85.75,
            'currency' => 'USD',
            'needs_review' => true,
        ]);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($this->history->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 3. Cataloguswaarde rechtstreeks verwijderen
        |--------------------------------------------------------------------------
        */

        $deletedValueId = $updatedGestempeld->id;

        $updatedGestempeld->delete();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $deletedValueId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $updatedEntry->id,
            'condition_id' => $this->gestempeld->id,
        ]);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        expect($updatedEntry->values()->count())
            ->toBe(1);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(1);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($this->history->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 4. Cataloguswaarde rechtstreeks opnieuw aanmaken
        |--------------------------------------------------------------------------
        */

        $replacementValue = $updatedEntry->values()->create([
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => '42.50',
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        expect($replacementValue->exists)
            ->toBeTrue();

        expect($replacementValue->fresh()->catalog_value)
            ->toBe('42.50');

        expect($replacementValue->fresh()->currency)
            ->toBe('USD');

        expect($replacementValue->fresh()->needs_review)
            ->toBeFalse();

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $replacementValue->id,
            'stamp_catalog_entry_id' => $updatedEntry->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 42.50,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        expect($updatedEntry->values()->count())
            ->toBe(2);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($this->history->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | Definitieve databaseaantallen
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(1);

        /*
        |--------------------------------------------------------------------------
        | Niet-betrokken catalogusgegevens blijven behouden
        |--------------------------------------------------------------------------
        */

        expect($unaffectedEntriesSnapshot())
            ->toBe($originalUnaffectedEntries);

        expect($unaffectedValuesSnapshot())
            ->toBe($originalUnaffectedValues);

        /*
        |--------------------------------------------------------------------------
        | Definitieve catalogusgegevens
        |--------------------------------------------------------------------------
        */

        expect($updatedEntry->fresh()->catalog_number)
            ->toBe('B-2200');

        expect($updatedPostfris->fresh()->catalog_value)
            ->toBe('85.75');

        expect($replacementValue->fresh()->catalog_value)
            ->toBe('42.50');

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke geschiedenis blijft volledig behouden
        |--------------------------------------------------------------------------
        */

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($batch->fresh()->logs()->count())
            ->toBe(3);

        expect($entryLog->fresh()->old_values['catalog_number'])
            ->toBe('B-100');

        expect($entryLog->fresh()->new_values['catalog_number'])
            ->toBe('B-2100');

        expect(
            (float) $postfrisLog->fresh()->old_values['catalog_value']
        )->toBe(25.00);

        expect(
            (float) $postfrisLog->fresh()->new_values['catalog_value']
        )->toBe(75.75);

        expect(
            (float) $gestempeldLog->fresh()->old_values['catalog_value']
        )->toBe(12.50);

        expect(
            (float) $gestempeldLog->fresh()->new_values['catalog_value']
        )->toBe(37.50);

        /*
        |--------------------------------------------------------------------------
        | Definitieve controle geschiedeniscontext
        |--------------------------------------------------------------------------
        */

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history);

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

    } finally {

        $this->travelBack();
    }
});

test('de actieve geschiedenisbatch wordt na een mislukte store volledig opgeruimd', function () {

    /*
    |--------------------------------------------------------------------------
    | Uitgangssituatie
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    /*
    |--------------------------------------------------------------------------
    | Gemeenschappelijke databasesnapshot
    |--------------------------------------------------------------------------
    */

    $snapshot = function () {
        $models = [
            Stamp::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            CatalogChangeBatch::class,
            CatalogChangeLog::class,
        ];

        $data = [];

        foreach ($models as $model) {
            $data[$model] = $model::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    /*
    |--------------------------------------------------------------------------
    | Niet-betrokken catalogusgegevens vastleggen
    |--------------------------------------------------------------------------
    */

    $unaffectedSnapshot = function () {
        return [
            'entries' => StampCatalogEntry::query()
                ->whereIn('id', [
                    $this->entryA1->id,
                    $this->entryA2->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'values' => StampCatalogValue::query()
                ->whereIn('stamp_catalog_entry_id', [
                    $this->entryA1->id,
                    $this->entryA2->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    $originalUnaffectedData = $unaffectedSnapshot();

    /*
    |--------------------------------------------------------------------------
    | Store-payload
    |--------------------------------------------------------------------------
    */

    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-3100';

    $payload['catalog_values'] = [
        'postfris' => '85.75',
        'gestempeld' => '42.50',
    ];

    /*
    |--------------------------------------------------------------------------
    | Voorbereiding foutinjectie
    |--------------------------------------------------------------------------
    */

    $exceptionMessage = 'Geforceerde fout tijdens store contexttest H3';

    $listenerTriggered = false;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    /** @var int|null $capturedBatchId */
    $capturedBatchId = null;

    /** @var int|null $createdStampId */
    $createdStampId = null;

    /** @var int|null $createdEntryId */
    $createdEntryId = null;

    /** @var int|null $createdValueId */
    $createdValueId = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    $temporaryDispatcher = clone $originalDispatcher;

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {

        /*
        |--------------------------------------------------------------------------
        | Tijdelijke eventdispatcher activeren
        |--------------------------------------------------------------------------
        */

        StampCatalogValue::setEventDispatcher(
            $temporaryDispatcher
        );

        /*
        |--------------------------------------------------------------------------
        | Foutinjectie bij tweede cataloguswaarde
        |--------------------------------------------------------------------------
        */

        StampCatalogValue::creating(function (
            StampCatalogValue $value
        ) use (
            $exceptionMessage,
            &$listenerTriggered,
            &$capturedHistory,
            &$capturedBatchId,
            &$createdStampId,
            &$createdEntryId,
            &$createdValueId
        ) {

            if ((int) $value->condition_id !== $this->gestempeld->id) {
                return;
            }

            $entry = StampCatalogEntry::query()
                ->findOrFail($value->stamp_catalog_entry_id);

            if (
                (int) $entry->catalog_edition_id !== $this->editionB1->id
                || $entry->catalog_number !== 'B-3100'
            ) {
                return;
            }

            $listenerTriggered = true;

            /*
            |--------------------------------------------------------------------------
            | Nieuwe postzegel daadwerkelijk aanwezig
            |--------------------------------------------------------------------------
            */

            $newStamp = Stamp::query()
                ->findOrFail($entry->stamp_id);

            $createdStampId = $newStamp->id;

            $createdEntryId = $entry->id;

            expect($newStamp->id)
                ->not->toBe($this->stamp->id);

            expect($newStamp->name)
                ->toBe('Nieuwe postzegel contexttest');

            expect($newStamp->country_id)
                ->toBe($this->country->id);

            expect($entry->catalog_number)
                ->toBe('B-3100');

            /*
            |--------------------------------------------------------------------------
            | Eerste cataloguswaarde daadwerkelijk opgeslagen
            |--------------------------------------------------------------------------
            */

            $firstValue = $entry->values()
                ->where('condition_id', $this->postfris->id)
                ->sole();

            $createdValueId = $firstValue->id;

            expect($firstValue->catalog_value)
                ->toBe('85.75');

            expect($firstValue->currency)
                ->toBe('USD');

            expect($firstValue->needs_review)
                ->toBeFalse();

            /*
            |--------------------------------------------------------------------------
            | Tweede cataloguswaarde nog niet opgeslagen
            |--------------------------------------------------------------------------
            */

            expect($value->exists)
                ->toBeFalse();

            expect($value->getKey())
                ->toBeNull();

            expect($value->catalog_value)
                ->toBe('42.50');

            expect($value->currency)
                ->toBe('USD');

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $entry->id,
                'condition_id' => $this->gestempeld->id,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Databaseaantallen tijdens foutinjectie
            |--------------------------------------------------------------------------
            */

            $this->assertDatabaseCount('stamps', 2);

            $this->assertDatabaseCount('stamp_catalog_entries', 4);

            $this->assertDatabaseCount('stamp_catalog_values', 7);

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseCount('catalog_change_logs', 2);

            /*
            |--------------------------------------------------------------------------
            | Actieve geschiedeniscontext vastleggen
            |--------------------------------------------------------------------------
            */

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->not->toBeNull();

            $capturedBatchId = $activeBatch->id;

            $batch = CatalogChangeBatch::query()
                ->findOrFail($capturedBatchId);

            expect($batch->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($batch->user_id)
                ->toBe($this->user->id);

            expect($batch->user_name)
                ->toBe($this->user->name);

            expect($batch->operation)
                ->toBe('stamp_create');

            expect($batch->description)
                ->toBe(
                    'Catalogusgegevens bij het aanmaken van een postzegel'
                );

            expect($batch->catalog_name)
                ->toBe($this->catalogB->name);

            expect($batch->catalog_edition_name)
                ->toBe($this->editionB1->edition_name);

            expect($batch->logs()->count())
                ->toBe(2);

            /*
            |--------------------------------------------------------------------------
            | Eerste geschiedenislog controleren
            |--------------------------------------------------------------------------
            */

            $entryLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->where('record_id', $entry->id)
                ->sole();

            expect($entryLog->action)
                ->toBe('created');

            expect($entryLog->stamp_id)
                ->toBe($newStamp->id);

            expect($entryLog->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($entryLog->old_values)
                ->toBeNull();

            expect($entryLog->new_values['catalog_number'])
                ->toBe('B-3100');

            /*
            |--------------------------------------------------------------------------
            | Tweede geschiedenislog controleren
            |--------------------------------------------------------------------------
            */

            $valueLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->where('record_id', $firstValue->id)
                ->sole();

            expect($valueLog->action)
                ->toBe('created');

            expect($valueLog->stamp_id)
                ->toBe($newStamp->id);

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($valueLog->old_values)
                ->toBeNull();

            expect((float) $valueLog->new_values['catalog_value'])
                ->toBe(85.75);

            expect($valueLog->new_values['currency'])
                ->toBe('USD');

            /*
            |--------------------------------------------------------------------------
            | Geen geschiedenislog voor tweede cataloguswaarde
            |--------------------------------------------------------------------------
            */

            expect(
                $batch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('condition_id', $this->gestempeld->id)
                    ->exists()
            )->toBeFalse();

            /*
            |--------------------------------------------------------------------------
            | Geforceerde exception
            |--------------------------------------------------------------------------
            */

            throw new RuntimeException(
                $exceptionMessage
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Mislukte store-aanvraag uitvoeren
        |--------------------------------------------------------------------------
        */

        expect(fn () => $this->post(
            route('backend.stamps.store'),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

    } finally {

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke testomgeving herstellen
        |--------------------------------------------------------------------------
        */

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }

    /*
    |--------------------------------------------------------------------------
    | Foutinjectie daadwerkelijk uitgevoerd
    |--------------------------------------------------------------------------
    */

    expect($listenerTriggered)
        ->toBeTrue();

    expect($capturedHistory)
        ->not->toBeNull();

    expect($capturedBatchId)
        ->not->toBeNull();

    expect($createdStampId)
        ->not->toBeNull();

    expect($createdEntryId)
        ->not->toBeNull();

    expect($createdValueId)
        ->not->toBeNull();

    /*
    |--------------------------------------------------------------------------
    | Volledige rollback controleren
    |--------------------------------------------------------------------------
    */

    expect($snapshot())
        ->toBe($originalData);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    $this->assertDatabaseMissing('stamps', [
        'id' => $createdStampId,
    ]);

    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'id' => $createdEntryId,
    ]);

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $createdValueId,
    ]);

    $this->assertDatabaseMissing('catalog_change_batches', [
        'id' => $capturedBatchId,
    ]);

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    /*
    |--------------------------------------------------------------------------
    | Geschiedeniscontext na rollback controleren
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($capturedHistory->getActiveBatch())
        ->toBeNull();

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($capturedHistory);

    expect(
        app(CatalogChangeHistoryService::class)
            ->getActiveBatch()
    )->toBeNull();

    /*
    |--------------------------------------------------------------------------
    | Oorspronkelijke eventdispatcher controleren
    |--------------------------------------------------------------------------
    */

    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    /*
    |--------------------------------------------------------------------------
    | Geschiedenissnapshot na rollback
    |--------------------------------------------------------------------------
    */

    $historySnapshot = function () {
        return [
            'batches' => CatalogChangeBatch::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'logs' => CatalogChangeLog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    $originalHistory = $historySnapshot();

    expect($originalHistory)->toBe([
        'batches' => [],
        'logs' => [],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Bestaande catalogusgegevens opnieuw ophalen
    |--------------------------------------------------------------------------
    */

    $existingEntry = $this->entryB1->fresh();

    $existingPostfris = $this->valueB1Postfris->fresh();

    $existingGestempeld = $this->valueB1Gestempeld->fresh();

    expect($existingEntry->catalog_number)
        ->toBe('B-100');

    expect($existingPostfris->catalog_value)
        ->toBe('25.00');

    expect($existingGestempeld->catalog_value)
        ->toBe('12.50');

    /*
    |--------------------------------------------------------------------------
    | Rechtstreekse modelbewerkingen na rollback
    |--------------------------------------------------------------------------
    */

    $this->travel(2)->minutes();

    try {

        /*
        |--------------------------------------------------------------------------
        | 1. Catalogusnummer rechtstreeks wijzigen
        |--------------------------------------------------------------------------
        */

        $existingEntry->update([
            'catalog_number' => 'B-3200',
        ]);

        expect($existingEntry->fresh()->catalog_number)
            ->toBe('B-3200');

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $existingEntry->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-3200',
        ]);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 2. Cataloguswaarde rechtstreeks wijzigen
        |--------------------------------------------------------------------------
        */

        $existingPostfris->update([
            'catalog_value' => '95.75',
        ]);

        expect($existingPostfris->fresh()->catalog_value)
            ->toBe('95.75');

        expect($existingPostfris->fresh()->currency)
            ->toBe('USD');

        expect($existingPostfris->fresh()->needs_review)
            ->toBeTrue();

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $existingPostfris->id,
            'stamp_catalog_entry_id' => $existingEntry->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 95.75,
            'currency' => 'USD',
            'needs_review' => true,
        ]);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 3. Cataloguswaarde rechtstreeks verwijderen
        |--------------------------------------------------------------------------
        */

        $deletedValueId = $existingGestempeld->id;

        $existingGestempeld->delete();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $deletedValueId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $existingEntry->id,
            'condition_id' => $this->gestempeld->id,
        ]);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        expect($existingEntry->values()->count())
            ->toBe(1);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 4. Cataloguswaarde rechtstreeks opnieuw aanmaken
        |--------------------------------------------------------------------------
        */

        $replacementValue = $existingEntry->values()->create([
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => '47.50',
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        expect($replacementValue->exists)
            ->toBeTrue();

        expect($replacementValue->fresh()->catalog_value)
            ->toBe('47.50');

        expect($replacementValue->fresh()->currency)
            ->toBe('USD');

        expect($replacementValue->fresh()->needs_review)
            ->toBeFalse();

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $replacementValue->id,
            'stamp_catalog_entry_id' => $existingEntry->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 47.50,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        expect($existingEntry->values()->count())
            ->toBe(2);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | Definitieve databaseaantallen
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(1);

        /*
        |--------------------------------------------------------------------------
        | Niet-betrokken catalogusgegevens blijven behouden
        |--------------------------------------------------------------------------
        */

        expect($unaffectedSnapshot())
            ->toBe($originalUnaffectedData);

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke postzegel blijft ongewijzigd
        |--------------------------------------------------------------------------
        */

        expect(
            Stamp::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($originalData[Stamp::class]);

        /*
        |--------------------------------------------------------------------------
        | Definitieve catalogusgegevens
        |--------------------------------------------------------------------------
        */

        expect($existingEntry->fresh()->catalog_number)
            ->toBe('B-3200');

        expect($existingPostfris->fresh()->catalog_value)
            ->toBe('95.75');

        expect($replacementValue->fresh()->catalog_value)
            ->toBe('47.50');

        /*
        |--------------------------------------------------------------------------
        | Definitieve controle geschiedenisgegevens
        |--------------------------------------------------------------------------
        */

        expect($historySnapshot())
            ->toBe($originalHistory);

        /*
        |--------------------------------------------------------------------------
        | Definitieve controle geschiedeniscontext
        |--------------------------------------------------------------------------
        */

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history);

        expect(app(CatalogChangeHistoryService::class))
            ->toBe($capturedHistory);

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        expect(StampCatalogValue::getEventDispatcher())
            ->toBe($originalDispatcher);

    } finally {

        $this->travelBack();
    }
});

test('de actieve geschiedenisbatch wordt na een mislukte update volledig opgeruimd', function () {

    /*
    |--------------------------------------------------------------------------
    | Uitgangssituatie
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Gestempeld->fresh()->catalog_value)
        ->toBe('12.50');

    /*
    |--------------------------------------------------------------------------
    | Gemeenschappelijke databasesnapshot
    |--------------------------------------------------------------------------
    */

    $snapshot = function () {
        $models = [
            Stamp::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            CatalogChangeBatch::class,
            CatalogChangeLog::class,
        ];

        $data = [];

        foreach ($models as $model) {
            $data[$model] = $model::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    /*
    |--------------------------------------------------------------------------
    | Niet-betrokken catalogusgegevens vastleggen
    |--------------------------------------------------------------------------
    */

    $unaffectedSnapshot = function () {
        return [
            'entries' => StampCatalogEntry::query()
                ->whereIn('id', [
                    $this->entryA1->id,
                    $this->entryA2->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'values' => StampCatalogValue::query()
                ->whereIn('stamp_catalog_entry_id', [
                    $this->entryA1->id,
                    $this->entryA2->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    $originalUnaffectedData = $unaffectedSnapshot();

    /*
    |--------------------------------------------------------------------------
    | Oorspronkelijke catalogusgegevens vastleggen
    |--------------------------------------------------------------------------
    */

    $originalEntry = $this->entryB1
        ->fresh()
        ->getAttributes();

    $originalPostfris = $this->valueB1Postfris
        ->fresh()
        ->getAttributes();

    $originalGestempeld = $this->valueB1Gestempeld
        ->fresh()
        ->getAttributes();

    /*
    |--------------------------------------------------------------------------
    | Update-payload
    |--------------------------------------------------------------------------
    */

    $payload = $this->updatePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-4100';

    $payload['catalog_values'] = [
        'postfris' => '95.75',
        'gestempeld' => '47.50',
    ];

    /*
    |--------------------------------------------------------------------------
    | Voorbereiding foutinjectie
    |--------------------------------------------------------------------------
    */

    $exceptionMessage = 'Geforceerde fout tijdens update contexttest H4';

    $listenerTriggered = false;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    /** @var int|null $capturedBatchId */
    $capturedBatchId = null;

    $capturedLogIds = [];

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    $temporaryDispatcher = clone $originalDispatcher;

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {

        /*
        |--------------------------------------------------------------------------
        | Tijdelijke eventdispatcher activeren
        |--------------------------------------------------------------------------
        */

        StampCatalogValue::setEventDispatcher(
            $temporaryDispatcher
        );

        /*
        |--------------------------------------------------------------------------
        | Foutinjectie bij tweede cataloguswaarde
        |--------------------------------------------------------------------------
        */

        StampCatalogValue::updating(function (
            StampCatalogValue $value
        ) use (
            $exceptionMessage,
            $originalEntry,
            $originalPostfris,
            $originalGestempeld,
            &$listenerTriggered,
            &$capturedHistory,
            &$capturedBatchId,
            &$capturedLogIds
        ) {

            if (
                (int) $value->id !== $this->valueB1Gestempeld->id
                || (int) $value->stamp_catalog_entry_id !== $this->entryB1->id
            ) {
                return;
            }

            $listenerTriggered = true;

            /*
            |--------------------------------------------------------------------------
            | Postzegel daadwerkelijk gewijzigd
            |--------------------------------------------------------------------------
            */

            $updatedStamp = $this->stamp->fresh();

            expect($updatedStamp->name)
                ->toBe('Gewijzigde postzegel contexttest');

            expect($updatedStamp->country_id)
                ->toBe($this->country->id);

            /*
            |--------------------------------------------------------------------------
            | Catalogusvermelding daadwerkelijk gewijzigd
            |--------------------------------------------------------------------------
            */

            $updatedEntry = $this->entryB1->fresh();

            expect($updatedEntry->id)
                ->toBe($this->entryB1->id);

            expect($updatedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($updatedEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($updatedEntry->catalog_number)
                ->toBe('B-4100');

            expect($updatedEntry->getAttributes()['updated_at'])
                ->not->toBe($originalEntry['updated_at']);

            /*
            |--------------------------------------------------------------------------
            | Eerste cataloguswaarde daadwerkelijk gewijzigd
            |--------------------------------------------------------------------------
            */

            $updatedPostfris = $this->valueB1Postfris->fresh();

            expect($updatedPostfris->id)
                ->toBe($this->valueB1Postfris->id);

            expect($updatedPostfris->catalog_value)
                ->toBe('95.75');

            expect($updatedPostfris->currency)
                ->toBe('USD');

            expect($updatedPostfris->needs_review)
                ->toBeTrue();

            expect($updatedPostfris->getAttributes()['updated_at'])
                ->not->toBe($originalPostfris['updated_at']);

            /*
            |--------------------------------------------------------------------------
            | Tweede cataloguswaarde nog niet opgeslagen
            |--------------------------------------------------------------------------
            */

            expect($value->exists)
                ->toBeTrue();

            expect($value->isDirty('catalog_value'))
                ->toBeTrue();

            expect($value->catalog_value)
                ->toBe('47.50');

            expect($value->currency)
                ->toBe('USD');

            expect($value->needs_review)
                ->toBeTrue();

            $storedGestempeld = StampCatalogValue::query()
                ->findOrFail($value->id);

            expect($storedGestempeld->catalog_value)
                ->toBe('12.50');

            expect($storedGestempeld->currency)
                ->toBe('USD');

            expect($storedGestempeld->needs_review)
                ->toBeFalse();

            expect($storedGestempeld->getAttributes())
                ->toBe($originalGestempeld);

            /*
            |--------------------------------------------------------------------------
            | Databaseaantallen tijdens foutinjectie
            |--------------------------------------------------------------------------
            */

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseCount('catalog_change_logs', 2);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(1);

            /*
            |--------------------------------------------------------------------------
            | Actieve geschiedeniscontext vastleggen
            |--------------------------------------------------------------------------
            */

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->not->toBeNull();

            $capturedBatchId = $activeBatch->id;

            $batch = CatalogChangeBatch::query()
                ->findOrFail($capturedBatchId);

            expect($batch->catalog_edition_id)
                ->toBe($this->editionB1->id);

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
                ->toBe($this->catalogB->name);

            expect($batch->catalog_edition_name)
                ->toBe($this->editionB1->edition_name);

            expect($batch->logs()->count())
                ->toBe(2);

            /*
            |--------------------------------------------------------------------------
            | Geschiedenislog catalogusvermelding
            |--------------------------------------------------------------------------
            */

            $entryLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->where('record_id', $updatedEntry->id)
                ->sole();

            expect($entryLog->action)
                ->toBe('updated');

            expect($entryLog->stamp_id)
                ->toBe($this->stamp->id);

            expect($entryLog->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->old_values)
                ->toBe([
                    'catalog_number' => 'B-100',
                ]);

            expect($entryLog->new_values)
                ->toBe([
                    'catalog_number' => 'B-4100',
                ]);

            /*
            |--------------------------------------------------------------------------
            | Geschiedenislog postfrisse cataloguswaarde
            |--------------------------------------------------------------------------
            */

            $postfrisLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->where('record_id', $updatedPostfris->id)
                ->sole();

            expect($postfrisLog->action)
                ->toBe('updated');

            expect($postfrisLog->stamp_id)
                ->toBe($this->stamp->id);

            expect($postfrisLog->condition_id)
                ->toBe($this->postfris->id);

            expect($postfrisLog->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect((float) $postfrisLog->old_values['catalog_value'])
                ->toBe(25.00);

            expect((float) $postfrisLog->new_values['catalog_value'])
                ->toBe(95.75);

            expect((bool) $postfrisLog->old_values['needs_review'])
                ->toBeFalse();

            expect((bool) $postfrisLog->new_values['needs_review'])
                ->toBeTrue();

            expect($postfrisLog->old_values)
                ->toHaveCount(2);

            expect($postfrisLog->new_values)
                ->toHaveCount(2);

            /*
            |--------------------------------------------------------------------------
            | Geen geschiedenislog voor tweede cataloguswaarde
            |--------------------------------------------------------------------------
            */

            expect(
                $batch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $value->id)
                    ->exists()
            )->toBeFalse();

            /*
            |--------------------------------------------------------------------------
            | Opgeslagen geschiedenislog-ID's vastleggen
            |--------------------------------------------------------------------------
            */

            $capturedLogIds = $batch->logs()
                ->orderBy('id')
                ->pluck('id')
                ->all();

            expect($capturedLogIds)
                ->toHaveCount(2);

            /*
            |--------------------------------------------------------------------------
            | Geforceerde exception
            |--------------------------------------------------------------------------
            */

            throw new RuntimeException(
                $exceptionMessage
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Mislukte update-aanvraag uitvoeren
        |--------------------------------------------------------------------------
        */

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

    } finally {

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke testomgeving herstellen
        |--------------------------------------------------------------------------
        */

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }

    /*
    |--------------------------------------------------------------------------
    | Foutinjectie daadwerkelijk uitgevoerd
    |--------------------------------------------------------------------------
    */

    expect($listenerTriggered)
        ->toBeTrue();

    expect($capturedHistory)
        ->not->toBeNull();

    expect($capturedBatchId)
        ->not->toBeNull();

    expect($capturedLogIds)
        ->toHaveCount(2);

    /*
    |--------------------------------------------------------------------------
    | Volledige rollback controleren
    |--------------------------------------------------------------------------
    */

    expect($snapshot())
        ->toBe($originalData);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    $this->assertDatabaseMissing('catalog_change_batches', [
        'id' => $capturedBatchId,
    ]);

    foreach ($capturedLogIds as $logId) {
        $this->assertDatabaseMissing('catalog_change_logs', [
            'id' => $logId,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Oorspronkelijke catalogusgegevens volledig hersteld
    |--------------------------------------------------------------------------
    */

    expect($this->entryB1->fresh()->getAttributes())
        ->toBe($originalEntry);

    expect($this->valueB1Postfris->fresh()->getAttributes())
        ->toBe($originalPostfris);

    expect($this->valueB1Gestempeld->fresh()->getAttributes())
        ->toBe($originalGestempeld);

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    /*
    |--------------------------------------------------------------------------
    | Geschiedeniscontext na rollback controleren
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($capturedHistory->getActiveBatch())
        ->toBeNull();

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($capturedHistory);

    expect(
        app(CatalogChangeHistoryService::class)
            ->getActiveBatch()
    )->toBeNull();

    /*
    |--------------------------------------------------------------------------
    | Oorspronkelijke eventdispatcher controleren
    |--------------------------------------------------------------------------
    */

    expect(StampCatalogValue::getEventDispatcher())
        ->toBe($originalDispatcher);

    /*
    |--------------------------------------------------------------------------
    | Geschiedenissnapshot na rollback
    |--------------------------------------------------------------------------
    */

    $historySnapshot = function () {
        return [
            'batches' => CatalogChangeBatch::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'logs' => CatalogChangeLog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    $originalHistory = $historySnapshot();

    expect($originalHistory)->toBe([
        'batches' => [],
        'logs' => [],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Bestaande catalogusgegevens opnieuw ophalen
    |--------------------------------------------------------------------------
    */

    $existingEntry = $this->entryB1->fresh();

    $existingPostfris = $this->valueB1Postfris->fresh();

    $existingGestempeld = $this->valueB1Gestempeld->fresh();

    expect($existingEntry->catalog_number)
        ->toBe('B-100');

    expect($existingPostfris->catalog_value)
        ->toBe('25.00');

    expect($existingGestempeld->catalog_value)
        ->toBe('12.50');

    /*
    |--------------------------------------------------------------------------
    | Rechtstreekse modelbewerkingen na rollback
    |--------------------------------------------------------------------------
    */

    $this->travel(2)->minutes();

    try {

        /*
        |--------------------------------------------------------------------------
        | 1. Catalogusnummer rechtstreeks wijzigen
        |--------------------------------------------------------------------------
        */

        $existingEntry->update([
            'catalog_number' => 'B-4200',
        ]);

        expect($existingEntry->fresh()->catalog_number)
            ->toBe('B-4200');

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $existingEntry->id,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-4200',
        ]);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 2. Cataloguswaarde rechtstreeks wijzigen
        |--------------------------------------------------------------------------
        */

        $existingPostfris->update([
            'catalog_value' => '105.75',
        ]);

        expect($existingPostfris->fresh()->catalog_value)
            ->toBe('105.75');

        expect($existingPostfris->fresh()->currency)
            ->toBe('USD');

        expect($existingPostfris->fresh()->needs_review)
            ->toBeTrue();

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $existingPostfris->id,
            'stamp_catalog_entry_id' => $existingEntry->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 105.75,
            'currency' => 'USD',
            'needs_review' => true,
        ]);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 3. Cataloguswaarde rechtstreeks verwijderen
        |--------------------------------------------------------------------------
        */

        $deletedValueId = $existingGestempeld->id;

        $existingGestempeld->delete();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $deletedValueId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $existingEntry->id,
            'condition_id' => $this->gestempeld->id,
        ]);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        expect($existingEntry->values()->count())
            ->toBe(1);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | 4. Cataloguswaarde rechtstreeks opnieuw aanmaken
        |--------------------------------------------------------------------------
        */

        $replacementValue = $existingEntry->values()->create([
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => '52.50',
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        expect($replacementValue->exists)
            ->toBeTrue();

        expect($replacementValue->fresh()->catalog_value)
            ->toBe('52.50');

        expect($replacementValue->fresh()->currency)
            ->toBe('USD');

        expect($replacementValue->fresh()->needs_review)
            ->toBeFalse();

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $replacementValue->id,
            'stamp_catalog_entry_id' => $existingEntry->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 52.50,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        expect($existingEntry->values()->count())
            ->toBe(2);

        expect($historySnapshot())
            ->toBe($originalHistory);

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        /*
        |--------------------------------------------------------------------------
        | Definitieve databaseaantallen
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(1);

        /*
        |--------------------------------------------------------------------------
        | Niet-betrokken catalogusgegevens blijven behouden
        |--------------------------------------------------------------------------
        */

        expect($unaffectedSnapshot())
            ->toBe($originalUnaffectedData);

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke postzegel blijft ongewijzigd
        |--------------------------------------------------------------------------
        */

        expect(
            Stamp::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($originalData[Stamp::class]);

        /*
        |--------------------------------------------------------------------------
        | Definitieve catalogusgegevens
        |--------------------------------------------------------------------------
        */

        expect($existingEntry->fresh()->catalog_number)
            ->toBe('B-4200');

        expect($existingPostfris->fresh()->catalog_value)
            ->toBe('105.75');

        expect($replacementValue->fresh()->catalog_value)
            ->toBe('52.50');

        /*
        |--------------------------------------------------------------------------
        | Definitieve controle geschiedenisgegevens
        |--------------------------------------------------------------------------
        */

        expect($historySnapshot())
            ->toBe($originalHistory);

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $capturedBatchId,
        ]);

        foreach ($capturedLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Definitieve controle geschiedeniscontext
        |--------------------------------------------------------------------------
        */

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history);

        expect(app(CatalogChangeHistoryService::class))
            ->toBe($capturedHistory);

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        expect(StampCatalogValue::getEventDispatcher())
            ->toBe($originalDispatcher);

    } finally {

        $this->travelBack();
    }
});

test('opeenvolgende succesvolle aanvragen krijgen volledig gescheiden geschiedenisregistraties', function () {

    /*
    |--------------------------------------------------------------------------
    | Uitgangssituatie
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($this->history);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie volledige geschiedenissnapshot
    |--------------------------------------------------------------------------
    */

    $historySnapshot = function () {
        return [
            'batches' => CatalogChangeBatch::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'logs' => CatalogChangeLog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie afzonderlijke geschiedenisbatches vastleggen
    |--------------------------------------------------------------------------
    */

    $batchSnapshot = function (int $batchId) {
        $batch = CatalogChangeBatch::query()
            ->findOrFail($batchId);

        return [
            'batch' => $batch->getAttributes(),

            'logs' => $batch->logs()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie geschiedeniscontext controleren
    |--------------------------------------------------------------------------
    */

    $assertCleanContext = function () {

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history);

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie geschiedenisbatch controleren
    |--------------------------------------------------------------------------
    */

    $assertBatch = function (
        CatalogChangeBatch $batch,
        CatalogEdition $edition,
        Catalog $catalog,
        string $operation,
        string $description
    ) {

        expect($batch->catalog_edition_id)
            ->toBe($edition->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe($operation);

        expect($batch->description)
            ->toBe($description);

        expect($batch->catalog_name)
            ->toBe($catalog->name);

        expect($batch->catalog_edition_name)
            ->toBe($edition->edition_name);

        expect($batch->catalogEdition->id)
            ->toBe($edition->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        expect($batch->logs()->count())
            ->toBe(3);
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie algemene geschiedenisloggegevens controleren
    |--------------------------------------------------------------------------
    */

    $assertLog = function (
        CatalogChangeLog $log,
        CatalogChangeBatch $batch,
        Stamp $stamp,
        string $recordType,
        int $recordId,
        string $action,
        ?int $conditionId
    ) {

        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($batch->catalog_edition_id);

        expect($log->stamp_id)
            ->toBe($stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->record_type)
            ->toBe($recordType);

        expect($log->record_id)
            ->toBe($recordId);

        expect($log->action)
            ->toBe($action);

        expect($log->condition_id)
            ->toBe($conditionId);

        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($batch->catalog_edition_id);

        expect($log->stamp->id)
            ->toBe($stamp->id);

        if ($conditionId === null) {

            expect($log->condition)
                ->toBeNull();

        } else {

            expect($log->condition->id)
                ->toBe($conditionId);
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie aangemaakte cataloguswaardelog controleren
    |--------------------------------------------------------------------------
    */

    $assertCreatedValueLog = function (
        CatalogChangeLog $log,
        StampCatalogValue $value,
        float $amount,
        string $currency
    ) {

        expect($log->old_values)
            ->toBeNull();

        expect($log->new_values)
            ->toBeArray();

        expect((float) $log->new_values['catalog_value'])
            ->toBe($amount);

        expect($log->new_values['currency'])
            ->toBe($currency);

        expect((int) $log->new_values['condition_id'])
            ->toBe($value->condition_id);

        expect((int) $log->new_values['stamp_catalog_entry_id'])
            ->toBe($value->stamp_catalog_entry_id);
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie gewijzigde cataloguswaardelog controleren
    |--------------------------------------------------------------------------
    */

    $assertUpdatedValueLog = function (
        CatalogChangeLog $log,
        float $oldAmount,
        float $newAmount
    ) {

        expect($log->old_values)
            ->toBeArray();

        expect($log->new_values)
            ->toBeArray();

        expect($log->old_values)
            ->toHaveCount(2);

        expect($log->new_values)
            ->toHaveCount(2);

        expect((float) $log->old_values['catalog_value'])
            ->toBe($oldAmount);

        expect((float) $log->new_values['catalog_value'])
            ->toBe($newAmount);

        expect((bool) $log->old_values['needs_review'])
            ->toBeFalse();

        expect((bool) $log->new_values['needs_review'])
            ->toBeTrue();
    };

    /*
    |--------------------------------------------------------------------------
    | Oorspronkelijke postzegelgegevens vastleggen
    |--------------------------------------------------------------------------
    */

    $originalStampSnapshot = $this->stamp
        ->fresh()
        ->getAttributes();

    $originalEntriesSnapshot = StampCatalogEntry::query()
        ->where('stamp_id', $this->stamp->id)
        ->orderBy('id')
        ->get()
        ->map(fn ($record) => $record->getAttributes())
        ->all();

    $originalValuesSnapshot = StampCatalogValue::query()
        ->whereIn('stamp_catalog_entry_id', [
            $this->entryA1->id,
            $this->entryA2->id,
            $this->entryB1->id,
        ])
        ->orderBy('id')
        ->get()
        ->map(fn ($record) => $record->getAttributes())
        ->all();

    /*
    |--------------------------------------------------------------------------
    | Niet-betrokken catalogusgegevens A2 en B1 vastleggen
    |--------------------------------------------------------------------------
    */

    $unaffectedSnapshot = function () {
        return [
            'entries' => StampCatalogEntry::query()
                ->whereIn('id', [
                    $this->entryA2->id,
                    $this->entryB1->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'values' => StampCatalogValue::query()
                ->whereIn('stamp_catalog_entry_id', [
                    $this->entryA2->id,
                    $this->entryB1->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    $originalUnaffectedData = $unaffectedSnapshot();

    /*
    |--------------------------------------------------------------------------
    | Aanvraag 1 — Nieuwe postzegel aanmaken in cataloguseditie B1
    |--------------------------------------------------------------------------
    */

    $storePayload = $this->storePayload;

    $storePayload['catalog_id'] = $this->catalogB->id;

    $storePayload['catalog_edition_id'] = $this->editionB1->id;

    $storePayload['catalog_number'] = 'B-5100';

    $storePayload['catalog_values'] = [
        'postfris' => '55.75',
        'gestempeld' => '27.50',
    ];

    $this->travel(1)->minutes();

    try {

        $firstResponse = $this->post(
            route('backend.stamps.store'),
            $storePayload
        );

        $firstResponse->assertRedirect();

        $firstResponse->assertSessionHasNoErrors();

        $firstResponse->assertSessionHas('success');

        /*
        |--------------------------------------------------------------------------
        | Aangemaakte postzegel controleren
        |--------------------------------------------------------------------------
        */

        $newStamp = Stamp::query()
            ->where('id', '!=', $this->stamp->id)
            ->sole();

        expect($newStamp->name)
            ->toBe('Nieuwe postzegel contexttest');

        expect($newStamp->country_id)
            ->toBe($this->country->id);

        /*
        |--------------------------------------------------------------------------
        | Aangemaakte catalogusvermelding controleren
        |--------------------------------------------------------------------------
        */

        $newEntry = $newStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionB1->id)
            ->sole();

        expect($newEntry->catalog_number)
            ->toBe('B-5100');

        expect($newEntry->stamp_id)
            ->toBe($newStamp->id);

        expect($newEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        /*
        |--------------------------------------------------------------------------
        | Aangemaakte cataloguswaarden controleren
        |--------------------------------------------------------------------------
        */

        $newPostfris = $newEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $newGestempeld = $newEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($newPostfris->catalog_value)
            ->toBe('55.75');

        expect($newPostfris->currency)
            ->toBe('USD');

        expect($newPostfris->needs_review)
            ->toBeFalse();

        expect($newGestempeld->catalog_value)
            ->toBe('27.50');

        expect($newGestempeld->currency)
            ->toBe('USD');

        expect($newGestempeld->needs_review)
            ->toBeFalse();

        /*
        |--------------------------------------------------------------------------
        | Databaseaantallen na aanvraag 1
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenisbatch controleren
        |--------------------------------------------------------------------------
        */

        $firstBatch = CatalogChangeBatch::query()->sole();

        $assertBatch(
            $firstBatch,
            $this->editionB1,
            $this->catalogB,
            'stamp_create',
            'Catalogusgegevens bij het aanmaken van een postzegel'
        );

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenislog catalogusvermelding
        |--------------------------------------------------------------------------
        */

        $firstEntryLog = $firstBatch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $newEntry->id)
            ->sole();

        $assertLog(
            $firstEntryLog,
            $firstBatch,
            $newStamp,
            'stamp_catalog_entry',
            $newEntry->id,
            'created',
            null
        );

        expect($firstEntryLog->old_values)
            ->toBeNull();

        expect($firstEntryLog->new_values['catalog_number'])
            ->toBe('B-5100');

        expect((int) $firstEntryLog->new_values['stamp_id'])
            ->toBe($newStamp->id);

        expect((int) $firstEntryLog->new_values['catalog_edition_id'])
            ->toBe($this->editionB1->id);

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenislog postfris
        |--------------------------------------------------------------------------
        */

        $firstPostfrisLog = $firstBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $newPostfris->id)
            ->sole();

        $assertLog(
            $firstPostfrisLog,
            $firstBatch,
            $newStamp,
            'stamp_catalog_value',
            $newPostfris->id,
            'created',
            $this->postfris->id
        );

        $assertCreatedValueLog(
            $firstPostfrisLog,
            $newPostfris,
            55.75,
            'USD'
        );

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenislog gestempeld
        |--------------------------------------------------------------------------
        */

        $firstGestempeldLog = $firstBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $newGestempeld->id)
            ->sole();

        $assertLog(
            $firstGestempeldLog,
            $firstBatch,
            $newStamp,
            'stamp_catalog_value',
            $newGestempeld->id,
            'created',
            $this->gestempeld->id
        );

        $assertCreatedValueLog(
            $firstGestempeldLog,
            $newGestempeld,
            27.50,
            'USD'
        );

        /*
        |--------------------------------------------------------------------------
        | Geschiedeniscontext na aanvraag 1
        |--------------------------------------------------------------------------
        */

        $assertCleanContext();

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenisregistratie volledig vastleggen
        |--------------------------------------------------------------------------
        */

        $firstBatchData = $batchSnapshot($firstBatch->id);

        $firstHistory = $historySnapshot();

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke postzegelgegevens blijven behouden
        |--------------------------------------------------------------------------
        */

        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampSnapshot);

        expect(
            StampCatalogEntry::query()
                ->where('stamp_id', $this->stamp->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($originalEntriesSnapshot);

        expect(
            StampCatalogValue::query()
                ->whereIn('stamp_catalog_entry_id', [
                    $this->entryA1->id,
                    $this->entryA2->id,
                    $this->entryB1->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($originalValuesSnapshot);

        /*
        |--------------------------------------------------------------------------
        | Aanvraag 2 — Dezelfde postzegel en cataloguseditie wijzigen
        |--------------------------------------------------------------------------
        */

        $this->travel(1)->minutes();

        $secondPayload = $this->updatePayload;

        $secondPayload['catalog_id'] = $this->catalogB->id;

        $secondPayload['catalog_edition_id'] = $this->editionB1->id;

        $secondPayload['catalog_number'] = 'B-5200';

        $secondPayload['catalog_values'] = [
            'postfris' => '65.75',
            'gestempeld' => '32.50',
        ];

        $secondPayload['translations']['nl']['name'] =
            'Gewijzigde nieuwe postzegel contexttest H5';

        $secondResponse = $this->put(
            route('backend.stamps.update', $newStamp),
            $secondPayload
        );

        $secondResponse->assertRedirect();

        $secondResponse->assertSessionHasNoErrors();

        $secondResponse->assertSessionHas('success');

        /*
        |--------------------------------------------------------------------------
        | Gewijzigde nieuwe postzegel controleren
        |--------------------------------------------------------------------------
        */

        expect($newStamp->fresh()->name)
            ->toBe('Gewijzigde nieuwe postzegel contexttest H5');

        expect($newStamp->fresh()->country_id)
            ->toBe($this->country->id);

        /*
        |--------------------------------------------------------------------------
        | Gewijzigde catalogusvermelding controleren
        |--------------------------------------------------------------------------
        */

        $updatedEntry = $newStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionB1->id)
            ->sole();

        expect($updatedEntry->id)
            ->toBe($newEntry->id);

        expect($updatedEntry->catalog_number)
            ->toBe('B-5200');

        /*
        |--------------------------------------------------------------------------
        | Gewijzigde cataloguswaarden controleren
        |--------------------------------------------------------------------------
        */

        $updatedPostfris = $updatedEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $updatedGestempeld = $updatedEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($updatedPostfris->id)
            ->toBe($newPostfris->id);

        expect($updatedPostfris->catalog_value)
            ->toBe('65.75');

        expect($updatedPostfris->currency)
            ->toBe('USD');

        expect($updatedPostfris->needs_review)
            ->toBeTrue();

        expect($updatedGestempeld->id)
            ->toBe($newGestempeld->id);

        expect($updatedGestempeld->catalog_value)
            ->toBe('32.50');

        expect($updatedGestempeld->currency)
            ->toBe('USD');

        expect($updatedGestempeld->needs_review)
            ->toBeTrue();

        /*
        |--------------------------------------------------------------------------
        | Databaseaantallen na aanvraag 2
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalog_change_batches', 2);

        $this->assertDatabaseCount('catalog_change_logs', 6);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(2);

        /*
        |--------------------------------------------------------------------------
        | Tweede geschiedenisbatch controleren
        |--------------------------------------------------------------------------
        */

        $secondBatch = CatalogChangeBatch::query()
            ->where('id', '!=', $firstBatch->id)
            ->sole();

        expect($secondBatch->id)
            ->not->toBe($firstBatch->id);

        expect($secondBatch->catalog_edition_id)
            ->toBe($firstBatch->catalog_edition_id);

        expect($secondBatch->created_at->gt($firstBatch->created_at))
            ->toBeTrue();

        $assertBatch(
            $secondBatch,
            $this->editionB1,
            $this->catalogB,
            'stamp_update',
            'Catalogusgegevens bij het wijzigen van een postzegel'
        );

        /*
        |--------------------------------------------------------------------------
        | Tweede geschiedenislog catalogusvermelding
        |--------------------------------------------------------------------------
        */

        $secondEntryLog = $secondBatch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $updatedEntry->id)
            ->sole();

        $assertLog(
            $secondEntryLog,
            $secondBatch,
            $newStamp,
            'stamp_catalog_entry',
            $updatedEntry->id,
            'updated',
            null
        );

        expect($secondEntryLog->old_values)
            ->toBe([
                'catalog_number' => 'B-5100',
            ]);

        expect($secondEntryLog->new_values)
            ->toBe([
                'catalog_number' => 'B-5200',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Tweede geschiedenislog postfris
        |--------------------------------------------------------------------------
        */

        $secondPostfrisLog = $secondBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $updatedPostfris->id)
            ->sole();

        $assertLog(
            $secondPostfrisLog,
            $secondBatch,
            $newStamp,
            'stamp_catalog_value',
            $updatedPostfris->id,
            'updated',
            $this->postfris->id
        );

        $assertUpdatedValueLog(
            $secondPostfrisLog,
            55.75,
            65.75
        );

        /*
        |--------------------------------------------------------------------------
        | Tweede geschiedenislog gestempeld
        |--------------------------------------------------------------------------
        */

        $secondGestempeldLog = $secondBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $updatedGestempeld->id)
            ->sole();

        $assertLog(
            $secondGestempeldLog,
            $secondBatch,
            $newStamp,
            'stamp_catalog_value',
            $updatedGestempeld->id,
            'updated',
            $this->gestempeld->id
        );

        $assertUpdatedValueLog(
            $secondGestempeldLog,
            27.50,
            32.50
        );

        /*
        |--------------------------------------------------------------------------
        | Aansluitende geschiedeniswaarden controleren
        |--------------------------------------------------------------------------
        */

        expect($secondEntryLog->old_values['catalog_number'])
            ->toBe($firstEntryLog->new_values['catalog_number']);

        expect((float) $secondPostfrisLog->old_values['catalog_value'])
            ->toBe((float) $firstPostfrisLog->new_values['catalog_value']);

        expect((float) $secondGestempeldLog->old_values['catalog_value'])
            ->toBe((float) $firstGestempeldLog->new_values['catalog_value']);

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenisregistratie blijft volledig ongewijzigd
        |--------------------------------------------------------------------------
        */

        expect($batchSnapshot($firstBatch->id))
            ->toBe($firstBatchData);

        $secondHistory = $historySnapshot();

        expect(array_slice($secondHistory['batches'], 0, 1))
            ->toBe($firstHistory['batches']);

        expect(array_slice($secondHistory['logs'], 0, 3))
            ->toBe($firstHistory['logs']);

        /*
        |--------------------------------------------------------------------------
        | Geschiedeniscontext na aanvraag 2
        |--------------------------------------------------------------------------
        */

        $assertCleanContext();

        /*
        |--------------------------------------------------------------------------
        | Tweede geschiedenisregistratie volledig vastleggen
        |--------------------------------------------------------------------------
        */

        $secondBatchData = $batchSnapshot($secondBatch->id);

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke postzegelgegevens blijven behouden
        |--------------------------------------------------------------------------
        */

        expect($this->stamp->fresh()->getAttributes())
            ->toBe($originalStampSnapshot);

        expect(
            StampCatalogEntry::query()
                ->where('stamp_id', $this->stamp->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($originalEntriesSnapshot);

        expect(
            StampCatalogValue::query()
                ->whereIn('stamp_catalog_entry_id', [
                    $this->entryA1->id,
                    $this->entryA2->id,
                    $this->entryB1->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($originalValuesSnapshot);

        /*
        |--------------------------------------------------------------------------
        | Gegevens nieuwe postzegel vastleggen
        |--------------------------------------------------------------------------
        */

        $newStampSnapshot = $newStamp->fresh()->getAttributes();

        $newEntrySnapshot = $updatedEntry->fresh()->getAttributes();

        $newValuesSnapshot = $updatedEntry->values()
            ->orderBy('id')
            ->get()
            ->map(fn ($record) => $record->getAttributes())
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Aanvraag 3 — Oorspronkelijke postzegel wijzigen in editie A1
        |--------------------------------------------------------------------------
        */

        $this->travel(1)->minutes();

        $thirdPayload = $this->updatePayload;

        $thirdPayload['catalog_id'] = $this->catalogA->id;

        $thirdPayload['catalog_edition_id'] = $this->editionA1->id;

        $thirdPayload['catalog_number'] = 'A-5300';

        $thirdPayload['catalog_values'] = [
            'postfris' => '75.75',
            'gestempeld' => '37.50',
        ];

        $thirdPayload['translations']['nl']['name'] =
            'Gewijzigde oorspronkelijke postzegel contexttest H5';

        $thirdResponse = $this->put(
            route('backend.stamps.update', $this->stamp),
            $thirdPayload
        );

        $thirdResponse->assertRedirect();

        $thirdResponse->assertSessionHasNoErrors();

        $thirdResponse->assertSessionHas('success');

        /*
        |--------------------------------------------------------------------------
        | Gewijzigde oorspronkelijke postzegel controleren
        |--------------------------------------------------------------------------
        */

        expect($this->stamp->fresh()->name)
            ->toBe('Gewijzigde oorspronkelijke postzegel contexttest H5');

        expect($this->stamp->fresh()->country_id)
            ->toBe($this->country->id);

        /*
        |--------------------------------------------------------------------------
        | Gewijzigde catalogusvermelding A1 controleren
        |--------------------------------------------------------------------------
        */

        $updatedEntryA1 = $this->entryA1->fresh();

        expect($updatedEntryA1->id)
            ->toBe($this->entryA1->id);

        expect($updatedEntryA1->stamp_id)
            ->toBe($this->stamp->id);

        expect($updatedEntryA1->catalog_edition_id)
            ->toBe($this->editionA1->id);

        expect($updatedEntryA1->catalog_number)
            ->toBe('A-5300');

        /*
        |--------------------------------------------------------------------------
        | Gewijzigde cataloguswaarden A1 controleren
        |--------------------------------------------------------------------------
        */

        $updatedA1Postfris = $this->valueA1Postfris->fresh();

        $updatedA1Gestempeld = $this->valueA1Gestempeld->fresh();

        expect($updatedA1Postfris->id)
            ->toBe($this->valueA1Postfris->id);

        expect($updatedA1Postfris->catalog_value)
            ->toBe('75.75');

        expect($updatedA1Postfris->currency)
            ->toBe('EUR');

        expect($updatedA1Postfris->needs_review)
            ->toBeTrue();

        expect($updatedA1Gestempeld->id)
            ->toBe($this->valueA1Gestempeld->id);

        expect($updatedA1Gestempeld->catalog_value)
            ->toBe('37.50');

        expect($updatedA1Gestempeld->currency)
            ->toBe('EUR');

        expect($updatedA1Gestempeld->needs_review)
            ->toBeTrue();

        /*
        |--------------------------------------------------------------------------
        | Databaseaantallen na aanvraag 3
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 9);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(4);

        /*
        |--------------------------------------------------------------------------
        | Derde geschiedenisbatch controleren
        |--------------------------------------------------------------------------
        */

        $thirdBatch = CatalogChangeBatch::query()
            ->whereNotIn('id', [
                $firstBatch->id,
                $secondBatch->id,
            ])
            ->sole();

        expect($thirdBatch->id)
            ->not->toBe($firstBatch->id);

        expect($thirdBatch->id)
            ->not->toBe($secondBatch->id);

        expect($thirdBatch->catalog_edition_id)
            ->not->toBe($firstBatch->catalog_edition_id);

        expect($thirdBatch->created_at->gt($secondBatch->created_at))
            ->toBeTrue();

        $assertBatch(
            $thirdBatch,
            $this->editionA1,
            $this->catalogA,
            'stamp_update',
            'Catalogusgegevens bij het wijzigen van een postzegel'
        );

        /*
        |--------------------------------------------------------------------------
        | Derde geschiedenislog catalogusvermelding
        |--------------------------------------------------------------------------
        */

        $thirdEntryLog = $thirdBatch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $updatedEntryA1->id)
            ->sole();

        $assertLog(
            $thirdEntryLog,
            $thirdBatch,
            $this->stamp,
            'stamp_catalog_entry',
            $updatedEntryA1->id,
            'updated',
            null
        );

        expect($thirdEntryLog->old_values)
            ->toBe([
                'catalog_number' => 'A-100',
            ]);

        expect($thirdEntryLog->new_values)
            ->toBe([
                'catalog_number' => 'A-5300',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Derde geschiedenislog postfris
        |--------------------------------------------------------------------------
        */

        $thirdPostfrisLog = $thirdBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $updatedA1Postfris->id)
            ->sole();

        $assertLog(
            $thirdPostfrisLog,
            $thirdBatch,
            $this->stamp,
            'stamp_catalog_value',
            $updatedA1Postfris->id,
            'updated',
            $this->postfris->id
        );

        $assertUpdatedValueLog(
            $thirdPostfrisLog,
            15.00,
            75.75
        );

        /*
        |--------------------------------------------------------------------------
        | Derde geschiedenislog gestempeld
        |--------------------------------------------------------------------------
        */

        $thirdGestempeldLog = $thirdBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $updatedA1Gestempeld->id)
            ->sole();

        $assertLog(
            $thirdGestempeldLog,
            $thirdBatch,
            $this->stamp,
            'stamp_catalog_value',
            $updatedA1Gestempeld->id,
            'updated',
            $this->gestempeld->id
        );

        $assertUpdatedValueLog(
            $thirdGestempeldLog,
            7.50,
            37.50
        );

        /*
        |--------------------------------------------------------------------------
        | Eerste en tweede geschiedenisregistratie blijven ongewijzigd
        |--------------------------------------------------------------------------
        */

        expect($batchSnapshot($firstBatch->id))
            ->toBe($firstBatchData);

        expect($batchSnapshot($secondBatch->id))
            ->toBe($secondBatchData);

        $thirdHistory = $historySnapshot();

        expect(array_slice($thirdHistory['batches'], 0, 2))
            ->toBe($secondHistory['batches']);

        expect(array_slice($thirdHistory['logs'], 0, 6))
            ->toBe($secondHistory['logs']);

        /*
        |--------------------------------------------------------------------------
        | Gegevens nieuwe postzegel blijven ongewijzigd
        |--------------------------------------------------------------------------
        */

        expect($newStamp->fresh()->getAttributes())
            ->toBe($newStampSnapshot);

        expect($updatedEntry->fresh()->getAttributes())
            ->toBe($newEntrySnapshot);

        expect(
            $updatedEntry->values()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($newValuesSnapshot);

        /*
        |--------------------------------------------------------------------------
        | Niet-betrokken catalogusgegevens blijven ongewijzigd
        |--------------------------------------------------------------------------
        */

        expect($unaffectedSnapshot())
            ->toBe($originalUnaffectedData);

        /*
        |--------------------------------------------------------------------------
        | Definitieve verdeling geschiedenisbatches
        |--------------------------------------------------------------------------
        */

        expect(
            CatalogChangeBatch::query()
                ->where('catalog_edition_id', $this->editionB1->id)
                ->count()
        )->toBe(2);

        expect(
            CatalogChangeBatch::query()
                ->where('catalog_edition_id', $this->editionA1->id)
                ->count()
        )->toBe(1);

        expect(
            CatalogChangeBatch::query()
                ->where('operation', 'stamp_create')
                ->count()
        )->toBe(1);

        expect(
            CatalogChangeBatch::query()
                ->where('operation', 'stamp_update')
                ->count()
        )->toBe(2);

        /*
        |--------------------------------------------------------------------------
        | Definitieve verdeling geschiedenislogs
        |--------------------------------------------------------------------------
        */

        expect(
            CatalogChangeLog::query()
                ->where('action', 'created')
                ->count()
        )->toBe(3);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(6);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'deleted')
                ->count()
        )->toBe(0);

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

        expect(
            CatalogChangeLog::query()
                ->where('stamp_id', $newStamp->id)
                ->count()
        )->toBe(6);

        expect(
            CatalogChangeLog::query()
                ->where('stamp_id', $this->stamp->id)
                ->count()
        )->toBe(3);

        expect(
            CatalogChangeLog::query()
                ->where('catalog_edition_id', $this->editionB1->id)
                ->count()
        )->toBe(6);

        expect(
            CatalogChangeLog::query()
                ->where('catalog_edition_id', $this->editionA1->id)
                ->count()
        )->toBe(3);

        /*
        |--------------------------------------------------------------------------
        | Geen vermenging tussen geschiedenisregistraties
        |--------------------------------------------------------------------------
        */

        $firstLogIds = $firstBatch->logs()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $secondLogIds = $secondBatch->logs()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $thirdLogIds = $thirdBatch->logs()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        expect($firstLogIds)
            ->toHaveCount(3);

        expect($secondLogIds)
            ->toHaveCount(3);

        expect($thirdLogIds)
            ->toHaveCount(3);

        expect(array_intersect($firstLogIds, $secondLogIds))
            ->toBe([]);

        expect(array_intersect($firstLogIds, $thirdLogIds))
            ->toBe([]);

        expect(array_intersect($secondLogIds, $thirdLogIds))
            ->toBe([]);

        expect(
            array_unique(array_merge(
                $firstLogIds,
                $secondLogIds,
                $thirdLogIds
            ))
        )->toHaveCount(9);

        /*
        |--------------------------------------------------------------------------
        | Alle logs behoren uitsluitend tot de juiste postzegel en editie
        |--------------------------------------------------------------------------
        */

        foreach ([$firstBatch, $secondBatch] as $batch) {

            foreach ($batch->logs()->get() as $log) {

                expect($log->stamp_id)
                    ->toBe($newStamp->id);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->catalog_change_batch_id)
                    ->toBe($batch->id);
            }
        }

        foreach ($thirdBatch->logs()->get() as $log) {

            expect($log->stamp_id)
                ->toBe($this->stamp->id);

            expect($log->catalog_edition_id)
                ->toBe($this->editionA1->id);

            expect($log->catalog_change_batch_id)
                ->toBe($thirdBatch->id);
        }

        /*
        |--------------------------------------------------------------------------
        | Definitieve geschiedeniscontext na aanvraag 3
        |--------------------------------------------------------------------------
        */

        $assertCleanContext();

        /*
        |--------------------------------------------------------------------------
        | Definitieve geschiedenisgegevens vastleggen
        |--------------------------------------------------------------------------
        */

        $finalHistory = $historySnapshot();

        /*
        |--------------------------------------------------------------------------
        | Rechtstreekse modelbewerkingen na drie succesvolle aanvragen
        |--------------------------------------------------------------------------
        */

        $this->travel(1)->minutes();

        $updatedEntry->fresh()->update([
            'catalog_number' => 'B-5400',
        ]);

        expect($updatedEntry->fresh()->catalog_number)
            ->toBe('B-5400');

        expect($historySnapshot())
            ->toBe($finalHistory);

        $assertCleanContext();

        $updatedA1Postfris->fresh()->update([
            'catalog_value' => '85.75',
        ]);

        expect($updatedA1Postfris->fresh()->catalog_value)
            ->toBe('85.75');

        expect($updatedA1Postfris->fresh()->currency)
            ->toBe('EUR');

        expect($updatedA1Postfris->fresh()->needs_review)
            ->toBeTrue();

        expect($historySnapshot())
            ->toBe($finalHistory);

        $assertCleanContext();

        /*
        |--------------------------------------------------------------------------
        | Definitieve databaseaantallen
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalog_change_batches', 3);

        $this->assertDatabaseCount('catalog_change_logs', 9);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(4);

        /*
        |--------------------------------------------------------------------------
        | Definitief behoud oorspronkelijke geschiedenisregistraties
        |--------------------------------------------------------------------------
        */

        expect($batchSnapshot($firstBatch->id))
            ->toBe($firstBatchData);

        expect($batchSnapshot($secondBatch->id))
            ->toBe($secondBatchData);

        expect($historySnapshot())
            ->toBe($finalHistory);

        expect($secondEntryLog->fresh()->new_values['catalog_number'])
            ->toBe('B-5200');

        expect((float) $thirdPostfrisLog->fresh()->new_values['catalog_value'])
            ->toBe(75.75);

        expect($unaffectedSnapshot())
            ->toBe($originalUnaffectedData);

        /*
        |--------------------------------------------------------------------------
        | Definitieve controle geschiedeniscontext
        |--------------------------------------------------------------------------
        */

        $assertCleanContext();

    } finally {

        $this->travelBack();
    }
});

test('een mislukte aanvraag heeft geen invloed op de geschiedenisregistratie van een volgende succesvolle aanvraag', function () {

    /*
    |--------------------------------------------------------------------------
    | Uitgangssituatie
    |--------------------------------------------------------------------------
    */

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(app(CatalogChangeHistoryService::class))
        ->toBe($this->history);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie volledige databasesnapshot
    |--------------------------------------------------------------------------
    */

    $snapshot = function () {

        $models = [
            Stamp::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            CatalogChangeBatch::class,
            CatalogChangeLog::class,
        ];

        $data = [];

        foreach ($models as $model) {

            $data[$model] = $model::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all();
        }

        return $data;
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie geschiedenisregistratie vastleggen
    |--------------------------------------------------------------------------
    */

    $historySnapshot = function () {

        return [
            'batches' => CatalogChangeBatch::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'logs' => CatalogChangeLog::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie afzonderlijke geschiedenisbatch vastleggen
    |--------------------------------------------------------------------------
    */

    $batchSnapshot = function (int $batchId) {

        $batch = CatalogChangeBatch::query()
            ->findOrFail($batchId);

        return [
            'batch' => $batch->getAttributes(),

            'logs' => $batch->logs()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie geschiedeniscontext controleren
    |--------------------------------------------------------------------------
    */

    $assertCleanContext = function () {

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history);

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie geschiedenisbatch controleren
    |--------------------------------------------------------------------------
    */

    $assertBatch = function (
        CatalogChangeBatch $batch,
        string $operation,
        string $description
    ) {

        expect($batch->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($batch->user_id)
            ->toBe($this->user->id);

        expect($batch->user_name)
            ->toBe($this->user->name);

        expect($batch->operation)
            ->toBe($operation);

        expect($batch->description)
            ->toBe($description);

        expect($batch->catalog_name)
            ->toBe($this->catalogB->name);

        expect($batch->catalog_edition_name)
            ->toBe($this->editionB1->edition_name);

        expect($batch->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($batch->user->id)
            ->toBe($this->user->id);

        expect($batch->logs()->count())
            ->toBe(3);
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie algemene geschiedenisloggegevens controleren
    |--------------------------------------------------------------------------
    */

    $assertLog = function (
        CatalogChangeLog $log,
        CatalogChangeBatch $batch,
        Stamp $stamp,
        string $recordType,
        int $recordId,
        string $action,
        ?int $conditionId
    ) {

        expect($log->catalog_change_batch_id)
            ->toBe($batch->id);

        expect($log->catalog_edition_id)
            ->toBe($this->editionB1->id);

        expect($log->stamp_id)
            ->toBe($stamp->id);

        expect($log->stamp_series_id)
            ->toBeNull();

        expect($log->record_type)
            ->toBe($recordType);

        expect($log->record_id)
            ->toBe($recordId);

        expect($log->action)
            ->toBe($action);

        expect($log->condition_id)
            ->toBe($conditionId);

        expect($log->batch->id)
            ->toBe($batch->id);

        expect($log->catalogEdition->id)
            ->toBe($this->editionB1->id);

        expect($log->stamp->id)
            ->toBe($stamp->id);

        if ($conditionId === null) {

            expect($log->condition)
                ->toBeNull();

        } else {

            expect($log->condition->id)
                ->toBe($conditionId);
        }
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie aangemaakte cataloguswaardelog controleren
    |--------------------------------------------------------------------------
    */

    $assertCreatedValueLog = function (
        CatalogChangeLog $log,
        StampCatalogValue $value,
        float $amount
    ) {

        expect($log->old_values)
            ->toBeNull();

        expect($log->new_values)
            ->toBeArray();

        expect((float) $log->new_values['catalog_value'])
            ->toBe($amount);

        expect($log->new_values['currency'])
            ->toBe('USD');

        expect((int) $log->new_values['condition_id'])
            ->toBe($value->condition_id);

        expect((int) $log->new_values['stamp_catalog_entry_id'])
            ->toBe($value->stamp_catalog_entry_id);
    };

    /*
    |--------------------------------------------------------------------------
    | Hulpfunctie gewijzigde cataloguswaardelog controleren
    |--------------------------------------------------------------------------
    */

    $assertUpdatedValueLog = function (
        CatalogChangeLog $log,
        float $oldAmount,
        float $newAmount
    ) {

        expect($log->old_values)
            ->toBeArray();

        expect($log->new_values)
            ->toBeArray();

        expect($log->old_values)
            ->toHaveCount(2);

        expect($log->new_values)
            ->toHaveCount(2);

        expect((float) $log->old_values['catalog_value'])
            ->toBe($oldAmount);

        expect((float) $log->new_values['catalog_value'])
            ->toBe($newAmount);

        expect((bool) $log->old_values['needs_review'])
            ->toBeFalse();

        expect((bool) $log->new_values['needs_review'])
            ->toBeTrue();
    };

    /*
    |--------------------------------------------------------------------------
    | Oorspronkelijke postzegelgegevens vastleggen
    |--------------------------------------------------------------------------
    */

    $originalStampSnapshot = function () {

        return [
            'stamp' => $this->stamp
                ->fresh()
                ->getAttributes(),

            'entries' => StampCatalogEntry::query()
                ->where('stamp_id', $this->stamp->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),

            'values' => StampCatalogValue::query()
                ->whereIn('stamp_catalog_entry_id', [
                    $this->entryA1->id,
                    $this->entryA2->id,
                    $this->entryB1->id,
                ])
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all(),
        ];
    };

    $originalStampData = $originalStampSnapshot();

    /*
    |--------------------------------------------------------------------------
    | Aanvraag 1 — Succesvolle store
    |--------------------------------------------------------------------------
    */

    $storePayload = $this->storePayload;

    $storePayload['catalog_id'] = $this->catalogB->id;

    $storePayload['catalog_edition_id'] = $this->editionB1->id;

    $storePayload['catalog_number'] = 'B-6100';

    $storePayload['catalog_values'] = [
        'postfris' => '65.75',
        'gestempeld' => '32.50',
    ];

    $originalDispatcher = CatalogChangeLog::getEventDispatcher();

    $this->travel(1)->minutes();

    try {

        $firstResponse = $this->post(
            route('backend.stamps.store'),
            $storePayload
        );

        $firstResponse->assertRedirect();

        $firstResponse->assertSessionHasNoErrors();

        $firstResponse->assertSessionHas('success');

        /*
        |--------------------------------------------------------------------------
        | Nieuwe postzegel controleren
        |--------------------------------------------------------------------------
        */

        $newStamp = Stamp::query()
            ->where('id', '!=', $this->stamp->id)
            ->sole();

        expect($newStamp->name)
            ->toBe('Nieuwe postzegel contexttest');

        expect($newStamp->country_id)
            ->toBe($this->country->id);

        /*
        |--------------------------------------------------------------------------
        | Nieuwe catalogusvermelding controleren
        |--------------------------------------------------------------------------
        */

        $newEntry = $newStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionB1->id)
            ->sole();

        expect($newEntry->catalog_number)
            ->toBe('B-6100');

        expect($newEntry->stamp_id)
            ->toBe($newStamp->id);

        expect($newEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        /*
        |--------------------------------------------------------------------------
        | Nieuwe cataloguswaarden controleren
        |--------------------------------------------------------------------------
        */

        $newPostfris = $newEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $newGestempeld = $newEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($newPostfris->catalog_value)
            ->toBe('65.75');

        expect($newPostfris->currency)
            ->toBe('USD');

        expect($newPostfris->needs_review)
            ->toBeFalse();

        expect($newGestempeld->catalog_value)
            ->toBe('32.50');

        expect($newGestempeld->currency)
            ->toBe('USD');

        expect($newGestempeld->needs_review)
            ->toBeFalse();

        /*
        |--------------------------------------------------------------------------
        | Databaseaantallen na aanvraag 1
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenisbatch controleren
        |--------------------------------------------------------------------------
        */

        $firstBatch = CatalogChangeBatch::query()->sole();

        $assertBatch(
            $firstBatch,
            'stamp_create',
            'Catalogusgegevens bij het aanmaken van een postzegel'
        );

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenislog catalogusvermelding
        |--------------------------------------------------------------------------
        */

        $firstEntryLog = $firstBatch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $newEntry->id)
            ->sole();

        $assertLog(
            $firstEntryLog,
            $firstBatch,
            $newStamp,
            'stamp_catalog_entry',
            $newEntry->id,
            'created',
            null
        );

        expect($firstEntryLog->old_values)
            ->toBeNull();

        expect($firstEntryLog->new_values['catalog_number'])
            ->toBe('B-6100');

        expect((int) $firstEntryLog->new_values['stamp_id'])
            ->toBe($newStamp->id);

        expect((int) $firstEntryLog->new_values['catalog_edition_id'])
            ->toBe($this->editionB1->id);

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenislog postfris
        |--------------------------------------------------------------------------
        */

        $firstPostfrisLog = $firstBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $newPostfris->id)
            ->sole();

        $assertLog(
            $firstPostfrisLog,
            $firstBatch,
            $newStamp,
            'stamp_catalog_value',
            $newPostfris->id,
            'created',
            $this->postfris->id
        );

        $assertCreatedValueLog(
            $firstPostfrisLog,
            $newPostfris,
            65.75
        );

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenislog gestempeld
        |--------------------------------------------------------------------------
        */

        $firstGestempeldLog = $firstBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $newGestempeld->id)
            ->sole();

        $assertLog(
            $firstGestempeldLog,
            $firstBatch,
            $newStamp,
            'stamp_catalog_value',
            $newGestempeld->id,
            'created',
            $this->gestempeld->id
        );

        $assertCreatedValueLog(
            $firstGestempeldLog,
            $newGestempeld,
            32.50
        );

        /*
        |--------------------------------------------------------------------------
        | Geschiedeniscontext na aanvraag 1
        |--------------------------------------------------------------------------
        */

        $assertCleanContext();

        expect($originalStampSnapshot())
            ->toBe($originalStampData);

        /*
        |--------------------------------------------------------------------------
        | Succesvolle uitgangssituatie vastleggen
        |--------------------------------------------------------------------------
        */

        $firstDatabaseSnapshot = $snapshot();

        $firstHistory = $historySnapshot();

        $firstBatchData = $batchSnapshot($firstBatch->id);

        $firstTranslationSnapshot = $newStamp->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($record) => $record->getAttributes())
            ->all();

        $firstLogIds = $firstBatch->logs()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        /*
        |--------------------------------------------------------------------------
        | Aanvraag 2 — Mislukte update voorbereiden
        |--------------------------------------------------------------------------
        */

        $this->travel(1)->minutes();

        $failedPayload = $this->updatePayload;

        $failedPayload['catalog_id'] = $this->catalogB->id;

        $failedPayload['catalog_edition_id'] = $this->editionB1->id;

        $failedPayload['catalog_number'] = 'B-6200';

        $failedPayload['catalog_values'] = [
            'postfris' => '75.75',
            'gestempeld' => '37.50',
        ];

        $failedPayload['translations']['nl']['name'] =
            'Mislukte wijziging contexttest H6';

        $failedPayload['translations']['nl']['description'] =
            'Mislukte beschrijving contexttest H6';

        $exceptionMessage =
            'Geforceerde fout na laatste geschiedenislog contexttest H6';

        $listenerTriggered = false;

        /** @var CatalogChangeHistoryService|null $capturedHistory */
        $capturedHistory = null;

        /** @var int|null $failedBatchId */
        $failedBatchId = null;

        $failedLogIds = [];

        $observedLogIds = [];

        $temporaryDispatcher = clone $originalDispatcher;

        /*
        |--------------------------------------------------------------------------
        | Tijdelijke eventdispatcher activeren
        |--------------------------------------------------------------------------
        */

        $this->withoutExceptionHandling();

        try {

            CatalogChangeLog::setEventDispatcher(
                $temporaryDispatcher
            );

            /*
            |--------------------------------------------------------------------------
            | Foutinjectie na het opslaan van de laatste geschiedenislog
            |--------------------------------------------------------------------------
            */

            CatalogChangeLog::created(function (
                CatalogChangeLog $log
            ) use (
                $newStamp,
                $newEntry,
                $newPostfris,
                $newGestempeld,
                $firstBatch,
                $firstBatchData,
                $firstLogIds,
                $batchSnapshot,
                $assertBatch,
                $assertLog,
                $assertUpdatedValueLog,
                $originalStampSnapshot,
                $originalStampData,
                $exceptionMessage,
                &$listenerTriggered,
                &$capturedHistory,
                &$failedBatchId,
                &$failedLogIds,
                &$observedLogIds
            ) {

                if (
                    (int) $log->stamp_id !== $newStamp->id
                    || (int) $log->catalog_edition_id !== $this->editionB1->id
                ) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Iedere nieuwe geschiedenislog vastleggen
                |--------------------------------------------------------------------------
                */

                $observedLogIds[] = $log->id;

                $capturedHistory = app(
                    CatalogChangeHistoryService::class
                );

                expect($capturedHistory)
                    ->toBe($this->history);

                $activeBatch = $capturedHistory->getActiveBatch();

                expect($activeBatch)
                    ->not->toBeNull();

                expect($activeBatch->id)
                    ->toBe($log->catalog_change_batch_id);

                expect($activeBatch->id)
                    ->not->toBe($firstBatch->id);

                expect($activeBatch->operation)
                    ->toBe('stamp_update');

                /*
                |--------------------------------------------------------------------------
                | Geschiedenislog daadwerkelijk opgeslagen
                |--------------------------------------------------------------------------
                */

                $storedLog = CatalogChangeLog::query()
                    ->findOrFail($log->id);

                expect($storedLog->getAttributes())
                    ->toBe($log->fresh()->getAttributes());

                /*
                |--------------------------------------------------------------------------
                | Eerste twee geschiedenislogs normaal laten verlopen
                |--------------------------------------------------------------------------
                */

                if (
                    $log->record_type !== 'stamp_catalog_value'
                    || (int) $log->record_id !== $newGestempeld->id
                    || (int) $log->condition_id !== $this->gestempeld->id
                    || $log->action !== 'updated'
                ) {
                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | Laatste geschiedenislog bereikt
                |--------------------------------------------------------------------------
                */

                $listenerTriggered = true;

                $failedBatchId = $activeBatch->id;

                $failedBatch = CatalogChangeBatch::query()
                    ->findOrFail($failedBatchId);

                expect($observedLogIds)
                    ->toHaveCount(3);

                /*
                |--------------------------------------------------------------------------
                | Alle wijzigingen daadwerkelijk opgeslagen
                |--------------------------------------------------------------------------
                */

                expect($newStamp->fresh()->name)
                    ->toBe('Mislukte wijziging contexttest H6');

                $failedTranslation = $newStamp->translations()
                    ->where('locale', 'nl')
                    ->sole();

                expect($failedTranslation->name)
                    ->toBe('Mislukte wijziging contexttest H6');

                expect($failedTranslation->description)
                    ->toBe('Mislukte beschrijving contexttest H6');

                expect($newEntry->fresh()->catalog_number)
                    ->toBe('B-6200');

                expect($newPostfris->fresh()->catalog_value)
                    ->toBe('75.75');

                expect($newPostfris->fresh()->currency)
                    ->toBe('USD');

                expect($newPostfris->fresh()->needs_review)
                    ->toBeTrue();

                expect($newGestempeld->fresh()->catalog_value)
                    ->toBe('37.50');

                expect($newGestempeld->fresh()->currency)
                    ->toBe('USD');

                expect($newGestempeld->fresh()->needs_review)
                    ->toBeTrue();

                /*
                |--------------------------------------------------------------------------
                | Databaseaantallen tijdens foutinjectie
                |--------------------------------------------------------------------------
                */

                $this->assertDatabaseCount('stamps', 2);

                $this->assertDatabaseCount('stamp_catalog_entries', 4);

                $this->assertDatabaseCount('stamp_catalog_values', 8);

                $this->assertDatabaseCount('catalog_change_batches', 2);

                $this->assertDatabaseCount('catalog_change_logs', 6);

                expect(
                    StampCatalogValue::query()
                        ->where('needs_review', true)
                        ->count()
                )->toBe(2);

                /*
                |--------------------------------------------------------------------------
                | Tweede geschiedenisbatch controleren
                |--------------------------------------------------------------------------
                */

                $assertBatch(
                    $failedBatch,
                    'stamp_update',
                    'Catalogusgegevens bij het wijzigen van een postzegel'
                );

                expect($failedBatch->id)
                    ->not->toBe($firstBatch->id);

                expect(
                    $failedBatch->created_at->gt(
                        $firstBatch->created_at
                    )
                )->toBeTrue();

                /*
                |--------------------------------------------------------------------------
                | Geschiedenislog catalogusvermelding
                |--------------------------------------------------------------------------
                */

                $failedEntryLog = $failedBatch->logs()
                    ->where('record_type', 'stamp_catalog_entry')
                    ->where('record_id', $newEntry->id)
                    ->sole();

                $assertLog(
                    $failedEntryLog,
                    $failedBatch,
                    $newStamp,
                    'stamp_catalog_entry',
                    $newEntry->id,
                    'updated',
                    null
                );

                expect($failedEntryLog->old_values)
                    ->toBe([
                        'catalog_number' => 'B-6100',
                    ]);

                expect($failedEntryLog->new_values)
                    ->toBe([
                        'catalog_number' => 'B-6200',
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Geschiedenislog postfris
                |--------------------------------------------------------------------------
                */

                $failedPostfrisLog = $failedBatch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $newPostfris->id)
                    ->sole();

                $assertLog(
                    $failedPostfrisLog,
                    $failedBatch,
                    $newStamp,
                    'stamp_catalog_value',
                    $newPostfris->id,
                    'updated',
                    $this->postfris->id
                );

                $assertUpdatedValueLog(
                    $failedPostfrisLog,
                    65.75,
                    75.75
                );

                /*
                |--------------------------------------------------------------------------
                | Geschiedenislog gestempeld
                |--------------------------------------------------------------------------
                */

                $failedGestempeldLog = $failedBatch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $newGestempeld->id)
                    ->sole();

                $assertLog(
                    $failedGestempeldLog,
                    $failedBatch,
                    $newStamp,
                    'stamp_catalog_value',
                    $newGestempeld->id,
                    'updated',
                    $this->gestempeld->id
                );

                $assertUpdatedValueLog(
                    $failedGestempeldLog,
                    32.50,
                    37.50
                );

                expect($failedGestempeldLog->id)
                    ->toBe($log->id);

                /*
                |--------------------------------------------------------------------------
                | Alle drie geschiedenislogs daadwerkelijk aanwezig
                |--------------------------------------------------------------------------
                */

                $failedLogIds = $failedBatch->logs()
                    ->orderBy('id')
                    ->pluck('id')
                    ->all();

                expect($failedLogIds)
                    ->toHaveCount(3);

                expect($failedLogIds)
                    ->toBe($observedLogIds);

                expect($failedLogIds)
                    ->toBe([
                        $failedEntryLog->id,
                        $failedPostfrisLog->id,
                        $failedGestempeldLog->id,
                    ]);

                expect(
                    array_intersect(
                        $firstLogIds,
                        $failedLogIds
                    )
                )->toBe([]);

                /*
                |--------------------------------------------------------------------------
                | Eerste geschiedenisregistratie blijft ongewijzigd
                |--------------------------------------------------------------------------
                */

                expect($batchSnapshot($firstBatch->id))
                    ->toBe($firstBatchData);

                expect($originalStampSnapshot())
                    ->toBe($originalStampData);

                /*
                |--------------------------------------------------------------------------
                | Geforceerde exception
                |--------------------------------------------------------------------------
                */

                throw new RuntimeException(
                    $exceptionMessage
                );
            });

            /*
            |--------------------------------------------------------------------------
            | Mislukte update-aanvraag uitvoeren
            |--------------------------------------------------------------------------
            */

            expect(fn () => $this->put(
                route('backend.stamps.update', $newStamp),
                $failedPayload
            ))->toThrow(
                RuntimeException::class,
                $exceptionMessage
            );

        } finally {

            /*
            |--------------------------------------------------------------------------
            | Oorspronkelijke eventdispatcher herstellen
            |--------------------------------------------------------------------------
            */

            CatalogChangeLog::setEventDispatcher(
                $originalDispatcher
            );

            $this->withExceptionHandling();
        }

        /*
        |--------------------------------------------------------------------------
        | Foutinjectie daadwerkelijk uitgevoerd
        |--------------------------------------------------------------------------
        */

        expect($listenerTriggered)
            ->toBeTrue();

        expect($capturedHistory)
            ->not->toBeNull();

        expect($capturedHistory)
            ->toBe($this->history);

        expect($failedBatchId)
            ->not->toBeNull();

        expect($failedLogIds)
            ->toHaveCount(3);

        expect($observedLogIds)
            ->toBe($failedLogIds);

        /*
        |--------------------------------------------------------------------------
        | Volledige rollback naar succesvolle uitgangssituatie
        |--------------------------------------------------------------------------
        */

        expect($snapshot())
            ->toBe($firstDatabaseSnapshot);

        expect($historySnapshot())
            ->toBe($firstHistory);

        expect($batchSnapshot($firstBatch->id))
            ->toBe($firstBatchData);

        expect(
            $newStamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($record) => $record->getAttributes())
                ->all()
        )->toBe($firstTranslationSnapshot);

        /*
        |--------------------------------------------------------------------------
        | Databaseaantallen na rollback
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalog_change_batches', 1);

        $this->assertDatabaseCount('catalog_change_logs', 3);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        /*
        |--------------------------------------------------------------------------
        | Mislukte geschiedenisregistratie volledig verdwenen
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $failedBatchId,
        ]);

        foreach ($failedLogIds as $logId) {

            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Catalogusgegevens na rollback
        |--------------------------------------------------------------------------
        */

        expect($newStamp->fresh()->name)
            ->toBe('Nieuwe postzegel contexttest');

        expect($newEntry->fresh()->catalog_number)
            ->toBe('B-6100');

        expect($newPostfris->fresh()->catalog_value)
            ->toBe('65.75');

        expect($newPostfris->fresh()->currency)
            ->toBe('USD');

        expect($newPostfris->fresh()->needs_review)
            ->toBeFalse();

        expect($newGestempeld->fresh()->catalog_value)
            ->toBe('32.50');

        expect($newGestempeld->fresh()->currency)
            ->toBe('USD');

        expect($newGestempeld->fresh()->needs_review)
            ->toBeFalse();

        /*
        |--------------------------------------------------------------------------
        | Geschiedeniscontext na mislukte aanvraag
        |--------------------------------------------------------------------------
        */

        $assertCleanContext();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(CatalogChangeLog::getEventDispatcher())
            ->toBe($originalDispatcher);

        expect($originalStampSnapshot())
            ->toBe($originalStampData);

        /*
        |--------------------------------------------------------------------------
        | Aanvraag 3 — Succesvolle update na rollback
        |--------------------------------------------------------------------------
        */

        $this->travel(1)->minutes();

        $successfulPayload = $this->updatePayload;

        $successfulPayload['catalog_id'] = $this->catalogB->id;

        $successfulPayload['catalog_edition_id'] = $this->editionB1->id;

        $successfulPayload['catalog_number'] = 'B-6300';

        $successfulPayload['catalog_values'] = [
            'postfris' => '85.75',
            'gestempeld' => '42.50',
        ];

        $successfulPayload['translations']['nl']['name'] =
            'Succesvolle wijziging contexttest H6';

        $successfulPayload['translations']['nl']['description'] =
            'Succesvolle beschrijving contexttest H6';

        $thirdResponse = $this->put(
            route('backend.stamps.update', $newStamp),
            $successfulPayload
        );

        $thirdResponse->assertRedirect();

        $thirdResponse->assertSessionHasNoErrors();

        $thirdResponse->assertSessionHas('success');

        /*
        |--------------------------------------------------------------------------
        | Succesvol gewijzigde postzegel controleren
        |--------------------------------------------------------------------------
        */

        expect($newStamp->fresh()->name)
            ->toBe('Succesvolle wijziging contexttest H6');

        expect($newStamp->fresh()->country_id)
            ->toBe($this->country->id);

        $successfulTranslation = $newStamp->translations()
            ->where('locale', 'nl')
            ->sole();

        expect($successfulTranslation->name)
            ->toBe('Succesvolle wijziging contexttest H6');

        expect($successfulTranslation->description)
            ->toBe('Succesvolle beschrijving contexttest H6');

        /*
        |--------------------------------------------------------------------------
        | Succesvol gewijzigde catalogusvermelding controleren
        |--------------------------------------------------------------------------
        */

        $updatedEntry = $newStamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionB1->id)
            ->sole();

        expect($updatedEntry->id)
            ->toBe($newEntry->id);

        expect($updatedEntry->catalog_number)
            ->toBe('B-6300');

        expect($updatedEntry->stamp_id)
            ->toBe($newStamp->id);

        expect($updatedEntry->catalog_edition_id)
            ->toBe($this->editionB1->id);

        /*
        |--------------------------------------------------------------------------
        | Succesvol gewijzigde cataloguswaarden controleren
        |--------------------------------------------------------------------------
        */

        $updatedPostfris = $updatedEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        $updatedGestempeld = $updatedEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        expect($updatedPostfris->id)
            ->toBe($newPostfris->id);

        expect($updatedPostfris->catalog_value)
            ->toBe('85.75');

        expect($updatedPostfris->currency)
            ->toBe('USD');

        expect($updatedPostfris->needs_review)
            ->toBeTrue();

        expect($updatedGestempeld->id)
            ->toBe($newGestempeld->id);

        expect($updatedGestempeld->catalog_value)
            ->toBe('42.50');

        expect($updatedGestempeld->currency)
            ->toBe('USD');

        expect($updatedGestempeld->needs_review)
            ->toBeTrue();

        /*
        |--------------------------------------------------------------------------
        | Databaseaantallen na aanvraag 3
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalog_change_batches', 2);

        $this->assertDatabaseCount('catalog_change_logs', 6);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(2);

        /*
        |--------------------------------------------------------------------------
        | Nieuwe succesvolle geschiedenisbatch controleren
        |--------------------------------------------------------------------------
        */

        $successfulBatch = CatalogChangeBatch::query()
            ->where('id', '!=', $firstBatch->id)
            ->sole();

        expect($successfulBatch->id)
            ->not->toBe($firstBatch->id);

        expect(
            $successfulBatch->created_at->gt(
                $firstBatch->created_at
            )
        )->toBeTrue();

        $assertBatch(
            $successfulBatch,
            'stamp_update',
            'Catalogusgegevens bij het wijzigen van een postzegel'
        );

        /*
        |--------------------------------------------------------------------------
        | Succesvolle geschiedenislog catalogusvermelding
        |--------------------------------------------------------------------------
        */

        $successfulEntryLog = $successfulBatch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $updatedEntry->id)
            ->sole();

        $assertLog(
            $successfulEntryLog,
            $successfulBatch,
            $newStamp,
            'stamp_catalog_entry',
            $updatedEntry->id,
            'updated',
            null
        );

        expect($successfulEntryLog->old_values)
            ->toBe([
                'catalog_number' => 'B-6100',
            ]);

        expect($successfulEntryLog->new_values)
            ->toBe([
                'catalog_number' => 'B-6300',
            ]);

        /*
        |--------------------------------------------------------------------------
        | Succesvolle geschiedenislog postfris
        |--------------------------------------------------------------------------
        */

        $successfulPostfrisLog = $successfulBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $updatedPostfris->id)
            ->sole();

        $assertLog(
            $successfulPostfrisLog,
            $successfulBatch,
            $newStamp,
            'stamp_catalog_value',
            $updatedPostfris->id,
            'updated',
            $this->postfris->id
        );

        $assertUpdatedValueLog(
            $successfulPostfrisLog,
            65.75,
            85.75
        );

        /*
        |--------------------------------------------------------------------------
        | Succesvolle geschiedenislog gestempeld
        |--------------------------------------------------------------------------
        */

        $successfulGestempeldLog = $successfulBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $updatedGestempeld->id)
            ->sole();

        $assertLog(
            $successfulGestempeldLog,
            $successfulBatch,
            $newStamp,
            'stamp_catalog_value',
            $updatedGestempeld->id,
            'updated',
            $this->gestempeld->id
        );

        $assertUpdatedValueLog(
            $successfulGestempeldLog,
            32.50,
            42.50
        );

        /*
        |--------------------------------------------------------------------------
        | Geschiedenis sluit aan op eerste succesvolle aanvraag
        |--------------------------------------------------------------------------
        */

        expect($successfulEntryLog->old_values['catalog_number'])
            ->toBe($firstEntryLog->new_values['catalog_number']);

        expect((float) $successfulPostfrisLog->old_values['catalog_value'])
            ->toBe((float) $firstPostfrisLog->new_values['catalog_value']);

        expect((float) $successfulGestempeldLog->old_values['catalog_value'])
            ->toBe((float) $firstGestempeldLog->new_values['catalog_value']);

        /*
        |--------------------------------------------------------------------------
        | Geen overgenomen waarden uit mislukte aanvraag
        |--------------------------------------------------------------------------
        */

        expect($successfulEntryLog->old_values['catalog_number'])
            ->not->toBe('B-6200');

        expect((float) $successfulPostfrisLog->old_values['catalog_value'])
            ->not->toBe(75.75);

        expect((float) $successfulGestempeldLog->old_values['catalog_value'])
            ->not->toBe(37.50);

        /*
        |--------------------------------------------------------------------------
        | Eerste geschiedenisregistratie blijft volledig ongewijzigd
        |--------------------------------------------------------------------------
        */

        expect($batchSnapshot($firstBatch->id))
            ->toBe($firstBatchData);

        $successfulHistory = $historySnapshot();

        expect(array_slice($successfulHistory['batches'], 0, 1))
            ->toBe($firstHistory['batches']);

        expect(array_slice($successfulHistory['logs'], 0, 3))
            ->toBe($firstHistory['logs']);

        /*
        |--------------------------------------------------------------------------
        | Geen vermenging tussen succesvolle geschiedenisregistraties
        |--------------------------------------------------------------------------
        */

        $successfulLogIds = $successfulBatch->logs()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        expect($successfulLogIds)
            ->toHaveCount(3);

        expect(
            array_intersect(
                $firstLogIds,
                $successfulLogIds
            )
        )->toBe([]);

        expect(
            array_unique(array_merge(
                $firstLogIds,
                $successfulLogIds
            ))
        )->toHaveCount(6);

        /*
        |--------------------------------------------------------------------------
        | Definitieve verdeling geschiedenisregistratie
        |--------------------------------------------------------------------------
        */

        expect(
            CatalogChangeBatch::query()
                ->where('catalog_edition_id', $this->editionB1->id)
                ->count()
        )->toBe(2);

        expect(
            CatalogChangeBatch::query()
                ->where('operation', 'stamp_create')
                ->count()
        )->toBe(1);

        expect(
            CatalogChangeBatch::query()
                ->where('operation', 'stamp_update')
                ->count()
        )->toBe(1);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'created')
                ->count()
        )->toBe(3);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'updated')
                ->count()
        )->toBe(3);

        expect(
            CatalogChangeLog::query()
                ->where('action', 'deleted')
                ->count()
        )->toBe(0);

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
                ->where('stamp_id', $newStamp->id)
                ->count()
        )->toBe(6);

        expect(
            CatalogChangeLog::query()
                ->where('catalog_edition_id', $this->editionB1->id)
                ->count()
        )->toBe(6);

        /*
        |--------------------------------------------------------------------------
        | Alle logs behoren uitsluitend tot de juiste postzegel en editie
        |--------------------------------------------------------------------------
        */

        foreach ([$firstBatch, $successfulBatch] as $batch) {

            foreach ($batch->logs()->get() as $log) {

                expect($log->stamp_id)
                    ->toBe($newStamp->id);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->catalog_change_batch_id)
                    ->toBe($batch->id);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Geen geschiedenisgegevens van mislukte aanvraag aanwezig
        |--------------------------------------------------------------------------
        */

        foreach (CatalogChangeLog::query()->get() as $log) {

            foreach ([
                $log->old_values,
                $log->new_values,
            ] as $values) {

                if ($values === null) {
                    continue;
                }

                if (array_key_exists('catalog_number', $values)) {

                    expect($values['catalog_number'])
                        ->not->toBe('B-6200');
                }

                if (array_key_exists('catalog_value', $values)) {

                    expect((float) $values['catalog_value'])
                        ->not->toBe(75.75);

                    expect((float) $values['catalog_value'])
                        ->not->toBe(37.50);
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Oorspronkelijke postzegelgegevens blijven ongewijzigd
        |--------------------------------------------------------------------------
        */

        expect($originalStampSnapshot())
            ->toBe($originalStampData);

        /*
        |--------------------------------------------------------------------------
        | Geschiedeniscontext na aanvraag 3
        |--------------------------------------------------------------------------
        */

        $assertCleanContext();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(CatalogChangeLog::getEventDispatcher())
            ->toBe($originalDispatcher);

        /*
        |--------------------------------------------------------------------------
        | Rechtstreekse modelbewerkingen na succesvolle herstel-aanvraag
        |--------------------------------------------------------------------------
        */

        $this->travel(1)->minutes();

        $updatedEntry->fresh()->update([
            'catalog_number' => 'B-6400',
        ]);

        expect($updatedEntry->fresh()->catalog_number)
            ->toBe('B-6400');

        expect($historySnapshot())
            ->toBe($successfulHistory);

        $assertCleanContext();

        $updatedPostfris->fresh()->update([
            'catalog_value' => '95.75',
        ]);

        expect($updatedPostfris->fresh()->catalog_value)
            ->toBe('95.75');

        expect($updatedPostfris->fresh()->currency)
            ->toBe('USD');

        expect($updatedPostfris->fresh()->needs_review)
            ->toBeTrue();

        expect($historySnapshot())
            ->toBe($successfulHistory);

        $assertCleanContext();

        /*
        |--------------------------------------------------------------------------
        | Definitieve databaseaantallen
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseCount('stamps', 2);

        $this->assertDatabaseCount('stamp_catalog_entries', 4);

        $this->assertDatabaseCount('stamp_catalog_values', 8);

        $this->assertDatabaseCount('catalog_change_batches', 2);

        $this->assertDatabaseCount('catalog_change_logs', 6);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(2);

        /*
        |--------------------------------------------------------------------------
        | Definitief behoud succesvolle geschiedenisregistraties
        |--------------------------------------------------------------------------
        */

        expect($batchSnapshot($firstBatch->id))
            ->toBe($firstBatchData);

        expect($historySnapshot())
            ->toBe($successfulHistory);

        expect($successfulEntryLog->fresh()->new_values['catalog_number'])
            ->toBe('B-6300');

        expect((float) $successfulPostfrisLog->fresh()->new_values['catalog_value'])
            ->toBe(85.75);

        expect((float) $successfulGestempeldLog->fresh()->new_values['catalog_value'])
            ->toBe(42.50);

        expect($originalStampSnapshot())
            ->toBe($originalStampData);

        /*
        |--------------------------------------------------------------------------
        | Definitieve controle geschiedeniscontext
        |--------------------------------------------------------------------------
        */

        $assertCleanContext();

        expect($capturedHistory)
            ->toBe($this->history);

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(CatalogChangeLog::getEventDispatcher())
            ->toBe($originalDispatcher);

    } finally {

        CatalogChangeLog::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});
