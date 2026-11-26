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
use App\Models\StampImage;
use App\Models\Theme;
use App\Models\User;
use App\Services\CatalogChangeHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // -------------------------------------------------
    // Applicatietaal.
    // -------------------------------------------------

    app()->setLocale('nl');

    // -------------------------------------------------
    // Gebruikers.
    // -------------------------------------------------

    $this->user = User::factory()->create([
        'name' => 'Testgebruiker catalogusgeschiedenis rollback',
        'is_admin' => false,
    ]);

    $this->otherUser = User::factory()->create([
        'name' => 'Andere testgebruiker rollback',
        'is_admin' => false,
    ]);

    // -------------------------------------------------
    // Postzegel en landen.
    // -------------------------------------------------

    $this->stamp = Stamp::factory()->create([
        'issue_year' => 2020,
    ]);

    $this->country = $this->stamp->country;

    $this->otherCountry = Country::factory()->create();

    // -------------------------------------------------
    // Waarderingscategorieën.
    // -------------------------------------------------

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

    // -------------------------------------------------
    // Catalogus A.
    // Uitsluitend beschikbaar voor het huidige land.
    // -------------------------------------------------

    $this->catalogA = Catalog::create([
        'name' => 'Testcatalogus A',
        'abbreviation' => 'TCA',
    ]);

    $this->catalogA->countries()->attach(
        $this->country->id
    );

    // -------------------------------------------------
    // Catalogus B.
    // Beschikbaar voor beide landen.
    // -------------------------------------------------

    $this->catalogB = Catalog::create([
        'name' => 'Testcatalogus B',
        'abbreviation' => 'TCB',
    ]);

    $this->catalogB->countries()->attach([
        $this->country->id,
        $this->otherCountry->id,
    ]);

    // -------------------------------------------------
    // Catalogus C.
    // Uitsluitend beschikbaar voor het andere land.
    // -------------------------------------------------

    $this->catalogC = Catalog::create([
        'name' => 'Testcatalogus C',
        'abbreviation' => 'TCC',
    ]);

    $this->catalogC->countries()->attach(
        $this->otherCountry->id
    );

    // -------------------------------------------------
    // Catalogusedities.
    // -------------------------------------------------

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

    // -------------------------------------------------
    // Algemene waarderingsregels.
    // Beide condities zijn toegestaan voor alle jaren.
    // -------------------------------------------------

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

    // -------------------------------------------------
    // Bestaande catalogusvermeldingen.
    // -------------------------------------------------

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

    // -------------------------------------------------
    // Cataloguswaarden editie A1.
    // -------------------------------------------------

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

    // -------------------------------------------------
    // Cataloguswaarden editie A2.
    // -------------------------------------------------

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

    // -------------------------------------------------
    // Cataloguswaarden editie B1.
    // -------------------------------------------------

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

    // -------------------------------------------------
    // Basisformulier voor het aanmaken van een postzegel.
    // -------------------------------------------------

    $this->storePayload = [
        'country_id' => $this->country->id,
        'issue_year' => 2020,
        'translations' => [
            'nl' => [
                'name' => 'Nieuwe postzegel rollbacktest',
                'description' => 'Beschrijving nieuwe postzegel rollbacktest',
            ],
        ],
    ];

    // -------------------------------------------------
    // Basisformulier voor het wijzigen van een postzegel.
    // -------------------------------------------------

    $this->updatePayload = [
        'country_id' => $this->country->id,
        'issue_year' => $this->stamp->issue_year,
        'translations' => [
            'nl' => [
                'name' => 'Gewijzigde postzegel rollbacktest',
                'description' => 'Gewijzigde beschrijving rollbacktest',
            ],
        ],
    ];

    // -------------------------------------------------
    // Geschiedenisservice.
    // -------------------------------------------------

    $this->history = app(CatalogChangeHistoryService::class);

    // -------------------------------------------------
    // Controleer de gemeenschappelijke uitgangssituatie.
    // -------------------------------------------------

    $this->assertDatabaseCount('users', 2);

    $this->assertDatabaseCount('countries', 2);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('catalogs', 3);

    $this->assertDatabaseCount('catalog_editions', 4);

    $this->assertDatabaseCount('conditions', 2);

    $this->assertDatabaseCount('catalog_condition_rules', 6);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('stamp_images', 0);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Authenticeer de testgebruiker.
    // -------------------------------------------------

    $this->actingAs($this->user);
});

test('de backend draait het aanmaken van een postzegel volledig terug bij een fout tijdens het aanmaken van een catalogusvermelding', function () {
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

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-300';

    $payload['catalog_values'] = [
        'postfris' => '35.75',
        'gestempeld' => '17.50',
    ];

    $payload['face_value'] = '2.50';

    $payload['currency'] = 'EUR';

    $payload['designer'] = 'Testontwerper rollback';

    $payload['printing_method'] = 'Offsetdruk';

    $payload['notes'] = 'Deze gegevens moeten worden teruggedraaid';

    // -------------------------------------------------
    // Controleer de geldigheid van de catalogusgegevens.
    // -------------------------------------------------

    expect($this->editionB1->catalog_id)
        ->toBe($this->catalogB->id);

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    foreach ([
        $this->postfris,
        $this->gestempeld,
    ] as $condition) {
        $this->assertDatabaseHas('catalog_condition_rules', [
            'catalog_id' => $this->catalogB->id,
            'condition_id' => $condition->id,
        ]);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het aanmaken van een catalogusvermelding';

    $listenerCalls = 0;

    /** @var int|null $createdStampId */
    $createdStampId = null;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogEntry::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogEntry::setEventDispatcher($testDispatcher);

    // -------------------------------------------------
    // Schakel de normale exceptionafhandeling uit.
    // -------------------------------------------------

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogEntry::creating(function (
            StampCatalogEntry $entry
        ) use (
            $payload,
            $exceptionMessage,
            &$listenerCalls,
            &$createdStampId,
            &$createdBatchId,
            &$capturedHistory
        ) {
            // Reageer uitsluitend op de bedoelde vermelding.
            if (
                $entry->catalog_edition_id !== $this->editionB1->id
                || $entry->catalog_number !== 'B-300'
            ) {
                return;
            }

            $listenerCalls++;

            $createdStampId = $entry->stamp_id;

            // -------------------------------------------------
            // Bewijs dat de postzegel al is opgeslagen.
            // -------------------------------------------------

            $createdStamp = Stamp::query()
                ->findOrFail($createdStampId);

            expect($createdStamp->id)
                ->not->toBe($this->stamp->id);

            expect($createdStamp->country_id)
                ->toBe($this->country->id);

            expect($createdStamp->issue_year)
                ->toBe(2020);

            expect($createdStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($createdStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($createdStamp->designer)
                ->toBe($payload['designer']);

            expect($createdStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($createdStamp->notes)
                ->toBe($payload['notes']);

            $this->assertDatabaseCount('stamps', 2);

            $this->assertDatabaseHas('stamps', [
                'id' => $createdStampId,
                'country_id' => $this->country->id,
                'issue_year' => 2020,
                'name' => $payload['translations']['nl']['name'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de vertaling al is opgeslagen.
            // -------------------------------------------------

            $this->assertDatabaseHas('stamp_translations', [
                'stamp_id' => $createdStampId,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect($createdStamp->translations()->count())
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de geschiedenisbatch al bestaat.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_create',
                'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // De catalogusvermelding is nog niet opgeslagen.
            // -------------------------------------------------

            expect($entry->exists)
                ->toBeFalse();

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $createdStampId,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-300',
            ]);

            // Er mogen nog geen wijzigingslogs bestaan.
            $this->assertDatabaseCount('catalog_change_logs', 0);

            expect($activeBatch->logs()->count())
                ->toBe(0);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->post(
            route('backend.stamps.store'),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdStampId)
            ->toBeInt();

        expect($createdBatchId)
            ->toBeInt();

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer de rollback van de nieuwe postzegel.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamps', [
            'id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamps', [
            'name' => $payload['translations']['nl']['name'],
        ]);

        expect(
            Stamp::query()->find($createdStampId)
        )->toBeNull();

        $this->assertDatabaseCount('stamps', 1);

        // Alle oorspronkelijke postzegelgegevens moeten
        // inclusief timestamps ongewijzigd zijn gebleven.
        expect(
            Stamp::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($stamp) => $stamp->getAttributes())
                ->all()
        )->toBe($originalStamps);

        // -------------------------------------------------
        // Controleer de rollback van de vertaling.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_translations', [
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        expect(
            $this->stamp->fresh()
                ->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalTranslations);

        expect(
            $this->stamp->fresh()
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalThemes);

        // -------------------------------------------------
        // Controleer de catalogusvermeldingen.
        // -------------------------------------------------

        expect(
            StampCatalogEntry::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($entry) => $entry->getAttributes())
                ->all()
        )->toBe($originalEntries);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-300',
        ]);

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer de oorspronkelijke cataloguswaarden.
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

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de oorspronkelijke catalogusrelaties.
        // -------------------------------------------------

        expect($this->stamp->fresh()->catalogEntries()->count())
            ->toBe(3);

        expect($this->stamp->fresh()->catalogValues()->count())
            ->toBe(6);

        foreach ([
            $this->entryA1,
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            expect($entry->fresh()->values()->count())
                ->toBe(2);
        }

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            CatalogChangeBatch::query()->exists()
        )->toBeFalse();

        expect(
            CatalogChangeLog::query()->exists()
        )->toBeFalse();

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $createdStampId,
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

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogEntry::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait het aanmaken van een postzegel volledig terug bij een fout tijdens het aanmaken van de eerste cataloguswaarde', function () {
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

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-400';

    $payload['catalog_values'] = [
        'postfris' => '42.75',
        'gestempeld' => '18.50',
    ];

    $payload['face_value'] = '3.50';

    $payload['currency'] = 'EUR';

    $payload['designer'] = 'Testontwerper eerste cataloguswaarde';

    $payload['printing_method'] = 'Offsetdruk';

    $payload['notes'] = 'Rollback bij de eerste cataloguswaarde';

    // -------------------------------------------------
    // Controleer de geldigheid van de catalogusgegevens.
    // -------------------------------------------------

    expect($this->editionB1->catalog_id)
        ->toBe($this->catalogB->id);

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    foreach ([
        $this->postfris,
        $this->gestempeld,
    ] as $condition) {
        $this->assertDatabaseHas('catalog_condition_rules', [
            'catalog_id' => $this->catalogB->id,
            'condition_id' => $condition->id,
        ]);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het aanmaken van de eerste cataloguswaarde';

    $listenerCalls = 0;

    /** @var int|null $createdStampId */
    $createdStampId = null;

    /** @var int|null $createdEntryId */
    $createdEntryId = null;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var int|null $createdLogId */
    $createdLogId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::creating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $exceptionMessage,
            &$listenerCalls,
            &$createdStampId,
            &$createdEntryId,
            &$createdBatchId,
            &$createdLogId,
            &$capturedHistory
        ) {
            $createdEntry = StampCatalogEntry::query()
                ->findOrFail($value->stamp_catalog_entry_id);

            // Reageer uitsluitend op de bedoelde vermelding
            // en de eerste cataloguswaarde.
            if (
                $createdEntry->catalog_edition_id !== $this->editionB1->id
                || $createdEntry->catalog_number !== 'B-400'
                || $value->condition_id !== $this->postfris->id
            ) {
                return;
            }

            $listenerCalls++;

            $createdEntryId = $createdEntry->id;

            $createdStampId = $createdEntry->stamp_id;

            // -------------------------------------------------
            // Bewijs dat de postzegel al is opgeslagen.
            // -------------------------------------------------

            $createdStamp = Stamp::query()
                ->findOrFail($createdStampId);

            expect($createdStamp->id)
                ->not->toBe($this->stamp->id);

            expect($createdStamp->country_id)
                ->toBe($this->country->id);

            expect($createdStamp->issue_year)
                ->toBe(2020);

            expect($createdStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($createdStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($createdStamp->designer)
                ->toBe($payload['designer']);

            expect($createdStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($createdStamp->notes)
                ->toBe($payload['notes']);

            $this->assertDatabaseCount('stamps', 2);

            $this->assertDatabaseHas('stamp_translations', [
                'stamp_id' => $createdStampId,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect($createdStamp->translations()->count())
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de catalogusvermelding al bestaat.
            // -------------------------------------------------

            expect($createdEntry->exists)
                ->toBeTrue();

            expect($createdEntry->stamp_id)
                ->toBe($createdStampId);

            expect($createdEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($createdEntry->catalog_number)
                ->toBe('B-400');

            expect($createdEntry->catalogEdition->currency)
                ->toBe('USD');

            expect($createdStamp->catalogEntries()->count())
                ->toBe(1);

            $this->assertDatabaseCount('stamp_catalog_entries', 4);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $createdEntryId,
                'stamp_id' => $createdStampId,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-400',
            ]);

            // -------------------------------------------------
            // Bewijs dat de geschiedenisbatch al bestaat.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_create',
                'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat de wijzigingslog al is opgeslagen.
            // -------------------------------------------------

            $this->assertDatabaseCount('catalog_change_logs', 1);

            expect($activeBatch->logs()->count())
                ->toBe(1);

            $createdLog = $activeBatch->logs()->sole();

            $createdLogId = $createdLog->id;

            expect($createdLog)
                ->toBeInstanceOf(CatalogChangeLog::class);

            expect($createdLog->catalog_change_batch_id)
                ->toBe($createdBatchId);

            expect($createdLog->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($createdLog->stamp_id)
                ->toBe($createdStampId);

            expect($createdLog->stamp_series_id)
                ->toBeNull();

            expect($createdLog->condition_id)
                ->toBeNull();

            expect($createdLog->record_type)
                ->toBe('stamp_catalog_entry');

            expect($createdLog->record_id)
                ->toBe($createdEntryId);

            expect($createdLog->action)
                ->toBe('created');

            expect($createdLog->old_values)
                ->toBeNull();

            expect($createdLog->new_values)
                ->toBeArray();

            expect($createdLog->new_values['id'])
                ->toBe($createdEntryId);

            expect($createdLog->new_values['stamp_id'])
                ->toBe($createdStampId);

            expect($createdLog->new_values['catalog_edition_id'])
                ->toBe($this->editionB1->id);

            expect($createdLog->new_values['catalog_number'])
                ->toBe('B-400');

            expect($createdLog->new_values)
                ->toHaveKeys([
                    'created_at',
                    'updated_at',
                ]);

            // Vergelijk alle daadwerkelijk geregistreerde
            // attributen met de opgeslagen catalogusvermelding.
            $createdEntryAttributes = $createdEntry->getAttributes();

            foreach (
                $createdLog->new_values as $attribute => $loggedValue
            ) {
                expect($createdEntryAttributes)
                    ->toHaveKey($attribute);

                expect($loggedValue)
                    ->toBe($createdEntryAttributes[$attribute]);
            }

            expect($createdLog->batch->id)
                ->toBe($createdBatchId);

            expect($createdLog->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($createdLog->stamp->id)
                ->toBe($createdStampId);

            expect($createdLog->stampSeries)
                ->toBeNull();

            expect($createdLog->condition)
                ->toBeNull();

            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $createdLogId,
                'catalog_change_batch_id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'stamp_id' => $createdStampId,
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $createdEntryId,
                'action' => 'created',
            ]);

            // -------------------------------------------------
            // De eerste cataloguswaarde is nog niet opgeslagen.
            // -------------------------------------------------

            expect($value->exists)
                ->toBeFalse();

            expect($value->stamp_catalog_entry_id)
                ->toBe($createdEntryId);

            expect($value->condition_id)
                ->toBe($this->postfris->id);

            expect($value->catalog_value)
                ->toBe('42.75');

            expect($value->currency)
                ->toBe('USD');

            expect($createdEntry->values()->count())
                ->toBe(0);

            expect($createdStamp->catalogValues()->count())
                ->toBe(0);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $createdEntryId,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'stamp_id' => $createdStampId,
                'record_type' => 'stamp_catalog_value',
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->post(
            route('backend.stamps.store'),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdStampId)
            ->toBeInt();

        expect($createdEntryId)
            ->toBeInt();

        expect($createdBatchId)
            ->toBeInt();

        expect($createdLogId)
            ->toBeInt();

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer de rollback van de nieuwe postzegel.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamps', [
            'id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamps', [
            'name' => $payload['translations']['nl']['name'],
        ]);

        expect(
            Stamp::query()->find($createdStampId)
        )->toBeNull();

        expect(
            Stamp::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($stamp) => $stamp->getAttributes())
                ->all()
        )->toBe($originalStamps);

        // -------------------------------------------------
        // Controleer de rollback van de vertaling.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_translations', [
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        expect(
            $this->stamp->fresh()
                ->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalTranslations);

        expect(
            $this->stamp->fresh()
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalThemes);

        // -------------------------------------------------
        // Controleer de rollback van de catalogusvermelding.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $createdEntryId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-400',
        ]);

        expect(
            StampCatalogEntry::query()->find($createdEntryId)
        )->toBeNull();

        // Alle oorspronkelijke attributen moeten
        // inclusief timestamps behouden zijn gebleven.
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

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $createdEntryId,
        ]);

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
                'amount' => '15.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA1Gestempeld,
                'amount' => '7.50',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA2Postfris,
                'amount' => '20.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA2Gestempeld,
                'amount' => '10.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueB1Postfris,
                'amount' => '25.00',
                'currency' => 'USD',
            ],
            [
                'model' => $this->valueB1Gestempeld,
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
        }

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de oorspronkelijke catalogusrelaties.
        // -------------------------------------------------

        expect($this->stamp->fresh()->catalogEntries()->count())
            ->toBe(3);

        expect($this->stamp->fresh()->catalogValues()->count())
            ->toBe(6);

        foreach ([
            $this->entryA1,
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            expect($entry->fresh()->values()->count())
                ->toBe(2);
        }

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        $this->assertDatabaseCount('catalog_change_batches', 0);

        // -------------------------------------------------
        // Controleer de rollback van de opgeslagen wijzigingslog.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_logs', [
            'id' => $createdLogId,
        ]);

        expect(
            CatalogChangeLog::query()->find($createdLogId)
        )->toBeNull();

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $createdEntryId,
        ]);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            CatalogChangeBatch::query()->exists()
        )->toBeFalse();

        expect(
            CatalogChangeLog::query()->exists()
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

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait het aanmaken van een postzegel volledig terug bij een fout tijdens het aanmaken van de tweede cataloguswaarde', function () {
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

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-500';

    $payload['catalog_values'] = [
        'postfris' => '48.75',
        'gestempeld' => '24.50',
    ];

    $payload['face_value'] = '4.50';

    $payload['currency'] = 'EUR';

    $payload['designer'] = 'Testontwerper tweede cataloguswaarde';

    $payload['printing_method'] = 'Offsetdruk';

    $payload['notes'] = 'Rollback bij de tweede cataloguswaarde';

    // -------------------------------------------------
    // Controleer de geldigheid van de catalogusgegevens.
    // -------------------------------------------------

    expect($this->editionB1->catalog_id)
        ->toBe($this->catalogB->id);

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    foreach ([
        $this->postfris,
        $this->gestempeld,
    ] as $condition) {
        $this->assertDatabaseHas('catalog_condition_rules', [
            'catalog_id' => $this->catalogB->id,
            'condition_id' => $condition->id,
        ]);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het aanmaken van de tweede cataloguswaarde';

    $listenerCalls = 0;

    /** @var int|null $createdStampId */
    $createdStampId = null;

    /** @var int|null $createdEntryId */
    $createdEntryId = null;

    /** @var int|null $createdValueId */
    $createdValueId = null;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var int|null $createdEntryLogId */
    $createdEntryLogId = null;

    /** @var int|null $createdValueLogId */
    $createdValueLogId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::creating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $exceptionMessage,
            &$listenerCalls,
            &$createdStampId,
            &$createdEntryId,
            &$createdValueId,
            &$createdBatchId,
            &$createdEntryLogId,
            &$createdValueLogId,
            &$capturedHistory
        ) {
            $createdEntry = StampCatalogEntry::query()
                ->findOrFail($value->stamp_catalog_entry_id);

            // Laat de eerste cataloguswaarde normaal opslaan.
            // Veroorzaak uitsluitend bij gestempeld een fout.
            if (
                $createdEntry->catalog_edition_id !== $this->editionB1->id
                || $createdEntry->catalog_number !== 'B-500'
                || $value->condition_id !== $this->gestempeld->id
            ) {
                return;
            }

            $listenerCalls++;

            $createdEntryId = $createdEntry->id;

            $createdStampId = $createdEntry->stamp_id;

            // -------------------------------------------------
            // Bewijs dat de postzegel al is opgeslagen.
            // -------------------------------------------------

            $createdStamp = Stamp::query()
                ->findOrFail($createdStampId);

            expect($createdStamp->id)
                ->not->toBe($this->stamp->id);

            expect($createdStamp->country_id)
                ->toBe($this->country->id);

            expect($createdStamp->issue_year)
                ->toBe(2020);

            expect($createdStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($createdStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($createdStamp->designer)
                ->toBe($payload['designer']);

            expect($createdStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($createdStamp->notes)
                ->toBe($payload['notes']);

            $this->assertDatabaseCount('stamps', 2);

            // -------------------------------------------------
            // Bewijs dat de vertaling al is opgeslagen.
            // -------------------------------------------------

            $this->assertDatabaseHas('stamp_translations', [
                'stamp_id' => $createdStampId,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect($createdStamp->translations()->count())
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de catalogusvermelding al bestaat.
            // -------------------------------------------------

            expect($createdEntry->exists)
                ->toBeTrue();

            expect($createdEntry->stamp_id)
                ->toBe($createdStampId);

            expect($createdEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($createdEntry->catalog_number)
                ->toBe('B-500');

            expect($createdEntry->catalogEdition->currency)
                ->toBe('USD');

            expect($createdStamp->catalogEntries()->count())
                ->toBe(1);

            $this->assertDatabaseCount('stamp_catalog_entries', 4);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $createdEntryId,
                'stamp_id' => $createdStampId,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-500',
            ]);

            // -------------------------------------------------
            // Bewijs dat de eerste cataloguswaarde al bestaat.
            // -------------------------------------------------

            $createdValue = $createdEntry->values()
                ->where('condition_id', $this->postfris->id)
                ->sole();

            $createdValueId = $createdValue->id;

            expect($createdValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($createdValue->exists)
                ->toBeTrue();

            expect($createdValue->stamp_catalog_entry_id)
                ->toBe($createdEntryId);

            expect($createdValue->condition_id)
                ->toBe($this->postfris->id);

            expect($createdValue->catalog_value)
                ->toBe('48.75');

            expect($createdValue->currency)
                ->toBe('USD');

            expect($createdValue->needs_review)
                ->toBeFalse();

            expect($createdValue->catalogEntry->id)
                ->toBe($createdEntryId);

            expect($createdValue->condition->id)
                ->toBe($this->postfris->id);

            expect($createdEntry->values()->count())
                ->toBe(1);

            expect($createdStamp->catalogValues()->count())
                ->toBe(1);

            $this->assertDatabaseCount('stamp_catalog_values', 7);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $createdValueId,
                'stamp_catalog_entry_id' => $createdEntryId,
                'condition_id' => $this->postfris->id,
                'catalog_value' => 48.75,
                'currency' => 'USD',
                'needs_review' => false,
            ]);

            // -------------------------------------------------
            // Bewijs dat de geschiedenisbatch al bestaat.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_create',
                'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat beide wijzigingslogs zijn opgeslagen.
            // -------------------------------------------------

            $this->assertDatabaseCount('catalog_change_logs', 2);

            expect($activeBatch->logs()->count())
                ->toBe(2);

            $entryLog = $activeBatch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            $valueLog = $activeBatch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->sole();

            $createdEntryLogId = $entryLog->id;

            $createdValueLogId = $valueLog->id;

            expect($createdEntryLogId)
                ->not->toBe($createdValueLogId);

            expect($createdEntryLogId)
                ->toBeLessThan($createdValueLogId);

            // -------------------------------------------------
            // Controleer de aanmaaklog van de vermelding.
            // -------------------------------------------------

            expect($entryLog->catalog_change_batch_id)
                ->toBe($createdBatchId);

            expect($entryLog->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($entryLog->stamp_id)
                ->toBe($createdStampId);

            expect($entryLog->stamp_series_id)
                ->toBeNull();

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->record_type)
                ->toBe('stamp_catalog_entry');

            expect($entryLog->record_id)
                ->toBe($createdEntryId);

            expect($entryLog->action)
                ->toBe('created');

            expect($entryLog->old_values)
                ->toBeNull();

            expect($entryLog->new_values)
                ->toBeArray();

            expect($entryLog->new_values['id'])
                ->toBe($createdEntryId);

            expect($entryLog->new_values['stamp_id'])
                ->toBe($createdStampId);

            expect($entryLog->new_values['catalog_edition_id'])
                ->toBe($this->editionB1->id);

            expect($entryLog->new_values['catalog_number'])
                ->toBe('B-500');

            expect($entryLog->new_values)
                ->toHaveKeys([
                    'created_at',
                    'updated_at',
                ]);

            $entryAttributes = $createdEntry->getAttributes();

            foreach (
                $entryLog->new_values as $attribute => $loggedValue
            ) {
                expect($entryAttributes)
                    ->toHaveKey($attribute);

                expect($loggedValue)
                    ->toBe($entryAttributes[$attribute]);
            }

            // -------------------------------------------------
            // Controleer de aanmaaklog van de eerste waarde.
            // -------------------------------------------------

            expect($valueLog->catalog_change_batch_id)
                ->toBe($createdBatchId);

            expect($valueLog->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($valueLog->stamp_id)
                ->toBe($createdStampId);

            expect($valueLog->stamp_series_id)
                ->toBeNull();

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->record_type)
                ->toBe('stamp_catalog_value');

            expect($valueLog->record_id)
                ->toBe($createdValueId);

            expect($valueLog->action)
                ->toBe('created');

            expect($valueLog->old_values)
                ->toBeNull();

            expect($valueLog->new_values)
                ->toBeArray();

            expect($valueLog->new_values['id'])
                ->toBe($createdValueId);

            expect($valueLog->new_values['stamp_catalog_entry_id'])
                ->toBe($createdEntryId);

            expect($valueLog->new_values['condition_id'])
                ->toBe($this->postfris->id);

            expect((float) $valueLog->new_values['catalog_value'])
                ->toBe(48.75);

            expect($valueLog->new_values['currency'])
                ->toBe('USD');

            expect($valueLog->new_values)
                ->toHaveKeys([
                    'created_at',
                    'updated_at',
                ]);

            // Vergelijk alle daadwerkelijk gelogde attributen.
            // Database-defaults hoeven niet in de aanmaaklog
            // aanwezig te zijn.
            $valueAttributes = $createdValue->getAttributes();

            foreach (
                $valueLog->new_values as $attribute => $loggedValue
            ) {
                expect($valueAttributes)
                    ->toHaveKey($attribute);

                $storedValue = $valueAttributes[$attribute];

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

            // -------------------------------------------------
            // Controleer de relaties van beide wijzigingslogs.
            // -------------------------------------------------

            foreach ([$entryLog, $valueLog] as $log) {
                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp->id)
                    ->toBe($createdStampId);

                expect($log->stampSeries)
                    ->toBeNull();
            }

            expect($entryLog->condition)
                ->toBeNull();

            expect($valueLog->condition->id)
                ->toBe($this->postfris->id);

            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $createdEntryLogId,
                'catalog_change_batch_id' => $createdBatchId,
                'stamp_id' => $createdStampId,
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $createdEntryId,
                'action' => 'created',
            ]);

            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $createdValueLogId,
                'catalog_change_batch_id' => $createdBatchId,
                'stamp_id' => $createdStampId,
                'condition_id' => $this->postfris->id,
                'record_type' => 'stamp_catalog_value',
                'record_id' => $createdValueId,
                'action' => 'created',
            ]);

            // -------------------------------------------------
            // De tweede waarde is nog niet opgeslagen.
            // -------------------------------------------------

            expect($value->exists)
                ->toBeFalse();

            expect($value->stamp_catalog_entry_id)
                ->toBe($createdEntryId);

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            expect($value->catalog_value)
                ->toBe('24.50');

            expect($value->currency)
                ->toBe('USD');

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $createdEntryId,
                'condition_id' => $this->gestempeld->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'stamp_id' => $createdStampId,
                'condition_id' => $this->gestempeld->id,
                'record_type' => 'stamp_catalog_value',
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->post(
            route('backend.stamps.store'),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdStampId)
            ->toBeInt();

        expect($createdEntryId)
            ->toBeInt();

        expect($createdValueId)
            ->toBeInt();

        expect($createdBatchId)
            ->toBeInt();

        expect($createdEntryLogId)
            ->toBeInt();

        expect($createdValueLogId)
            ->toBeInt();

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer de rollback van de nieuwe postzegel.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamps', [
            'id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamps', [
            'name' => $payload['translations']['nl']['name'],
        ]);

        expect(
            Stamp::query()->find($createdStampId)
        )->toBeNull();

        expect(
            Stamp::query()
                ->orderBy('id')
                ->get()
                ->map(fn ($stamp) => $stamp->getAttributes())
                ->all()
        )->toBe($originalStamps);

        // -------------------------------------------------
        // Controleer de rollback van de vertaling.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_translations', [
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        expect(
            $this->stamp->fresh()
                ->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalTranslations);

        expect(
            $this->stamp->fresh()
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalThemes);

        // -------------------------------------------------
        // Controleer de rollback van de catalogusvermelding.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $createdEntryId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-500',
        ]);

        expect(
            StampCatalogEntry::query()->find($createdEntryId)
        )->toBeNull();

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

        // -------------------------------------------------
        // Controleer de rollback van de eerste cataloguswaarde.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $createdValueId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $createdEntryId,
        ]);

        expect(
            StampCatalogValue::query()->find($createdValueId)
        )->toBeNull();

        // Beide ingediende cataloguswaarden moeten ontbreken.
        foreach ([
            $this->postfris,
            $this->gestempeld,
        ] as $condition) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $createdEntryId,
                'condition_id' => $condition->id,
            ]);
        }

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

        $expectedValues = [
            [
                'model' => $this->valueA1Postfris,
                'amount' => '15.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA1Gestempeld,
                'amount' => '7.50',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA2Postfris,
                'amount' => '20.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA2Gestempeld,
                'amount' => '10.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueB1Postfris,
                'amount' => '25.00',
                'currency' => 'USD',
            ],
            [
                'model' => $this->valueB1Gestempeld,
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
        }

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de oorspronkelijke catalogusrelaties.
        // -------------------------------------------------

        expect($this->stamp->fresh()->catalogEntries()->count())
            ->toBe(3);

        expect($this->stamp->fresh()->catalogValues()->count())
            ->toBe(6);

        foreach ([
            $this->entryA1,
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            expect($entry->fresh()->values()->count())
                ->toBe(2);
        }

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        $this->assertDatabaseCount('catalog_change_batches', 0);

        // -------------------------------------------------
        // Controleer de rollback van beide wijzigingslogs.
        // -------------------------------------------------

        foreach ([
            $createdEntryLogId,
            $createdValueLogId,
        ] as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $createdEntryId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $createdValueId,
        ]);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            CatalogChangeBatch::query()->exists()
        )->toBeFalse();

        expect(
            CatalogChangeLog::query()->exists()
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

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait het aanmaken van een postzegel volledig terug bij een fout nadat een wijzigingslog is opgeslagen', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-600';

    $payload['catalog_values'] = [
        'postfris' => '56.75',
        'gestempeld' => '28.50',
    ];

    $payload['face_value'] = '5.50';

    $payload['currency'] = 'EUR';

    $payload['designer'] = 'Testontwerper wijzigingslog';

    $payload['printing_method'] = 'Offsetdruk';

    $payload['notes'] = 'Rollback na het opslaan van de wijzigingslog';

    // -------------------------------------------------
    // Controleer de geldigheid van de catalogusgegevens.
    // -------------------------------------------------

    expect($this->editionB1->catalog_id)
        ->toBe($this->catalogB->id);

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    foreach ([
        $this->postfris,
        $this->gestempeld,
    ] as $condition) {
        $this->assertDatabaseHas('catalog_condition_rules', [
            'catalog_id' => $this->catalogB->id,
            'condition_id' => $condition->id,
        ]);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout nadat een wijzigingslog is opgeslagen';

    $listenerCalls = 0;

    $observedLogIds = [];

    /** @var int|null $createdStampId */
    $createdStampId = null;

    /** @var int|null $createdEntryId */
    $createdEntryId = null;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    $createdValueIds = [];

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = CatalogChangeLog::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    CatalogChangeLog::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        CatalogChangeLog::created(function (
            CatalogChangeLog $log
        ) use (
            $payload,
            $exceptionMessage,
            &$listenerCalls,
            &$observedLogIds,
            &$createdStampId,
            &$createdEntryId,
            &$createdBatchId,
            &$createdValueIds,
            &$capturedHistory
        ) {
            // Reageer uitsluitend op de nieuwe postzegel
            // en de bedoelde cataloguseditie.
            if (
                $log->catalog_edition_id !== $this->editionB1->id
                || $log->stamp_id === $this->stamp->id
                || $log->action !== 'created'
            ) {
                return;
            }

            $observedLogIds[] = $log->id;

            // Laat de eerste twee wijzigingslogs normaal
            // verwerken. Veroorzaak pas een uitzondering
            // nadat de log voor gestempeld is opgeslagen.
            if (
                $log->record_type !== 'stamp_catalog_value'
                || $log->condition_id !== $this->gestempeld->id
            ) {
                return;
            }

            $listenerCalls++;

            $createdStampId = $log->stamp_id;

            $createdBatchId = $log->catalog_change_batch_id;

            // -------------------------------------------------
            // Bewijs dat de nieuwe postzegel is opgeslagen.
            // -------------------------------------------------

            $createdStamp = Stamp::query()
                ->findOrFail($createdStampId);

            expect($createdStamp->id)
                ->not->toBe($this->stamp->id);

            expect($createdStamp->country_id)
                ->toBe($this->country->id);

            expect($createdStamp->issue_year)
                ->toBe(2020);

            expect($createdStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($createdStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($createdStamp->designer)
                ->toBe($payload['designer']);

            expect($createdStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($createdStamp->notes)
                ->toBe($payload['notes']);

            $this->assertDatabaseCount('stamps', 2);

            // -------------------------------------------------
            // Bewijs dat de vertaling is opgeslagen.
            // -------------------------------------------------

            $this->assertDatabaseHas('stamp_translations', [
                'stamp_id' => $createdStampId,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect($createdStamp->translations()->count())
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de catalogusvermelding is opgeslagen.
            // -------------------------------------------------

            $createdEntry = $createdStamp->catalogEntries()
                ->where('catalog_edition_id', $this->editionB1->id)
                ->sole();

            $createdEntryId = $createdEntry->id;

            expect($createdEntry->exists)
                ->toBeTrue();

            expect($createdEntry->stamp_id)
                ->toBe($createdStampId);

            expect($createdEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($createdEntry->catalog_number)
                ->toBe('B-600');

            expect($createdEntry->catalogEdition->currency)
                ->toBe('USD');

            expect($createdStamp->catalogEntries()->count())
                ->toBe(1);

            $this->assertDatabaseCount('stamp_catalog_entries', 4);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $createdEntryId,
                'stamp_id' => $createdStampId,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-600',
            ]);

            // -------------------------------------------------
            // Bewijs dat beide cataloguswaarden zijn opgeslagen.
            // -------------------------------------------------

            $expectedValues = [
                $this->postfris->id => '56.75',
                $this->gestempeld->id => '28.50',
            ];

            $createdValues = $createdEntry->values()
                ->get()
                ->keyBy('condition_id');

            expect($createdValues->count())
                ->toBe(2);

            foreach ($expectedValues as $conditionId => $amount) {
                $createdValue = $createdValues->get($conditionId);

                expect($createdValue)
                    ->toBeInstanceOf(StampCatalogValue::class);

                $createdValueIds[] = $createdValue->id;

                expect($createdValue->exists)
                    ->toBeTrue();

                expect($createdValue->stamp_catalog_entry_id)
                    ->toBe($createdEntryId);

                expect($createdValue->condition_id)
                    ->toBe($conditionId);

                expect($createdValue->catalog_value)
                    ->toBe($amount);

                expect($createdValue->currency)
                    ->toBe('USD');

                expect($createdValue->needs_review)
                    ->toBeFalse();

                expect($createdValue->catalogEntry->id)
                    ->toBe($createdEntryId);

                expect($createdValue->condition->id)
                    ->toBe($conditionId);

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $createdValue->id,
                    'stamp_catalog_entry_id' => $createdEntryId,
                    'condition_id' => $conditionId,
                    'catalog_value' => (float) $amount,
                    'currency' => 'USD',
                    'needs_review' => false,
                ]);
            }

            expect($createdStamp->catalogValues()->count())
                ->toBe(2);

            $this->assertDatabaseCount('stamp_catalog_values', 8);

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($activeBatch->id)
                ->toBe($createdBatchId);

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_create',
                'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat alle drie wijzigingslogs bestaan.
            // -------------------------------------------------

            expect($log->exists)
                ->toBeTrue();

            expect($log->id)
                ->toBeInt();

            $this->assertDatabaseCount('catalog_change_logs', 3);

            $logs = $activeBatch->logs()
                ->orderBy('id')
                ->get();

            expect($logs->count())
                ->toBe(3);

            expect($logs->pluck('id')->all())
                ->toBe($observedLogIds);

            expect($logs->last()->id)
                ->toBe($log->id);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'created',
                    'created',
                    'created',
                ]);

            expect($logs->pluck('condition_id')->all())
                ->toBe([
                    null,
                    $this->postfris->id,
                    $this->gestempeld->id,
                ]);

            // -------------------------------------------------
            // Controleer de algemene gegevens van alle logs.
            // -------------------------------------------------

            foreach ($logs as $storedLog) {
                expect($storedLog->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($storedLog->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($storedLog->stamp_id)
                    ->toBe($createdStampId);

                expect($storedLog->stamp_series_id)
                    ->toBeNull();

                expect($storedLog->action)
                    ->toBe('created');

                expect($storedLog->old_values)
                    ->toBeNull();

                expect($storedLog->new_values)
                    ->toBeArray();

                expect($storedLog->new_values)
                    ->toHaveKeys([
                        'id',
                        'created_at',
                        'updated_at',
                    ]);

                expect($storedLog->batch->id)
                    ->toBe($createdBatchId);

                expect($storedLog->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($storedLog->stamp->id)
                    ->toBe($createdStampId);

                expect($storedLog->stampSeries)
                    ->toBeNull();

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $storedLog->id,
                    'catalog_change_batch_id' => $createdBatchId,
                    'catalog_edition_id' => $this->editionB1->id,
                    'stamp_id' => $createdStampId,
                    'record_type' => $storedLog->record_type,
                    'record_id' => $storedLog->record_id,
                    'action' => 'created',
                ]);
            }

            // -------------------------------------------------
            // Controleer de aanmaaklog van de vermelding.
            // -------------------------------------------------

            $entryLog = $logs->first();

            expect($entryLog->record_id)
                ->toBe($createdEntryId);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->new_values['id'])
                ->toBe($createdEntryId);

            expect($entryLog->new_values['stamp_id'])
                ->toBe($createdStampId);

            expect($entryLog->new_values['catalog_edition_id'])
                ->toBe($this->editionB1->id);

            expect($entryLog->new_values['catalog_number'])
                ->toBe('B-600');

            $entryAttributes = $createdEntry->getAttributes();

            foreach (
                $entryLog->new_values as $attribute => $loggedValue
            ) {
                expect($entryAttributes)
                    ->toHaveKey($attribute);

                expect($loggedValue)
                    ->toBe($entryAttributes[$attribute]);
            }

            // -------------------------------------------------
            // Controleer beide aanmaaklogs van cataloguswaarden.
            // -------------------------------------------------

            foreach ($expectedValues as $conditionId => $amount) {
                $valueLog = $logs
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('condition_id', $conditionId)
                    ->sole();

                $createdValue = $createdValues->get($conditionId);

                expect($valueLog->record_id)
                    ->toBe($createdValue->id);

                expect($valueLog->condition->id)
                    ->toBe($conditionId);

                expect($valueLog->new_values['id'])
                    ->toBe($createdValue->id);

                expect($valueLog->new_values['stamp_catalog_entry_id'])
                    ->toBe($createdEntryId);

                expect($valueLog->new_values['condition_id'])
                    ->toBe($conditionId);

                expect((float) $valueLog->new_values['catalog_value'])
                    ->toBe((float) $amount);

                expect($valueLog->new_values['currency'])
                    ->toBe('USD');

                $valueAttributes = $createdValue->getAttributes();

                foreach (
                    $valueLog->new_values as $attribute => $loggedValue
                ) {
                    expect($valueAttributes)
                        ->toHaveKey($attribute);

                    $storedValue = $valueAttributes[$attribute];

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
            // Controleer specifiek de laatste wijzigingslog.
            // -------------------------------------------------

            expect($log->record_id)
                ->toBe(
                    $createdValues->get($this->gestempeld->id)->id
                );

            $storedLog = CatalogChangeLog::query()
                ->findOrFail($log->id);

            expect($storedLog->new_values)
                ->toBe($log->new_values);

            expect($storedLog->condition_id)
                ->toBe($this->gestempeld->id);

            expect((float) $storedLog->new_values['catalog_value'])
                ->toBe(28.50);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // De derde wijzigingslog is daadwerkelijk opgeslagen.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->post(
            route('backend.stamps.store'),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdStampId)
            ->toBeInt();

        expect($createdEntryId)
            ->toBeInt();

        expect($createdBatchId)
            ->toBeInt();

        expect($createdValueIds)
            ->toHaveCount(2);

        expect(array_unique($createdValueIds))
            ->toHaveCount(2);

        expect($observedLogIds)
            ->toHaveCount(3);

        expect(array_unique($observedLogIds))
            ->toHaveCount(3);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer de rollback van de nieuwe postzegel.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamps', [
            'id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamps', [
            'name' => $payload['translations']['nl']['name'],
        ]);

        expect(
            Stamp::query()->find($createdStampId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van de vertaling.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_translations', [
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer de rollback van de catalogusvermelding.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $createdEntryId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-600',
        ]);

        expect(
            StampCatalogEntry::query()->find($createdEntryId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide cataloguswaarden.
        // -------------------------------------------------

        foreach ($createdValueIds as $valueId) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $valueId,
            ]);

            expect(
                StampCatalogValue::query()->find($valueId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $createdEntryId,
        ]);

        foreach ([
            $this->postfris,
            $this->gestempeld,
        ] as $condition) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $createdEntryId,
                'condition_id' => $condition->id,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van alle drie wijzigingslogs.
        // -------------------------------------------------

        foreach ($observedLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $createdEntryId,
        ]);

        foreach ($createdValueIds as $valueId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $valueId,
            ]);
        }

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief attributen, timestamps en koppelingen.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de oorspronkelijke catalogusnummers.
        // -------------------------------------------------

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer de oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        $expectedOriginalValues = [
            [
                'model' => $this->valueA1Postfris,
                'amount' => '15.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA1Gestempeld,
                'amount' => '7.50',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA2Postfris,
                'amount' => '20.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueA2Gestempeld,
                'amount' => '10.00',
                'currency' => 'EUR',
            ],
            [
                'model' => $this->valueB1Postfris,
                'amount' => '25.00',
                'currency' => 'USD',
            ],
            [
                'model' => $this->valueB1Gestempeld,
                'amount' => '12.50',
                'currency' => 'USD',
            ],
        ];

        foreach ($expectedOriginalValues as $expectedValue) {
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
        }

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de oorspronkelijke catalogusrelaties.
        // -------------------------------------------------

        expect($this->stamp->fresh()->catalogEntries()->count())
            ->toBe(3);

        expect($this->stamp->fresh()->catalogValues()->count())
            ->toBe(6);

        foreach ([
            $this->entryA1,
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            expect($entry->fresh()->values()->count())
                ->toBe(2);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        CatalogChangeLog::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait het aanmaken van een postzegel volledig terug en verwijdert het afbeeldingsbestand bij een fout tijdens het aanmaken van een afbeeldingsrecord', function () {
    // -------------------------------------------------
    // Gebruik een afzonderlijke tijdelijke opslagschijf.
    // -------------------------------------------------

    Storage::fake('public');

    $disk = Storage::disk('public');

    // Een bestaand bestand mag bij de rollback
    // niet per ongeluk worden verwijderd.
    $existingImagePath =
        'stamps/'.$this->country->slug.'/2020/bestaande-afbeelding.jpg';

    $existingImageContent = 'Bestaande afbeelding rollbacktest';

    $disk->put(
        $existingImagePath,
        $existingImageContent
    );

    $disk->assertExists($existingImagePath);

    $originalFiles = $disk->allFiles();

    sort($originalFiles);

    expect($originalFiles)
        ->toBe([$existingImagePath]);

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('stamp_images', 0);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-700';

    $payload['catalog_values'] = [
        'postfris' => '64.75',
        'gestempeld' => '32.50',
    ];

    $payload['face_value'] = '6.50';

    $payload['currency'] = 'EUR';

    $payload['designer'] = 'Testontwerper afbeeldingsrollback';

    $payload['printing_method'] = 'Offsetdruk';

    $payload['notes'] = 'Rollback bij het aanmaken van een afbeeldingsrecord';

    $payload['image'] = UploadedFile::fake()->image(
        'rollback-s5.jpg',
        600,
        800
    );

    $payload['image_type'] = 'front';

    $payload['alt_text'] = 'Voorzijde rollbacktest S5';

    // -------------------------------------------------
    // Controleer de geldigheid van de catalogusgegevens.
    // -------------------------------------------------

    expect($this->editionB1->catalog_id)
        ->toBe($this->catalogB->id);

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    foreach ([
        $this->postfris,
        $this->gestempeld,
    ] as $condition) {
        $this->assertDatabaseHas('catalog_condition_rules', [
            'catalog_id' => $this->catalogB->id,
            'condition_id' => $condition->id,
        ]);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het aanmaken van een afbeeldingsrecord';

    $listenerCalls = 0;

    /** @var int|null $createdStampId */
    $createdStampId = null;

    /** @var int|null $createdEntryId */
    $createdEntryId = null;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var string|null $storedImagePath */
    $storedImagePath = null;

    $createdValueIds = [];

    $createdLogIds = [];

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampImage::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampImage::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampImage::creating(function (
            StampImage $image
        ) use (
            $payload,
            $disk,
            $existingImagePath,
            $existingImageContent,
            $exceptionMessage,
            &$listenerCalls,
            &$createdStampId,
            &$createdEntryId,
            &$createdBatchId,
            &$storedImagePath,
            &$createdValueIds,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (
                $image->stamp_id === $this->stamp->id
                || $image->alt_text !== $payload['alt_text']
            ) {
                return;
            }

            $listenerCalls++;

            $createdStampId = $image->stamp_id;

            $storedImagePath = $image->path;

            // -------------------------------------------------
            // Bewijs dat de nieuwe postzegel is opgeslagen.
            // -------------------------------------------------

            $createdStamp = Stamp::query()
                ->findOrFail($createdStampId);

            expect($createdStamp->id)
                ->not->toBe($this->stamp->id);

            expect($createdStamp->country_id)
                ->toBe($this->country->id);

            expect($createdStamp->issue_year)
                ->toBe(2020);

            expect($createdStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($createdStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($createdStamp->designer)
                ->toBe($payload['designer']);

            expect($createdStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($createdStamp->notes)
                ->toBe($payload['notes']);

            $this->assertDatabaseCount('stamps', 2);

            // -------------------------------------------------
            // Bewijs dat de vertaling is opgeslagen.
            // -------------------------------------------------

            $this->assertDatabaseHas('stamp_translations', [
                'stamp_id' => $createdStampId,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect($createdStamp->translations()->count())
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de catalogusvermelding is opgeslagen.
            // -------------------------------------------------

            $createdEntry = $createdStamp->catalogEntries()
                ->where('catalog_edition_id', $this->editionB1->id)
                ->sole();

            $createdEntryId = $createdEntry->id;

            expect($createdEntry->catalog_number)
                ->toBe('B-700');

            expect($createdEntry->catalogEdition->currency)
                ->toBe('USD');

            expect($createdStamp->catalogEntries()->count())
                ->toBe(1);

            $this->assertDatabaseCount('stamp_catalog_entries', 4);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $createdEntryId,
                'stamp_id' => $createdStampId,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-700',
            ]);

            // -------------------------------------------------
            // Bewijs dat beide cataloguswaarden zijn opgeslagen.
            // -------------------------------------------------

            $expectedValues = [
                $this->postfris->id => '64.75',
                $this->gestempeld->id => '32.50',
            ];

            $createdValues = $createdEntry->values()
                ->get()
                ->keyBy('condition_id');

            expect($createdValues->count())
                ->toBe(2);

            foreach ($expectedValues as $conditionId => $amount) {
                $createdValue = $createdValues->get($conditionId);

                expect($createdValue)
                    ->toBeInstanceOf(StampCatalogValue::class);

                $createdValueIds[] = $createdValue->id;

                expect($createdValue->stamp_catalog_entry_id)
                    ->toBe($createdEntryId);

                expect($createdValue->condition_id)
                    ->toBe($conditionId);

                expect($createdValue->catalog_value)
                    ->toBe($amount);

                expect($createdValue->currency)
                    ->toBe('USD');

                expect($createdValue->needs_review)
                    ->toBeFalse();

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $createdValue->id,
                    'stamp_catalog_entry_id' => $createdEntryId,
                    'condition_id' => $conditionId,
                    'catalog_value' => (float) $amount,
                    'currency' => 'USD',
                    'needs_review' => false,
                ]);
            }

            expect($createdStamp->catalogValues()->count())
                ->toBe(2);

            $this->assertDatabaseCount('stamp_catalog_values', 8);

            // -------------------------------------------------
            // Bewijs dat de geschiedenisbatch is opgeslagen.
            // -------------------------------------------------

            $batch = CatalogChangeBatch::query()->sole();

            $createdBatchId = $batch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_create',
                'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($batch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($batch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat alle drie wijzigingslogs bestaan.
            // -------------------------------------------------

            $logs = $batch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(3);

            $this->assertDatabaseCount('catalog_change_logs', 3);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('condition_id')->all())
                ->toBe([
                    null,
                    $this->postfris->id,
                    $this->gestempeld->id,
                ]);

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp_id)
                    ->toBe($createdStampId);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('created');

                expect($log->old_values)
                    ->toBeNull();

                expect($log->new_values)
                    ->toBeArray();

                expect($log->new_values)
                    ->toHaveKeys([
                        'id',
                        'created_at',
                        'updated_at',
                    ]);

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp->id)
                    ->toBe($createdStampId);

                expect($log->stampSeries)
                    ->toBeNull();
            }

            // -------------------------------------------------
            // Controleer de aanmaaklog van de vermelding.
            // -------------------------------------------------

            $entryLog = $logs->first();

            expect($entryLog->record_id)
                ->toBe($createdEntryId);

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->new_values['id'])
                ->toBe($createdEntryId);

            expect($entryLog->new_values['stamp_id'])
                ->toBe($createdStampId);

            expect($entryLog->new_values['catalog_edition_id'])
                ->toBe($this->editionB1->id);

            expect($entryLog->new_values['catalog_number'])
                ->toBe('B-700');

            $entryAttributes = $createdEntry->getAttributes();

            foreach (
                $entryLog->new_values as $attribute => $loggedValue
            ) {
                expect($entryAttributes)
                    ->toHaveKey($attribute);

                expect($loggedValue)
                    ->toBe($entryAttributes[$attribute]);
            }

            // -------------------------------------------------
            // Controleer beide aanmaaklogs van cataloguswaarden.
            // -------------------------------------------------

            foreach ($expectedValues as $conditionId => $amount) {
                $valueLog = $logs
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('condition_id', $conditionId)
                    ->sole();

                $createdValue = $createdValues->get($conditionId);

                expect($valueLog->record_id)
                    ->toBe($createdValue->id);

                expect($valueLog->condition->id)
                    ->toBe($conditionId);

                expect($valueLog->new_values['id'])
                    ->toBe($createdValue->id);

                expect($valueLog->new_values['stamp_catalog_entry_id'])
                    ->toBe($createdEntryId);

                expect($valueLog->new_values['condition_id'])
                    ->toBe($conditionId);

                expect((float) $valueLog->new_values['catalog_value'])
                    ->toBe((float) $amount);

                expect($valueLog->new_values['currency'])
                    ->toBe('USD');

                $valueAttributes = $createdValue->getAttributes();

                foreach (
                    $valueLog->new_values as $attribute => $loggedValue
                ) {
                    expect($valueAttributes)
                        ->toHaveKey($attribute);

                    $storedValue = $valueAttributes[$attribute];

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
            // De geschiedeniscontext moet al zijn opgeschoond.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            expect($capturedHistory->getActiveBatch())
                ->toBeNull();

            // -------------------------------------------------
            // Bewijs dat het afbeeldingsbestand al bestaat.
            // -------------------------------------------------

            expect($storedImagePath)
                ->toBeString();

            expect($storedImagePath)
                ->not->toBe($existingImagePath);

            expect($storedImagePath)
                ->toStartWith(
                    'stamps/'.$this->country->slug.'/2020/'
                );

            $disk->assertExists($storedImagePath);

            expect($disk->size($storedImagePath))
                ->toBeGreaterThan(0);

            expect($disk->get($storedImagePath))
                ->toBe(
                    file_get_contents(
                        $payload['image']->getRealPath()
                    )
                );

            // Ook het oorspronkelijke bestand moet nog bestaan.
            $disk->assertExists($existingImagePath);

            expect($disk->get($existingImagePath))
                ->toBe($existingImageContent);

            $expectedFiles = [
                $existingImagePath,
                $storedImagePath,
            ];

            sort($expectedFiles);

            $currentFiles = $disk->allFiles();

            sort($currentFiles);

            expect($currentFiles)
                ->toBe($expectedFiles);

            // -------------------------------------------------
            // Het afbeeldingsrecord is nog niet opgeslagen.
            // -------------------------------------------------

            expect($image->exists)
                ->toBeFalse();

            expect($image->stamp_id)
                ->toBe($createdStampId);

            expect($image->image_type)
                ->toBe('front');

            expect($image->alt_text)
                ->toBe($payload['alt_text']);

            expect($image->is_primary)
                ->toBeFalse();

            expect($createdStamp->images()->count())
                ->toBe(0);

            $this->assertDatabaseCount('stamp_images', 0);

            $this->assertDatabaseMissing('stamp_images', [
                'stamp_id' => $createdStampId,
                'path' => $storedImagePath,
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // Het bestand bestaat, maar het record nog niet.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->post(
            route('backend.stamps.store'),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdStampId)
            ->toBeInt();

        expect($createdEntryId)
            ->toBeInt();

        expect($createdBatchId)
            ->toBeInt();

        expect($storedImagePath)
            ->toBeString();

        expect($createdValueIds)
            ->toHaveCount(2);

        expect(array_unique($createdValueIds))
            ->toHaveCount(2);

        expect($createdLogIds)
            ->toHaveCount(3);

        expect(array_unique($createdLogIds))
            ->toHaveCount(3);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer de verwijdering van het nieuwe bestand.
        // -------------------------------------------------

        $disk->assertMissing($storedImagePath);

        expect($disk->exists($storedImagePath))
            ->toBeFalse();

        // Het oorspronkelijke bestand moet behouden blijven.
        $disk->assertExists($existingImagePath);

        expect($disk->get($existingImagePath))
            ->toBe($existingImageContent);

        $restoredFiles = $disk->allFiles();

        sort($restoredFiles);

        expect($restoredFiles)
            ->toBe($originalFiles);

        // -------------------------------------------------
        // Controleer de rollback van de nieuwe postzegel.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamps', [
            'id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamps', [
            'name' => $payload['translations']['nl']['name'],
        ]);

        expect(
            Stamp::query()->find($createdStampId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van de vertaling.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_translations', [
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer de rollback van de catalogusvermelding.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $createdEntryId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-700',
        ]);

        expect(
            StampCatalogEntry::query()->find($createdEntryId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide cataloguswaarden.
        // -------------------------------------------------

        foreach ($createdValueIds as $valueId) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $valueId,
            ]);

            expect(
                StampCatalogValue::query()->find($valueId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $createdEntryId,
        ]);

        // -------------------------------------------------
        // Controleer de afwezigheid van het afbeeldingsrecord.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_images', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_images', [
            'path' => $storedImagePath,
        ]);

        $this->assertDatabaseCount('stamp_images', 0);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van alle wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $createdStampId,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de oorspronkelijke catalogusnummers.
        // -------------------------------------------------

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer de oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        $expectedOriginalValues = [
            [$this->valueA1Postfris, '15.00', 'EUR'],
            [$this->valueA1Gestempeld, '7.50', 'EUR'],
            [$this->valueA2Postfris, '20.00', 'EUR'],
            [$this->valueA2Gestempeld, '10.00', 'EUR'],
            [$this->valueB1Postfris, '25.00', 'USD'],
            [$this->valueB1Gestempeld, '12.50', 'USD'],
        ];

        foreach (
            $expectedOriginalValues as [$model, $amount, $currency]
        ) {
            $freshValue = $model->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($model->id);

            expect($freshValue->catalog_value)
                ->toBe($amount);

            expect($freshValue->currency)
                ->toBe($currency);

            expect($freshValue->needs_review)
                ->toBeFalse();
        }

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de oorspronkelijke catalogusrelaties.
        // -------------------------------------------------

        expect($this->stamp->fresh()->catalogEntries()->count())
            ->toBe(3);

        expect($this->stamp->fresh()->catalogValues()->count())
            ->toBe(6);

        foreach ([
            $this->entryA1,
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            expect($entry->fresh()->values()->count())
                ->toBe(2);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // -------------------------------------------------
        // Controleer de definitieve geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampImage::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait het aanmaken van een postzegel volledig terug en verwijdert het afbeeldingsbestand bij een fout nadat een afbeeldingsrecord is opgeslagen', function () {
    // -------------------------------------------------
    // Gebruik een afzonderlijke tijdelijke opslagschijf.
    // -------------------------------------------------

    Storage::fake('public');

    $disk = Storage::disk('public');

    $existingImagePath =
        'stamps/'.$this->country->slug.'/2020/bestaande-afbeelding.jpg';

    $existingImageContent = 'Bestaande afbeelding rollbacktest S6';

    $disk->put(
        $existingImagePath,
        $existingImageContent
    );

    $disk->assertExists($existingImagePath);

    $originalFiles = $disk->allFiles();

    sort($originalFiles);

    expect($originalFiles)
        ->toBe([$existingImagePath]);

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('stamp_images', 0);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->storePayload;

    $payload['catalog_id'] = $this->catalogB->id;

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-800';

    $payload['catalog_values'] = [
        'postfris' => '72.75',
        'gestempeld' => '36.50',
    ];

    $payload['face_value'] = '7.50';

    $payload['currency'] = 'EUR';

    $payload['designer'] = 'Testontwerper opgeslagen afbeelding';

    $payload['printing_method'] = 'Offsetdruk';

    $payload['notes'] = 'Rollback na het opslaan van een afbeeldingsrecord';

    $payload['image'] = UploadedFile::fake()->image(
        'rollback-s6.jpg',
        600,
        800
    );

    $payload['image_type'] = 'front';

    $payload['alt_text'] = 'Voorzijde rollbacktest S6';

    // -------------------------------------------------
    // Controleer de geldigheid van de catalogusgegevens.
    // -------------------------------------------------

    expect($this->editionB1->catalog_id)
        ->toBe($this->catalogB->id);

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    foreach ([
        $this->postfris,
        $this->gestempeld,
    ] as $condition) {
        $this->assertDatabaseHas('catalog_condition_rules', [
            'catalog_id' => $this->catalogB->id,
            'condition_id' => $condition->id,
        ]);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout nadat een afbeeldingsrecord is opgeslagen';

    $listenerCalls = 0;

    /** @var int|null $createdStampId */
    $createdStampId = null;

    /** @var int|null $createdEntryId */
    $createdEntryId = null;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var int|null $createdImageId */
    $createdImageId = null;

    /** @var string|null $storedImagePath */
    $storedImagePath = null;

    $createdValueIds = [];

    $createdLogIds = [];

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampImage::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampImage::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampImage::created(function (
            StampImage $image
        ) use (
            $payload,
            $disk,
            $existingImagePath,
            $existingImageContent,
            $exceptionMessage,
            &$listenerCalls,
            &$createdStampId,
            &$createdEntryId,
            &$createdBatchId,
            &$createdImageId,
            &$storedImagePath,
            &$createdValueIds,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (
                $image->stamp_id === $this->stamp->id
                || $image->alt_text !== $payload['alt_text']
            ) {
                return;
            }

            $listenerCalls++;

            $createdStampId = $image->stamp_id;

            $createdImageId = $image->id;

            $storedImagePath = $image->path;

            // -------------------------------------------------
            // Bewijs dat de nieuwe postzegel is opgeslagen.
            // -------------------------------------------------

            $createdStamp = Stamp::query()
                ->findOrFail($createdStampId);

            expect($createdStamp->id)
                ->not->toBe($this->stamp->id);

            expect($createdStamp->country_id)
                ->toBe($this->country->id);

            expect($createdStamp->issue_year)
                ->toBe(2020);

            expect($createdStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($createdStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($createdStamp->designer)
                ->toBe($payload['designer']);

            expect($createdStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($createdStamp->notes)
                ->toBe($payload['notes']);

            $this->assertDatabaseCount('stamps', 2);

            // -------------------------------------------------
            // Bewijs dat de vertaling is opgeslagen.
            // -------------------------------------------------

            $this->assertDatabaseHas('stamp_translations', [
                'stamp_id' => $createdStampId,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect($createdStamp->translations()->count())
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de catalogusvermelding is opgeslagen.
            // -------------------------------------------------

            $createdEntry = $createdStamp->catalogEntries()
                ->where('catalog_edition_id', $this->editionB1->id)
                ->sole();

            $createdEntryId = $createdEntry->id;

            expect($createdEntry->catalog_number)
                ->toBe('B-800');

            expect($createdEntry->catalogEdition->currency)
                ->toBe('USD');

            expect($createdStamp->catalogEntries()->count())
                ->toBe(1);

            $this->assertDatabaseCount('stamp_catalog_entries', 4);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $createdEntryId,
                'stamp_id' => $createdStampId,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-800',
            ]);

            // -------------------------------------------------
            // Bewijs dat beide cataloguswaarden zijn opgeslagen.
            // -------------------------------------------------

            $expectedValues = [
                $this->postfris->id => '72.75',
                $this->gestempeld->id => '36.50',
            ];

            $createdValues = $createdEntry->values()
                ->get()
                ->keyBy('condition_id');

            expect($createdValues->count())
                ->toBe(2);

            foreach ($expectedValues as $conditionId => $amount) {
                $createdValue = $createdValues->get($conditionId);

                expect($createdValue)
                    ->toBeInstanceOf(StampCatalogValue::class);

                $createdValueIds[] = $createdValue->id;

                expect($createdValue->stamp_catalog_entry_id)
                    ->toBe($createdEntryId);

                expect($createdValue->condition_id)
                    ->toBe($conditionId);

                expect($createdValue->catalog_value)
                    ->toBe($amount);

                expect($createdValue->currency)
                    ->toBe('USD');

                expect($createdValue->needs_review)
                    ->toBeFalse();

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $createdValue->id,
                    'stamp_catalog_entry_id' => $createdEntryId,
                    'condition_id' => $conditionId,
                    'catalog_value' => (float) $amount,
                    'currency' => 'USD',
                    'needs_review' => false,
                ]);
            }

            expect($createdStamp->catalogValues()->count())
                ->toBe(2);

            $this->assertDatabaseCount('stamp_catalog_values', 8);

            // -------------------------------------------------
            // Bewijs dat de geschiedenisbatch is opgeslagen.
            // -------------------------------------------------

            $batch = CatalogChangeBatch::query()->sole();

            $createdBatchId = $batch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_create',
                'description' => 'Catalogusgegevens bij het aanmaken van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($batch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($batch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat alle drie wijzigingslogs bestaan.
            // -------------------------------------------------

            $logs = $batch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(3);

            $this->assertDatabaseCount('catalog_change_logs', 3);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('condition_id')->all())
                ->toBe([
                    null,
                    $this->postfris->id,
                    $this->gestempeld->id,
                ]);

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp_id)
                    ->toBe($createdStampId);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('created');

                expect($log->old_values)
                    ->toBeNull();

                expect($log->new_values)
                    ->toBeArray();

                expect($log->new_values)
                    ->toHaveKeys([
                        'id',
                        'created_at',
                        'updated_at',
                    ]);

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp->id)
                    ->toBe($createdStampId);

                expect($log->stampSeries)
                    ->toBeNull();
            }

            // -------------------------------------------------
            // Controleer de aanmaaklog van de vermelding.
            // -------------------------------------------------

            $entryLog = $logs->first();

            expect($entryLog->record_id)
                ->toBe($createdEntryId);

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->new_values['id'])
                ->toBe($createdEntryId);

            expect($entryLog->new_values['stamp_id'])
                ->toBe($createdStampId);

            expect($entryLog->new_values['catalog_edition_id'])
                ->toBe($this->editionB1->id);

            expect($entryLog->new_values['catalog_number'])
                ->toBe('B-800');

            $entryAttributes = $createdEntry->getAttributes();

            foreach (
                $entryLog->new_values as $attribute => $loggedValue
            ) {
                expect($entryAttributes)
                    ->toHaveKey($attribute);

                expect($loggedValue)
                    ->toBe($entryAttributes[$attribute]);
            }

            // -------------------------------------------------
            // Controleer beide aanmaaklogs van cataloguswaarden.
            // -------------------------------------------------

            foreach ($expectedValues as $conditionId => $amount) {
                $valueLog = $logs
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('condition_id', $conditionId)
                    ->sole();

                $createdValue = $createdValues->get($conditionId);

                expect($valueLog->record_id)
                    ->toBe($createdValue->id);

                expect($valueLog->condition->id)
                    ->toBe($conditionId);

                expect($valueLog->new_values['id'])
                    ->toBe($createdValue->id);

                expect($valueLog->new_values['stamp_catalog_entry_id'])
                    ->toBe($createdEntryId);

                expect($valueLog->new_values['condition_id'])
                    ->toBe($conditionId);

                expect((float) $valueLog->new_values['catalog_value'])
                    ->toBe((float) $amount);

                expect($valueLog->new_values['currency'])
                    ->toBe('USD');

                $valueAttributes = $createdValue->getAttributes();

                foreach (
                    $valueLog->new_values as $attribute => $loggedValue
                ) {
                    expect($valueAttributes)
                        ->toHaveKey($attribute);

                    $storedValue = $valueAttributes[$attribute];

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
            // Controleer de opgeschoonde geschiedeniscontext.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            expect($capturedHistory->getActiveBatch())
                ->toBeNull();

            // -------------------------------------------------
            // Bewijs dat het afbeeldingsbestand is opgeslagen.
            // -------------------------------------------------

            expect($storedImagePath)
                ->toBeString();

            expect($storedImagePath)
                ->not->toBe($existingImagePath);

            expect($storedImagePath)
                ->toStartWith(
                    'stamps/'.$this->country->slug.'/2020/'
                );

            $disk->assertExists($storedImagePath);

            expect($disk->size($storedImagePath))
                ->toBeGreaterThan(0);

            expect($disk->get($storedImagePath))
                ->toBe(
                    file_get_contents(
                        $payload['image']->getRealPath()
                    )
                );

            $disk->assertExists($existingImagePath);

            expect($disk->get($existingImagePath))
                ->toBe($existingImageContent);

            $expectedFiles = [
                $existingImagePath,
                $storedImagePath,
            ];

            sort($expectedFiles);

            $currentFiles = $disk->allFiles();

            sort($currentFiles);

            expect($currentFiles)
                ->toBe($expectedFiles);

            // -------------------------------------------------
            // Bewijs dat het afbeeldingsrecord is opgeslagen.
            // -------------------------------------------------

            expect($image->exists)
                ->toBeTrue();

            expect($createdImageId)
                ->toBeInt();

            expect($image->stamp_id)
                ->toBe($createdStampId);

            expect($image->path)
                ->toBe($storedImagePath);

            expect($image->image_type)
                ->toBe('front');

            expect($image->alt_text)
                ->toBe($payload['alt_text']);

            expect($image->is_primary)
                ->toBeFalse();

            $this->assertDatabaseCount('stamp_images', 1);

            $this->assertDatabaseHas('stamp_images', [
                'id' => $createdImageId,
                'stamp_id' => $createdStampId,
                'path' => $storedImagePath,
                'image_type' => 'front',
                'alt_text' => $payload['alt_text'],
                'is_primary' => false,
            ]);

            // Haal het afbeeldingsrecord opnieuw op
            // om te bewijzen dat het werkelijk is opgeslagen.

            $storedImage = StampImage::query()
                ->findOrFail($createdImageId);

            expect($storedImage)
                ->toBeInstanceOf(StampImage::class);

            expect($storedImage->exists)
                ->toBeTrue();

            expect($storedImage->id)
                ->toBe($createdImageId);

            expect($storedImage->stamp_id)
                ->toBe($createdStampId);

            expect($storedImage->path)
                ->toBe($storedImagePath);

            expect($storedImage->image_type)
                ->toBe('front');

            expect($storedImage->alt_text)
                ->toBe($payload['alt_text']);

            expect($storedImage->is_primary)
                ->toBeFalse();

            expect($storedImage->created_at)
                ->not->toBeNull();

            expect($storedImage->updated_at)
                ->not->toBeNull();

            // -------------------------------------------------
            // Controleer de relatie met de nieuwe postzegel.
            // -------------------------------------------------

            expect($createdStamp->images()->count())
                ->toBe(1);

            expect($createdStamp->images()->sole()->id)
                ->toBe($createdImageId);

            expect($this->stamp->fresh()->images()->count())
                ->toBe(0);

            // Het afbeeldingsrecord mag geen afzonderlijke
            // cataloguswijzigingslog veroorzaken.

            $this->assertDatabaseCount('catalog_change_logs', 3);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // Zowel het bestand als het record bestaan.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->post(
            route('backend.stamps.store'),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdStampId)
            ->toBeInt();

        expect($createdEntryId)
            ->toBeInt();

        expect($createdBatchId)
            ->toBeInt();

        expect($createdImageId)
            ->toBeInt();

        expect($storedImagePath)
            ->toBeString();

        expect($createdValueIds)
            ->toHaveCount(2);

        expect(array_unique($createdValueIds))
            ->toHaveCount(2);

        expect($createdLogIds)
            ->toHaveCount(3);

        expect(array_unique($createdLogIds))
            ->toHaveCount(3);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer de verwijdering van het nieuwe bestand.
        // -------------------------------------------------

        $disk->assertMissing($storedImagePath);

        expect($disk->exists($storedImagePath))
            ->toBeFalse();

        // Het oorspronkelijke bestand moet behouden blijven.

        $disk->assertExists($existingImagePath);

        expect($disk->get($existingImagePath))
            ->toBe($existingImageContent);

        $restoredFiles = $disk->allFiles();

        sort($restoredFiles);

        expect($restoredFiles)
            ->toBe($originalFiles);

        // -------------------------------------------------
        // Controleer de rollback van het afbeeldingsrecord.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_images', [
            'id' => $createdImageId,
        ]);

        $this->assertDatabaseMissing('stamp_images', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_images', [
            'path' => $storedImagePath,
        ]);

        expect(
            StampImage::query()->find($createdImageId)
        )->toBeNull();

        $this->assertDatabaseCount('stamp_images', 0);

        expect($this->stamp->fresh()->images()->count())
            ->toBe(0);

        // -------------------------------------------------
        // Controleer de rollback van de nieuwe postzegel.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamps', [
            'id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamps', [
            'name' => $payload['translations']['nl']['name'],
        ]);

        expect(
            Stamp::query()->find($createdStampId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van de vertaling.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_translations', [
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer de rollback van de catalogusvermelding.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $createdEntryId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $createdStampId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_edition_id' => $this->editionB1->id,
            'catalog_number' => 'B-800',
        ]);

        expect(
            StampCatalogEntry::query()->find($createdEntryId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide cataloguswaarden.
        // -------------------------------------------------

        foreach ($createdValueIds as $valueId) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $valueId,
            ]);

            expect(
                StampCatalogValue::query()->find($valueId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $createdEntryId,
        ]);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van alle wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $createdStampId,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de oorspronkelijke catalogusnummers.
        // -------------------------------------------------

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer de oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        $expectedOriginalValues = [
            [$this->valueA1Postfris, '15.00', 'EUR'],
            [$this->valueA1Gestempeld, '7.50', 'EUR'],
            [$this->valueA2Postfris, '20.00', 'EUR'],
            [$this->valueA2Gestempeld, '10.00', 'EUR'],
            [$this->valueB1Postfris, '25.00', 'USD'],
            [$this->valueB1Gestempeld, '12.50', 'USD'],
        ];

        foreach (
            $expectedOriginalValues as [$model, $amount, $currency]
        ) {
            $freshValue = $model->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($model->id);

            expect($freshValue->catalog_value)
                ->toBe($amount);

            expect($freshValue->currency)
                ->toBe($currency);

            expect($freshValue->needs_review)
                ->toBeFalse();
        }

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de oorspronkelijke catalogusrelaties.
        // -------------------------------------------------

        expect($this->stamp->fresh()->catalogEntries()->count())
            ->toBe(3);

        expect($this->stamp->fresh()->catalogValues()->count())
            ->toBe(6);

        foreach ([
            $this->entryA1,
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            expect($entry->fresh()->values()->count())
                ->toBe(2);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        // -------------------------------------------------
        // Controleer de definitieve geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampImage::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait de landwijziging van een postzegel volledig terug bij een fout tijdens het automatisch verwijderen van een cataloguswaarde', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalTranslations = $originalData['translations'];

    $originalThemes = $originalData['themes'];

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    expect($this->stamp->country_id)
        ->toBe($this->country->id);

    expect($this->country->id)
        ->not->toBe($this->otherCountry->id);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Controleer welke catalogi geldig blijven.
    // -------------------------------------------------

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeFalse();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    $invalidEntryIds = [
        $this->entryA1->id,
        $this->entryA2->id,
    ];

    $invalidValueIds = [
        $this->valueA1Postfris->id,
        $this->valueA1Gestempeld->id,
        $this->valueA2Postfris->id,
        $this->valueA2Gestempeld->id,
    ];

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->otherCountry->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest A1';

    $payload['printing_method'] = 'Gewijzigde drukmethode';

    $payload['notes'] = 'Rollback automatische verwijdering cataloguswaarde';

    // Er worden bewust geen catalogusgegevens ingediend.
    // Alleen route A mag catalogusgegevens verwerken.

    expect($payload)
        ->not->toHaveKey('catalog_entries');

    expect($payload)
        ->not->toHaveKey('catalog_editions');

    expect($payload)
        ->not->toHaveKey('catalog_edition_id');

    expect($payload)
        ->not->toHaveKey('catalog_values');

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het automatisch verwijderen van een cataloguswaarde';

    $listenerCalls = 0;

    $observedValueIds = [];

    /** @var int|null $deletedValueId */
    $deletedValueId = null;

    /** @var int|null $failedValueId */
    $failedValueId = null;

    /** @var int|null $affectedEntryId */
    $affectedEntryId = null;

    /** @var int|null $affectedEditionId */
    $affectedEditionId = null;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var int|null $createdLogId */
    $createdLogId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::deleting(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalTranslations,
            $originalThemes,
            $originalValues,
            $invalidEntryIds,
            $invalidValueIds,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$deletedValueId,
            &$failedValueId,
            &$affectedEntryId,
            &$affectedEditionId,
            &$createdBatchId,
            &$createdLogId,
            &$capturedHistory
        ) {
            if (
                ! in_array(
                    $value->stamp_catalog_entry_id,
                    $invalidEntryIds,
                    true
                )
            ) {
                return;
            }

            $listenerCalls++;

            $observedValueIds[] = $value->id;

            expect($value->id)
                ->toBeIn($invalidValueIds);

            // Laat de eerste cataloguswaarde normaal
            // verwijderen, inclusief de wijzigingslog.

            if ($listenerCalls === 1) {
                $deletedValueId = $value->id;

                $affectedEntryId =
                    $value->stamp_catalog_entry_id;

                $affectedEditionId =
                    $value->catalogEntry->catalog_edition_id;

                return;
            }

            // Veroorzaak bij de tweede verwijdering een fout.

            expect($listenerCalls)
                ->toBe(2);

            $failedValueId = $value->id;

            expect($failedValueId)
                ->not->toBe($deletedValueId);

            expect($value->stamp_catalog_entry_id)
                ->toBe($affectedEntryId);

            // -------------------------------------------------
            // Bewijs dat de postzegel al is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->id)
                ->toBe($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->otherCountry->id);

            expect($changedStamp->country_id)
                ->not->toBe($originalStamp['country_id']);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->otherCountry->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Route A vindt plaats vóór het synchroniseren
            // van vertalingen en thema's.
            // -------------------------------------------------

            expect(
                $changedStamp->translations()
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($translation) => $translation->getAttributes())
                    ->all()
            )->toBe($originalTranslations);

            expect(
                $changedStamp->themes()
                    ->orderBy('themes.id')
                    ->pluck('themes.id')
                    ->all()
            )->toBe($originalThemes);

            // -------------------------------------------------
            // Bewijs dat de eerste cataloguswaarde is verwijderd.
            // -------------------------------------------------

            expect($deletedValueId)
                ->toBeInt();

            expect(
                StampCatalogValue::query()->find($deletedValueId)
            )->toBeNull();

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $deletedValueId,
            ]);

            $this->assertDatabaseCount('stamp_catalog_values', 5);

            // -------------------------------------------------
            // De tweede cataloguswaarde bestaat nog.
            // -------------------------------------------------

            expect($value->exists)
                ->toBeTrue();

            $remainingValue = StampCatalogValue::query()
                ->findOrFail($failedValueId);

            expect($remainingValue->getAttributes())
                ->toBe($originalValues[$failedValueId]);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $failedValueId,
                'stamp_catalog_entry_id' => $affectedEntryId,
            ]);

            // -------------------------------------------------
            // De overige cataloguswaarden moeten nog bestaan.
            // -------------------------------------------------

            foreach ($originalValues as $valueId => $attributes) {
                if ($valueId === $deletedValueId) {
                    continue;
                }

                $storedValue = StampCatalogValue::query()
                    ->findOrFail($valueId);

                expect($storedValue->getAttributes())
                    ->toBe($attributes);
            }

            // -------------------------------------------------
            // Alle catalogusvermeldingen bestaan nog.
            // -------------------------------------------------

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $affectedEntry = StampCatalogEntry::query()
                ->findOrFail($affectedEntryId);

            expect($affectedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($affectedEntry->catalog_edition_id)
                ->toBe($affectedEditionId);

            expect($affectedEntry->values()->count())
                ->toBe(1);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(5);

            // -------------------------------------------------
            // Controleer de geldige catalogusvermelding B1.
            // -------------------------------------------------

            expect($this->entryB1->fresh()->catalog_number)
                ->toBe('B-100');

            expect($this->entryB1->fresh()->values()->count())
                ->toBe(2);

            expect($this->valueB1Postfris->fresh()->catalog_value)
                ->toBe('25.00');

            expect($this->valueB1Gestempeld->fresh()->catalog_value)
                ->toBe('12.50');

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $affectedEdition = CatalogEdition::query()
                ->findOrFail($affectedEditionId);

            expect($affectedEdition->catalog_id)
                ->toBe($this->catalogA->id);

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $affectedEditionId,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogA->name,
                'catalog_edition_name' => $affectedEdition->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($affectedEditionId);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat de verwijderingslog is opgeslagen.
            // -------------------------------------------------

            $this->assertDatabaseCount('catalog_change_logs', 1);

            $log = $activeBatch->logs()->sole();

            $createdLogId = $log->id;

            $deletedValueAttributes =
                $originalValues[$deletedValueId];

            expect($log->catalog_change_batch_id)
                ->toBe($createdBatchId);

            expect($log->catalog_edition_id)
                ->toBe($affectedEditionId);

            expect($log->stamp_id)
                ->toBe($this->stamp->id);

            expect($log->stamp_series_id)
                ->toBeNull();

            expect($log->condition_id)
                ->toBe($deletedValueAttributes['condition_id']);

            expect($log->record_type)
                ->toBe('stamp_catalog_value');

            expect($log->record_id)
                ->toBe($deletedValueId);

            expect($log->action)
                ->toBe('deleted');

            expect($log->old_values)
                ->toBeArray();

            expect($log->new_values)
                ->toBeNull();

            // -------------------------------------------------
            // Vergelijk alle oorspronkelijke attributen
            // met de inhoud van de verwijderingslog.
            // -------------------------------------------------

            $loggedAttributes = $log->old_values;

            ksort($loggedAttributes);

            ksort($deletedValueAttributes);

            expect(array_keys($loggedAttributes))
                ->toBe(array_keys($deletedValueAttributes));

            foreach (
                $deletedValueAttributes as $attribute => $originalValue
            ) {
                expect($loggedAttributes)
                    ->toHaveKey($attribute);

                $loggedValue = $loggedAttributes[$attribute];

                if ($attribute === 'catalog_value') {
                    expect((float) $loggedValue)
                        ->toBe((float) $originalValue);

                    continue;
                }

                if ($attribute === 'needs_review') {
                    expect((bool) $loggedValue)
                        ->toBe((bool) $originalValue);

                    continue;
                }

                expect($loggedValue)
                    ->toBe($originalValue);
            }

            // -------------------------------------------------
            // Controleer de relaties van de verwijderingslog.
            // -------------------------------------------------

            expect($log->batch->id)
                ->toBe($createdBatchId);

            expect($log->catalogEdition->id)
                ->toBe($affectedEditionId);

            expect($log->stamp->id)
                ->toBe($this->stamp->id);

            expect($log->stampSeries)
                ->toBeNull();

            expect($log->condition->id)
                ->toBe($deletedValueAttributes['condition_id']);

            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $createdLogId,
                'catalog_change_batch_id' => $createdBatchId,
                'catalog_edition_id' => $affectedEditionId,
                'stamp_id' => $this->stamp->id,
                'condition_id' => $deletedValueAttributes['condition_id'],
                'record_type' => 'stamp_catalog_value',
                'record_id' => $deletedValueId,
                'action' => 'deleted',
            ]);

            // -------------------------------------------------
            // Voor de tweede waarde bestaat nog geen log.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $failedValueId,
            ]);

            // Er mag ook nog geen verwijderingslog voor
            // een catalogusvermelding bestaan.

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'action' => 'deleted',
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(2);

        expect($observedValueIds)
            ->toHaveCount(2);

        expect(array_unique($observedValueIds))
            ->toHaveCount(2);

        expect($deletedValueId)
            ->toBeInt();

        expect($failedValueId)
            ->toBeInt();

        expect($affectedEntryId)
            ->toBeInt();

        expect($affectedEditionId)
            ->toBeInt();

        expect($createdBatchId)
            ->toBeInt();

        expect($createdLogId)
            ->toBeInt();

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het volledige herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->country_id)
            ->toBe($this->country->id);

        expect($restoredStamp->country_id)
            ->not->toBe($this->otherCountry->id);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // -------------------------------------------------
        // Controleer het behoud van vertalingen en thema's.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalTranslations);

        expect(
            $restoredStamp->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalThemes);

        // -------------------------------------------------
        // Controleer het herstel van de verwijderde waarde.
        // -------------------------------------------------

        $restoredValue = StampCatalogValue::query()
            ->findOrFail($deletedValueId);

        expect($restoredValue->exists)
            ->toBeTrue();

        expect($restoredValue->id)
            ->toBe($deletedValueId);

        expect($restoredValue->getAttributes())
            ->toBe($originalValues[$deletedValueId]);

        expect($restoredValue->stamp_catalog_entry_id)
            ->toBe($affectedEntryId);

        expect($restoredValue->catalogEntry->id)
            ->toBe($affectedEntryId);

        expect($restoredValue->needs_review)
            ->toBeFalse();

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $deletedValueId,
            'stamp_catalog_entry_id' => $affectedEntryId,
        ]);

        // -------------------------------------------------
        // Controleer ook de waarde waarbij de fout ontstond.
        // -------------------------------------------------

        $unchangedValue = StampCatalogValue::query()
            ->findOrFail($failedValueId);

        expect($unchangedValue->getAttributes())
            ->toBe($originalValues[$failedValueId]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            $storedValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($storedValue->getAttributes())
                ->toBe($attributes);
        }

        $expectedOriginalValues = [
            [$this->valueA1Postfris, '15.00', 'EUR'],
            [$this->valueA1Gestempeld, '7.50', 'EUR'],
            [$this->valueA2Postfris, '20.00', 'EUR'],
            [$this->valueA2Gestempeld, '10.00', 'EUR'],
            [$this->valueB1Postfris, '25.00', 'USD'],
            [$this->valueB1Gestempeld, '12.50', 'USD'],
        ];

        foreach (
            $expectedOriginalValues as [$model, $amount, $currency]
        ) {
            $freshValue = $model->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($model->id);

            expect($freshValue->catalog_value)
                ->toBe($amount);

            expect($freshValue->currency)
                ->toBe($currency);

            expect($freshValue->needs_review)
                ->toBeFalse();
        }

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        foreach ([
            $this->entryA1,
            $this->entryA2,
            $this->entryB1,
        ] as $entry) {
            $freshEntry = $entry->fresh();

            expect($freshEntry->id)
                ->toBe($entry->id);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van de verwijderingslog.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_logs', [
            'id' => $createdLogId,
        ]);

        expect(
            CatalogChangeLog::query()->find($createdLogId)
        )->toBeNull();

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $deletedValueId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait de landwijziging van een postzegel volledig terug bij een fout tijdens het automatisch verwijderen van een catalogusvermelding', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    expect($this->stamp->country_id)
        ->toBe($this->country->id);

    expect($this->country->id)
        ->not->toBe($this->otherCountry->id);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Controleer welke catalogi geldig blijven.
    // -------------------------------------------------

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeFalse();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    $invalidEntryIds = [
        $this->entryA1->id,
        $this->entryA2->id,
    ];

    foreach ([
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ] as $entry) {
        expect($entry->values()->count())
            ->toBe(2);
    }

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->otherCountry->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest A2';

    $payload['printing_method'] = 'Gewijzigde drukmethode A2';

    $payload['notes'] = 'Rollback automatische verwijdering catalogusvermelding';

    // Alleen route A mag catalogusgegevens verwerken.

    foreach ([
        'catalog_entries',
        'catalog_editions',
        'catalog_edition_id',
        'catalog_values',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het automatisch verwijderen van een catalogusvermelding';

    $listenerCalls = 0;

    /** @var int|null $affectedEntryId */
    $affectedEntryId = null;

    /** @var int|null $affectedEditionId */
    $affectedEditionId = null;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    $deletedValueIds = [];

    $createdLogIds = [];

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogEntry::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogEntry::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogEntry::deleting(function (
            StampCatalogEntry $entry
        ) use (
            $payload,
            $originalData,
            $originalStamp,
            $originalEntries,
            $originalValues,
            $invalidEntryIds,
            $exceptionMessage,
            &$listenerCalls,
            &$affectedEntryId,
            &$affectedEditionId,
            &$createdBatchId,
            &$deletedValueIds,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (
                ! in_array(
                    $entry->id,
                    $invalidEntryIds,
                    true
                )
            ) {
                return;
            }

            $listenerCalls++;

            $affectedEntryId = $entry->id;

            $affectedEditionId = $entry->catalog_edition_id;

            expect($listenerCalls)
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de postzegel al is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->id)
                ->toBe($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->otherCountry->id);

            expect($changedStamp->country_id)
                ->not->toBe($originalStamp['country_id']);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->otherCountry->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Vertalingen en thema's zijn nog niet gewijzigd.
            // -------------------------------------------------

            expect(
                $changedStamp->translations()
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($translation) => $translation->getAttributes())
                    ->all()
            )->toBe($originalData['translations']);

            expect(
                $changedStamp->themes()
                    ->orderBy('themes.id')
                    ->pluck('themes.id')
                    ->all()
            )->toBe($originalData['themes']);

            // -------------------------------------------------
            // Bewijs dat beide cataloguswaarden zijn verwijderd.
            // -------------------------------------------------

            foreach ($originalValues as $valueId => $attributes) {
                if (
                    $attributes['stamp_catalog_entry_id']
                    === $affectedEntryId
                ) {
                    $deletedValueIds[] = $valueId;

                    $this->assertDatabaseMissing(
                        'stamp_catalog_values',
                        ['id' => $valueId]
                    );

                    expect(
                        StampCatalogValue::query()->find($valueId)
                    )->toBeNull();

                    continue;
                }

                // Alle overige waarden moeten ongewijzigd zijn.

                $storedValue = StampCatalogValue::query()
                    ->findOrFail($valueId);

                expect($storedValue->getAttributes())
                    ->toBe($attributes);
            }

            expect($deletedValueIds)
                ->toHaveCount(2);

            expect(array_unique($deletedValueIds))
                ->toHaveCount(2);

            expect($entry->values()->count())
                ->toBe(0);

            $this->assertDatabaseCount('stamp_catalog_values', 4);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $affectedEntryId,
            ]);

            // -------------------------------------------------
            // De catalogusvermelding bestaat nog.
            // -------------------------------------------------

            expect($entry->exists)
                ->toBeTrue();

            $storedEntry = StampCatalogEntry::query()
                ->findOrFail($affectedEntryId);

            expect($storedEntry->getAttributes())
                ->toBe($originalEntries[$affectedEntryId]);

            expect($storedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($storedEntry->catalog_edition_id)
                ->toBe($affectedEditionId);

            expect($storedEntry->values()->count())
                ->toBe(0);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $affectedEntryId,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $affectedEditionId,
            ]);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(4);

            // Alle vermeldingen moeten nog ongewijzigd bestaan.

            foreach ($originalEntries as $entryId => $attributes) {
                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            // -------------------------------------------------
            // Controleer de tweede ongeldige vermelding.
            // -------------------------------------------------

            $remainingInvalidEntryId = collect($invalidEntryIds)
                ->first(fn ($id) => $id !== $affectedEntryId);

            expect($remainingInvalidEntryId)
                ->toBeInt();

            $remainingInvalidEntry = StampCatalogEntry::query()
                ->findOrFail($remainingInvalidEntryId);

            expect($remainingInvalidEntry->values()->count())
                ->toBe(2);

            expect($remainingInvalidEntry->getAttributes())
                ->toBe($originalEntries[$remainingInvalidEntryId]);

            // -------------------------------------------------
            // Controleer de geldige catalogusvermelding B1.
            // -------------------------------------------------

            expect($this->entryB1->fresh()->catalog_number)
                ->toBe('B-100');

            expect($this->entryB1->fresh()->values()->count())
                ->toBe(2);

            expect($this->valueB1Postfris->fresh()->catalog_value)
                ->toBe('25.00');

            expect($this->valueB1Gestempeld->fresh()->catalog_value)
                ->toBe('12.50');

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $affectedEdition = CatalogEdition::query()
                ->findOrFail($affectedEditionId);

            expect($affectedEdition->catalog_id)
                ->toBe($this->catalogA->id);

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $affectedEditionId,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogA->name,
                'catalog_edition_name' => $affectedEdition->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($affectedEditionId);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat beide verwijderingslogs zijn opgeslagen.
            // -------------------------------------------------

            $logs = $activeBatch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(2);

            $this->assertDatabaseCount('catalog_change_logs', 2);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_value',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'deleted',
                    'deleted',
                ]);

            $loggedValueIds = $logs->pluck('record_id')->all();

            $expectedDeletedValueIds = $deletedValueIds;

            sort($loggedValueIds);

            sort($expectedDeletedValueIds);

            expect($loggedValueIds)
                ->toBe($expectedDeletedValueIds);

            // -------------------------------------------------
            // Controleer beide verwijderingslogs afzonderlijk.
            // -------------------------------------------------

            foreach ($logs as $log) {
                $deletedValueAttributes =
                    $originalValues[$log->record_id];

                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($affectedEditionId);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->condition_id)
                    ->toBe($deletedValueAttributes['condition_id']);

                expect($log->record_type)
                    ->toBe('stamp_catalog_value');

                expect($log->record_id)
                    ->toBeIn($deletedValueIds);

                expect($log->action)
                    ->toBe('deleted');

                expect($log->old_values)
                    ->toBeArray();

                expect($log->new_values)
                    ->toBeNull();

                expect($log->old_values['stamp_catalog_entry_id'])
                    ->toBe($affectedEntryId);

                // -------------------------------------------------
                // Vergelijk alle oorspronkelijke attributen.
                // -------------------------------------------------

                $loggedAttributes = $log->old_values;

                ksort($loggedAttributes);

                ksort($deletedValueAttributes);

                expect(array_keys($loggedAttributes))
                    ->toBe(array_keys($deletedValueAttributes));

                foreach (
                    $deletedValueAttributes as $attribute => $originalValue
                ) {
                    expect($loggedAttributes)
                        ->toHaveKey($attribute);

                    $loggedValue = $loggedAttributes[$attribute];

                    if ($attribute === 'catalog_value') {
                        expect((float) $loggedValue)
                            ->toBe((float) $originalValue);

                        continue;
                    }

                    if ($attribute === 'needs_review') {
                        expect((bool) $loggedValue)
                            ->toBe((bool) $originalValue);

                        continue;
                    }

                    expect($loggedValue)
                        ->toBe($originalValue);
                }

                // -------------------------------------------------
                // Controleer de relaties van de wijzigingslog.
                // -------------------------------------------------

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($affectedEditionId);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                expect($log->condition->id)
                    ->toBe($deletedValueAttributes['condition_id']);

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $createdBatchId,
                    'catalog_edition_id' => $affectedEditionId,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $deletedValueAttributes['condition_id'],
                    'record_type' => 'stamp_catalog_value',
                    'record_id' => $log->record_id,
                    'action' => 'deleted',
                ]);
            }

            // -------------------------------------------------
            // De vermelding zelf is nog niet verwijderd.
            // Er mag daarom nog geen verwijderingslog bestaan.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $affectedEntryId,
                'action' => 'deleted',
            ]);

            expect(
                CatalogChangeLog::query()
                    ->where('record_type', 'stamp_catalog_entry')
                    ->count()
            )->toBe(0);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // Beide waarden en hun logs zijn al verwerkt.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($affectedEntryId)
            ->toBeInt();

        expect($affectedEntryId)
            ->toBeIn($invalidEntryIds);

        expect($affectedEditionId)
            ->toBeInt();

        expect($createdBatchId)
            ->toBeInt();

        expect($deletedValueIds)
            ->toHaveCount(2);

        expect(array_unique($deletedValueIds))
            ->toHaveCount(2);

        expect($createdLogIds)
            ->toHaveCount(2);

        expect(array_unique($createdLogIds))
            ->toHaveCount(2);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het volledige herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->country_id)
            ->toBe($this->country->id);

        expect($restoredStamp->country_id)
            ->not->toBe($this->otherCountry->id);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // -------------------------------------------------
        // Controleer het behoud van vertalingen en thema's.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalData['translations']);

        expect(
            $restoredStamp->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalData['themes']);

        // -------------------------------------------------
        // Controleer het behoud van de catalogusvermelding.
        // -------------------------------------------------

        $restoredEntry = StampCatalogEntry::query()
            ->findOrFail($affectedEntryId);

        expect($restoredEntry->exists)
            ->toBeTrue();

        expect($restoredEntry->id)
            ->toBe($affectedEntryId);

        expect($restoredEntry->getAttributes())
            ->toBe($originalEntries[$affectedEntryId]);

        expect($restoredEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($restoredEntry->catalog_edition_id)
            ->toBe($affectedEditionId);

        expect($restoredEntry->values()->count())
            ->toBe(2);

        // -------------------------------------------------
        // Controleer het herstel van beide verwijderde waarden.
        // -------------------------------------------------

        foreach ($deletedValueIds as $valueId) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($restoredValue->exists)
                ->toBeTrue();

            expect($restoredValue->id)
                ->toBe($valueId);

            expect($restoredValue->getAttributes())
                ->toBe($originalValues[$valueId]);

            expect($restoredValue->stamp_catalog_entry_id)
                ->toBe($affectedEntryId);

            expect($restoredValue->catalogEntry->id)
                ->toBe($affectedEntryId);

            expect($restoredValue->condition->id)
                ->toBe($originalValues[$valueId]['condition_id']);

            expect($restoredValue->needs_review)
                ->toBeFalse();

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $valueId,
                'stamp_catalog_entry_id' => $affectedEntryId,
                'condition_id' => $originalValues[$valueId]['condition_id'],
                'catalog_value' => $originalValues[$valueId]['catalog_value'],
                'currency' => $originalValues[$valueId]['currency'],
                'needs_review' => false,
            ]);
        }

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        $expectedOriginalValues = [
            [$this->valueA1Postfris, '15.00', 'EUR'],
            [$this->valueA1Gestempeld, '7.50', 'EUR'],
            [$this->valueA2Postfris, '20.00', 'EUR'],
            [$this->valueA2Gestempeld, '10.00', 'EUR'],
            [$this->valueB1Postfris, '25.00', 'USD'],
            [$this->valueB1Gestempeld, '12.50', 'USD'],
        ];

        foreach (
            $expectedOriginalValues as [$model, $amount, $currency]
        ) {
            $freshValue = $model->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($model->id);

            expect($freshValue->catalog_value)
                ->toBe($amount);

            expect($freshValue->currency)
                ->toBe($currency);

            expect($freshValue->needs_review)
                ->toBeFalse();
        }

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $freshEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($freshEntry->getAttributes())
                ->toBe($attributes);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide verwijderingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        foreach ($deletedValueIds as $valueId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $valueId,
            ]);
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogEntry::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait de landwijziging van een postzegel volledig terug bij een fout tijdens de verwerking van de tweede ongeldige catalogusvermelding', function () {
    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    expect($this->stamp->country_id)
        ->toBe($this->country->id);

    expect($this->country->id)
        ->not->toBe($this->otherCountry->id);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    // -------------------------------------------------
    // Controleer welke catalogi geldig blijven.
    // -------------------------------------------------

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeFalse();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    $invalidEntryIds = [
        $this->entryA1->id,
        $this->entryA2->id,
    ];

    foreach ([
        $this->entryA1,
        $this->entryA2,
        $this->entryB1,
    ] as $entry) {
        expect($entry->values()->count())
            ->toBe(2);
    }

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->otherCountry->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest A3';

    $payload['printing_method'] = 'Gewijzigde drukmethode A3';

    $payload['notes'] = 'Rollback verwerking tweede ongeldige catalogusvermelding';

    // Alleen route A mag catalogusgegevens verwerken.

    foreach ([
        'catalog_entries',
        'catalog_editions',
        'catalog_edition_id',
        'catalog_values',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens de verwerking van de tweede ongeldige catalogusvermelding';

    $listenerCalls = 0;

    $observedValueIds = [];

    $deletedValueIds = [];

    $createdBatchIds = [];

    $createdLogIds = [];

    /** @var int|null $firstEntryId */
    $firstEntryId = null;

    /** @var int|null $firstEditionId */
    $firstEditionId = null;

    /** @var int|null $secondEntryId */
    $secondEntryId = null;

    /** @var int|null $secondEditionId */
    $secondEditionId = null;

    /** @var int|null $failedValueId */
    $failedValueId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::deleting(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalData,
            $originalStamp,
            $originalEntries,
            $originalValues,
            $invalidEntryIds,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$deletedValueIds,
            &$createdBatchIds,
            &$createdLogIds,
            &$firstEntryId,
            &$firstEditionId,
            &$secondEntryId,
            &$secondEditionId,
            &$failedValueId,
            &$capturedHistory
        ) {
            if (
                ! in_array(
                    $value->stamp_catalog_entry_id,
                    $invalidEntryIds,
                    true
                )
            ) {
                return;
            }

            $listenerCalls++;

            $observedValueIds[] = $value->id;

            // -------------------------------------------------
            // Bepaal dynamisch welke vermelding als eerste
            // wordt verwerkt.
            // -------------------------------------------------

            if ($listenerCalls === 1) {
                $firstEntryId = $value->stamp_catalog_entry_id;

                $firstEditionId =
                    $value->catalogEntry->catalog_edition_id;
            }

            // -------------------------------------------------
            // Laat beide waarden van de eerste vermelding
            // normaal verwijderen.
            // -------------------------------------------------

            if ($value->stamp_catalog_entry_id === $firstEntryId) {
                expect($listenerCalls)
                    ->toBeLessThanOrEqual(2);

                $deletedValueIds[] = $value->id;

                return;
            }

            // -------------------------------------------------
            // De tweede ongeldige vermelding wordt verwerkt.
            // -------------------------------------------------

            expect($listenerCalls)
                ->toBe(3);

            $secondEntryId = $value->stamp_catalog_entry_id;

            $secondEditionId =
                $value->catalogEntry->catalog_edition_id;

            $failedValueId = $value->id;

            expect($firstEntryId)
                ->toBeIn($invalidEntryIds);

            expect($secondEntryId)
                ->toBeIn($invalidEntryIds);

            expect($secondEntryId)
                ->not->toBe($firstEntryId);

            expect($secondEditionId)
                ->not->toBe($firstEditionId);

            expect($deletedValueIds)
                ->toHaveCount(2);

            expect(array_unique($deletedValueIds))
                ->toHaveCount(2);

            expect($failedValueId)
                ->not->toBeIn($deletedValueIds);

            // -------------------------------------------------
            // Bewijs dat de postzegel al is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->id)
                ->toBe($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->otherCountry->id);

            expect($changedStamp->country_id)
                ->not->toBe($originalStamp['country_id']);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->otherCountry->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Vertalingen en thema's zijn nog niet gewijzigd.
            // -------------------------------------------------

            expect(
                $changedStamp->translations()
                    ->orderBy('id')
                    ->get()
                    ->map(fn ($translation) => $translation->getAttributes())
                    ->all()
            )->toBe($originalData['translations']);

            expect(
                $changedStamp->themes()
                    ->orderBy('themes.id')
                    ->pluck('themes.id')
                    ->all()
            )->toBe($originalData['themes']);

            // -------------------------------------------------
            // Bewijs dat de eerste vermelding daadwerkelijk
            // uit de database is verwijderd.
            // -------------------------------------------------

            expect(
                StampCatalogEntry::query()->find($firstEntryId)
            )->toBeNull();

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $firstEntryId,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $firstEditionId,
            ]);

            $this->assertDatabaseCount('stamp_catalog_entries', 2);

            // -------------------------------------------------
            // Bewijs dat beide bijbehorende waarden
            // daadwerkelijk zijn verwijderd.
            // -------------------------------------------------

            foreach ($originalValues as $valueId => $attributes) {
                if (
                    $attributes['stamp_catalog_entry_id']
                    === $firstEntryId
                ) {
                    expect($valueId)
                        ->toBeIn($deletedValueIds);

                    expect(
                        StampCatalogValue::query()->find($valueId)
                    )->toBeNull();

                    $this->assertDatabaseMissing(
                        'stamp_catalog_values',
                        ['id' => $valueId]
                    );

                    continue;
                }

                // Alle overige waarden moeten ongewijzigd zijn.

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $firstEntryId,
            ]);

            $this->assertDatabaseCount('stamp_catalog_values', 4);

            // -------------------------------------------------
            // De tweede ongeldige vermelding bestaat nog.
            // -------------------------------------------------

            $secondEntry = StampCatalogEntry::query()
                ->findOrFail($secondEntryId);

            expect($secondEntry->getAttributes())
                ->toBe($originalEntries[$secondEntryId]);

            expect($secondEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($secondEntry->catalog_edition_id)
                ->toBe($secondEditionId);

            expect($secondEntry->values()->count())
                ->toBe(2);

            expect($value->exists)
                ->toBeTrue();

            expect(
                StampCatalogValue::query()
                    ->findOrFail($failedValueId)
                    ->getAttributes()
            )->toBe($originalValues[$failedValueId]);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $failedValueId,
                'stamp_catalog_entry_id' => $secondEntryId,
            ]);

            // -------------------------------------------------
            // Controleer de geldige catalogusvermelding B1.
            // -------------------------------------------------

            expect($this->entryB1->fresh()->getAttributes())
                ->toBe($originalEntries[$this->entryB1->id]);

            expect($this->entryB1->fresh()->catalog_number)
                ->toBe('B-100');

            expect($this->entryB1->fresh()->values()->count())
                ->toBe(2);

            expect($this->valueB1Postfris->fresh()->getAttributes())
                ->toBe($originalValues[$this->valueB1Postfris->id]);

            expect($this->valueB1Gestempeld->fresh()->getAttributes())
                ->toBe($originalValues[$this->valueB1Gestempeld->id]);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(2);

            expect($changedStamp->catalogValues()->count())
                ->toBe(4);

            // -------------------------------------------------
            // Bewijs dat twee geschiedenisbatches bestaan.
            // -------------------------------------------------

            $this->assertDatabaseCount('catalog_change_batches', 2);

            $batches = CatalogChangeBatch::query()
                ->get()
                ->keyBy('catalog_edition_id');

            $firstBatch = $batches->get($firstEditionId);

            $secondBatch = $batches->get($secondEditionId);

            expect($firstBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($secondBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($firstBatch->id)
                ->not->toBe($secondBatch->id);

            $createdBatchIds = [
                $firstBatch->id,
                $secondBatch->id,
            ];

            // -------------------------------------------------
            // Controleer beide geschiedenisbatches.
            // -------------------------------------------------

            foreach ([$firstBatch, $secondBatch] as $batch) {
                $edition = CatalogEdition::query()
                    ->findOrFail($batch->catalog_edition_id);

                expect($edition->catalog_id)
                    ->toBe($this->catalogA->id);

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
                    ->toBe($edition->edition_name);

                expect($batch->catalogEdition->id)
                    ->toBe($edition->id);

                expect($batch->user->id)
                    ->toBe($this->user->id);

                $this->assertDatabaseHas('catalog_change_batches', [
                    'id' => $batch->id,
                    'catalog_edition_id' => $edition->id,
                    'user_id' => $this->user->id,
                    'operation' => 'stamp_update',
                    'catalog_name' => $this->catalogA->name,
                    'catalog_edition_name' => $edition->edition_name,
                ]);
            }

            // -------------------------------------------------
            // De tweede geschiedenisbatch moet actief zijn.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($activeBatch->id)
                ->toBe($secondBatch->id);

            expect($activeBatch->catalog_edition_id)
                ->toBe($secondEditionId);

            expect($activeBatch->id)
                ->not->toBe($firstBatch->id);

            // -------------------------------------------------
            // De eerste batch bevat drie verwijderingslogs.
            // De tweede batch bevat nog geen logs.
            // -------------------------------------------------

            $logs = $firstBatch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(3);

            expect($secondBatch->logs()->count())
                ->toBe(0);

            $this->assertDatabaseCount('catalog_change_logs', 3);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_value',
                    'stamp_catalog_value',
                    'stamp_catalog_entry',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'deleted',
                    'deleted',
                    'deleted',
                ]);

            // -------------------------------------------------
            // Controleer de verwijderingslogs van beide waarden.
            // -------------------------------------------------

            $valueLogs = $logs->where(
                'record_type',
                'stamp_catalog_value'
            );

            expect($valueLogs->count())
                ->toBe(2);

            $loggedValueIds = $valueLogs
                ->pluck('record_id')
                ->all();

            $expectedValueIds = $deletedValueIds;

            sort($loggedValueIds);

            sort($expectedValueIds);

            expect($loggedValueIds)
                ->toBe($expectedValueIds);

            // -------------------------------------------------
            // Controleer alle drie verwijderingslogs.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($firstBatch->id);

                expect($log->catalog_edition_id)
                    ->toBe($firstEditionId);

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

                expect($log->batch->id)
                    ->toBe($firstBatch->id);

                expect($log->catalogEdition->id)
                    ->toBe($firstEditionId);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                // Bepaal de oorspronkelijke attributen.

                if ($log->record_type === 'stamp_catalog_value') {
                    expect($log->record_id)
                        ->toBeIn($deletedValueIds);

                    $originalAttributes =
                        $originalValues[$log->record_id];

                    expect($log->condition_id)
                        ->toBe($originalAttributes['condition_id']);

                    expect($log->condition->id)
                        ->toBe($originalAttributes['condition_id']);

                    expect($log->old_values['stamp_catalog_entry_id'])
                        ->toBe($firstEntryId);
                } else {
                    expect($log->record_type)
                        ->toBe('stamp_catalog_entry');

                    expect($log->record_id)
                        ->toBe($firstEntryId);

                    $originalAttributes =
                        $originalEntries[$firstEntryId];

                    expect($log->condition_id)
                        ->toBeNull();

                    expect($log->condition)
                        ->toBeNull();

                    expect($log->old_values['stamp_id'])
                        ->toBe($this->stamp->id);

                    expect($log->old_values['catalog_edition_id'])
                        ->toBe($firstEditionId);

                    expect($log->old_values['catalog_number'])
                        ->toBe($originalAttributes['catalog_number']);
                }

                // -------------------------------------------------
                // Vergelijk alle oorspronkelijke attributen.
                // -------------------------------------------------

                $loggedAttributes = $log->old_values;

                ksort($loggedAttributes);

                ksort($originalAttributes);

                expect(array_keys($loggedAttributes))
                    ->toBe(array_keys($originalAttributes));

                foreach (
                    $originalAttributes as $attribute => $originalValue
                ) {
                    expect($loggedAttributes)
                        ->toHaveKey($attribute);

                    $loggedValue = $loggedAttributes[$attribute];

                    if ($attribute === 'catalog_value') {
                        expect((float) $loggedValue)
                            ->toBe((float) $originalValue);

                        continue;
                    }

                    if ($attribute === 'needs_review') {
                        expect((bool) $loggedValue)
                            ->toBe((bool) $originalValue);

                        continue;
                    }

                    expect($loggedValue)
                        ->toBe($originalValue);
                }

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $firstBatch->id,
                    'catalog_edition_id' => $firstEditionId,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $log->condition_id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => 'deleted',
                ]);
            }

            // -------------------------------------------------
            // Controleer specifiek de verwijderingslog
            // van de eerste catalogusvermelding.
            // -------------------------------------------------

            $entryLog = $logs
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            expect($entryLog->record_id)
                ->toBe($firstEntryId);

            expect($entryLog->old_values['id'])
                ->toBe($firstEntryId);

            expect($entryLog->old_values['catalog_number'])
                ->toBe($originalEntries[$firstEntryId]['catalog_number']);

            // -------------------------------------------------
            // Er mogen nog geen logs bestaan voor de tweede
            // vermelding of de waarde waarbij de fout ontstaat.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $secondBatch->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $secondEntryId,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $failedValueId,
            ]);

            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $this->editionB1->id,
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // De eerste vermelding is volledig verwijderd.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(3);

        expect($observedValueIds)
            ->toHaveCount(3);

        expect(array_unique($observedValueIds))
            ->toHaveCount(3);

        expect($firstEntryId)
            ->toBeInt();

        expect($secondEntryId)
            ->toBeInt();

        expect($firstEditionId)
            ->toBeInt();

        expect($secondEditionId)
            ->toBeInt();

        expect($failedValueId)
            ->toBeInt();

        expect($firstEntryId)
            ->not->toBe($secondEntryId);

        expect($firstEditionId)
            ->not->toBe($secondEditionId);

        expect($deletedValueIds)
            ->toHaveCount(2);

        expect($createdBatchIds)
            ->toHaveCount(2);

        expect(array_unique($createdBatchIds))
            ->toHaveCount(2);

        expect($createdLogIds)
            ->toHaveCount(3);

        expect(array_unique($createdLogIds))
            ->toHaveCount(3);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het volledige herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->country_id)
            ->toBe($this->country->id);

        expect($restoredStamp->country_id)
            ->not->toBe($this->otherCountry->id);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        $this->assertDatabaseHas('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->country->id,
        ]);

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // -------------------------------------------------
        // Controleer het behoud van vertalingen en thema's.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->orderBy('id')
                ->get()
                ->map(fn ($translation) => $translation->getAttributes())
                ->all()
        )->toBe($originalData['translations']);

        expect(
            $restoredStamp->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all()
        )->toBe($originalData['themes']);

        // -------------------------------------------------
        // Controleer het herstel van de daadwerkelijk
        // verwijderde catalogusvermelding.
        // -------------------------------------------------

        $restoredFirstEntry = StampCatalogEntry::query()
            ->findOrFail($firstEntryId);

        expect($restoredFirstEntry->exists)
            ->toBeTrue();

        expect($restoredFirstEntry->id)
            ->toBe($firstEntryId);

        expect($restoredFirstEntry->getAttributes())
            ->toBe($originalEntries[$firstEntryId]);

        expect($restoredFirstEntry->stamp_id)
            ->toBe($this->stamp->id);

        expect($restoredFirstEntry->catalog_edition_id)
            ->toBe($firstEditionId);

        expect($restoredFirstEntry->catalog_number)
            ->toBe($originalEntries[$firstEntryId]['catalog_number']);

        expect($restoredFirstEntry->values()->count())
            ->toBe(2);

        expect($restoredFirstEntry->catalogEdition->id)
            ->toBe($firstEditionId);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $firstEntryId,
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $firstEditionId,
            'catalog_number' => $originalEntries[$firstEntryId]['catalog_number'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van beide verwijderde waarden.
        // -------------------------------------------------

        foreach ($deletedValueIds as $valueId) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($restoredValue->exists)
                ->toBeTrue();

            expect($restoredValue->id)
                ->toBe($valueId);

            expect($restoredValue->getAttributes())
                ->toBe($originalValues[$valueId]);

            expect($restoredValue->stamp_catalog_entry_id)
                ->toBe($firstEntryId);

            expect($restoredValue->catalogEntry->id)
                ->toBe($firstEntryId);

            expect($restoredValue->condition->id)
                ->toBe($originalValues[$valueId]['condition_id']);

            expect($restoredValue->needs_review)
                ->toBeFalse();

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $valueId,
                'stamp_catalog_entry_id' => $firstEntryId,
                'condition_id' => $originalValues[$valueId]['condition_id'],
                'catalog_value' => $originalValues[$valueId]['catalog_value'],
                'currency' => $originalValues[$valueId]['currency'],
                'needs_review' => false,
            ]);
        }

        // -------------------------------------------------
        // Controleer de tweede ongeldige vermelding.
        // -------------------------------------------------

        $restoredSecondEntry = StampCatalogEntry::query()
            ->findOrFail($secondEntryId);

        expect($restoredSecondEntry->getAttributes())
            ->toBe($originalEntries[$secondEntryId]);

        expect($restoredSecondEntry->catalog_edition_id)
            ->toBe($secondEditionId);

        expect($restoredSecondEntry->values()->count())
            ->toBe(2);

        expect(
            StampCatalogValue::query()
                ->findOrFail($failedValueId)
                ->getAttributes()
        )->toBe($originalValues[$failedValueId]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke vermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $freshEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($freshEntry->getAttributes())
                ->toBe($attributes);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        $expectedOriginalValues = [
            [$this->valueA1Postfris, '15.00', 'EUR'],
            [$this->valueA1Gestempeld, '7.50', 'EUR'],
            [$this->valueA2Postfris, '20.00', 'EUR'],
            [$this->valueA2Gestempeld, '10.00', 'EUR'],
            [$this->valueB1Postfris, '25.00', 'USD'],
            [$this->valueB1Gestempeld, '12.50', 'USD'],
        ];

        foreach (
            $expectedOriginalValues as [$model, $amount, $currency]
        ) {
            $freshValue = $model->fresh();

            expect($freshValue)
                ->toBeInstanceOf(StampCatalogValue::class);

            expect($freshValue->id)
                ->toBe($model->id);

            expect($freshValue->catalog_value)
                ->toBe($amount);

            expect($freshValue->currency)
                ->toBe($currency);

            expect($freshValue->needs_review)
                ->toBeFalse();
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van beide geschiedenisbatches.
        // -------------------------------------------------

        foreach ($createdBatchIds as $batchId) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'id' => $batchId,
            ]);

            expect(
                CatalogChangeBatch::query()->find($batchId)
            )->toBeNull();

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $batchId,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van alle drie wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        foreach ($deletedValueIds as $valueId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $valueId,
            ]);
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $firstEntryId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route B volledig terug bij een fout tijdens het aanmaken van een nieuwe catalogusvermelding', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $this->catalogC->countries()->attach(
        $this->country->id
    );

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback B1',
        'slug' => 'oorspronkelijk-thema-rollback-b1',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback B1',
        'slug' => 'vervangend-thema-rollback-b1',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam B1',
            'description' => 'Oorspronkelijke Nederlandse beschrijving B1',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name B1',
            'description' => 'Original English description B1',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->stamp->fresh()->country_id)
        ->toBe($this->country->id);

    expect(
        $this->catalogC->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect($this->editionC1->currency)
        ->toBe('GBP');

    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    expect($originalDutchTranslation['name'])
        ->toBe('Oorspronkelijke Nederlandse naam B1');

    expect($originalEnglishTranslation['name'])
        ->toBe('Original English name B1');

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest B1';

    $payload['printing_method'] = 'Gewijzigde drukmethode B1';

    $payload['notes'] = 'Rollback nieuwe catalogusvermelding route B';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam B1',
        'description' => 'Gewijzigde Nederlandse beschrijving B1',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_edition_id'] = $this->editionC1->id;

    $payload['catalog_number'] = 'C-400';

    $payload['catalog_values'] = [
        'postfris' => '48.75',
        'gestempeld' => '24.50',
    ];

    // Alleen route B mag catalogusgegevens verwerken.

    expect($payload)
        ->not->toHaveKey('catalog_entries');

    expect($payload)
        ->not->toHaveKey('catalog_editions');

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het aanmaken van een catalogusvermelding via route B';

    $listenerCalls = 0;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogEntry::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogEntry::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogEntry::creating(function (
            StampCatalogEntry $entry
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $exceptionMessage,
            &$listenerCalls,
            &$createdBatchId,
            &$capturedHistory
        ) {
            if (
                $entry->stamp_id !== $this->stamp->id
                || $entry->catalog_edition_id !== $this->editionC1->id
            ) {
                return;
            }

            $listenerCalls++;

            expect($listenerCalls)
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de postzegel al is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->id)
                ->toBe($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->country_id)
                ->toBe($originalStamp['country_id']);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            $this->assertDatabaseCount('stamps', 1);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling
            // daadwerkelijk is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->name)
                ->not->toBe($originalDutchTranslation['name']);

            expect($changedDutchTranslation->description)
                ->not->toBe($originalDutchTranslation['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            $this->assertDatabaseHas('stamp_translations', [
                'id' => $originalDutchTranslation['id'],
                'stamp_id' => $this->stamp->id,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            // -------------------------------------------------
            // De niet-ingediende Engelse vertaling
            // moet ongewijzigd blijven.
            // -------------------------------------------------

            $englishTranslation = $changedStamp
                ->translations()
                ->where('locale', 'en')
                ->firstOrFail();

            expect($englishTranslation->getAttributes())
                ->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect(
                $changedStamp->themes()
                    ->where('themes.id', $originalTheme->id)
                    ->exists()
            )->toBeFalse();

            expect(
                $changedStamp->themes()
                    ->where('themes.id', $replacementTheme->id)
                    ->exists()
            )->toBeTrue();

            // Beide thema's zelf moeten blijven bestaan.

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Bewijs dat de nieuwe catalogusvermelding
            // nog niet is opgeslagen.
            // -------------------------------------------------

            expect($entry->exists)
                ->toBeFalse();

            expect($entry->stamp_id)
                ->toBe($this->stamp->id);

            expect($entry->catalog_edition_id)
                ->toBe($this->editionC1->id);

            expect($entry->catalog_number)
                ->toBe('C-400');

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionC1->id,
            ]);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(6);

            // -------------------------------------------------
            // Controleer alle bestaande catalogusvermeldingen.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                $storedEntry = StampCatalogEntry::query()
                    ->findOrFail($entryId);

                expect($storedEntry->getAttributes())
                    ->toBe($attributes);

                expect($storedEntry->values()->count())
                    ->toBe(2);
            }

            // -------------------------------------------------
            // Controleer alle bestaande cataloguswaarden.
            // -------------------------------------------------

            foreach ($originalValues as $valueId => $attributes) {
                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            expect($activeBatch->catalog_edition_id)
                ->toBe($this->editionC1->id);

            expect($activeBatch->user_id)
                ->toBe($this->user->id);

            expect($activeBatch->user_name)
                ->toBe($this->user->name);

            expect($activeBatch->operation)
                ->toBe('stamp_update');

            expect($activeBatch->description)
                ->toBe(
                    'Catalogusgegevens bij het wijzigen van een postzegel'
                );

            expect($activeBatch->catalog_name)
                ->toBe($this->catalogC->name);

            expect($activeBatch->catalog_edition_name)
                ->toBe($this->editionC1->edition_name);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionC1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionC1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogC->name,
                'catalog_edition_name' => $this->editionC1->edition_name,
            ]);

            // -------------------------------------------------
            // De geschiedenisbatch bevat nog geen logs.
            // -------------------------------------------------

            expect($activeBatch->logs()->count())
                ->toBe(0);

            $this->assertDatabaseCount('catalog_change_logs', 0);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $createdBatchId,
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdBatchId)
            ->toBeInt();

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->country_id)
            ->toBe($this->country->id);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        expect($restoredStamp->designer)
            ->toBe($originalStamp['designer']);

        expect($restoredStamp->printing_method)
            ->toBe($originalStamp['printing_method']);

        expect($restoredStamp->notes)
            ->toBe($originalStamp['notes']);

        // -------------------------------------------------
        // Controleer het herstel van de Nederlandse vertaling.
        // -------------------------------------------------

        $restoredDutchTranslation = $restoredStamp
            ->translations()
            ->where('locale', 'nl')
            ->firstOrFail();

        expect($restoredDutchTranslation->getAttributes())
            ->toBe($originalDutchTranslation);

        expect($restoredDutchTranslation->id)
            ->toBe($originalDutchTranslation['id']);

        expect($restoredDutchTranslation->name)
            ->toBe($originalDutchTranslation['name']);

        expect($restoredDutchTranslation->description)
            ->toBe($originalDutchTranslation['description']);

        expect($restoredDutchTranslation->getRawOriginal('updated_at'))
            ->toBe($originalDutchTranslation['updated_at']);

        $this->assertDatabaseHas('stamp_translations', [
            'id' => $originalDutchTranslation['id'],
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $originalDutchTranslation['name'],
            'description' => $originalDutchTranslation['description'],
        ]);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het behoud van de Engelse vertaling.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect(
            $restoredStamp->themes()
                ->where('themes.id', $originalTheme->id)
                ->exists()
        )->toBeTrue();

        expect(
            $restoredStamp->themes()
                ->where('themes.id', $replacementTheme->id)
                ->exists()
        )->toBeFalse();

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer dat de nieuwe vermelding niet bestaat.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        expect(
            $restoredStamp->catalogEntries()
                ->where('catalog_edition_id', $this->editionC1->id)
                ->exists()
        )->toBeFalse();

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke vermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $freshEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($freshEntry->getAttributes())
                ->toBe($attributes);

            expect($freshEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($freshEntry->values()->count())
                ->toBe(2);
        }

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        $expectedOriginalValues = [
            [$this->valueA1Postfris, '15.00', 'EUR'],
            [$this->valueA1Gestempeld, '7.50', 'EUR'],
            [$this->valueA2Postfris, '20.00', 'EUR'],
            [$this->valueA2Gestempeld, '10.00', 'EUR'],
            [$this->valueB1Postfris, '25.00', 'USD'],
            [$this->valueB1Gestempeld, '12.50', 'USD'],
        ];

        foreach (
            $expectedOriginalValues as [$model, $amount, $currency]
        ) {
            $freshValue = $model->fresh();

            expect($freshValue->id)
                ->toBe($model->id);

            expect($freshValue->catalog_value)
                ->toBe($amount);

            expect($freshValue->currency)
                ->toBe($currency);

            expect($freshValue->needs_review)
                ->toBeFalse();
        }

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogEntry::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route B volledig terug bij een fout tijdens het wijzigen van een bestaande cataloguswaarde', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback B2',
        'slug' => 'oorspronkelijk-thema-rollback-b2',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback B2',
        'slug' => 'vervangend-thema-rollback-b2',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam B2',
            'description' => 'Oorspronkelijke Nederlandse beschrijving B2',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name B2',
            'description' => 'Original English description B2',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Gestempeld->fresh()->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->fresh()->needs_review)
        ->toBeFalse();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest B2';

    $payload['printing_method'] = 'Gewijzigde drukmethode B2';

    $payload['notes'] = 'Rollback wijzigen cataloguswaarde route B';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam B2',
        'description' => 'Gewijzigde Nederlandse beschrijving B2',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-450';

    $payload['catalog_values'] = [
        'postfris' => '58.75',
        'gestempeld' => '29.50',
    ];

    expect($payload)
        ->not->toHaveKey('catalog_entries');

    expect($payload)
        ->not->toHaveKey('catalog_editions');

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het wijzigen van een cataloguswaarde via route B';

    $listenerCalls = 0;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    $createdLogIds = [];

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::updating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $exceptionMessage,
            &$listenerCalls,
            &$createdBatchId,
            &$createdLogIds,
            &$capturedHistory
        ) {
            // Laat de postfrisse waarde normaal wijzigen.
            // Veroorzaak uitsluitend een fout bij gestempeld.

            if ($value->id !== $this->valueB1Gestempeld->id) {
                return;
            }

            $listenerCalls++;

            expect($listenerCalls)
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de postzegel al is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            $this->assertDatabaseHas('stamp_translations', [
                'id' => $originalDutchTranslation['id'],
                'stamp_id' => $this->stamp->id,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Bewijs dat het catalogusnummer daadwerkelijk
            // in de database is gewijzigd.
            // -------------------------------------------------

            $changedEntry = StampCatalogEntry::query()
                ->findOrFail($this->entryB1->id);

            expect($changedEntry->id)
                ->toBe($this->entryB1->id);

            expect($changedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($changedEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($changedEntry->catalog_number)
                ->toBe('B-450');

            expect($changedEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryB1->id]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-450',
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_number' => 'B-100',
            ]);

            // -------------------------------------------------
            // Bewijs dat de postfrisse waarde daadwerkelijk
            // is gewijzigd en opgeslagen.
            // -------------------------------------------------

            $changedPostfris = StampCatalogValue::query()
                ->findOrFail($this->valueB1Postfris->id);

            expect($changedPostfris->id)
                ->toBe($this->valueB1Postfris->id);

            expect($changedPostfris->stamp_catalog_entry_id)
                ->toBe($this->entryB1->id);

            expect($changedPostfris->condition_id)
                ->toBe($this->postfris->id);

            expect($changedPostfris->catalog_value)
                ->toBe('58.75');

            expect($changedPostfris->currency)
                ->toBe('USD');

            expect($changedPostfris->needs_review)
                ->toBeTrue();

            expect($changedPostfris->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalValues[$this->valueB1Postfris->id]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->postfris->id,
                'catalog_value' => 58.75,
                'currency' => 'USD',
                'needs_review' => true,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'catalog_value' => 25.00,
            ]);

            // -------------------------------------------------
            // De gestempelde waarde is nog niet opgeslagen.
            // -------------------------------------------------

            expect($value->exists)
                ->toBeTrue();

            expect($value->id)
                ->toBe($this->valueB1Gestempeld->id);

            expect($value->stamp_catalog_entry_id)
                ->toBe($this->entryB1->id);

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            expect($value->catalog_value)
                ->toBe('29.50');

            expect($value->isDirty('catalog_value'))
                ->toBeTrue();

            $storedGestempeld = StampCatalogValue::query()
                ->findOrFail($this->valueB1Gestempeld->id);

            expect($storedGestempeld->getAttributes())
                ->toBe(
                    $originalValues[$this->valueB1Gestempeld->id]
                );

            expect($storedGestempeld->catalog_value)
                ->toBe('12.50');

            expect($storedGestempeld->needs_review)
                ->toBeFalse();

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => 29.50,
            ]);

            // -------------------------------------------------
            // Controleer alle overige catalogusvermeldingen.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                if ($entryId === $this->entryB1->id) {
                    continue;
                }

                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            // -------------------------------------------------
            // Controleer alle overige cataloguswaarden.
            // -------------------------------------------------

            foreach ($originalValues as $valueId => $attributes) {
                if ($valueId === $this->valueB1Postfris->id) {
                    continue;
                }

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            expect($changedEntry->values()->count())
                ->toBe(2);

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat beide wijzigingslogs zijn opgeslagen.
            // -------------------------------------------------

            $logs = $activeBatch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(2);

            $this->assertDatabaseCount('catalog_change_logs', 2);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'updated',
                    'updated',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van het nummer.
            // -------------------------------------------------

            $entryLog = $logs
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            expect($entryLog->record_id)
                ->toBe($this->entryB1->id);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->old_values)
                ->toBe([
                    'catalog_number' => 'B-100',
                ]);

            expect($entryLog->new_values)
                ->toBe([
                    'catalog_number' => 'B-450',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van postfris.
            // -------------------------------------------------

            $valueLog = $logs
                ->where('record_type', 'stamp_catalog_value')
                ->sole();

            expect($valueLog->record_id)
                ->toBe($this->valueB1Postfris->id);

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->condition->id)
                ->toBe($this->postfris->id);

            expect($valueLog->old_values)
                ->toHaveCount(2);

            expect($valueLog->new_values)
                ->toHaveCount(2);

            expect($valueLog->old_values)
                ->toHaveKeys([
                    'catalog_value',
                    'needs_review',
                ]);

            expect($valueLog->new_values)
                ->toHaveKeys([
                    'catalog_value',
                    'needs_review',
                ]);

            expect((float) $valueLog->old_values['catalog_value'])
                ->toBe(25.00);

            expect((float) $valueLog->new_values['catalog_value'])
                ->toBe(58.75);

            expect((bool) $valueLog->old_values['needs_review'])
                ->toBeFalse();

            expect((bool) $valueLog->new_values['needs_review'])
                ->toBeTrue();

            // -------------------------------------------------
            // Controleer de gemeenschappelijke loggegevens.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('updated');

                expect($log->old_values)
                    ->not->toHaveKey('updated_at');

                expect($log->new_values)
                    ->not->toHaveKey('updated_at');

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $createdBatchId,
                    'catalog_edition_id' => $this->editionB1->id,
                    'stamp_id' => $this->stamp->id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => 'updated',
                ]);
            }

            // -------------------------------------------------
            // Voor gestempeld bestaat nog geen wijzigingslog.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Gestempeld->id,
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdBatchId)
            ->toBeInt();

        expect($createdLogIds)
            ->toHaveCount(2);

        expect(array_unique($createdLogIds))
            ->toHaveCount(2);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer het herstel van het catalogusnummer.
        // -------------------------------------------------

        $restoredEntry = $this->entryB1->fresh();

        expect($restoredEntry->getAttributes())
            ->toBe($originalEntries[$this->entryB1->id]);

        expect($restoredEntry->catalog_number)
            ->toBe('B-100');

        expect($restoredEntry->values()->count())
            ->toBe(2);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-100',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-450',
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de postfrisse waarde.
        // -------------------------------------------------

        $restoredPostfris = $this->valueB1Postfris->fresh();

        expect($restoredPostfris->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]
            );

        expect($restoredPostfris->catalog_value)
            ->toBe('25.00');

        expect($restoredPostfris->currency)
            ->toBe('USD');

        expect($restoredPostfris->needs_review)
            ->toBeFalse();

        expect($restoredPostfris->getRawOriginal('updated_at'))
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]['updated_at']
            );

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 25.00,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 58.75,
        ]);

        // -------------------------------------------------
        // Controleer het behoud van de gestempelde waarde.
        // -------------------------------------------------

        $restoredGestempeld = $this->valueB1Gestempeld->fresh();

        expect($restoredGestempeld->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Gestempeld->id]
            );

        expect($restoredGestempeld->catalog_value)
            ->toBe('12.50');

        expect($restoredGestempeld->currency)
            ->toBe('USD');

        expect($restoredGestempeld->needs_review)
            ->toBeFalse();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Gestempeld->id,
            'catalog_value' => 29.50,
        ]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusgegevens.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            expect(
                StampCatalogEntry::query()
                    ->findOrFail($entryId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route B volledig terug bij een fout tijdens het verwijderen van een bestaande cataloguswaarde', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback B3',
        'slug' => 'oorspronkelijk-thema-rollback-b3',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback B3',
        'slug' => 'vervangend-thema-rollback-b3',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam B3',
            'description' => 'Oorspronkelijke Nederlandse beschrijving B3',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name B3',
            'description' => 'Original English description B3',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->fresh()->values()->count())
        ->toBe(2);

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Gestempeld->fresh()->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->fresh()->needs_review)
        ->toBeFalse();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest B3';

    $payload['printing_method'] = 'Gewijzigde drukmethode B3';

    $payload['notes'] = 'Rollback verwijderen cataloguswaarde route B';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam B3',
        'description' => 'Gewijzigde Nederlandse beschrijving B3',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-550';

    $payload['catalog_values'] = [
        'postfris' => '',
        'gestempeld' => '',
    ];

    expect($payload)
        ->not->toHaveKey('catalog_entries');

    expect($payload)
        ->not->toHaveKey('catalog_editions');

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het verwijderen van een cataloguswaarde via route B';

    $listenerCalls = 0;

    $observedValueIds = [];

    $createdLogIds = [];

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::deleting(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$createdBatchId,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (
                $value->stamp_catalog_entry_id
                !== $this->entryB1->id
            ) {
                return;
            }

            $listenerCalls++;

            $observedValueIds[] = $value->id;

            // -------------------------------------------------
            // Laat de postfrisse waarde normaal verwijderen.
            // -------------------------------------------------

            if ($value->id === $this->valueB1Postfris->id) {
                expect($listenerCalls)
                    ->toBe(1);

                return;
            }

            // -------------------------------------------------
            // Veroorzaak uitsluitend een fout bij gestempeld.
            // -------------------------------------------------

            expect($value->id)
                ->toBe($this->valueB1Gestempeld->id);

            expect($listenerCalls)
                ->toBe(2);

            expect($observedValueIds)
                ->toBe([
                    $this->valueB1Postfris->id,
                    $this->valueB1Gestempeld->id,
                ]);

            // -------------------------------------------------
            // Bewijs dat de postzegel al is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            $this->assertDatabaseHas('stamp_translations', [
                'id' => $originalDutchTranslation['id'],
                'stamp_id' => $this->stamp->id,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Bewijs dat het catalogusnummer is gewijzigd.
            // -------------------------------------------------

            $changedEntry = StampCatalogEntry::query()
                ->findOrFail($this->entryB1->id);

            expect($changedEntry->id)
                ->toBe($this->entryB1->id);

            expect($changedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($changedEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($changedEntry->catalog_number)
                ->toBe('B-550');

            expect($changedEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryB1->id]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-550',
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_number' => 'B-100',
            ]);

            // -------------------------------------------------
            // Bewijs dat de postfrisse waarde daadwerkelijk
            // uit de database is verwijderd.
            // -------------------------------------------------

            expect(
                StampCatalogValue::query()
                    ->find($this->valueB1Postfris->id)
            )->toBeNull();

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->postfris->id,
            ]);

            expect(
                $changedEntry->values()
                    ->where('condition_id', $this->postfris->id)
                    ->exists()
            )->toBeFalse();

            expect($changedEntry->values()->count())
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de gestempelde waarde nog bestaat.
            // -------------------------------------------------

            expect($value->exists)
                ->toBeTrue();

            expect($value->id)
                ->toBe($this->valueB1Gestempeld->id);

            expect($value->stamp_catalog_entry_id)
                ->toBe($this->entryB1->id);

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            $storedGestempeld = StampCatalogValue::query()
                ->findOrFail($this->valueB1Gestempeld->id);

            expect($storedGestempeld->getAttributes())
                ->toBe(
                    $originalValues[$this->valueB1Gestempeld->id]
                );

            expect($storedGestempeld->catalog_value)
                ->toBe('12.50');

            expect($storedGestempeld->currency)
                ->toBe('USD');

            expect($storedGestempeld->needs_review)
                ->toBeFalse();

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->gestempeld->id,
                'catalog_value' => 12.50,
                'currency' => 'USD',
                'needs_review' => false,
            ]);

            // -------------------------------------------------
            // Controleer alle overige catalogusvermeldingen.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                if ($entryId === $this->entryB1->id) {
                    continue;
                }

                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            // -------------------------------------------------
            // Controleer alle overige cataloguswaarden.
            // -------------------------------------------------

            foreach ($originalValues as $valueId => $attributes) {
                if ($valueId === $this->valueB1Postfris->id) {
                    continue;
                }

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 5);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(5);

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat beide wijzigingslogs zijn opgeslagen.
            // -------------------------------------------------

            $logs = $activeBatch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(2);

            $this->assertDatabaseCount('catalog_change_logs', 2);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'updated',
                    'deleted',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van het nummer.
            // -------------------------------------------------

            $entryLog = $logs
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            expect($entryLog->record_id)
                ->toBe($this->entryB1->id);

            expect($entryLog->action)
                ->toBe('updated');

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->old_values)
                ->toBe([
                    'catalog_number' => 'B-100',
                ]);

            expect($entryLog->new_values)
                ->toBe([
                    'catalog_number' => 'B-550',
                ]);

            // -------------------------------------------------
            // Controleer de verwijderingslog van postfris.
            // -------------------------------------------------

            $valueLog = $logs
                ->where('record_type', 'stamp_catalog_value')
                ->sole();

            expect($valueLog->record_id)
                ->toBe($this->valueB1Postfris->id);

            expect($valueLog->action)
                ->toBe('deleted');

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->condition->id)
                ->toBe($this->postfris->id);

            expect($valueLog->old_values)
                ->toBeArray();

            expect($valueLog->new_values)
                ->toBeNull();

            // -------------------------------------------------
            // Vergelijk alle oorspronkelijke attributen
            // van de verwijderde cataloguswaarde.
            // -------------------------------------------------

            $loggedAttributes = $valueLog->old_values;

            $originalAttributes =
                $originalValues[$this->valueB1Postfris->id];

            ksort($loggedAttributes);

            ksort($originalAttributes);

            expect(array_keys($loggedAttributes))
                ->toBe(array_keys($originalAttributes));

            foreach (
                $originalAttributes as $attribute => $originalValue
            ) {
                expect($loggedAttributes)
                    ->toHaveKey($attribute);

                $loggedValue = $loggedAttributes[$attribute];

                if ($attribute === 'catalog_value') {
                    expect((float) $loggedValue)
                        ->toBe((float) $originalValue);

                    continue;
                }

                if ($attribute === 'needs_review') {
                    expect((bool) $loggedValue)
                        ->toBe((bool) $originalValue);

                    continue;
                }

                expect($loggedValue)
                    ->toBe($originalValue);
            }

            expect($valueLog->old_values['id'])
                ->toBe($this->valueB1Postfris->id);

            expect($valueLog->old_values['stamp_catalog_entry_id'])
                ->toBe($this->entryB1->id);

            expect((float) $valueLog->old_values['catalog_value'])
                ->toBe(25.00);

            expect($valueLog->old_values['currency'])
                ->toBe('USD');

            expect((bool) $valueLog->old_values['needs_review'])
                ->toBeFalse();

            // -------------------------------------------------
            // Controleer de gemeenschappelijke loggegevens.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $createdBatchId,
                    'catalog_edition_id' => $this->editionB1->id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $log->condition_id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => $log->action,
                ]);
            }

            // -------------------------------------------------
            // Voor gestempeld bestaat nog geen verwijderingslog.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Gestempeld->id,
            ]);

            // De catalogusvermelding zelf is niet verwijderd.

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $this->entryB1->id,
                'action' => 'deleted',
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(2);

        expect($observedValueIds)
            ->toBe([
                $this->valueB1Postfris->id,
                $this->valueB1Gestempeld->id,
            ]);

        expect($createdBatchId)
            ->toBeInt();

        expect($createdLogIds)
            ->toHaveCount(2);

        expect(array_unique($createdLogIds))
            ->toHaveCount(2);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer het herstel van het catalogusnummer.
        // -------------------------------------------------

        $restoredEntry = $this->entryB1->fresh();

        expect($restoredEntry->getAttributes())
            ->toBe($originalEntries[$this->entryB1->id]);

        expect($restoredEntry->catalog_number)
            ->toBe('B-100');

        expect($restoredEntry->values()->count())
            ->toBe(2);

        expect($restoredEntry->getRawOriginal('updated_at'))
            ->toBe(
                $originalEntries[$this->entryB1->id]['updated_at']
            );

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-100',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-550',
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de daadwerkelijk
        // verwijderde postfrisse cataloguswaarde.
        // -------------------------------------------------

        $restoredPostfris = StampCatalogValue::query()
            ->findOrFail($this->valueB1Postfris->id);

        expect($restoredPostfris->exists)
            ->toBeTrue();

        expect($restoredPostfris->id)
            ->toBe($this->valueB1Postfris->id);

        expect($restoredPostfris->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]
            );

        expect($restoredPostfris->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($restoredPostfris->condition_id)
            ->toBe($this->postfris->id);

        expect($restoredPostfris->catalog_value)
            ->toBe('25.00');

        expect($restoredPostfris->currency)
            ->toBe('USD');

        expect($restoredPostfris->needs_review)
            ->toBeFalse();

        expect($restoredPostfris->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($restoredPostfris->condition->id)
            ->toBe($this->postfris->id);

        expect($restoredPostfris->getRawOriginal('created_at'))
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]['created_at']
            );

        expect($restoredPostfris->getRawOriginal('updated_at'))
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]['updated_at']
            );

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 25.00,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // -------------------------------------------------
        // Controleer het behoud van de gestempelde waarde.
        // -------------------------------------------------

        $restoredGestempeld = $this->valueB1Gestempeld->fresh();

        expect($restoredGestempeld->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Gestempeld->id]
            );

        expect($restoredGestempeld->catalog_value)
            ->toBe('12.50');

        expect($restoredGestempeld->currency)
            ->toBe('USD');

        expect($restoredGestempeld->needs_review)
            ->toBeFalse();

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusgegevens.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            expect(
                StampCatalogEntry::query()
                    ->findOrFail($entryId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
            'action' => 'updated',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Postfris->id,
            'action' => 'deleted',
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route C volledig terug bij een fout tijdens het wijzigen van een bestaand catalogusnummer', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback C1',
        'slug' => 'oorspronkelijk-thema-rollback-c1',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback C1',
        'slug' => 'vervangend-thema-rollback-c1',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam C1',
            'description' => 'Oorspronkelijke Nederlandse beschrijving C1',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name C1',
            'description' => 'Original English description C1',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryA1->fresh()->catalog_number)
        ->toBe('A-100');

    expect($this->entryA2->fresh()->catalog_number)
        ->toBe('A-200');

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->valueA1Postfris->fresh()->catalog_value)
        ->toBe('15.00');

    expect($this->valueA1Gestempeld->fresh()->catalog_value)
        ->toBe('7.50');

    expect($this->valueA1Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($this->valueA1Gestempeld->fresh()->needs_review)
        ->toBeFalse();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest C1';

    $payload['printing_method'] = 'Gewijzigde drukmethode C1';

    $payload['notes'] = 'Rollback wijzigen catalogusnummer route C';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam C1',
        'description' => 'Gewijzigde Nederlandse beschrijving C1',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_entries'] = [
        $this->entryA1->id => [
            'catalog_number' => 'A-650',
            'values' => [
                'postfris' => '65.75',
                'gestempeld' => '32.50',
            ],
        ],
        $this->entryA2->id => [
            'catalog_number' => 'A-750',
            'values' => [
                'postfris' => '75.75',
                'gestempeld' => '37.50',
            ],
        ],
    ];

    // Alleen route C mag catalogusgegevens verwerken.

    foreach ([
        'catalog_edition_id',
        'catalog_number',
        'catalog_values',
        'catalog_editions',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het wijzigen van een catalogusnummer via route C';

    $listenerCalls = 0;

    $observedEntryIds = [];

    $createdBatchIds = [];

    $createdLogIds = [];

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogEntry::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogEntry::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogEntry::updating(function (
            StampCatalogEntry $entry
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $exceptionMessage,
            &$listenerCalls,
            &$observedEntryIds,
            &$createdBatchIds,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if ($entry->stamp_id !== $this->stamp->id) {
                return;
            }

            $listenerCalls++;

            $observedEntryIds[] = $entry->id;

            // -------------------------------------------------
            // Laat catalogusvermelding A1 normaal wijzigen.
            // -------------------------------------------------

            if ($entry->id === $this->entryA1->id) {
                expect($listenerCalls)
                    ->toBe(1);

                return;
            }

            // -------------------------------------------------
            // Veroorzaak uitsluitend een fout bij A2.
            // -------------------------------------------------

            expect($entry->id)
                ->toBe($this->entryA2->id);

            expect($listenerCalls)
                ->toBe(2);

            expect($observedEntryIds)
                ->toBe([
                    $this->entryA1->id,
                    $this->entryA2->id,
                ]);

            // -------------------------------------------------
            // Bewijs dat de postzegel al is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            $this->assertDatabaseHas('stamp_translations', [
                'id' => $originalDutchTranslation['id'],
                'stamp_id' => $this->stamp->id,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Bewijs dat catalogusnummer A1 is gewijzigd.
            // -------------------------------------------------

            $changedEntryA1 = StampCatalogEntry::query()
                ->findOrFail($this->entryA1->id);

            expect($changedEntryA1->stamp_id)
                ->toBe($this->stamp->id);

            expect($changedEntryA1->catalog_edition_id)
                ->toBe($this->editionA1->id);

            expect($changedEntryA1->catalog_number)
                ->toBe('A-650');

            expect($changedEntryA1->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryA1->id]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $this->entryA1->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionA1->id,
                'catalog_number' => 'A-650',
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryA1->id,
                'catalog_number' => 'A-100',
            ]);

            // -------------------------------------------------
            // Bewijs dat beide waarden van A1 zijn gewijzigd.
            // -------------------------------------------------

            $expectedChangedValues = [
                [
                    $this->valueA1Postfris,
                    $this->postfris,
                    '65.75',
                ],
                [
                    $this->valueA1Gestempeld,
                    $this->gestempeld,
                    '32.50',
                ],
            ];

            foreach (
                $expectedChangedValues as [$model, $condition, $amount]
            ) {
                $changedValue = StampCatalogValue::query()
                    ->findOrFail($model->id);

                expect($changedValue->id)
                    ->toBe($model->id);

                expect($changedValue->stamp_catalog_entry_id)
                    ->toBe($this->entryA1->id);

                expect($changedValue->condition_id)
                    ->toBe($condition->id);

                expect($changedValue->catalog_value)
                    ->toBe($amount);

                expect($changedValue->currency)
                    ->toBe('EUR');

                expect($changedValue->needs_review)
                    ->toBeTrue();

                expect($changedValue->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalValues[$model->id]['updated_at']
                    );

                expect($changedValue->catalogEntry->id)
                    ->toBe($this->entryA1->id);

                expect($changedValue->condition->id)
                    ->toBe($condition->id);

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $model->id,
                    'stamp_catalog_entry_id' => $this->entryA1->id,
                    'condition_id' => $condition->id,
                    'catalog_value' => (float) $amount,
                    'currency' => 'EUR',
                    'needs_review' => true,
                ]);
            }

            // -------------------------------------------------
            // A2 is in voorbereiding, maar nog niet gewijzigd
            // in de database.
            // -------------------------------------------------

            expect($entry->exists)
                ->toBeTrue();

            expect($entry->catalog_edition_id)
                ->toBe($this->editionA2->id);

            expect($entry->catalog_number)
                ->toBe('A-750');

            expect($entry->isDirty('catalog_number'))
                ->toBeTrue();

            $storedEntryA2 = StampCatalogEntry::query()
                ->findOrFail($this->entryA2->id);

            expect($storedEntryA2->getAttributes())
                ->toBe($originalEntries[$this->entryA2->id]);

            expect($storedEntryA2->catalog_number)
                ->toBe('A-200');

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryA2->id,
                'catalog_number' => 'A-750',
            ]);

            // -------------------------------------------------
            // Controleer alle overige catalogusvermeldingen.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                if ($entryId === $this->entryA1->id) {
                    continue;
                }

                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            // -------------------------------------------------
            // Controleer alle overige cataloguswaarden.
            // -------------------------------------------------

            $changedValueIds = [
                $this->valueA1Postfris->id,
                $this->valueA1Gestempeld->id,
            ];

            foreach ($originalValues as $valueId => $attributes) {
                if (in_array($valueId, $changedValueIds, true)) {
                    continue;
                }

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            expect($this->entryB1->fresh()->catalog_number)
                ->toBe('B-100');

            expect($this->valueA2Postfris->fresh()->catalog_value)
                ->toBe('20.00');

            expect($this->valueA2Gestempeld->fresh()->catalog_value)
                ->toBe('10.00');

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(6);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(2);

            // -------------------------------------------------
            // Bewijs dat twee geschiedenisbatches bestaan.
            // -------------------------------------------------

            $this->assertDatabaseCount('catalog_change_batches', 2);

            $batches = CatalogChangeBatch::query()
                ->get()
                ->keyBy('catalog_edition_id');

            $firstBatch = $batches->get($this->editionA1->id);

            $secondBatch = $batches->get($this->editionA2->id);

            expect($firstBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($secondBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($firstBatch->id)
                ->not->toBe($secondBatch->id);

            $createdBatchIds = [
                $firstBatch->id,
                $secondBatch->id,
            ];

            // -------------------------------------------------
            // Controleer beide geschiedenisbatches.
            // -------------------------------------------------

            foreach ([$firstBatch, $secondBatch] as $batch) {
                $edition = CatalogEdition::query()
                    ->findOrFail($batch->catalog_edition_id);

                expect($edition->catalog_id)
                    ->toBe($this->catalogA->id);

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
                    ->toBe($edition->edition_name);

                expect($batch->catalogEdition->id)
                    ->toBe($edition->id);

                expect($batch->user->id)
                    ->toBe($this->user->id);

                $this->assertDatabaseHas('catalog_change_batches', [
                    'id' => $batch->id,
                    'catalog_edition_id' => $edition->id,
                    'user_id' => $this->user->id,
                    'operation' => 'stamp_update',
                    'catalog_name' => $this->catalogA->name,
                    'catalog_edition_name' => $edition->edition_name,
                ]);
            }

            // -------------------------------------------------
            // De tweede geschiedenisbatch moet actief zijn.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($activeBatch->id)
                ->toBe($secondBatch->id);

            expect($activeBatch->catalog_edition_id)
                ->toBe($this->editionA2->id);

            expect($activeBatch->id)
                ->not->toBe($firstBatch->id);

            // -------------------------------------------------
            // De eerste batch bevat drie wijzigingslogs.
            // De tweede batch bevat nog geen logs.
            // -------------------------------------------------

            $logs = $firstBatch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(3);

            expect($secondBatch->logs()->count())
                ->toBe(0);

            $this->assertDatabaseCount('catalog_change_logs', 3);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'updated',
                    'updated',
                    'updated',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van catalogusnummer A1.
            // -------------------------------------------------

            $entryLog = $logs
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            expect($entryLog->record_id)
                ->toBe($this->entryA1->id);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->old_values)
                ->toBe([
                    'catalog_number' => 'A-100',
                ]);

            expect($entryLog->new_values)
                ->toBe([
                    'catalog_number' => 'A-650',
                ]);

            // -------------------------------------------------
            // Controleer beide wijzigingslogs van de waarden.
            // -------------------------------------------------

            $expectedValueLogs = [
                [
                    $this->valueA1Postfris,
                    $this->postfris,
                    15.00,
                    65.75,
                ],
                [
                    $this->valueA1Gestempeld,
                    $this->gestempeld,
                    7.50,
                    32.50,
                ],
            ];

            foreach (
                $expectedValueLogs as [
                    $model,
                    $condition,
                    $oldAmount,
                    $newAmount,
                ]
            ) {
                $valueLog = $logs
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $model->id)
                    ->sole();

                expect($valueLog->condition_id)
                    ->toBe($condition->id);

                expect($valueLog->condition->id)
                    ->toBe($condition->id);

                expect($valueLog->old_values)
                    ->toHaveCount(2);

                expect($valueLog->new_values)
                    ->toHaveCount(2);

                expect($valueLog->old_values)
                    ->toHaveKeys([
                        'catalog_value',
                        'needs_review',
                    ]);

                expect($valueLog->new_values)
                    ->toHaveKeys([
                        'catalog_value',
                        'needs_review',
                    ]);

                expect((float) $valueLog->old_values['catalog_value'])
                    ->toBe($oldAmount);

                expect((float) $valueLog->new_values['catalog_value'])
                    ->toBe($newAmount);

                expect((bool) $valueLog->old_values['needs_review'])
                    ->toBeFalse();

                expect((bool) $valueLog->new_values['needs_review'])
                    ->toBeTrue();
            }

            // -------------------------------------------------
            // Controleer de gemeenschappelijke loggegevens.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($firstBatch->id);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionA1->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('updated');

                expect($log->old_values)
                    ->not->toHaveKey('updated_at');

                expect($log->new_values)
                    ->not->toHaveKey('updated_at');

                expect($log->batch->id)
                    ->toBe($firstBatch->id);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionA1->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $firstBatch->id,
                    'catalog_edition_id' => $this->editionA1->id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $log->condition_id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => 'updated',
                ]);
            }

            // -------------------------------------------------
            // Voor A2 mogen nog geen wijzigingslogs bestaan.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $secondBatch->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $this->editionA2->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_entry',
                'record_id' => $this->entryA2->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $this->editionB1->id,
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(2);

        expect($observedEntryIds)
            ->toBe([
                $this->entryA1->id,
                $this->entryA2->id,
            ]);

        expect($createdBatchIds)
            ->toHaveCount(2);

        expect(array_unique($createdBatchIds))
            ->toHaveCount(2);

        expect($createdLogIds)
            ->toHaveCount(3);

        expect(array_unique($createdLogIds))
            ->toHaveCount(3);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer het herstel van catalogusvermelding A1.
        // -------------------------------------------------

        $restoredEntryA1 = $this->entryA1->fresh();

        expect($restoredEntryA1->getAttributes())
            ->toBe($originalEntries[$this->entryA1->id]);

        expect($restoredEntryA1->catalog_number)
            ->toBe('A-100');

        expect($restoredEntryA1->values()->count())
            ->toBe(2);

        expect($restoredEntryA1->getRawOriginal('updated_at'))
            ->toBe(
                $originalEntries[$this->entryA1->id]['updated_at']
            );

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryA1->id,
            'catalog_number' => 'A-100',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryA1->id,
            'catalog_number' => 'A-650',
        ]);

        // -------------------------------------------------
        // Controleer het herstel van beide gewijzigde waarden.
        // -------------------------------------------------

        $expectedRestoredValues = [
            [
                $this->valueA1Postfris,
                $this->postfris,
                '15.00',
                '65.75',
            ],
            [
                $this->valueA1Gestempeld,
                $this->gestempeld,
                '7.50',
                '32.50',
            ],
        ];

        foreach (
            $expectedRestoredValues as [
                $model,
                $condition,
                $originalAmount,
                $changedAmount,
            ]
        ) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($model->id);

            expect($restoredValue->getAttributes())
                ->toBe($originalValues[$model->id]);

            expect($restoredValue->id)
                ->toBe($model->id);

            expect($restoredValue->stamp_catalog_entry_id)
                ->toBe($this->entryA1->id);

            expect($restoredValue->condition_id)
                ->toBe($condition->id);

            expect($restoredValue->catalog_value)
                ->toBe($originalAmount);

            expect($restoredValue->currency)
                ->toBe('EUR');

            expect($restoredValue->needs_review)
                ->toBeFalse();

            expect($restoredValue->catalogEntry->id)
                ->toBe($this->entryA1->id);

            expect($restoredValue->condition->id)
                ->toBe($condition->id);

            expect($restoredValue->getRawOriginal('updated_at'))
                ->toBe(
                    $originalValues[$model->id]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $model->id,
                'stamp_catalog_entry_id' => $this->entryA1->id,
                'condition_id' => $condition->id,
                'catalog_value' => (float) $originalAmount,
                'currency' => 'EUR',
                'needs_review' => false,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $model->id,
                'catalog_value' => (float) $changedAmount,
            ]);
        }

        // -------------------------------------------------
        // Controleer het behoud van catalogusvermelding A2.
        // -------------------------------------------------

        $restoredEntryA2 = $this->entryA2->fresh();

        expect($restoredEntryA2->getAttributes())
            ->toBe($originalEntries[$this->entryA2->id]);

        expect($restoredEntryA2->catalog_number)
            ->toBe('A-200');

        expect($restoredEntryA2->values()->count())
            ->toBe(2);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryA2->id,
            'catalog_number' => 'A-750',
        ]);

        expect($this->valueA2Postfris->fresh()->catalog_value)
            ->toBe('20.00');

        expect($this->valueA2Gestempeld->fresh()->catalog_value)
            ->toBe('10.00');

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusgegevens.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $restoredEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($restoredEntry->getAttributes())
                ->toBe($attributes);

            expect($restoredEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($restoredEntry->values()->count())
                ->toBe(2);
        }

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van beide geschiedenisbatches.
        // -------------------------------------------------

        foreach ($createdBatchIds as $batchId) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'id' => $batchId,
            ]);

            expect(
                CatalogChangeBatch::query()->find($batchId)
            )->toBeNull();

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $batchId,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van alle drie wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogEntry::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route C volledig terug bij een fout tijdens het wijzigen van een bestaande cataloguswaarde', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback C2',
        'slug' => 'oorspronkelijk-thema-rollback-c2',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback C2',
        'slug' => 'vervangend-thema-rollback-c2',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam C2',
            'description' => 'Oorspronkelijke Nederlandse beschrijving C2',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name C2',
            'description' => 'Original English description C2',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Gestempeld->fresh()->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->fresh()->needs_review)
        ->toBeFalse();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest C2';

    $payload['printing_method'] = 'Gewijzigde drukmethode C2';

    $payload['notes'] = 'Rollback wijzigen cataloguswaarde route C';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam C2',
        'description' => 'Gewijzigde Nederlandse beschrijving C2',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-850',
            'values' => [
                'postfris' => '85.75',
                'gestempeld' => '42.50',
            ],
        ],
    ];

    // Alleen route C mag catalogusgegevens verwerken.

    foreach ([
        'catalog_edition_id',
        'catalog_number',
        'catalog_values',
        'catalog_editions',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het wijzigen van een cataloguswaarde via route C';

    $listenerCalls = 0;

    $observedValueIds = [];

    $createdLogIds = [];

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::updating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$createdBatchId,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (
                $value->stamp_catalog_entry_id
                !== $this->entryB1->id
            ) {
                return;
            }

            $listenerCalls++;

            $observedValueIds[] = $value->id;

            // -------------------------------------------------
            // Laat de postfrisse waarde normaal wijzigen.
            // -------------------------------------------------

            if ($value->id === $this->valueB1Postfris->id) {
                expect($listenerCalls)
                    ->toBe(1);

                return;
            }

            // -------------------------------------------------
            // Veroorzaak uitsluitend een fout bij gestempeld.
            // -------------------------------------------------

            expect($value->id)
                ->toBe($this->valueB1Gestempeld->id);

            expect($listenerCalls)
                ->toBe(2);

            expect($observedValueIds)
                ->toBe([
                    $this->valueB1Postfris->id,
                    $this->valueB1Gestempeld->id,
                ]);

            // -------------------------------------------------
            // Bewijs dat de postzegel daadwerkelijk is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            $this->assertDatabaseHas('stamp_translations', [
                'id' => $originalDutchTranslation['id'],
                'stamp_id' => $this->stamp->id,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Bewijs dat het catalogusnummer is gewijzigd.
            // -------------------------------------------------

            $changedEntry = StampCatalogEntry::query()
                ->findOrFail($this->entryB1->id);

            expect($changedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($changedEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($changedEntry->catalog_number)
                ->toBe('B-850');

            expect($changedEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryB1->id]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-850',
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_number' => 'B-100',
            ]);

            // -------------------------------------------------
            // Bewijs dat de postfrisse waarde is gewijzigd.
            // -------------------------------------------------

            $changedPostfris = StampCatalogValue::query()
                ->findOrFail($this->valueB1Postfris->id);

            expect($changedPostfris->stamp_catalog_entry_id)
                ->toBe($this->entryB1->id);

            expect($changedPostfris->condition_id)
                ->toBe($this->postfris->id);

            expect($changedPostfris->catalog_value)
                ->toBe('85.75');

            expect($changedPostfris->currency)
                ->toBe('USD');

            expect($changedPostfris->needs_review)
                ->toBeTrue();

            expect($changedPostfris->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalValues[$this->valueB1Postfris->id]['updated_at']
                );

            expect($changedPostfris->catalogEntry->id)
                ->toBe($this->entryB1->id);

            expect($changedPostfris->condition->id)
                ->toBe($this->postfris->id);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->postfris->id,
                'catalog_value' => 85.75,
                'currency' => 'USD',
                'needs_review' => true,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'catalog_value' => 25.00,
            ]);

            // -------------------------------------------------
            // De gestempelde waarde is nog niet opgeslagen.
            // -------------------------------------------------

            expect($value->exists)
                ->toBeTrue();

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            expect($value->catalog_value)
                ->toBe('42.50');

            expect($value->isDirty('catalog_value'))
                ->toBeTrue();

            $storedGestempeld = StampCatalogValue::query()
                ->findOrFail($this->valueB1Gestempeld->id);

            expect($storedGestempeld->getAttributes())
                ->toBe(
                    $originalValues[$this->valueB1Gestempeld->id]
                );

            expect($storedGestempeld->catalog_value)
                ->toBe('12.50');

            expect($storedGestempeld->needs_review)
                ->toBeFalse();

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => 42.50,
            ]);

            // -------------------------------------------------
            // Controleer alle overige catalogusgegevens.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                if ($entryId === $this->entryB1->id) {
                    continue;
                }

                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            foreach ($originalValues as $valueId => $attributes) {
                if ($valueId === $this->valueB1Postfris->id) {
                    continue;
                }

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            expect($changedEntry->values()->count())
                ->toBe(2);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(1);

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat beide wijzigingslogs zijn opgeslagen.
            // -------------------------------------------------

            $logs = $activeBatch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(2);

            $this->assertDatabaseCount('catalog_change_logs', 2);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'updated',
                    'updated',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van het nummer.
            // -------------------------------------------------

            $entryLog = $logs
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            expect($entryLog->record_id)
                ->toBe($this->entryB1->id);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->old_values)
                ->toBe([
                    'catalog_number' => 'B-100',
                ]);

            expect($entryLog->new_values)
                ->toBe([
                    'catalog_number' => 'B-850',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van postfris.
            // -------------------------------------------------

            $valueLog = $logs
                ->where('record_type', 'stamp_catalog_value')
                ->sole();

            expect($valueLog->record_id)
                ->toBe($this->valueB1Postfris->id);

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->condition->id)
                ->toBe($this->postfris->id);

            expect($valueLog->old_values)
                ->toHaveCount(2);

            expect($valueLog->new_values)
                ->toHaveCount(2);

            expect($valueLog->old_values)
                ->toHaveKeys([
                    'catalog_value',
                    'needs_review',
                ]);

            expect($valueLog->new_values)
                ->toHaveKeys([
                    'catalog_value',
                    'needs_review',
                ]);

            expect((float) $valueLog->old_values['catalog_value'])
                ->toBe(25.00);

            expect((float) $valueLog->new_values['catalog_value'])
                ->toBe(85.75);

            expect((bool) $valueLog->old_values['needs_review'])
                ->toBeFalse();

            expect((bool) $valueLog->new_values['needs_review'])
                ->toBeTrue();

            // -------------------------------------------------
            // Controleer de gemeenschappelijke loggegevens.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('updated');

                expect($log->old_values)
                    ->not->toHaveKey('updated_at');

                expect($log->new_values)
                    ->not->toHaveKey('updated_at');

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $createdBatchId,
                    'catalog_edition_id' => $this->editionB1->id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $log->condition_id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => 'updated',
                ]);
            }

            // -------------------------------------------------
            // Voor gestempeld bestaat nog geen wijzigingslog.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Gestempeld->id,
            ]);

            foreach ([
                $this->editionA1,
                $this->editionA2,
                $this->editionC1,
            ] as $edition) {
                $this->assertDatabaseMissing('catalog_change_batches', [
                    'catalog_edition_id' => $edition->id,
                ]);
            }

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(2);

        expect($observedValueIds)
            ->toBe([
                $this->valueB1Postfris->id,
                $this->valueB1Gestempeld->id,
            ]);

        expect($createdBatchId)
            ->toBeInt();

        expect($createdLogIds)
            ->toHaveCount(2);

        expect(array_unique($createdLogIds))
            ->toHaveCount(2);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer het herstel van het catalogusnummer.
        // -------------------------------------------------

        $restoredEntry = $this->entryB1->fresh();

        expect($restoredEntry->getAttributes())
            ->toBe($originalEntries[$this->entryB1->id]);

        expect($restoredEntry->catalog_number)
            ->toBe('B-100');

        expect($restoredEntry->values()->count())
            ->toBe(2);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-100',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-850',
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de postfrisse waarde.
        // -------------------------------------------------

        $restoredPostfris = $this->valueB1Postfris->fresh();

        expect($restoredPostfris->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]
            );

        expect($restoredPostfris->catalog_value)
            ->toBe('25.00');

        expect($restoredPostfris->currency)
            ->toBe('USD');

        expect($restoredPostfris->needs_review)
            ->toBeFalse();

        expect($restoredPostfris->getRawOriginal('updated_at'))
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]['updated_at']
            );

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 25.00,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 85.75,
        ]);

        // -------------------------------------------------
        // Controleer het behoud van de gestempelde waarde.
        // -------------------------------------------------

        $restoredGestempeld = $this->valueB1Gestempeld->fresh();

        expect($restoredGestempeld->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Gestempeld->id]
            );

        expect($restoredGestempeld->catalog_value)
            ->toBe('12.50');

        expect($restoredGestempeld->currency)
            ->toBe('USD');

        expect($restoredGestempeld->needs_review)
            ->toBeFalse();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Gestempeld->id,
            'catalog_value' => 42.50,
        ]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusgegevens.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            expect(
                StampCatalogEntry::query()
                    ->findOrFail($entryId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route C volledig terug bij een fout tijdens het aanmaken van een ontbrekende cataloguswaarde', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback C3',
        'slug' => 'oorspronkelijk-thema-rollback-c3',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback C3',
        'slug' => 'vervangend-thema-rollback-c3',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam C3',
            'description' => 'Oorspronkelijke Nederlandse beschrijving C3',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name C3',
            'description' => 'Original English description C3',
        ]
    );

    // -------------------------------------------------
    // Verwijder de gestempelde waarde buiten de aanvraag
    // en zonder actieve geschiedenisbatch.
    // -------------------------------------------------

    expect($this->history->getActiveBatch())
        ->toBeNull();

    $removedValueId = $this->valueB1Gestempeld->id;

    $this->valueB1Gestempeld->delete();

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'id' => $removedValueId,
    ]);

    $this->assertDatabaseMissing('stamp_catalog_values', [
        'stamp_catalog_entry_id' => $this->entryB1->id,
        'condition_id' => $this->gestempeld->id,
    ]);

    $this->assertDatabaseCount('stamp_catalog_values', 5);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->entryB1->values()->count())
        ->toBe(1);

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Postfris->fresh()->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    expect($originalValues)
        ->toHaveCount(5);

    expect($originalValues)
        ->not->toHaveKey($removedValueId);

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest C3';

    $payload['printing_method'] = 'Gewijzigde drukmethode C3';

    $payload['notes'] = 'Rollback aanmaken cataloguswaarde route C';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam C3',
        'description' => 'Gewijzigde Nederlandse beschrijving C3',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-950',
            'values' => [
                'postfris' => '95.75',
                'gestempeld' => '47.50',
            ],
        ],
    ];

    // Alleen route C mag catalogusgegevens verwerken.

    foreach ([
        'catalog_edition_id',
        'catalog_number',
        'catalog_values',
        'catalog_editions',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het aanmaken van een ontbrekende cataloguswaarde via route C';

    $listenerCalls = 0;

    $createdLogIds = [];

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::creating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $removedValueId,
            $exceptionMessage,
            &$listenerCalls,
            &$createdBatchId,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (
                $value->stamp_catalog_entry_id
                !== $this->entryB1->id
            ) {
                return;
            }

            $listenerCalls++;

            expect($listenerCalls)
                ->toBe(1);

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            // -------------------------------------------------
            // Bewijs dat de nieuwe waarde in voorbereiding is.
            // -------------------------------------------------

            expect($value->exists)
                ->toBeFalse();

            expect($value->getKey())
                ->toBeNull();

            expect($value->stamp_catalog_entry_id)
                ->toBe($this->entryB1->id);

            expect($value->catalog_value)
                ->toBe('47.50');

            expect($value->currency)
                ->toBe('USD');

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->gestempeld->id,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $removedValueId,
            ]);

            // -------------------------------------------------
            // Bewijs dat de postzegel daadwerkelijk is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            $this->assertDatabaseHas('stamp_translations', [
                'id' => $originalDutchTranslation['id'],
                'stamp_id' => $this->stamp->id,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Bewijs dat het catalogusnummer is gewijzigd.
            // -------------------------------------------------

            $changedEntry = StampCatalogEntry::query()
                ->findOrFail($this->entryB1->id);

            expect($changedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($changedEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($changedEntry->catalog_number)
                ->toBe('B-950');

            expect($changedEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryB1->id]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-950',
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_number' => 'B-100',
            ]);

            // -------------------------------------------------
            // Bewijs dat de postfrisse waarde is gewijzigd.
            // -------------------------------------------------

            $changedPostfris = StampCatalogValue::query()
                ->findOrFail($this->valueB1Postfris->id);

            expect($changedPostfris->stamp_catalog_entry_id)
                ->toBe($this->entryB1->id);

            expect($changedPostfris->condition_id)
                ->toBe($this->postfris->id);

            expect($changedPostfris->catalog_value)
                ->toBe('95.75');

            expect($changedPostfris->currency)
                ->toBe('USD');

            expect($changedPostfris->needs_review)
                ->toBeTrue();

            expect($changedPostfris->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalValues[$this->valueB1Postfris->id]['updated_at']
                );

            expect($changedPostfris->catalogEntry->id)
                ->toBe($this->entryB1->id);

            expect($changedPostfris->condition->id)
                ->toBe($this->postfris->id);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->postfris->id,
                'catalog_value' => 95.75,
                'currency' => 'USD',
                'needs_review' => true,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'catalog_value' => 25.00,
            ]);

            // -------------------------------------------------
            // Controleer alle overige catalogusgegevens.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                if ($entryId === $this->entryB1->id) {
                    continue;
                }

                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            foreach ($originalValues as $valueId => $attributes) {
                if ($valueId === $this->valueB1Postfris->id) {
                    continue;
                }

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 5);

            expect($changedEntry->values()->count())
                ->toBe(1);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(5);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(1);

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat beide wijzigingslogs zijn opgeslagen.
            // -------------------------------------------------

            $logs = $activeBatch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(2);

            $this->assertDatabaseCount('catalog_change_logs', 2);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'updated',
                    'updated',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van het nummer.
            // -------------------------------------------------

            $entryLog = $logs
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            expect($entryLog->record_id)
                ->toBe($this->entryB1->id);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->old_values)
                ->toBe([
                    'catalog_number' => 'B-100',
                ]);

            expect($entryLog->new_values)
                ->toBe([
                    'catalog_number' => 'B-950',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van postfris.
            // -------------------------------------------------

            $valueLog = $logs
                ->where('record_type', 'stamp_catalog_value')
                ->sole();

            expect($valueLog->record_id)
                ->toBe($this->valueB1Postfris->id);

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->condition->id)
                ->toBe($this->postfris->id);

            expect($valueLog->old_values)
                ->toHaveCount(2);

            expect($valueLog->new_values)
                ->toHaveCount(2);

            expect($valueLog->old_values)
                ->toHaveKeys([
                    'catalog_value',
                    'needs_review',
                ]);

            expect($valueLog->new_values)
                ->toHaveKeys([
                    'catalog_value',
                    'needs_review',
                ]);

            expect((float) $valueLog->old_values['catalog_value'])
                ->toBe(25.00);

            expect((float) $valueLog->new_values['catalog_value'])
                ->toBe(95.75);

            expect((bool) $valueLog->old_values['needs_review'])
                ->toBeFalse();

            expect((bool) $valueLog->new_values['needs_review'])
                ->toBeTrue();

            // -------------------------------------------------
            // Controleer de gemeenschappelijke loggegevens.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('updated');

                expect($log->old_values)
                    ->not->toHaveKey('updated_at');

                expect($log->new_values)
                    ->not->toHaveKey('updated_at');

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $createdBatchId,
                    'catalog_edition_id' => $this->editionB1->id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $log->condition_id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => 'updated',
                ]);
            }

            // -------------------------------------------------
            // Er mag nog geen created-log bestaan.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'action' => 'created',
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $removedValueId,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $this->editionB1->id,
                'condition_id' => $this->gestempeld->id,
            ]);

            foreach ([
                $this->editionA1,
                $this->editionA2,
                $this->editionC1,
            ] as $edition) {
                $this->assertDatabaseMissing('catalog_change_batches', [
                    'catalog_edition_id' => $edition->id,
                ]);
            }

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdBatchId)
            ->toBeInt();

        expect($createdLogIds)
            ->toHaveCount(2);

        expect(array_unique($createdLogIds))
            ->toHaveCount(2);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer het herstel van het catalogusnummer.
        // -------------------------------------------------

        $restoredEntry = $this->entryB1->fresh();

        expect($restoredEntry->getAttributes())
            ->toBe($originalEntries[$this->entryB1->id]);

        expect($restoredEntry->catalog_number)
            ->toBe('B-100');

        expect($restoredEntry->values()->count())
            ->toBe(1);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-100',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-950',
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de postfrisse waarde.
        // -------------------------------------------------

        $restoredPostfris = $this->valueB1Postfris->fresh();

        expect($restoredPostfris->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]
            );

        expect($restoredPostfris->catalog_value)
            ->toBe('25.00');

        expect($restoredPostfris->currency)
            ->toBe('USD');

        expect($restoredPostfris->needs_review)
            ->toBeFalse();

        expect($restoredPostfris->getRawOriginal('updated_at'))
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]['updated_at']
            );

        expect($restoredPostfris->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($restoredPostfris->condition->id)
            ->toBe($this->postfris->id);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 25.00,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 95.75,
        ]);

        // -------------------------------------------------
        // De ontbrekende gestempelde waarde moet afwezig blijven.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $removedValueId,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->gestempeld->id,
        ]);

        expect(
            $restoredEntry->values()
                ->where('condition_id', $this->gestempeld->id)
                ->exists()
        )->toBeFalse();

        expect(
            StampCatalogValue::query()->find($removedValueId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusgegevens.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            expect(
                StampCatalogEntry::query()
                    ->findOrFail($entryId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(5);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 5);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route D volledig terug bij een fout tijdens het aanmaken van een nieuwe catalogusvermelding', function () {
    // -------------------------------------------------
    // Bereid catalogus C voor.
    // -------------------------------------------------

    $this->catalogC->countries()->attach(
        $this->country->id
    );

    expect(
        $this->catalogC->countries()
            ->where('countries.id', $this->country->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogC->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    expect($this->editionC1->fresh()->currency)
        ->toBe('GBP');

    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback D1',
        'slug' => 'oorspronkelijk-thema-rollback-d1',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback D1',
        'slug' => 'vervangend-thema-rollback-d1',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam D1',
            'description' => 'Oorspronkelijke Nederlandse beschrijving D1',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name D1',
            'description' => 'Original English description D1',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    expect($originalEntries)
        ->toHaveCount(3);

    expect($originalValues)
        ->toHaveCount(6);

    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    expect(
        $this->stamp->catalogEntries()
            ->where('catalog_edition_id', $this->editionC1->id)
            ->exists()
    )->toBeFalse();

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest D1';

    $payload['printing_method'] = 'Gewijzigde drukmethode D1';

    $payload['notes'] = 'Rollback aanmaken catalogusvermelding route D';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam D1',
        'description' => 'Gewijzigde Nederlandse beschrijving D1',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_editions'] = [
        $this->editionC1->id => [
            'catalog_number' => 'C-650',
            'values' => [
                'postfris' => '65.75',
                'gestempeld' => '32.50',
            ],
        ],
    ];

    // Alleen route D mag catalogusgegevens verwerken.

    foreach ([
        'catalog_edition_id',
        'catalog_number',
        'catalog_values',
        'catalog_entries',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het aanmaken van een catalogusvermelding via route D';

    $listenerCalls = 0;

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogEntry::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogEntry::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogEntry::creating(function (
            StampCatalogEntry $entry
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $exceptionMessage,
            &$listenerCalls,
            &$createdBatchId,
            &$capturedHistory
        ) {
            if (
                $entry->catalog_edition_id
                !== $this->editionC1->id
            ) {
                return;
            }

            $listenerCalls++;

            expect($listenerCalls)
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat de nieuwe catalogusvermelding
            // in voorbereiding is.
            // -------------------------------------------------

            expect($entry->exists)
                ->toBeFalse();

            expect($entry->getKey())
                ->toBeNull();

            expect($entry->stamp_id)
                ->toBe($this->stamp->id);

            expect($entry->catalog_edition_id)
                ->toBe($this->editionC1->id);

            expect($entry->catalog_number)
                ->toBe('C-650');

            expect($entry->catalogEdition->id)
                ->toBe($this->editionC1->id);

            expect($entry->catalogEdition->currency)
                ->toBe('GBP');

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionC1->id,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'catalog_number' => 'C-650',
            ]);

            // -------------------------------------------------
            // Bewijs dat de postzegel daadwerkelijk is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            $this->assertDatabaseHas('stamp_translations', [
                'id' => $originalDutchTranslation['id'],
                'stamp_id' => $this->stamp->id,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            // De Engelse vertaling mag niet gewijzigd zijn.

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Controleer alle bestaande catalogusvermeldingen.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                $storedEntry = StampCatalogEntry::query()
                    ->findOrFail($entryId);

                expect($storedEntry->getAttributes())
                    ->toBe($attributes);

                expect($storedEntry->stamp_id)
                    ->toBe($this->stamp->id);

                expect($storedEntry->values()->count())
                    ->toBe(2);
            }

            // -------------------------------------------------
            // Controleer alle bestaande cataloguswaarden.
            // -------------------------------------------------

            foreach ($originalValues as $valueId => $attributes) {
                $storedValue = StampCatalogValue::query()
                    ->findOrFail($valueId);

                expect($storedValue->getAttributes())
                    ->toBe($attributes);

                expect($storedValue->needs_review)
                    ->toBeFalse();
            }

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(6);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(0);

            // -------------------------------------------------
            // Bewijs dat nog geen nieuwe cataloguswaarden
            // voor cataloguseditie C1 bestaan.
            // -------------------------------------------------

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

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            expect($createdBatchId)
                ->toBeInt();

            $this->assertDatabaseCount('catalog_change_batches', 1);

            // -------------------------------------------------
            // Bewijs dat de geschiedenisbatch daadwerkelijk
            // in de database is opgeslagen.
            // -------------------------------------------------

            $storedBatch = CatalogChangeBatch::query()
                ->findOrFail($createdBatchId);

            expect($storedBatch->catalog_edition_id)
                ->toBe($this->editionC1->id);

            expect($storedBatch->user_id)
                ->toBe($this->user->id);

            expect($storedBatch->user_name)
                ->toBe($this->user->name);

            expect($storedBatch->operation)
                ->toBe('stamp_update');

            expect($storedBatch->description)
                ->toBe(
                    'Catalogusgegevens bij het wijzigen van een postzegel'
                );

            expect($storedBatch->catalog_name)
                ->toBe($this->catalogC->name);

            expect($storedBatch->catalog_edition_name)
                ->toBe($this->editionC1->edition_name);

            expect($storedBatch->catalogEdition->id)
                ->toBe($this->editionC1->id);

            expect($storedBatch->catalogEdition->catalog->id)
                ->toBe($this->catalogC->id);

            expect($storedBatch->user->id)
                ->toBe($this->user->id);

            expect($activeBatch->id)
                ->toBe($storedBatch->id);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionC1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogC->name,
                'catalog_edition_name' => $this->editionC1->edition_name,
            ]);

            // -------------------------------------------------
            // De geschiedenisbatch mag nog geen logs bevatten.
            // -------------------------------------------------

            expect($storedBatch->logs()->count())
                ->toBe(0);

            expect($activeBatch->logs()->exists())
                ->toBeFalse();

            $this->assertDatabaseCount('catalog_change_logs', 0);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $createdBatchId,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $this->editionC1->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'stamp_id' => $this->stamp->id,
            ]);

            // -------------------------------------------------
            // Andere catalogusedities mogen geen batch hebben.
            // -------------------------------------------------

            foreach ([
                $this->editionA1,
                $this->editionA2,
                $this->editionB1,
            ] as $edition) {
                $this->assertDatabaseMissing('catalog_change_batches', [
                    'catalog_edition_id' => $edition->id,
                ]);
            }

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(1);

        expect($createdBatchId)
            ->toBeInt();

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'designer' => $payload['designer'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // De nieuwe catalogusvermelding moet afwezig blijven.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'catalog_number' => 'C-650',
        ]);

        expect(
            $restoredStamp->catalogEntries()
                ->where('catalog_edition_id', $this->editionC1->id)
                ->exists()
        )->toBeFalse();

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $restoredEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($restoredEntry->getAttributes())
                ->toBe($attributes);

            expect($restoredEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($restoredEntry->values()->count())
                ->toBe(2);
        }

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($restoredValue->getAttributes())
                ->toBe($attributes);

            expect($restoredValue->needs_review)
                ->toBeFalse();

            expect($restoredValue->catalogEntry->id)
                ->toBe($attributes['stamp_catalog_entry_id']);

            expect($restoredValue->condition->id)
                ->toBe($attributes['condition_id']);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Er mogen geen cataloguswaarden voor C1 bestaan.
        // -------------------------------------------------

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

        // -------------------------------------------------
        // De tijdens de voorbereiding gemaakte landkoppeling
        // moet behouden blijven.
        // -------------------------------------------------

        expect(
            $this->catalogC->countries()
                ->orderBy('countries.id')
                ->pluck('countries.id')
                ->all()
        )->toBe(
            $originalData['catalog_countries'][$this->catalogC->id]
        );

        expect(
            $this->catalogC->countries()
                ->where('countries.id', $this->country->id)
                ->exists()
        )->toBeTrue();

        expect(
            $this->catalogC->countries()
                ->where('countries.id', $this->otherCountry->id)
                ->exists()
        )->toBeTrue();

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        $this->assertDatabaseMissing('catalog_change_batches', [
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogEntry::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route D volledig terug bij een fout tijdens het wijzigen van een bestaande cataloguswaarde', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback D2',
        'slug' => 'oorspronkelijk-thema-rollback-d2',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback D2',
        'slug' => 'vervangend-thema-rollback-d2',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam D2',
            'description' => 'Oorspronkelijke Nederlandse beschrijving D2',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name D2',
            'description' => 'Original English description D2',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->editionB1->fresh()->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Gestempeld->fresh()->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->fresh()->needs_review)
        ->toBeFalse();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest D2';

    $payload['printing_method'] = 'Gewijzigde drukmethode D2';

    $payload['notes'] = 'Rollback wijzigen cataloguswaarde route D';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam D2',
        'description' => 'Gewijzigde Nederlandse beschrijving D2',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-750',
            'values' => [
                'postfris' => '75.75',
                'gestempeld' => '37.50',
            ],
        ],
    ];

    // Alleen route D mag catalogusgegevens verwerken.

    foreach ([
        'catalog_edition_id',
        'catalog_number',
        'catalog_values',
        'catalog_entries',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het wijzigen van een cataloguswaarde via route D';

    $listenerCalls = 0;

    $observedValueIds = [];

    $createdLogIds = [];

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::updating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$createdBatchId,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (
                $value->stamp_catalog_entry_id
                !== $this->entryB1->id
            ) {
                return;
            }

            $listenerCalls++;

            $observedValueIds[] = $value->id;

            // -------------------------------------------------
            // Laat de postfrisse waarde normaal wijzigen.
            // -------------------------------------------------

            if ($value->id === $this->valueB1Postfris->id) {
                expect($listenerCalls)
                    ->toBe(1);

                return;
            }

            // -------------------------------------------------
            // Veroorzaak uitsluitend een fout bij gestempeld.
            // -------------------------------------------------

            expect($value->id)
                ->toBe($this->valueB1Gestempeld->id);

            expect($listenerCalls)
                ->toBe(2);

            expect($observedValueIds)
                ->toBe([
                    $this->valueB1Postfris->id,
                    $this->valueB1Gestempeld->id,
                ]);

            // -------------------------------------------------
            // Bewijs dat de postzegel daadwerkelijk is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            $this->assertDatabaseHas('stamp_translations', [
                'id' => $originalDutchTranslation['id'],
                'stamp_id' => $this->stamp->id,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Bewijs dat het catalogusnummer is gewijzigd.
            // -------------------------------------------------

            $changedEntry = StampCatalogEntry::query()
                ->findOrFail($this->entryB1->id);

            expect($changedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($changedEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($changedEntry->catalog_number)
                ->toBe('B-750');

            expect($changedEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryB1->id]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-750',
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_number' => 'B-100',
            ]);

            // -------------------------------------------------
            // Bewijs dat de postfrisse waarde is gewijzigd.
            // -------------------------------------------------

            $changedPostfris = StampCatalogValue::query()
                ->findOrFail($this->valueB1Postfris->id);

            expect($changedPostfris->stamp_catalog_entry_id)
                ->toBe($this->entryB1->id);

            expect($changedPostfris->condition_id)
                ->toBe($this->postfris->id);

            expect($changedPostfris->catalog_value)
                ->toBe('75.75');

            expect($changedPostfris->currency)
                ->toBe('USD');

            expect($changedPostfris->needs_review)
                ->toBeTrue();

            expect($changedPostfris->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalValues[$this->valueB1Postfris->id]['updated_at']
                );

            expect($changedPostfris->catalogEntry->id)
                ->toBe($this->entryB1->id);

            expect($changedPostfris->condition->id)
                ->toBe($this->postfris->id);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->postfris->id,
                'catalog_value' => 75.75,
                'currency' => 'USD',
                'needs_review' => true,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'catalog_value' => 25.00,
            ]);

            // -------------------------------------------------
            // De gestempelde waarde is nog niet opgeslagen.
            // -------------------------------------------------

            expect($value->exists)
                ->toBeTrue();

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            expect($value->catalog_value)
                ->toBe('37.50');

            expect($value->currency)
                ->toBe('USD');

            expect($value->isDirty('catalog_value'))
                ->toBeTrue();

            $storedGestempeld = StampCatalogValue::query()
                ->findOrFail($this->valueB1Gestempeld->id);

            expect($storedGestempeld->getAttributes())
                ->toBe(
                    $originalValues[$this->valueB1Gestempeld->id]
                );

            expect($storedGestempeld->catalog_value)
                ->toBe('12.50');

            expect($storedGestempeld->needs_review)
                ->toBeFalse();

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => 37.50,
            ]);

            // -------------------------------------------------
            // Controleer alle overige catalogusgegevens.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                if ($entryId === $this->entryB1->id) {
                    continue;
                }

                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            foreach ($originalValues as $valueId => $attributes) {
                if ($valueId === $this->valueB1Postfris->id) {
                    continue;
                }

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            expect($changedEntry->values()->count())
                ->toBe(2);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(1);

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat beide wijzigingslogs zijn opgeslagen.
            // -------------------------------------------------

            $logs = $activeBatch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(2);

            $this->assertDatabaseCount('catalog_change_logs', 2);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'updated',
                    'updated',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van het nummer.
            // -------------------------------------------------

            $entryLog = $logs
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            expect($entryLog->record_id)
                ->toBe($this->entryB1->id);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->old_values)
                ->toBe([
                    'catalog_number' => 'B-100',
                ]);

            expect($entryLog->new_values)
                ->toBe([
                    'catalog_number' => 'B-750',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van postfris.
            // -------------------------------------------------

            $valueLog = $logs
                ->where('record_type', 'stamp_catalog_value')
                ->sole();

            expect($valueLog->record_id)
                ->toBe($this->valueB1Postfris->id);

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->condition->id)
                ->toBe($this->postfris->id);

            expect($valueLog->old_values)
                ->toHaveCount(2);

            expect($valueLog->new_values)
                ->toHaveCount(2);

            expect($valueLog->old_values)
                ->toHaveKeys([
                    'catalog_value',
                    'needs_review',
                ]);

            expect($valueLog->new_values)
                ->toHaveKeys([
                    'catalog_value',
                    'needs_review',
                ]);

            expect((float) $valueLog->old_values['catalog_value'])
                ->toBe(25.00);

            expect((float) $valueLog->new_values['catalog_value'])
                ->toBe(75.75);

            expect((bool) $valueLog->old_values['needs_review'])
                ->toBeFalse();

            expect((bool) $valueLog->new_values['needs_review'])
                ->toBeTrue();

            // -------------------------------------------------
            // Controleer de gemeenschappelijke loggegevens.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('updated');

                expect($log->old_values)
                    ->not->toHaveKey('updated_at');

                expect($log->new_values)
                    ->not->toHaveKey('updated_at');

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $createdBatchId,
                    'catalog_edition_id' => $this->editionB1->id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $log->condition_id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => 'updated',
                ]);
            }

            // -------------------------------------------------
            // Voor gestempeld bestaat nog geen wijzigingslog.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Gestempeld->id,
            ]);

            foreach ([
                $this->editionA1,
                $this->editionA2,
                $this->editionC1,
            ] as $edition) {
                $this->assertDatabaseMissing('catalog_change_batches', [
                    'catalog_edition_id' => $edition->id,
                ]);
            }

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(2);

        expect($observedValueIds)
            ->toBe([
                $this->valueB1Postfris->id,
                $this->valueB1Gestempeld->id,
            ]);

        expect($createdBatchId)
            ->toBeInt();

        expect($createdLogIds)
            ->toHaveCount(2);

        expect(array_unique($createdLogIds))
            ->toHaveCount(2);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer het herstel van het catalogusnummer.
        // -------------------------------------------------

        $restoredEntry = $this->entryB1->fresh();

        expect($restoredEntry->getAttributes())
            ->toBe($originalEntries[$this->entryB1->id]);

        expect($restoredEntry->catalog_number)
            ->toBe('B-100');

        expect($restoredEntry->values()->count())
            ->toBe(2);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-100',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-750',
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de postfrisse waarde.
        // -------------------------------------------------

        $restoredPostfris = $this->valueB1Postfris->fresh();

        expect($restoredPostfris->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]
            );

        expect($restoredPostfris->catalog_value)
            ->toBe('25.00');

        expect($restoredPostfris->currency)
            ->toBe('USD');

        expect($restoredPostfris->needs_review)
            ->toBeFalse();

        expect($restoredPostfris->getRawOriginal('updated_at'))
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]['updated_at']
            );

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 25.00,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 75.75,
        ]);

        // -------------------------------------------------
        // Controleer het behoud van de gestempelde waarde.
        // -------------------------------------------------

        $restoredGestempeld = $this->valueB1Gestempeld->fresh();

        expect($restoredGestempeld->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Gestempeld->id]
            );

        expect($restoredGestempeld->catalog_value)
            ->toBe('12.50');

        expect($restoredGestempeld->currency)
            ->toBe('USD');

        expect($restoredGestempeld->needs_review)
            ->toBeFalse();

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Gestempeld->id,
            'catalog_value' => 37.50,
        ]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusgegevens.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            expect(
                StampCatalogEntry::query()
                    ->findOrFail($entryId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route D volledig terug bij een fout tijdens het verwijderen van een bestaande cataloguswaarde', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback D3',
        'slug' => 'oorspronkelijk-thema-rollback-d3',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback D3',
        'slug' => 'vervangend-thema-rollback-d3',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam D3',
            'description' => 'Oorspronkelijke Nederlandse beschrijving D3',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name D3',
            'description' => 'Original English description D3',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->editionB1->fresh()->currency)
        ->toBe('USD');

    expect($this->entryB1->values()->count())
        ->toBe(2);

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Gestempeld->fresh()->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->fresh()->needs_review)
        ->toBeFalse();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest D3';

    $payload['printing_method'] = 'Gewijzigde drukmethode D3';

    $payload['notes'] = 'Rollback verwijderen cataloguswaarde route D';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam D3',
        'description' => 'Gewijzigde Nederlandse beschrijving D3',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-850',
            'values' => [
                'postfris' => '',
                'gestempeld' => null,
            ],
        ],
    ];

    // Alleen route D mag catalogusgegevens verwerken.

    foreach ([
        'catalog_edition_id',
        'catalog_number',
        'catalog_values',
        'catalog_entries',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het verwijderen van een cataloguswaarde via route D';

    $listenerCalls = 0;

    $observedValueIds = [];

    $createdLogIds = [];

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::deleting(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$createdBatchId,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (
                $value->stamp_catalog_entry_id
                !== $this->entryB1->id
            ) {
                return;
            }

            $listenerCalls++;

            $observedValueIds[] = $value->id;

            // -------------------------------------------------
            // Laat de postfrisse waarde normaal verwijderen.
            // -------------------------------------------------

            if ($value->id === $this->valueB1Postfris->id) {
                expect($listenerCalls)
                    ->toBe(1);

                expect($value->getAttributes())
                    ->toBe(
                        $originalValues[$this->valueB1Postfris->id]
                    );

                return;
            }

            // -------------------------------------------------
            // Veroorzaak uitsluitend een fout bij gestempeld.
            // -------------------------------------------------

            expect($value->id)
                ->toBe($this->valueB1Gestempeld->id);

            expect($listenerCalls)
                ->toBe(2);

            expect($observedValueIds)
                ->toBe([
                    $this->valueB1Postfris->id,
                    $this->valueB1Gestempeld->id,
                ]);

            // -------------------------------------------------
            // Bewijs dat de postzegel daadwerkelijk is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            $this->assertDatabaseHas('stamp_translations', [
                'id' => $originalDutchTranslation['id'],
                'stamp_id' => $this->stamp->id,
                'locale' => 'nl',
                'name' => $payload['translations']['nl']['name'],
                'description' => $payload['translations']['nl']['description'],
            ]);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Bewijs dat het catalogusnummer is gewijzigd.
            // -------------------------------------------------

            $changedEntry = StampCatalogEntry::query()
                ->findOrFail($this->entryB1->id);

            expect($changedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($changedEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($changedEntry->catalog_number)
                ->toBe('B-850');

            expect($changedEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryB1->id]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-850',
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_number' => 'B-100',
            ]);

            // -------------------------------------------------
            // Bewijs dat postfris daadwerkelijk is verwijderd.
            // -------------------------------------------------

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->postfris->id,
            ]);

            expect(
                StampCatalogValue::query()
                    ->find($this->valueB1Postfris->id)
            )->toBeNull();

            expect(
                $changedEntry->values()
                    ->where('condition_id', $this->postfris->id)
                    ->exists()
            )->toBeFalse();

            // -------------------------------------------------
            // Gestempeld mag nog niet verwijderd zijn.
            // -------------------------------------------------

            expect($value->exists)
                ->toBeTrue();

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            expect($value->catalog_value)
                ->toBe('12.50');

            expect($value->currency)
                ->toBe('USD');

            expect($value->needs_review)
                ->toBeFalse();

            $storedGestempeld = StampCatalogValue::query()
                ->findOrFail($this->valueB1Gestempeld->id);

            expect($storedGestempeld->getAttributes())
                ->toBe(
                    $originalValues[$this->valueB1Gestempeld->id]
                );

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->gestempeld->id,
                'catalog_value' => 12.50,
                'currency' => 'USD',
                'needs_review' => false,
            ]);

            expect($changedEntry->values()->count())
                ->toBe(1);

            // -------------------------------------------------
            // Controleer alle overige catalogusgegevens.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                if ($entryId === $this->entryB1->id) {
                    continue;
                }

                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            foreach ($originalValues as $valueId => $attributes) {
                if ($valueId === $this->valueB1Postfris->id) {
                    continue;
                }

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 5);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(5);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(0);

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $createdBatchId = $activeBatch->id;

            $this->assertDatabaseCount('catalog_change_batches', 1);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'user_name' => $this->user->name,
                'operation' => 'stamp_update',
                'description' => 'Catalogusgegevens bij het wijzigen van een postzegel',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            expect($activeBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($activeBatch->user->id)
                ->toBe($this->user->id);

            // -------------------------------------------------
            // Bewijs dat beide wijzigingslogs zijn opgeslagen.
            // -------------------------------------------------

            $logs = $activeBatch->logs()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(2);

            $this->assertDatabaseCount('catalog_change_logs', 2);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'updated',
                    'deleted',
                ]);

            // -------------------------------------------------
            // Controleer de wijzigingslog van het nummer.
            // -------------------------------------------------

            $entryLog = $logs
                ->where('record_type', 'stamp_catalog_entry')
                ->sole();

            expect($entryLog->record_id)
                ->toBe($this->entryB1->id);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->condition)
                ->toBeNull();

            expect($entryLog->action)
                ->toBe('updated');

            expect($entryLog->old_values)
                ->toBe([
                    'catalog_number' => 'B-100',
                ]);

            expect($entryLog->new_values)
                ->toBe([
                    'catalog_number' => 'B-850',
                ]);

            expect($entryLog->old_values)
                ->not->toHaveKey('updated_at');

            expect($entryLog->new_values)
                ->not->toHaveKey('updated_at');

            // -------------------------------------------------
            // Controleer de verwijderingslog van postfris.
            // -------------------------------------------------

            $valueLog = $logs
                ->where('record_type', 'stamp_catalog_value')
                ->sole();

            expect($valueLog->record_id)
                ->toBe($this->valueB1Postfris->id);

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->condition->id)
                ->toBe($this->postfris->id);

            expect($valueLog->action)
                ->toBe('deleted');

            expect($valueLog->new_values)
                ->toBeNull();

            expect($valueLog->old_values)
                ->toBeArray();

            // Vergelijk alle oorspronkelijke attributen.
            // Normaliseer uitsluitend decimale en booleaanse waarden.

            $expectedOldValues =
                $originalValues[$this->valueB1Postfris->id];

            $actualOldValues = $valueLog->old_values;

            $expectedOldValues['catalog_value'] =
                (float) $expectedOldValues['catalog_value'];

            $actualOldValues['catalog_value'] =
                (float) $actualOldValues['catalog_value'];

            $expectedOldValues['needs_review'] =
                (bool) $expectedOldValues['needs_review'];

            $actualOldValues['needs_review'] =
                (bool) $actualOldValues['needs_review'];

            ksort($expectedOldValues);

            ksort($actualOldValues);

            expect($actualOldValues)
                ->toBe($expectedOldValues);

            expect($actualOldValues['id'])
                ->toBe($this->valueB1Postfris->id);

            expect($actualOldValues['stamp_catalog_entry_id'])
                ->toBe($this->entryB1->id);

            expect($actualOldValues['condition_id'])
                ->toBe($this->postfris->id);

            expect($actualOldValues['catalog_value'])
                ->toBe(25.00);

            expect($actualOldValues['currency'])
                ->toBe('USD');

            expect($actualOldValues['needs_review'])
                ->toBeFalse();

            expect($actualOldValues['created_at'])
                ->toBe($expectedOldValues['created_at']);

            expect($actualOldValues['updated_at'])
                ->toBe($expectedOldValues['updated_at']);

            // -------------------------------------------------
            // Controleer de gemeenschappelijke loggegevens.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $createdBatchId,
                    'catalog_edition_id' => $this->editionB1->id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $log->condition_id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => $log->action,
                ]);
            }

            // -------------------------------------------------
            // Voor gestempeld bestaat nog geen verwijderingslog.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Gestempeld->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'action' => 'created',
            ]);

            foreach ([
                $this->editionA1,
                $this->editionA2,
                $this->editionC1,
            ] as $edition) {
                $this->assertDatabaseMissing('catalog_change_batches', [
                    'catalog_edition_id' => $edition->id,
                ]);
            }

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(2);

        expect($observedValueIds)
            ->toBe([
                $this->valueB1Postfris->id,
                $this->valueB1Gestempeld->id,
            ]);

        expect($createdBatchId)
            ->toBeInt();

        expect($createdLogIds)
            ->toHaveCount(2);

        expect(array_unique($createdLogIds))
            ->toHaveCount(2);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer het herstel van het catalogusnummer.
        // -------------------------------------------------

        $restoredEntry = $this->entryB1->fresh();

        expect($restoredEntry->getAttributes())
            ->toBe($originalEntries[$this->entryB1->id]);

        expect($restoredEntry->catalog_number)
            ->toBe('B-100');

        expect($restoredEntry->values()->count())
            ->toBe(2);

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-100',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-850',
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de verwijderde waarde.
        // -------------------------------------------------

        $restoredPostfris = StampCatalogValue::query()
            ->findOrFail($this->valueB1Postfris->id);

        expect($restoredPostfris->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]
            );

        expect($restoredPostfris->id)
            ->toBe($this->valueB1Postfris->id);

        expect($restoredPostfris->stamp_catalog_entry_id)
            ->toBe($this->entryB1->id);

        expect($restoredPostfris->condition_id)
            ->toBe($this->postfris->id);

        expect($restoredPostfris->catalog_value)
            ->toBe('25.00');

        expect($restoredPostfris->currency)
            ->toBe('USD');

        expect($restoredPostfris->needs_review)
            ->toBeFalse();

        expect($restoredPostfris->getRawOriginal('created_at'))
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]['created_at']
            );

        expect($restoredPostfris->getRawOriginal('updated_at'))
            ->toBe(
                $originalValues[$this->valueB1Postfris->id]['updated_at']
            );

        expect($restoredPostfris->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($restoredPostfris->condition->id)
            ->toBe($this->postfris->id);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->postfris->id,
            'catalog_value' => 25.00,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // -------------------------------------------------
        // Controleer het behoud van de gestempelde waarde.
        // -------------------------------------------------

        $restoredGestempeld = StampCatalogValue::query()
            ->findOrFail($this->valueB1Gestempeld->id);

        expect($restoredGestempeld->getAttributes())
            ->toBe(
                $originalValues[$this->valueB1Gestempeld->id]
            );

        expect($restoredGestempeld->catalog_value)
            ->toBe('12.50');

        expect($restoredGestempeld->currency)
            ->toBe('USD');

        expect($restoredGestempeld->needs_review)
            ->toBeFalse();

        expect($restoredGestempeld->catalogEntry->id)
            ->toBe($this->entryB1->id);

        expect($restoredGestempeld->condition->id)
            ->toBe($this->gestempeld->id);

        $this->assertDatabaseHas('stamp_catalog_values', [
            'id' => $this->valueB1Gestempeld->id,
            'stamp_catalog_entry_id' => $this->entryB1->id,
            'condition_id' => $this->gestempeld->id,
            'catalog_value' => 12.50,
            'currency' => 'USD',
            'needs_review' => false,
        ]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusgegevens.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            expect(
                StampCatalogEntry::query()
                    ->findOrFail($entryId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        foreach ($originalValues as $valueId => $attributes) {
            expect(
                StampCatalogValue::query()
                    ->findOrFail($valueId)
                    ->getAttributes()
            )->toBe($attributes);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van beide wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait route D volledig terug bij een fout tijdens het verwerken van een tweede cataloguseditie', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback D4',
        'slug' => 'oorspronkelijk-thema-rollback-d4',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback D4',
        'slug' => 'vervangend-thema-rollback-d4',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam D4',
            'description' => 'Oorspronkelijke Nederlandse beschrijving D4',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name D4',
            'description' => 'Original English description D4',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    expect($this->entryA1->fresh()->catalog_number)
        ->toBe('A-100');

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->editionA1->fresh()->currency)
        ->toBe('EUR');

    expect($this->editionB1->fresh()->currency)
        ->toBe('USD');

    expect($this->valueA1Postfris->fresh()->catalog_value)
        ->toBe('15.00');

    expect($this->valueA1Gestempeld->fresh()->catalog_value)
        ->toBe('7.50');

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Gestempeld->fresh()->catalog_value)
        ->toBe('12.50');

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    // -------------------------------------------------
    // Stel geldige formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest D4';

    $payload['printing_method'] = 'Gewijzigde drukmethode D4';

    $payload['notes'] = 'Rollback meerdere catalogusedities route D';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam D4',
        'description' => 'Gewijzigde Nederlandse beschrijving D4',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    $payload['catalog_editions'] = [
        $this->editionA1->id => [
            'catalog_number' => 'A-950',
            'values' => [
                'postfris' => '95.75',
                'gestempeld' => '47.50',
            ],
        ],
        $this->editionB1->id => [
            'catalog_number' => 'B-950',
            'values' => [
                'postfris' => '85.75',
                'gestempeld' => '42.50',
            ],
        ],
    ];

    // Alleen route D mag catalogusgegevens verwerken.

    foreach ([
        'catalog_edition_id',
        'catalog_number',
        'catalog_values',
        'catalog_entries',
    ] as $field) {
        expect($payload)
            ->not->toHaveKey($field);
    }

    // -------------------------------------------------
    // Definieer de verwachte gewijzigde cataloguswaarden.
    // -------------------------------------------------

    $expectedChangedValues = [
        $this->valueA1Postfris->id => [
            'entry_id' => $this->entryA1->id,
            'edition_id' => $this->editionA1->id,
            'condition_id' => $this->postfris->id,
            'old_value' => 15.00,
            'new_value' => '95.75',
            'currency' => 'EUR',
        ],
        $this->valueA1Gestempeld->id => [
            'entry_id' => $this->entryA1->id,
            'edition_id' => $this->editionA1->id,
            'condition_id' => $this->gestempeld->id,
            'old_value' => 7.50,
            'new_value' => '47.50',
            'currency' => 'EUR',
        ],
        $this->valueB1Postfris->id => [
            'entry_id' => $this->entryB1->id,
            'edition_id' => $this->editionB1->id,
            'condition_id' => $this->postfris->id,
            'old_value' => 25.00,
            'new_value' => '85.75',
            'currency' => 'USD',
        ],
    ];

    $expectedChangedEntries = [
        $this->entryA1->id => [
            'edition_id' => $this->editionA1->id,
            'old_number' => 'A-100',
            'new_number' => 'A-950',
        ],
        $this->entryB1->id => [
            'edition_id' => $this->editionB1->id,
            'old_number' => 'B-100',
            'new_number' => 'B-950',
        ],
    ];

    $expectedObservedValueIds = [
        $this->valueA1Postfris->id,
        $this->valueA1Gestempeld->id,
        $this->valueB1Postfris->id,
        $this->valueB1Gestempeld->id,
    ];

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout tijdens het verwerken van een tweede cataloguseditie via route D';

    $listenerCalls = 0;

    $observedValueIds = [];

    $observedBatchIds = [];

    $createdBatchIds = [];

    $createdLogIds = [];

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::updating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $expectedChangedEntries,
            $expectedChangedValues,
            $expectedObservedValueIds,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$observedBatchIds,
            &$createdBatchIds,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (! in_array(
                $value->stamp_catalog_entry_id,
                [
                    $this->entryA1->id,
                    $this->entryB1->id,
                ],
                true
            )) {
                return;
            }

            $listenerCalls++;

            $observedValueIds[] = $value->id;

            // -------------------------------------------------
            // Controleer de actieve batch bij iedere wijziging.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            $expectedEditionId =
                $value->stamp_catalog_entry_id === $this->entryA1->id
                    ? $this->editionA1->id
                    : $this->editionB1->id;

            expect($activeBatch->catalog_edition_id)
                ->toBe($expectedEditionId);

            $observedBatchIds[] = $activeBatch->id;

            expect($value->id)
                ->toBe(
                    $expectedObservedValueIds[$listenerCalls - 1]
                );

            // -------------------------------------------------
            // Laat de eerste drie waarden normaal wijzigen.
            // -------------------------------------------------

            if ($value->id !== $this->valueB1Gestempeld->id) {
                expect($expectedChangedValues)
                    ->toHaveKey($value->id);

                expect($value->catalog_value)
                    ->toBe(
                        $expectedChangedValues[$value->id]['new_value']
                    );

                expect($value->isDirty('catalog_value'))
                    ->toBeTrue();

                return;
            }

            // -------------------------------------------------
            // De vierde wijziging veroorzaakt de uitzondering.
            // -------------------------------------------------

            expect($listenerCalls)
                ->toBe(4);

            expect($observedValueIds)
                ->toBe($expectedObservedValueIds);

            expect($value->exists)
                ->toBeTrue();

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            expect($value->catalog_value)
                ->toBe('42.50');

            expect($value->currency)
                ->toBe('USD');

            expect($value->isDirty('catalog_value'))
                ->toBeTrue();

            // -------------------------------------------------
            // Bewijs dat de postzegel daadwerkelijk is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Bewijs dat de Nederlandse vertaling is gewijzigd.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            expect($originalTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            expect($replacementTheme->fresh())
                ->toBeInstanceOf(Theme::class);

            // -------------------------------------------------
            // Bewijs dat beide catalogusnummers zijn gewijzigd.
            // -------------------------------------------------

            foreach ($expectedChangedEntries as $entryId => $expected) {
                $changedEntry = StampCatalogEntry::query()
                    ->findOrFail($entryId);

                expect($changedEntry->stamp_id)
                    ->toBe($this->stamp->id);

                expect($changedEntry->catalog_edition_id)
                    ->toBe($expected['edition_id']);

                expect($changedEntry->catalog_number)
                    ->toBe($expected['new_number']);

                expect($changedEntry->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalEntries[$entryId]['updated_at']
                    );

                expect($changedEntry->values()->count())
                    ->toBe(2);

                $this->assertDatabaseHas('stamp_catalog_entries', [
                    'id' => $entryId,
                    'stamp_id' => $this->stamp->id,
                    'catalog_edition_id' => $expected['edition_id'],
                    'catalog_number' => $expected['new_number'],
                ]);

                $this->assertDatabaseMissing('stamp_catalog_entries', [
                    'id' => $entryId,
                    'catalog_number' => $expected['old_number'],
                ]);
            }

            // -------------------------------------------------
            // Bewijs dat drie cataloguswaarden zijn gewijzigd.
            // -------------------------------------------------

            foreach ($expectedChangedValues as $valueId => $expected) {
                $changedValue = StampCatalogValue::query()
                    ->findOrFail($valueId);

                expect($changedValue->stamp_catalog_entry_id)
                    ->toBe($expected['entry_id']);

                expect($changedValue->condition_id)
                    ->toBe($expected['condition_id']);

                expect($changedValue->catalog_value)
                    ->toBe($expected['new_value']);

                expect($changedValue->currency)
                    ->toBe($expected['currency']);

                expect($changedValue->needs_review)
                    ->toBeTrue();

                expect($changedValue->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalValues[$valueId]['updated_at']
                    );

                expect($changedValue->catalogEntry->id)
                    ->toBe($expected['entry_id']);

                expect($changedValue->condition->id)
                    ->toBe($expected['condition_id']);

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $valueId,
                    'stamp_catalog_entry_id' => $expected['entry_id'],
                    'condition_id' => $expected['condition_id'],
                    'catalog_value' => (float) $expected['new_value'],
                    'currency' => $expected['currency'],
                    'needs_review' => true,
                ]);

                $this->assertDatabaseMissing('stamp_catalog_values', [
                    'id' => $valueId,
                    'catalog_value' => $expected['old_value'],
                ]);
            }

            // -------------------------------------------------
            // De vierde cataloguswaarde is nog niet gewijzigd.
            // -------------------------------------------------

            $storedGestempeld = StampCatalogValue::query()
                ->findOrFail($this->valueB1Gestempeld->id);

            expect($storedGestempeld->getAttributes())
                ->toBe(
                    $originalValues[$this->valueB1Gestempeld->id]
                );

            expect($storedGestempeld->catalog_value)
                ->toBe('12.50');

            expect($storedGestempeld->currency)
                ->toBe('USD');

            expect($storedGestempeld->needs_review)
                ->toBeFalse();

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => 42.50,
            ]);

            // -------------------------------------------------
            // Controleer alle niet-gewijzigde catalogusgegevens.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                if (isset($expectedChangedEntries[$entryId])) {
                    continue;
                }

                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            foreach ($originalValues as $valueId => $attributes) {
                if (isset($expectedChangedValues[$valueId])) {
                    continue;
                }

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            expect($this->entryA2->fresh()->catalog_number)
                ->toBe('A-200');

            expect($this->valueA2Postfris->fresh()->catalog_value)
                ->toBe('20.00');

            expect($this->valueA2Gestempeld->fresh()->catalog_value)
                ->toBe('10.00');

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(6);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(3);

            // -------------------------------------------------
            // Bewijs dat beide geschiedenisbatches bestaan.
            // -------------------------------------------------

            $batches = CatalogChangeBatch::query()
                ->orderBy('id')
                ->get();

            expect($batches->count())
                ->toBe(2);

            $this->assertDatabaseCount('catalog_change_batches', 2);

            expect($batches->pluck('catalog_edition_id')->all())
                ->toBe([
                    $this->editionA1->id,
                    $this->editionB1->id,
                ]);

            $batchA1 = $batches
                ->where('catalog_edition_id', $this->editionA1->id)
                ->sole();

            $batchB1 = $batches
                ->where('catalog_edition_id', $this->editionB1->id)
                ->sole();

            $createdBatchIds = $batches->pluck('id')->all();

            expect($batchA1->id)
                ->not->toBe($batchB1->id);

            expect($activeBatch->id)
                ->toBe($batchB1->id);

            // -------------------------------------------------
            // Controleer het wisselen van actieve batch.
            // -------------------------------------------------

            expect($observedBatchIds)
                ->toBe([
                    $batchA1->id,
                    $batchA1->id,
                    $batchB1->id,
                    $batchB1->id,
                ]);

            $expectedBatches = [
                [
                    'batch' => $batchA1,
                    'edition' => $this->editionA1,
                    'catalog' => $this->catalogA,
                    'log_count' => 3,
                ],
                [
                    'batch' => $batchB1,
                    'edition' => $this->editionB1,
                    'catalog' => $this->catalogB,
                    'log_count' => 2,
                ],
            ];

            foreach ($expectedBatches as $expected) {
                $batch = $expected['batch'];

                $edition = $expected['edition'];

                $catalog = $expected['catalog'];

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
                    ->toBe($expected['log_count']);

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
            }

            // -------------------------------------------------
            // Bewijs dat vijf wijzigingslogs zijn opgeslagen.
            // -------------------------------------------------

            $logs = CatalogChangeLog::query()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(5);

            $this->assertDatabaseCount('catalog_change_logs', 5);

            expect($logs->pluck('action')->all())
                ->toBe(array_fill(0, 5, 'updated'));

            expect($logs->pluck('catalog_change_batch_id')->all())
                ->toBe([
                    $batchA1->id,
                    $batchA1->id,
                    $batchA1->id,
                    $batchB1->id,
                    $batchB1->id,
                ]);

            expect($logs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                    'stamp_catalog_value',
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                ]);

            expect($logs->pluck('record_id')->all())
                ->toBe([
                    $this->entryA1->id,
                    $this->valueA1Postfris->id,
                    $this->valueA1Gestempeld->id,
                    $this->entryB1->id,
                    $this->valueB1Postfris->id,
                ]);

            // -------------------------------------------------
            // Controleer beide catalogusnummerlogs.
            // -------------------------------------------------

            foreach ($expectedChangedEntries as $entryId => $expected) {
                $entryLog = $logs
                    ->where('record_type', 'stamp_catalog_entry')
                    ->where('record_id', $entryId)
                    ->sole();

                expect($entryLog->catalog_edition_id)
                    ->toBe($expected['edition_id']);

                expect($entryLog->condition_id)
                    ->toBeNull();

                expect($entryLog->condition)
                    ->toBeNull();

                expect($entryLog->old_values)
                    ->toBe([
                        'catalog_number' => $expected['old_number'],
                    ]);

                expect($entryLog->new_values)
                    ->toBe([
                        'catalog_number' => $expected['new_number'],
                    ]);
            }

            // -------------------------------------------------
            // Controleer de drie cataloguswaardelogs.
            // -------------------------------------------------

            foreach ($expectedChangedValues as $valueId => $expected) {
                $valueLog = $logs
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $valueId)
                    ->sole();

                expect($valueLog->catalog_edition_id)
                    ->toBe($expected['edition_id']);

                expect($valueLog->condition_id)
                    ->toBe($expected['condition_id']);

                expect($valueLog->condition->id)
                    ->toBe($expected['condition_id']);

                expect($valueLog->old_values)
                    ->toHaveCount(2);

                expect($valueLog->new_values)
                    ->toHaveCount(2);

                expect($valueLog->old_values)
                    ->toHaveKeys([
                        'catalog_value',
                        'needs_review',
                    ]);

                expect($valueLog->new_values)
                    ->toHaveKeys([
                        'catalog_value',
                        'needs_review',
                    ]);

                expect((float) $valueLog->old_values['catalog_value'])
                    ->toBe($expected['old_value']);

                expect((float) $valueLog->new_values['catalog_value'])
                    ->toBe((float) $expected['new_value']);

                expect((bool) $valueLog->old_values['needs_review'])
                    ->toBeFalse();

                expect((bool) $valueLog->new_values['needs_review'])
                    ->toBeTrue();
            }

            // -------------------------------------------------
            // Controleer de gemeenschappelijke loggegevens.
            // -------------------------------------------------

            foreach ($logs as $log) {
                $expectedBatch =
                    $log->catalog_edition_id === $this->editionA1->id
                        ? $batchA1
                        : $batchB1;

                expect($log->catalog_change_batch_id)
                    ->toBe($expectedBatch->id);

                expect($log->catalog_edition_id)
                    ->toBe($expectedBatch->catalog_edition_id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('updated');

                expect($log->old_values)
                    ->not->toHaveKey('updated_at');

                expect($log->new_values)
                    ->not->toHaveKey('updated_at');

                expect($log->batch->id)
                    ->toBe($expectedBatch->id);

                expect($log->catalogEdition->id)
                    ->toBe($expectedBatch->catalog_edition_id);

                expect($log->stamp->id)
                    ->toBe($this->stamp->id);

                expect($log->stampSeries)
                    ->toBeNull();

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $expectedBatch->id,
                    'catalog_edition_id' => $expectedBatch->catalog_edition_id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $log->condition_id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => 'updated',
                ]);
            }

            // -------------------------------------------------
            // Voor de vierde waarde bestaat nog geen log.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Gestempeld->id,
            ]);

            // Niet-ingediende catalogusedities hebben geen historie.

            foreach ([
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

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de aanvraag en controleer de uitzondering.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de bedoelde foutinjectie plaatsvond.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(4);

        expect($observedValueIds)
            ->toBe($expectedObservedValueIds);

        expect($createdBatchIds)
            ->toHaveCount(2);

        expect(array_unique($createdBatchIds))
            ->toHaveCount(2);

        expect($createdLogIds)
            ->toHaveCount(5);

        expect(array_unique($createdLogIds))
            ->toHaveCount(5);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer het herstel van alle catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $restoredEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($restoredEntry->getAttributes())
                ->toBe($attributes);

            expect($restoredEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($restoredEntry->values()->count())
                ->toBe(2);
        }

        foreach ($expectedChangedEntries as $entryId => $expected) {
            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $entryId,
                'catalog_edition_id' => $expected['edition_id'],
                'catalog_number' => $expected['old_number'],
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $entryId,
                'catalog_number' => $expected['new_number'],
            ]);
        }

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer het herstel van alle cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($restoredValue->getAttributes())
                ->toBe($attributes);

            expect($restoredValue->needs_review)
                ->toBeFalse();

            expect($restoredValue->catalogEntry->id)
                ->toBe($attributes['stamp_catalog_entry_id']);

            expect($restoredValue->condition->id)
                ->toBe($attributes['condition_id']);

            expect($restoredValue->getRawOriginal('created_at'))
                ->toBe($attributes['created_at']);

            expect($restoredValue->getRawOriginal('updated_at'))
                ->toBe($attributes['updated_at']);
        }

        foreach ($expectedChangedValues as $valueId => $expected) {
            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $valueId,
                'stamp_catalog_entry_id' => $expected['entry_id'],
                'condition_id' => $expected['condition_id'],
                'catalog_value' => $expected['old_value'],
                'currency' => $expected['currency'],
                'needs_review' => false,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $valueId,
                'catalog_value' => (float) $expected['new_value'],
            ]);
        }

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

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Gestempeld->id,
            'catalog_value' => 42.50,
        ]);

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van beide geschiedenisbatches.
        // -------------------------------------------------

        foreach ($createdBatchIds as $batchId) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'id' => $batchId,
            ]);

            expect(
                CatalogChangeBatch::query()->find($batchId)
            )->toBeNull();

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $batchId,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van alle vijf wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

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

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait gecombineerde routes volledig terug bij een fout in route D binnen dezelfde cataloguseditie', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback G1',
        'slug' => 'oorspronkelijk-thema-rollback-g1',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback G1',
        'slug' => 'vervangend-thema-rollback-g1',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam G1',
            'description' => 'Oorspronkelijke Nederlandse beschrijving G1',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name G1',
            'description' => 'Original English description G1',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    expect($this->entryB1->fresh()->catalog_number)
        ->toBe('B-100');

    expect($this->editionB1->fresh()->currency)
        ->toBe('USD');

    expect($this->valueB1Postfris->fresh()->catalog_value)
        ->toBe('25.00');

    expect($this->valueB1Gestempeld->fresh()->catalog_value)
        ->toBe('12.50');

    expect($this->valueB1Postfris->fresh()->needs_review)
        ->toBeFalse();

    expect($this->valueB1Gestempeld->fresh()->needs_review)
        ->toBeFalse();

    // -------------------------------------------------
    // Stel geldige algemene formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest G1';

    $payload['printing_method'] = 'Gewijzigde drukmethode G1';

    $payload['notes'] = 'Rollback gecombineerde routes binnen dezelfde editie';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam G1',
        'description' => 'Gewijzigde Nederlandse beschrijving G1',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    // -------------------------------------------------
    // Route B: legacy catalogusgegevens.
    // -------------------------------------------------

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-1100';

    $payload['catalog_values'] = [
        'postfris' => '35.75',
        'gestempeld' => '17.50',
    ];

    // -------------------------------------------------
    // Route C: bestaande catalogusvermelding.
    // -------------------------------------------------

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-1200',
            'values' => [
                'postfris' => '45.75',
                'gestempeld' => '22.50',
            ],
        ],
    ];

    // -------------------------------------------------
    // Route D: catalogusgegevens per cataloguseditie.
    // -------------------------------------------------

    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-1300',
            'values' => [
                'postfris' => '55.75',
                'gestempeld' => '27.50',
            ],
        ],
    ];

    // -------------------------------------------------
    // Definieer de verwachte verwerkingsvolgorde.
    // -------------------------------------------------

    $expectedValueUpdates = [
        [
            'id' => $this->valueB1Postfris->id,
            'amount' => '35.75',
            'stored_amount' => '25.00',
            'stored_review' => false,
            'log_count' => 1,
            'catalog_number' => 'B-1100',
        ],
        [
            'id' => $this->valueB1Gestempeld->id,
            'amount' => '17.50',
            'stored_amount' => '12.50',
            'stored_review' => false,
            'log_count' => 2,
            'catalog_number' => 'B-1100',
        ],
        [
            'id' => $this->valueB1Postfris->id,
            'amount' => '45.75',
            'stored_amount' => '35.75',
            'stored_review' => true,
            'log_count' => 4,
            'catalog_number' => 'B-1200',
        ],
        [
            'id' => $this->valueB1Gestempeld->id,
            'amount' => '22.50',
            'stored_amount' => '17.50',
            'stored_review' => true,
            'log_count' => 5,
            'catalog_number' => 'B-1200',
        ],
        [
            'id' => $this->valueB1Postfris->id,
            'amount' => '55.75',
            'stored_amount' => '45.75',
            'stored_review' => true,
            'log_count' => 7,
            'catalog_number' => 'B-1300',
        ],
        [
            'id' => $this->valueB1Gestempeld->id,
            'amount' => '27.50',
            'stored_amount' => '22.50',
            'stored_review' => true,
            'log_count' => 8,
            'catalog_number' => 'B-1300',
        ],
    ];

    // -------------------------------------------------
    // Definieer alle verwachte wijzigingslogs.
    // -------------------------------------------------

    $expectedLogs = [
        [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
            'condition_id' => null,
            'old_values' => [
                'catalog_number' => 'B-100',
            ],
            'new_values' => [
                'catalog_number' => 'B-1100',
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Postfris->id,
            'condition_id' => $this->postfris->id,
            'old_values' => [
                'catalog_value' => 25.00,
                'needs_review' => false,
            ],
            'new_values' => [
                'catalog_value' => 35.75,
                'needs_review' => true,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Gestempeld->id,
            'condition_id' => $this->gestempeld->id,
            'old_values' => [
                'catalog_value' => 12.50,
                'needs_review' => false,
            ],
            'new_values' => [
                'catalog_value' => 17.50,
                'needs_review' => true,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
            'condition_id' => null,
            'old_values' => [
                'catalog_number' => 'B-1100',
            ],
            'new_values' => [
                'catalog_number' => 'B-1200',
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Postfris->id,
            'condition_id' => $this->postfris->id,
            'old_values' => [
                'catalog_value' => 35.75,
            ],
            'new_values' => [
                'catalog_value' => 45.75,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Gestempeld->id,
            'condition_id' => $this->gestempeld->id,
            'old_values' => [
                'catalog_value' => 17.50,
            ],
            'new_values' => [
                'catalog_value' => 22.50,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
            'condition_id' => null,
            'old_values' => [
                'catalog_number' => 'B-1200',
            ],
            'new_values' => [
                'catalog_number' => 'B-1300',
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Postfris->id,
            'condition_id' => $this->postfris->id,
            'old_values' => [
                'catalog_value' => 45.75,
            ],
            'new_values' => [
                'catalog_value' => 55.75,
            ],
        ],
    ];

    // -------------------------------------------------
    // Normaliseer uitsluitend decimale en booleaanse
    // waarden bij het vergelijken van wijzigingslogs.
    // -------------------------------------------------

    $normalizeLogValues = function (array $attributes): array {
        if (array_key_exists('catalog_value', $attributes)) {
            $attributes['catalog_value'] =
                (float) $attributes['catalog_value'];
        }

        if (array_key_exists('needs_review', $attributes)) {
            $attributes['needs_review'] =
                (bool) $attributes['needs_review'];
        }

        ksort($attributes);

        return $attributes;
    };

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout in route D na verwerking van routes B en C binnen dezelfde cataloguseditie';

    $listenerCalls = 0;

    $observedValueIds = [];

    $observedBatchIds = [];

    $createdLogIds = [];

    /** @var int|null $createdBatchId */
    $createdBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::updating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $expectedValueUpdates,
            $expectedLogs,
            $normalizeLogValues,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$observedBatchIds,
            &$createdBatchId,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (
                $value->stamp_catalog_entry_id
                !== $this->entryB1->id
            ) {
                return;
            }

            $listenerCalls++;

            expect($listenerCalls)
                ->toBeLessThanOrEqual(6);

            $expected = $expectedValueUpdates[
                $listenerCalls - 1
            ];

            $observedValueIds[] = $value->id;

            // -------------------------------------------------
            // Controleer iedere afzonderlijke waardewijziging.
            // -------------------------------------------------

            expect($value->id)
                ->toBe($expected['id']);

            expect($value->catalog_value)
                ->toBe($expected['amount']);

            expect($value->currency)
                ->toBe('USD');

            expect($value->isDirty('catalog_value'))
                ->toBeTrue();

            $storedValue = StampCatalogValue::query()
                ->findOrFail($value->id);

            expect($storedValue->catalog_value)
                ->toBe($expected['stored_amount']);

            expect($storedValue->needs_review)
                ->toBe($expected['stored_review']);

            expect($storedValue->currency)
                ->toBe('USD');

            expect($this->entryB1->fresh()->catalog_number)
                ->toBe($expected['catalog_number']);

            // -------------------------------------------------
            // Controleer bij iedere wijziging de actieve batch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($activeBatch->catalog_edition_id)
                ->toBe($this->editionB1->id);

            if ($createdBatchId === null) {
                $createdBatchId = $activeBatch->id;
            }

            expect($activeBatch->id)
                ->toBe($createdBatchId);

            $observedBatchIds[] = $activeBatch->id;

            $this->assertDatabaseCount(
                'catalog_change_batches',
                1
            );

            $this->assertDatabaseCount(
                'catalog_change_logs',
                $expected['log_count']
            );

            expect($activeBatch->logs()->count())
                ->toBe($expected['log_count']);

            // -------------------------------------------------
            // Laat de eerste vijf wijzigingen normaal verlopen.
            // -------------------------------------------------

            if ($listenerCalls < 6) {
                return;
            }

            // -------------------------------------------------
            // De zesde wijziging behoort tot route D.
            // -------------------------------------------------

            expect($value->id)
                ->toBe($this->valueB1Gestempeld->id);

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            expect($value->catalog_value)
                ->toBe('27.50');

            expect($storedValue->catalog_value)
                ->toBe('22.50');

            expect($storedValue->needs_review)
                ->toBeTrue();

            expect($observedBatchIds)
                ->toBe(
                    array_fill(0, 6, $createdBatchId)
                );

            // -------------------------------------------------
            // Bewijs dat de algemene postzegelgegevens
            // daadwerkelijk zijn gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Controleer beide vertalingen.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Bewijs dat de themakoppeling is gewijzigd.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            // -------------------------------------------------
            // Controleer de uiteindelijke catalogusvermelding
            // vlak voordat de uitzondering wordt veroorzaakt.
            // -------------------------------------------------

            $changedEntry = StampCatalogEntry::query()
                ->findOrFail($this->entryB1->id);

            expect($changedEntry->catalog_number)
                ->toBe('B-1300');

            expect($changedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($changedEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($changedEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryB1->id]['updated_at']
                );

            expect($changedEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_edition_id' => $this->editionB1->id,
                'catalog_number' => 'B-1300',
            ]);

            // -------------------------------------------------
            // Controleer de daadwerkelijk opgeslagen waarden.
            // -------------------------------------------------

            $expectedStoredValues = [
                $this->valueB1Postfris->id => [
                    'condition_id' => $this->postfris->id,
                    'amount' => '55.75',
                ],
                $this->valueB1Gestempeld->id => [
                    'condition_id' => $this->gestempeld->id,
                    'amount' => '22.50',
                ],
            ];

            foreach ($expectedStoredValues as $valueId => $expectedValue) {
                $changedValue = StampCatalogValue::query()
                    ->findOrFail($valueId);

                expect($changedValue->stamp_catalog_entry_id)
                    ->toBe($this->entryB1->id);

                expect($changedValue->condition_id)
                    ->toBe($expectedValue['condition_id']);

                expect($changedValue->catalog_value)
                    ->toBe($expectedValue['amount']);

                expect($changedValue->currency)
                    ->toBe('USD');

                expect($changedValue->needs_review)
                    ->toBeTrue();

                expect($changedValue->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalValues[$valueId]['updated_at']
                    );

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $valueId,
                    'stamp_catalog_entry_id' => $this->entryB1->id,
                    'condition_id' => $expectedValue['condition_id'],
                    'catalog_value' => (float) $expectedValue['amount'],
                    'currency' => 'USD',
                    'needs_review' => true,
                ]);
            }

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => 27.50,
            ]);

            // -------------------------------------------------
            // Controleer alle overige catalogusgegevens.
            // -------------------------------------------------

            foreach ($originalEntries as $entryId => $attributes) {
                if ($entryId === $this->entryB1->id) {
                    continue;
                }

                expect(
                    StampCatalogEntry::query()
                        ->findOrFail($entryId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            foreach ($originalValues as $valueId => $attributes) {
                if (in_array(
                    $valueId,
                    [
                        $this->valueB1Postfris->id,
                        $this->valueB1Gestempeld->id,
                    ],
                    true
                )) {
                    continue;
                }

                expect(
                    StampCatalogValue::query()
                        ->findOrFail($valueId)
                        ->getAttributes()
                )->toBe($attributes);
            }

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(2);

            // -------------------------------------------------
            // Controleer de enige opgeslagen geschiedenisbatch.
            // -------------------------------------------------

            $storedBatch = CatalogChangeBatch::query()
                ->sole();

            expect($storedBatch->id)
                ->toBe($createdBatchId);

            expect($storedBatch->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($storedBatch->user_id)
                ->toBe($this->user->id);

            expect($storedBatch->user_name)
                ->toBe($this->user->name);

            expect($storedBatch->operation)
                ->toBe('stamp_update');

            expect($storedBatch->description)
                ->toBe(
                    'Catalogusgegevens bij het wijzigen van een postzegel'
                );

            expect($storedBatch->catalog_name)
                ->toBe($this->catalogB->name);

            expect($storedBatch->catalog_edition_name)
                ->toBe($this->editionB1->edition_name);

            expect($storedBatch->catalogEdition->id)
                ->toBe($this->editionB1->id);

            expect($storedBatch->user->id)
                ->toBe($this->user->id);

            $this->assertDatabaseHas('catalog_change_batches', [
                'id' => $createdBatchId,
                'catalog_edition_id' => $this->editionB1->id,
                'user_id' => $this->user->id,
                'operation' => 'stamp_update',
                'catalog_name' => $this->catalogB->name,
                'catalog_edition_name' => $this->editionB1->edition_name,
            ]);

            // -------------------------------------------------
            // Bewijs dat alle acht wijzigingslogs bestaan.
            // -------------------------------------------------

            $logs = CatalogChangeLog::query()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(8);

            expect($storedBatch->logs()->count())
                ->toBe(8);

            expect($logs->pluck('action')->all())
                ->toBe(array_fill(0, 8, 'updated'));

            expect($logs->pluck('catalog_change_batch_id')->all())
                ->toBe(
                    array_fill(0, 8, $createdBatchId)
                );

            // -------------------------------------------------
            // Controleer de volledige chronologische keten.
            // -------------------------------------------------

            foreach ($logs as $index => $log) {
                $expectedLog = $expectedLogs[$index];

                expect($log->record_type)
                    ->toBe($expectedLog['record_type']);

                expect($log->record_id)
                    ->toBe($expectedLog['record_id']);

                expect($log->condition_id)
                    ->toBe($expectedLog['condition_id']);

                expect($log->catalog_change_batch_id)
                    ->toBe($createdBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($this->editionB1->id);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('updated');

                expect($log->old_values)
                    ->toBeArray();

                expect($log->new_values)
                    ->toBeArray();

                expect(
                    $normalizeLogValues($log->old_values)
                )->toBe(
                    $normalizeLogValues($expectedLog['old_values'])
                );

                expect(
                    $normalizeLogValues($log->new_values)
                )->toBe(
                    $normalizeLogValues($expectedLog['new_values'])
                );

                expect($log->old_values)
                    ->not->toHaveKey('updated_at');

                expect($log->new_values)
                    ->not->toHaveKey('updated_at');

                expect($log->batch->id)
                    ->toBe($createdBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($this->editionB1->id);

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

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $createdBatchId,
                    'catalog_edition_id' => $this->editionB1->id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $expectedLog['condition_id'],
                    'record_type' => $expectedLog['record_type'],
                    'record_id' => $expectedLog['record_id'],
                    'action' => 'updated',
                ]);
            }

            // -------------------------------------------------
            // Controleer het aantal logs per record.
            // -------------------------------------------------

            expect(
                $logs
                    ->where('record_type', 'stamp_catalog_entry')
                    ->where('record_id', $this->entryB1->id)
                    ->count()
            )->toBe(3);

            expect(
                $logs
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $this->valueB1Postfris->id)
                    ->count()
            )->toBe(3);

            expect(
                $logs
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $this->valueB1Gestempeld->id)
                    ->count()
            )->toBe(2);

            // -------------------------------------------------
            // Niet-ingediende catalogusedities hebben geen
            // geschiedenisbatch of wijzigingslogs.
            // -------------------------------------------------

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

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de gecombineerde aanvraag.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat alle drie de routes zijn bereikt.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(6);

        expect($observedValueIds)
            ->toBe(
                array_column($expectedValueUpdates, 'id')
            );

        expect($createdBatchId)
            ->toBeInt();

        expect($observedBatchIds)
            ->toBe(
                array_fill(0, 6, $createdBatchId)
            );

        expect(array_unique($observedBatchIds))
            ->toHaveCount(1);

        expect($createdLogIds)
            ->toHaveCount(8);

        expect(array_unique($createdLogIds))
            ->toHaveCount(8);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $restoredEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($restoredEntry->getAttributes())
                ->toBe($attributes);

            expect($restoredEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($restoredEntry->values()->count())
                ->toBe(2);
        }

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        $this->assertDatabaseHas('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-100',
        ]);

        foreach ([
            'B-1100',
            'B-1200',
            'B-1300',
        ] as $number) {
            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_number' => $number,
            ]);
        }

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($restoredValue->getAttributes())
                ->toBe($attributes);

            expect($restoredValue->needs_review)
                ->toBeFalse();

            expect($restoredValue->catalogEntry->id)
                ->toBe($attributes['stamp_catalog_entry_id']);

            expect($restoredValue->condition->id)
                ->toBe($attributes['condition_id']);

            expect($restoredValue->getRawOriginal('created_at'))
                ->toBe($attributes['created_at']);

            expect($restoredValue->getRawOriginal('updated_at'))
                ->toBe($attributes['updated_at']);
        }

        expect($this->valueB1Postfris->fresh()->catalog_value)
            ->toBe('25.00');

        expect($this->valueB1Gestempeld->fresh()->catalog_value)
            ->toBe('12.50');

        expect($this->valueB1Postfris->fresh()->currency)
            ->toBe('USD');

        expect($this->valueB1Gestempeld->fresh()->currency)
            ->toBe('USD');

        foreach ($expectedValueUpdates as $expectedUpdate) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $expectedUpdate['id'],
                'catalog_value' => (float) $expectedUpdate['amount'],
            ]);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van de geschiedenisbatch.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_batches', [
            'id' => $createdBatchId,
        ]);

        expect(
            CatalogChangeBatch::query()->find($createdBatchId)
        )->toBeNull();

        // -------------------------------------------------
        // Controleer de rollback van alle acht wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'catalog_change_batch_id' => $createdBatchId,
        ]);

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait gecombineerde routes volledig terug bij een fout in route D binnen verschillende catalogusedities', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback G2',
        'slug' => 'oorspronkelijk-thema-rollback-g2',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback G2',
        'slug' => 'vervangend-thema-rollback-g2',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam G2',
            'description' => 'Oorspronkelijke Nederlandse beschrijving G2',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name G2',
            'description' => 'Original English description G2',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    // -------------------------------------------------
    // Stel geldige algemene formuliergegevens samen.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->country->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest G2';

    $payload['printing_method'] = 'Gewijzigde drukmethode G2';

    $payload['notes'] = 'Rollback gecombineerde routes binnen verschillende edities';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam G2',
        'description' => 'Gewijzigde Nederlandse beschrijving G2',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    // -------------------------------------------------
    // Route B: cataloguseditie A1.
    // -------------------------------------------------

    $payload['catalog_edition_id'] = $this->editionA1->id;

    $payload['catalog_number'] = 'A-2100';

    $payload['catalog_values'] = [
        'postfris' => '35.75',
        'gestempeld' => '17.50',
    ];

    // -------------------------------------------------
    // Route C: cataloguseditie A2.
    // -------------------------------------------------

    $payload['catalog_entries'] = [
        $this->entryA2->id => [
            'catalog_number' => 'A-2200',
            'values' => [
                'postfris' => '45.75',
                'gestempeld' => '22.50',
            ],
        ],
    ];

    // -------------------------------------------------
    // Route D: cataloguseditie B1.
    // -------------------------------------------------

    $payload['catalog_editions'] = [
        $this->editionB1->id => [
            'catalog_number' => 'B-2300',
            'values' => [
                'postfris' => '55.75',
                'gestempeld' => '27.50',
            ],
        ],
    ];

    // -------------------------------------------------
    // Definieer de verwachte catalogusnummerwijzigingen.
    // -------------------------------------------------

    $expectedEntries = [
        $this->entryA1->id => [
            'edition_id' => $this->editionA1->id,
            'old_number' => 'A-100',
            'new_number' => 'A-2100',
        ],
        $this->entryA2->id => [
            'edition_id' => $this->editionA2->id,
            'old_number' => 'A-200',
            'new_number' => 'A-2200',
        ],
        $this->entryB1->id => [
            'edition_id' => $this->editionB1->id,
            'old_number' => 'B-100',
            'new_number' => 'B-2300',
        ],
    ];

    // -------------------------------------------------
    // Definieer de verwachte waardewijzigingen.
    // De zesde wijziging wordt niet opgeslagen.
    // -------------------------------------------------

    $expectedUpdates = [
        [
            'id' => $this->valueA1Postfris->id,
            'entry_id' => $this->entryA1->id,
            'edition_id' => $this->editionA1->id,
            'condition_id' => $this->postfris->id,
            'old_value' => '15.00',
            'new_value' => '35.75',
            'currency' => 'EUR',
            'batch_count' => 1,
            'batch_log_count' => 1,
            'total_log_count' => 1,
        ],
        [
            'id' => $this->valueA1Gestempeld->id,
            'entry_id' => $this->entryA1->id,
            'edition_id' => $this->editionA1->id,
            'condition_id' => $this->gestempeld->id,
            'old_value' => '7.50',
            'new_value' => '17.50',
            'currency' => 'EUR',
            'batch_count' => 1,
            'batch_log_count' => 2,
            'total_log_count' => 2,
        ],
        [
            'id' => $this->valueA2Postfris->id,
            'entry_id' => $this->entryA2->id,
            'edition_id' => $this->editionA2->id,
            'condition_id' => $this->postfris->id,
            'old_value' => '20.00',
            'new_value' => '45.75',
            'currency' => 'EUR',
            'batch_count' => 2,
            'batch_log_count' => 1,
            'total_log_count' => 4,
        ],
        [
            'id' => $this->valueA2Gestempeld->id,
            'entry_id' => $this->entryA2->id,
            'edition_id' => $this->editionA2->id,
            'condition_id' => $this->gestempeld->id,
            'old_value' => '10.00',
            'new_value' => '22.50',
            'currency' => 'EUR',
            'batch_count' => 2,
            'batch_log_count' => 2,
            'total_log_count' => 5,
        ],
        [
            'id' => $this->valueB1Postfris->id,
            'entry_id' => $this->entryB1->id,
            'edition_id' => $this->editionB1->id,
            'condition_id' => $this->postfris->id,
            'old_value' => '25.00',
            'new_value' => '55.75',
            'currency' => 'USD',
            'batch_count' => 3,
            'batch_log_count' => 1,
            'total_log_count' => 7,
        ],
        [
            'id' => $this->valueB1Gestempeld->id,
            'entry_id' => $this->entryB1->id,
            'edition_id' => $this->editionB1->id,
            'condition_id' => $this->gestempeld->id,
            'old_value' => '12.50',
            'new_value' => '27.50',
            'currency' => 'USD',
            'batch_count' => 3,
            'batch_log_count' => 2,
            'total_log_count' => 8,
        ],
    ];

    $expectedStoredUpdates = array_slice(
        $expectedUpdates,
        0,
        5
    );

    // -------------------------------------------------
    // Definieer de verwachte geschiedenisbatches.
    // -------------------------------------------------

    $expectedBatches = [
        $this->editionA1->id => [
            'edition' => $this->editionA1,
            'catalog' => $this->catalogA,
            'log_count' => 3,
        ],
        $this->editionA2->id => [
            'edition' => $this->editionA2,
            'catalog' => $this->catalogA,
            'log_count' => 3,
        ],
        $this->editionB1->id => [
            'edition' => $this->editionB1,
            'catalog' => $this->catalogB,
            'log_count' => 2,
        ],
    ];

    // -------------------------------------------------
    // Definieer de chronologische wijzigingslogs.
    // -------------------------------------------------

    $expectedLogs = [];

    foreach ($expectedEntries as $entryId => $entryData) {
        $expectedLogs[] = [
            'edition_id' => $entryData['edition_id'],
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $entryId,
            'condition_id' => null,
            'old_values' => [
                'catalog_number' => $entryData['old_number'],
            ],
            'new_values' => [
                'catalog_number' => $entryData['new_number'],
            ],
        ];

        foreach ($expectedStoredUpdates as $update) {
            if ($update['entry_id'] !== $entryId) {
                continue;
            }

            $expectedLogs[] = [
                'edition_id' => $update['edition_id'],
                'record_type' => 'stamp_catalog_value',
                'record_id' => $update['id'],
                'condition_id' => $update['condition_id'],
                'old_values' => [
                    'catalog_value' => (float) $update['old_value'],
                    'needs_review' => false,
                ],
                'new_values' => [
                    'catalog_value' => (float) $update['new_value'],
                    'needs_review' => true,
                ],
            ];
        }
    }

    expect($expectedLogs)
        ->toHaveCount(8);

    // -------------------------------------------------
    // Normaliseer decimale en booleaanse logwaarden.
    // -------------------------------------------------

    $normalizeLogValues = function (array $attributes): array {
        if (array_key_exists('catalog_value', $attributes)) {
            $attributes['catalog_value'] =
                (float) $attributes['catalog_value'];
        }

        if (array_key_exists('needs_review', $attributes)) {
            $attributes['needs_review'] =
                (bool) $attributes['needs_review'];
        }

        ksort($attributes);

        return $attributes;
    };

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout in route D na verwerking van routes B en C binnen verschillende catalogusedities';

    $listenerCalls = 0;

    $observedValueIds = [];

    $observedBatchIds = [];

    $batchIdsByEdition = [];

    $createdLogIds = [];

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::updating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $expectedEntries,
            $expectedUpdates,
            $expectedStoredUpdates,
            $expectedBatches,
            $expectedLogs,
            $normalizeLogValues,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$observedBatchIds,
            &$batchIdsByEdition,
            &$createdLogIds,
            &$capturedHistory
        ) {
            if (! in_array(
                $value->stamp_catalog_entry_id,
                array_keys($expectedEntries),
                true
            )) {
                return;
            }

            $listenerCalls++;

            expect($listenerCalls)
                ->toBeLessThanOrEqual(6);

            $expected = $expectedUpdates[$listenerCalls - 1];

            $observedValueIds[] = $value->id;

            // -------------------------------------------------
            // Controleer de huidige waardewijziging.
            // -------------------------------------------------

            expect($value->id)
                ->toBe($expected['id']);

            expect($value->stamp_catalog_entry_id)
                ->toBe($expected['entry_id']);

            expect($value->condition_id)
                ->toBe($expected['condition_id']);

            expect($value->catalog_value)
                ->toBe($expected['new_value']);

            expect($value->currency)
                ->toBe($expected['currency']);

            expect($value->isDirty('catalog_value'))
                ->toBeTrue();

            $storedValue = StampCatalogValue::query()
                ->findOrFail($value->id);

            expect($storedValue->catalog_value)
                ->toBe($expected['old_value']);

            expect($storedValue->needs_review)
                ->toBeFalse();

            expect($storedValue->getAttributes())
                ->toBe($originalValues[$value->id]);

            expect($storedValue->currency)
                ->toBe($expected['currency']);

            $storedEntry = StampCatalogEntry::query()
                ->findOrFail($expected['entry_id']);

            expect($storedEntry->catalog_number)
                ->toBe(
                    $expectedEntries[
                        $expected['entry_id']
                    ]['new_number']
                );

            // -------------------------------------------------
            // Controleer de actieve geschiedenisbatch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($activeBatch->catalog_edition_id)
                ->toBe($expected['edition_id']);

            if (! isset(
                $batchIdsByEdition[$expected['edition_id']]
            )) {
                $batchIdsByEdition[$expected['edition_id']] =
                    $activeBatch->id;
            }

            expect($activeBatch->id)
                ->toBe(
                    $batchIdsByEdition[$expected['edition_id']]
                );

            $observedBatchIds[] = $activeBatch->id;

            $this->assertDatabaseCount(
                'catalog_change_batches',
                $expected['batch_count']
            );

            $this->assertDatabaseCount(
                'catalog_change_logs',
                $expected['total_log_count']
            );

            expect($activeBatch->logs()->count())
                ->toBe($expected['batch_log_count']);

            // -------------------------------------------------
            // Laat de eerste vijf wijzigingen normaal verlopen.
            // -------------------------------------------------

            if ($listenerCalls < 6) {
                return;
            }

            // -------------------------------------------------
            // De zesde wijziging veroorzaakt de uitzondering.
            // -------------------------------------------------

            expect($value->id)
                ->toBe($this->valueB1Gestempeld->id);

            expect($value->catalog_value)
                ->toBe('27.50');

            expect($storedValue->catalog_value)
                ->toBe('12.50');

            expect($storedValue->needs_review)
                ->toBeFalse();

            expect($batchIdsByEdition)
                ->toHaveCount(3);

            expect(array_unique($batchIdsByEdition))
                ->toHaveCount(3);

            expect($observedBatchIds)
                ->toBe([
                    $batchIdsByEdition[$this->editionA1->id],
                    $batchIdsByEdition[$this->editionA1->id],
                    $batchIdsByEdition[$this->editionA2->id],
                    $batchIdsByEdition[$this->editionA2->id],
                    $batchIdsByEdition[$this->editionB1->id],
                    $batchIdsByEdition[$this->editionB1->id],
                ]);

            // -------------------------------------------------
            // Bewijs dat de postzegel daadwerkelijk is gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->country->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->country->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Controleer beide vertalingen.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Controleer de gewijzigde themakoppeling.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            // -------------------------------------------------
            // Bewijs dat alle drie catalogusnummers zijn gewijzigd.
            // -------------------------------------------------

            foreach ($expectedEntries as $entryId => $entryData) {
                $changedEntry = StampCatalogEntry::query()
                    ->findOrFail($entryId);

                expect($changedEntry->stamp_id)
                    ->toBe($this->stamp->id);

                expect($changedEntry->catalog_edition_id)
                    ->toBe($entryData['edition_id']);

                expect($changedEntry->catalog_number)
                    ->toBe($entryData['new_number']);

                expect($changedEntry->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalEntries[$entryId]['updated_at']
                    );

                expect($changedEntry->values()->count())
                    ->toBe(2);

                $this->assertDatabaseHas('stamp_catalog_entries', [
                    'id' => $entryId,
                    'stamp_id' => $this->stamp->id,
                    'catalog_edition_id' => $entryData['edition_id'],
                    'catalog_number' => $entryData['new_number'],
                ]);

                $this->assertDatabaseMissing('stamp_catalog_entries', [
                    'id' => $entryId,
                    'catalog_number' => $entryData['old_number'],
                ]);
            }

            // -------------------------------------------------
            // Bewijs dat vijf cataloguswaarden zijn gewijzigd.
            // -------------------------------------------------

            foreach ($expectedStoredUpdates as $update) {
                $changedValue = StampCatalogValue::query()
                    ->findOrFail($update['id']);

                expect($changedValue->stamp_catalog_entry_id)
                    ->toBe($update['entry_id']);

                expect($changedValue->condition_id)
                    ->toBe($update['condition_id']);

                expect($changedValue->catalog_value)
                    ->toBe($update['new_value']);

                expect($changedValue->currency)
                    ->toBe($update['currency']);

                expect($changedValue->needs_review)
                    ->toBeTrue();

                expect($changedValue->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalValues[$update['id']]['updated_at']
                    );

                expect($changedValue->catalogEntry->id)
                    ->toBe($update['entry_id']);

                expect($changedValue->condition->id)
                    ->toBe($update['condition_id']);

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $update['id'],
                    'stamp_catalog_entry_id' => $update['entry_id'],
                    'condition_id' => $update['condition_id'],
                    'catalog_value' => (float) $update['new_value'],
                    'currency' => $update['currency'],
                    'needs_review' => true,
                ]);

                $this->assertDatabaseMissing('stamp_catalog_values', [
                    'id' => $update['id'],
                    'catalog_value' => (float) $update['old_value'],
                ]);
            }

            // -------------------------------------------------
            // De zesde cataloguswaarde is nog ongewijzigd.
            // -------------------------------------------------

            expect(
                $this->valueB1Gestempeld
                    ->fresh()
                    ->getAttributes()
            )->toBe(
                $originalValues[$this->valueB1Gestempeld->id]
            );

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => 27.50,
            ]);

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 3);

            $this->assertDatabaseCount('stamp_catalog_values', 6);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(3);

            expect($changedStamp->catalogValues()->count())
                ->toBe(6);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(5);

            // -------------------------------------------------
            // Bewijs dat drie geschiedenisbatches bestaan.
            // -------------------------------------------------

            $batches = CatalogChangeBatch::query()
                ->orderBy('id')
                ->get();

            expect($batches->count())
                ->toBe(3);

            expect($batches->pluck('catalog_edition_id')->all())
                ->toBe([
                    $this->editionA1->id,
                    $this->editionA2->id,
                    $this->editionB1->id,
                ]);

            expect($batches->pluck('id')->all())
                ->toBe(array_values($batchIdsByEdition));

            expect($activeBatch->id)
                ->toBe(
                    $batchIdsByEdition[$this->editionB1->id]
                );

            // -------------------------------------------------
            // Controleer alle afzonderlijke batches.
            // -------------------------------------------------

            foreach ($expectedBatches as $editionId => $batchData) {
                $batch = $batches
                    ->where('catalog_edition_id', $editionId)
                    ->sole();

                $edition = $batchData['edition'];

                $catalog = $batchData['catalog'];

                expect($batch->id)
                    ->toBe($batchIdsByEdition[$editionId]);

                expect($batch->catalog_edition_id)
                    ->toBe($editionId);

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
                    ->toBe($editionId);

                expect($batch->catalogEdition->catalog->id)
                    ->toBe($catalog->id);

                expect($batch->user->id)
                    ->toBe($this->user->id);

                expect($batch->logs()->count())
                    ->toBe($batchData['log_count']);

                $this->assertDatabaseHas('catalog_change_batches', [
                    'id' => $batch->id,
                    'catalog_edition_id' => $editionId,
                    'user_id' => $this->user->id,
                    'user_name' => $this->user->name,
                    'operation' => 'stamp_update',
                    'catalog_name' => $catalog->name,
                    'catalog_edition_name' => $edition->edition_name,
                ]);
            }

            // -------------------------------------------------
            // Bewijs dat alle acht wijzigingslogs bestaan.
            // -------------------------------------------------

            $logs = CatalogChangeLog::query()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(8);

            expect($logs->pluck('action')->all())
                ->toBe(array_fill(0, 8, 'updated'));

            expect($logs->pluck('catalog_change_batch_id')->all())
                ->toBe([
                    $batchIdsByEdition[$this->editionA1->id],
                    $batchIdsByEdition[$this->editionA1->id],
                    $batchIdsByEdition[$this->editionA1->id],
                    $batchIdsByEdition[$this->editionA2->id],
                    $batchIdsByEdition[$this->editionA2->id],
                    $batchIdsByEdition[$this->editionA2->id],
                    $batchIdsByEdition[$this->editionB1->id],
                    $batchIdsByEdition[$this->editionB1->id],
                ]);

            // -------------------------------------------------
            // Controleer de volledige chronologische keten.
            // -------------------------------------------------

            foreach ($logs as $index => $log) {
                $expectedLog = $expectedLogs[$index];

                $expectedBatchId =
                    $batchIdsByEdition[$expectedLog['edition_id']];

                expect($log->record_type)
                    ->toBe($expectedLog['record_type']);

                expect($log->record_id)
                    ->toBe($expectedLog['record_id']);

                expect($log->condition_id)
                    ->toBe($expectedLog['condition_id']);

                expect($log->catalog_change_batch_id)
                    ->toBe($expectedBatchId);

                expect($log->catalog_edition_id)
                    ->toBe($expectedLog['edition_id']);

                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->action)
                    ->toBe('updated');

                expect($log->old_values)
                    ->toBeArray();

                expect($log->new_values)
                    ->toBeArray();

                expect(
                    $normalizeLogValues($log->old_values)
                )->toBe(
                    $normalizeLogValues($expectedLog['old_values'])
                );

                expect(
                    $normalizeLogValues($log->new_values)
                )->toBe(
                    $normalizeLogValues($expectedLog['new_values'])
                );

                expect($log->old_values)
                    ->not->toHaveKey('updated_at');

                expect($log->new_values)
                    ->not->toHaveKey('updated_at');

                expect($log->batch->id)
                    ->toBe($expectedBatchId);

                expect($log->catalogEdition->id)
                    ->toBe($expectedLog['edition_id']);

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

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $expectedBatchId,
                    'catalog_edition_id' => $expectedLog['edition_id'],
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $expectedLog['condition_id'],
                    'record_type' => $expectedLog['record_type'],
                    'record_id' => $expectedLog['record_id'],
                    'action' => 'updated',
                ]);
            }

            // -------------------------------------------------
            // Voor de zesde waarde bestaat nog geen log.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Gestempeld->id,
            ]);

            // -------------------------------------------------
            // Niet-ingediende cataloguseditie C1 blijft ongemoeid.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $this->editionC1->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $this->editionC1->id,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionC1->id,
            ]);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de gecombineerde aanvraag.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat alle drie de routes zijn bereikt.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(6);

        expect($observedValueIds)
            ->toBe(
                array_column($expectedUpdates, 'id')
            );

        expect($batchIdsByEdition)
            ->toHaveCount(3);

        expect(array_unique($batchIdsByEdition))
            ->toHaveCount(3);

        expect($observedBatchIds)
            ->toHaveCount(6);

        expect(array_unique($observedBatchIds))
            ->toHaveCount(3);

        expect($createdLogIds)
            ->toHaveCount(8);

        expect(array_unique($createdLogIds))
            ->toHaveCount(8);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $restoredEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($restoredEntry->getAttributes())
                ->toBe($attributes);

            expect($restoredEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($restoredEntry->values()->count())
                ->toBe(2);
        }

        foreach ($expectedEntries as $entryId => $entryData) {
            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $entryId,
                'catalog_edition_id' => $entryData['edition_id'],
                'catalog_number' => $entryData['old_number'],
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $entryId,
                'catalog_number' => $entryData['new_number'],
            ]);
        }

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($restoredValue->getAttributes())
                ->toBe($attributes);

            expect($restoredValue->needs_review)
                ->toBeFalse();

            expect($restoredValue->catalogEntry->id)
                ->toBe($attributes['stamp_catalog_entry_id']);

            expect($restoredValue->condition->id)
                ->toBe($attributes['condition_id']);

            expect($restoredValue->getRawOriginal('created_at'))
                ->toBe($attributes['created_at']);

            expect($restoredValue->getRawOriginal('updated_at'))
                ->toBe($attributes['updated_at']);
        }

        foreach ($expectedUpdates as $update) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($update['id']);

            expect($restoredValue->catalog_value)
                ->toBe($update['old_value']);

            expect($restoredValue->currency)
                ->toBe($update['currency']);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $update['id'],
                'stamp_catalog_entry_id' => $update['entry_id'],
                'condition_id' => $update['condition_id'],
                'catalog_value' => (float) $update['old_value'],
                'currency' => $update['currency'],
                'needs_review' => false,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $update['id'],
                'catalog_value' => (float) $update['new_value'],
            ]);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van alle drie de batches.
        // -------------------------------------------------

        foreach ($batchIdsByEdition as $editionId => $batchId) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'id' => $batchId,
            ]);

            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $editionId,
            ]);

            expect(
                CatalogChangeBatch::query()->find($batchId)
            )->toBeNull();

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $batchId,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van alle acht wijzigingslogs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

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

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // Inclusief ID's, attributen, timestamps en relaties.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait gecombineerde routes volledig terug bij een fout in route B na verwijderingen door route A', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback G3',
        'slug' => 'oorspronkelijk-thema-rollback-g3',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback G3',
        'slug' => 'vervangend-thema-rollback-g3',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam G3',
            'description' => 'Oorspronkelijke Nederlandse beschrijving G3',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name G3',
            'description' => 'Original English description G3',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Definieer de te verwijderen catalogusgegevens.
    // -------------------------------------------------

    $deletedEntries = [
        $this->entryA1->id => [
            'edition_id' => $this->editionA1->id,
            'value_ids' => [
                $this->valueA1Postfris->id,
                $this->valueA1Gestempeld->id,
            ],
        ],
        $this->entryA2->id => [
            'edition_id' => $this->editionA2->id,
            'value_ids' => [
                $this->valueA2Postfris->id,
                $this->valueA2Gestempeld->id,
            ],
        ],
    ];

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    expect($this->stamp->fresh()->country_id)
        ->toBe($this->country->id);

    expect($this->otherCountry->id)
        ->not->toBe($this->country->id);

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeFalse();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    $this->assertDatabaseCount('stamps', 1);

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

    // -------------------------------------------------
    // Stel de algemene formuliergegevens samen.
    //
    // Route A wordt automatisch uitgevoerd vanwege
    // de wijziging van het land.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->otherCountry->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest G3';

    $payload['printing_method'] = 'Gewijzigde drukmethode G3';

    $payload['notes'] = 'Rollback route A gevolgd door route B';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam G3',
        'description' => 'Gewijzigde Nederlandse beschrijving G3',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    // -------------------------------------------------
    // Route B: wijzig cataloguseditie B1.
    // -------------------------------------------------

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-3100';

    $payload['catalog_values'] = [
        'postfris' => '65.75',
        'gestempeld' => '32.50',
    ];

    // -------------------------------------------------
    // Definieer de verwachte geschiedenisbatches.
    // -------------------------------------------------

    $expectedBatches = [
        $this->editionA1->id => [
            'edition' => $this->editionA1,
            'catalog' => $this->catalogA,
            'log_count' => 3,
        ],
        $this->editionA2->id => [
            'edition' => $this->editionA2,
            'catalog' => $this->catalogA,
            'log_count' => 3,
        ],
        $this->editionB1->id => [
            'edition' => $this->editionB1,
            'catalog' => $this->catalogB,
            'log_count' => 2,
        ],
    ];

    // -------------------------------------------------
    // Normaliseer decimale en booleaanse logwaarden.
    // -------------------------------------------------

    $normalizeLogValues = function (array $attributes): array {
        if (array_key_exists('catalog_value', $attributes)) {
            $attributes['catalog_value'] =
                (float) $attributes['catalog_value'];
        }

        if (array_key_exists('needs_review', $attributes)) {
            $attributes['needs_review'] =
                (bool) $attributes['needs_review'];
        }

        ksort($attributes);

        return $attributes;
    };

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout in route B na verwijderingen door route A';

    $listenerCalls = 0;

    $observedValueIds = [];

    $batchIdsByEdition = [];

    $createdLogIds = [];

    /** @var int|null $activeBatchId */
    $activeBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::updating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $deletedEntries,
            $expectedBatches,
            $normalizeLogValues,
            $exceptionMessage,
            &$listenerCalls,
            &$observedValueIds,
            &$batchIdsByEdition,
            &$createdLogIds,
            &$activeBatchId,
            &$capturedHistory
        ) {
            if (
                $value->stamp_catalog_entry_id
                !== $this->entryB1->id
            ) {
                return;
            }

            $listenerCalls++;

            $observedValueIds[] = $value->id;

            expect($listenerCalls)
                ->toBeLessThanOrEqual(2);

            // -------------------------------------------------
            // Controleer de actieve geschiedeniscontext.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($activeBatch->catalog_edition_id)
                ->toBe($this->editionB1->id);

            if ($activeBatchId === null) {
                $activeBatchId = $activeBatch->id;
            }

            expect($activeBatch->id)
                ->toBe($activeBatchId);

            // -------------------------------------------------
            // Bewijs dat route A volledig is uitgevoerd.
            // -------------------------------------------------

            $this->assertDatabaseCount('stamp_catalog_entries', 1);

            $this->assertDatabaseCount('stamp_catalog_values', 2);

            foreach ($deletedEntries as $entryId => $entryData) {
                $this->assertDatabaseMissing('stamp_catalog_entries', [
                    'id' => $entryId,
                ]);

                expect(
                    StampCatalogEntry::query()->find($entryId)
                )->toBeNull();

                foreach ($entryData['value_ids'] as $valueId) {
                    $this->assertDatabaseMissing('stamp_catalog_values', [
                        'id' => $valueId,
                    ]);

                    expect(
                        StampCatalogValue::query()->find($valueId)
                    )->toBeNull();
                }
            }

            $this->assertDatabaseCount('catalog_change_batches', 3);

            expect(
                CatalogChangeLog::query()
                    ->where('action', 'deleted')
                    ->count()
            )->toBe(6);

            // -------------------------------------------------
            // Laat de eerste waardewijziging normaal verlopen.
            // -------------------------------------------------

            if ($listenerCalls === 1) {
                expect($value->id)
                    ->toBe($this->valueB1Postfris->id);

                expect($value->catalog_value)
                    ->toBe('65.75');

                expect($value->isDirty('catalog_value'))
                    ->toBeTrue();

                expect(
                    $this->valueB1Postfris
                        ->fresh()
                        ->getAttributes()
                )->toBe(
                    $originalValues[$this->valueB1Postfris->id]
                );

                expect($this->entryB1->fresh()->catalog_number)
                    ->toBe('B-3100');

                expect($activeBatch->logs()->count())
                    ->toBe(1);

                $this->assertDatabaseCount(
                    'catalog_change_logs',
                    7
                );

                return;
            }

            // -------------------------------------------------
            // De tweede waardewijziging veroorzaakt de fout.
            // -------------------------------------------------

            expect($value->id)
                ->toBe($this->valueB1Gestempeld->id);

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            expect($value->catalog_value)
                ->toBe('32.50');

            expect($value->currency)
                ->toBe('USD');

            expect($value->isDirty('catalog_value'))
                ->toBeTrue();

            expect(
                $this->valueB1Gestempeld
                    ->fresh()
                    ->getAttributes()
            )->toBe(
                $originalValues[$this->valueB1Gestempeld->id]
            );

            // -------------------------------------------------
            // Bewijs dat het land en de algemene
            // postzegelgegevens daadwerkelijk zijn gewijzigd.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->otherCountry->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            $this->assertDatabaseHas('stamps', [
                'id' => $this->stamp->id,
                'country_id' => $this->otherCountry->id,
                'designer' => $payload['designer'],
                'printing_method' => $payload['printing_method'],
                'notes' => $payload['notes'],
            ]);

            // -------------------------------------------------
            // Controleer de gewijzigde Nederlandse vertaling.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Controleer de gewijzigde themakoppeling.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            // -------------------------------------------------
            // Controleer de overgebleven catalogusvermelding.
            // -------------------------------------------------

            $changedEntry = StampCatalogEntry::query()
                ->sole();

            expect($changedEntry->id)
                ->toBe($this->entryB1->id);

            expect($changedEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($changedEntry->catalog_edition_id)
                ->toBe($this->editionB1->id);

            expect($changedEntry->catalog_number)
                ->toBe('B-3100');

            expect($changedEntry->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryB1->id]['updated_at']
                );

            expect($changedEntry->values()->count())
                ->toBe(2);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(1);

            expect($changedStamp->catalogValues()->count())
                ->toBe(2);

            // -------------------------------------------------
            // Bewijs dat de postfrisse waarde is gewijzigd.
            // -------------------------------------------------

            $changedPostfris = StampCatalogValue::query()
                ->findOrFail($this->valueB1Postfris->id);

            expect($changedPostfris->catalog_value)
                ->toBe('65.75');

            expect($changedPostfris->currency)
                ->toBe('USD');

            expect($changedPostfris->needs_review)
                ->toBeTrue();

            expect($changedPostfris->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalValues[
                        $this->valueB1Postfris->id
                    ]['updated_at']
                );

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'stamp_catalog_entry_id' => $this->entryB1->id,
                'condition_id' => $this->postfris->id,
                'catalog_value' => 65.75,
                'currency' => 'USD',
                'needs_review' => true,
            ]);

            // -------------------------------------------------
            // De gestempelde waarde is nog ongewijzigd.
            // -------------------------------------------------

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => 12.50,
                'currency' => 'USD',
                'needs_review' => false,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => 32.50,
            ]);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(1);

            // -------------------------------------------------
            // Controleer alle drie geschiedenisbatches.
            // -------------------------------------------------

            $batches = CatalogChangeBatch::query()
                ->orderBy('id')
                ->get();

            expect($batches->count())
                ->toBe(3);

            foreach ($expectedBatches as $editionId => $batchData) {
                $batch = $batches
                    ->where('catalog_edition_id', $editionId)
                    ->sole();

                $batchIdsByEdition[$editionId] = $batch->id;

                expect($batch->catalog_edition_id)
                    ->toBe($editionId);

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
                    ->toBe($batchData['catalog']->name);

                expect($batch->catalog_edition_name)
                    ->toBe($batchData['edition']->edition_name);

                expect($batch->catalogEdition->id)
                    ->toBe($editionId);

                expect($batch->catalogEdition->catalog->id)
                    ->toBe($batchData['catalog']->id);

                expect($batch->user->id)
                    ->toBe($this->user->id);

                expect($batch->logs()->count())
                    ->toBe($batchData['log_count']);
            }

            expect(array_unique($batchIdsByEdition))
                ->toHaveCount(3);

            expect($activeBatchId)
                ->toBe(
                    $batchIdsByEdition[$this->editionB1->id]
                );

            expect($batches->last()->id)
                ->toBe($activeBatchId);

            // -------------------------------------------------
            // Controleer alle acht opgeslagen wijzigingslogs.
            // -------------------------------------------------

            $logs = CatalogChangeLog::query()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs->count())
                ->toBe(8);

            expect($logs->pluck('action')->all())
                ->toBe([
                    'deleted',
                    'deleted',
                    'deleted',
                    'deleted',
                    'deleted',
                    'deleted',
                    'updated',
                    'updated',
                ]);

            // -------------------------------------------------
            // Controleer de zes verwijderingslogs van route A.
            //
            // De volgorde tussen A1 en A2 wordt niet
            // verondersteld. Binnen iedere batch moeten
            // de waarden vóór de vermelding zijn verwijderd.
            // -------------------------------------------------

            foreach ($deletedEntries as $entryId => $entryData) {
                $editionId = $entryData['edition_id'];

                $batchId = $batchIdsByEdition[$editionId];

                $editionLogs = $logs
                    ->where('catalog_change_batch_id', $batchId)
                    ->values();

                expect($editionLogs)
                    ->toHaveCount(3);

                expect($editionLogs->pluck('record_type')->all())
                    ->toBe([
                        'stamp_catalog_value',
                        'stamp_catalog_value',
                        'stamp_catalog_entry',
                    ]);

                expect($editionLogs->pluck('action')->all())
                    ->toBe([
                        'deleted',
                        'deleted',
                        'deleted',
                    ]);

                $entryLog = $editionLogs->last();

                expect($entryLog->record_id)
                    ->toBe($entryId);

                expect($entryLog->condition_id)
                    ->toBeNull();

                expect($entryLog->old_values)
                    ->toBeArray();

                expect(
                    $normalizeLogValues($entryLog->old_values)
                )->toBe(
                    $normalizeLogValues($originalEntries[$entryId])
                );

                expect($entryLog->new_values)
                    ->toBeNull();

                foreach ($entryData['value_ids'] as $valueId) {
                    $valueLog = $editionLogs
                        ->where('record_type', 'stamp_catalog_value')
                        ->where('record_id', $valueId)
                        ->sole();

                    expect($valueLog->condition_id)
                        ->toBe(
                            $originalValues[$valueId]['condition_id']
                        );

                    expect($valueLog->old_values)
                        ->toBeArray();

                    expect(
                        $normalizeLogValues($valueLog->old_values)
                    )->toBe(
                        $normalizeLogValues($originalValues[$valueId])
                    );

                    expect($valueLog->new_values)
                        ->toBeNull();

                    expect($valueLog->id)
                        ->toBeLessThan($entryLog->id);

                    expect($valueLog->condition->id)
                        ->toBe(
                            $originalValues[$valueId]['condition_id']
                        );
                }
            }

            // -------------------------------------------------
            // Controleer de twee wijzigingslogs van route B.
            // -------------------------------------------------

            $routeBLogs = $logs
                ->where('catalog_change_batch_id', $activeBatchId)
                ->values();

            expect($routeBLogs)
                ->toHaveCount(2);

            expect($routeBLogs->pluck('id')->all())
                ->toBe(
                    $logs->slice(6)->pluck('id')->all()
                );

            $entryLog = $routeBLogs[0];

            expect($entryLog->record_type)
                ->toBe('stamp_catalog_entry');

            expect($entryLog->record_id)
                ->toBe($this->entryB1->id);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->action)
                ->toBe('updated');

            expect($entryLog->old_values)
                ->toBe([
                    'catalog_number' => 'B-100',
                ]);

            expect($entryLog->new_values)
                ->toBe([
                    'catalog_number' => 'B-3100',
                ]);

            $valueLog = $routeBLogs[1];

            expect($valueLog->record_type)
                ->toBe('stamp_catalog_value');

            expect($valueLog->record_id)
                ->toBe($this->valueB1Postfris->id);

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->action)
                ->toBe('updated');

            expect(
                $normalizeLogValues($valueLog->old_values)
            )->toBe(
                $normalizeLogValues([
                    'catalog_value' => 25.00,
                    'needs_review' => false,
                ])
            );

            expect(
                $normalizeLogValues($valueLog->new_values)
            )->toBe(
                $normalizeLogValues([
                    'catalog_value' => 65.75,
                    'needs_review' => true,
                ])
            );

            // -------------------------------------------------
            // Controleer de relaties van alle acht logs.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->catalog_change_batch_id)
                    ->toBe(
                        $batchIdsByEdition[$log->catalog_edition_id]
                    );

                expect($log->batch->id)
                    ->toBe($log->catalog_change_batch_id);

                expect($log->batch->catalog_edition_id)
                    ->toBe($log->catalog_edition_id);

                expect($log->catalogEdition->id)
                    ->toBe($log->catalog_edition_id);

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

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $log->catalog_change_batch_id,
                    'catalog_edition_id' => $log->catalog_edition_id,
                    'stamp_id' => $this->stamp->id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => $log->action,
                ]);
            }

            // -------------------------------------------------
            // De mislukte waardewijziging heeft nog geen log.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'record_type' => 'stamp_catalog_value',
                'record_id' => $this->valueB1Gestempeld->id,
            ]);

            // -------------------------------------------------
            // Niet-ingediende cataloguseditie C1 blijft ongemoeid.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $this->editionC1->id,
            ]);

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_edition_id' => $this->editionC1->id,
            ]);

            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionC1->id,
            ]);

            $this->assertDatabaseCount('catalog_change_logs', 8);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de gecombineerde aanvraag.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de foutinjectie is bereikt.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(2);

        expect($observedValueIds)
            ->toBe([
                $this->valueB1Postfris->id,
                $this->valueB1Gestempeld->id,
            ]);

        expect($batchIdsByEdition)
            ->toHaveCount(3);

        expect(array_unique($batchIdsByEdition))
            ->toHaveCount(3);

        expect($activeBatchId)
            ->toBeInt();

        expect($createdLogIds)
            ->toHaveCount(8);

        expect(array_unique($createdLogIds))
            ->toHaveCount(8);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->country_id)
            ->toBe($this->country->id);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Bewijs dat beide verwijderde catalogusvermeldingen
        // inclusief hun oorspronkelijke waarden zijn hersteld.
        // -------------------------------------------------

        foreach ($deletedEntries as $entryId => $entryData) {
            $restoredEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($restoredEntry->getAttributes())
                ->toBe($originalEntries[$entryId]);

            expect($restoredEntry->catalog_edition_id)
                ->toBe($entryData['edition_id']);

            expect($restoredEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($restoredEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $entryId,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $entryData['edition_id'],
                'catalog_number' => $originalEntries[$entryId]['catalog_number'],
            ]);

            foreach ($entryData['value_ids'] as $valueId) {
                $restoredValue = StampCatalogValue::query()
                    ->findOrFail($valueId);

                expect($restoredValue->getAttributes())
                    ->toBe($originalValues[$valueId]);

                expect($restoredValue->stamp_catalog_entry_id)
                    ->toBe($entryId);

                expect($restoredValue->catalogEntry->id)
                    ->toBe($entryId);

                expect($restoredValue->currency)
                    ->toBe('EUR');

                expect($restoredValue->needs_review)
                    ->toBeFalse();

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $valueId,
                    'stamp_catalog_entry_id' => $entryId,
                    'condition_id' => $originalValues[$valueId]['condition_id'],
                    'catalog_value' => (float) $originalValues[$valueId]['catalog_value'],
                    'currency' => 'EUR',
                    'needs_review' => false,
                ]);
            }
        }

        // -------------------------------------------------
        // Controleer het herstel van cataloguseditie B1.
        // -------------------------------------------------

        expect($this->entryB1->fresh()->getAttributes())
            ->toBe($originalEntries[$this->entryB1->id]);

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        expect($this->valueB1Postfris->fresh()->catalog_value)
            ->toBe('25.00');

        expect($this->valueB1Gestempeld->fresh()->catalog_value)
            ->toBe('12.50');

        expect($this->valueB1Postfris->fresh()->needs_review)
            ->toBeFalse();

        expect($this->valueB1Gestempeld->fresh()->needs_review)
            ->toBeFalse();

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $this->entryB1->id,
            'catalog_number' => 'B-3100',
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Postfris->id,
            'catalog_value' => 65.75,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $this->valueB1Gestempeld->id,
            'catalog_value' => 32.50,
        ]);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke vermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $restoredEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($restoredEntry->getAttributes())
                ->toBe($attributes);

            expect($restoredEntry->values()->count())
                ->toBe(2);
        }

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($restoredValue->getAttributes())
                ->toBe($attributes);

            expect($restoredValue->needs_review)
                ->toBeFalse();

            expect($restoredValue->catalogEntry->id)
                ->toBe($attributes['stamp_catalog_entry_id']);

            expect($restoredValue->condition->id)
                ->toBe($attributes['condition_id']);

            expect($restoredValue->getRawOriginal('created_at'))
                ->toBe($attributes['created_at']);

            expect($restoredValue->getRawOriginal('updated_at'))
                ->toBe($attributes['updated_at']);
        }

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van alle drie batches.
        // -------------------------------------------------

        foreach ($batchIdsByEdition as $editionId => $batchId) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'id' => $batchId,
            ]);

            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $editionId,
            ]);

            expect(
                CatalogChangeBatch::query()->find($batchId)
            )->toBeNull();

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $batchId,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van alle acht logs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait gecombineerde routes volledig terug bij een fout in route D na verwerking van routes A B en C', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback G4',
        'slug' => 'oorspronkelijk-thema-rollback-g4',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback G4',
        'slug' => 'vervangend-thema-rollback-g4',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam G4',
            'description' => 'Oorspronkelijke Nederlandse beschrijving G4',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name G4',
            'description' => 'Original English description G4',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Definieer de door route A te verwijderen records.
    // -------------------------------------------------

    $deletedEntries = [
        $this->entryA1->id => [
            'edition_id' => $this->editionA1->id,
            'value_ids' => [
                $this->valueA1Postfris->id,
                $this->valueA1Gestempeld->id,
            ],
        ],
        $this->entryA2->id => [
            'edition_id' => $this->editionA2->id,
            'value_ids' => [
                $this->valueA2Postfris->id,
                $this->valueA2Gestempeld->id,
            ],
        ],
    ];

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    expect($this->stamp->fresh()->country_id)
        ->toBe($this->country->id);

    expect($this->otherCountry->id)
        ->not->toBe($this->country->id);

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeFalse();

    expect(
        $this->catalogB->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    expect(
        $this->catalogC->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeTrue();

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    // -------------------------------------------------
    // Algemene formuliergegevens en route A.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->otherCountry->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest G4';

    $payload['printing_method'] = 'Gewijzigde drukmethode G4';

    $payload['notes'] = 'Rollback van alle vier updateroutes';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam G4',
        'description' => 'Gewijzigde Nederlandse beschrijving G4',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    // -------------------------------------------------
    // Route B: wijzig cataloguseditie B1.
    // -------------------------------------------------

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-4100';

    $payload['catalog_values'] = [
        'postfris' => '75.75',
        'gestempeld' => '37.50',
    ];

    // -------------------------------------------------
    // Route C: wijzig dezelfde catalogusvermelding opnieuw.
    // -------------------------------------------------

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-4200',
            'values' => [
                'postfris' => '85.75',
                'gestempeld' => '42.50',
            ],
        ],
    ];

    // -------------------------------------------------
    // Route D: maak catalogusvermelding C1 aan.
    // -------------------------------------------------

    $payload['catalog_editions'] = [
        $this->editionC1->id => [
            'catalog_number' => 'C-4300',
            'values' => [
                'postfris' => '95.75',
                'gestempeld' => '47.50',
            ],
        ],
    ];

    // -------------------------------------------------
    // Definieer de verwachte geschiedenisbatches.
    // -------------------------------------------------

    $expectedBatches = [
        $this->editionA1->id => [
            'edition' => $this->editionA1,
            'catalog' => $this->catalogA,
            'log_count' => 3,
        ],
        $this->editionA2->id => [
            'edition' => $this->editionA2,
            'catalog' => $this->catalogA,
            'log_count' => 3,
        ],
        $this->editionB1->id => [
            'edition' => $this->editionB1,
            'catalog' => $this->catalogB,
            'log_count' => 6,
        ],
        $this->editionC1->id => [
            'edition' => $this->editionC1,
            'catalog' => $this->catalogC,
            'log_count' => 2,
        ],
    ];

    // -------------------------------------------------
    // Definieer de verwachte wijzigingslogs van B en C.
    // -------------------------------------------------

    $expectedUpdateLogs = [
        [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
            'condition_id' => null,
            'old_values' => [
                'catalog_number' => 'B-100',
            ],
            'new_values' => [
                'catalog_number' => 'B-4100',
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Postfris->id,
            'condition_id' => $this->postfris->id,
            'old_values' => [
                'catalog_value' => 25.00,
                'needs_review' => false,
            ],
            'new_values' => [
                'catalog_value' => 75.75,
                'needs_review' => true,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Gestempeld->id,
            'condition_id' => $this->gestempeld->id,
            'old_values' => [
                'catalog_value' => 12.50,
                'needs_review' => false,
            ],
            'new_values' => [
                'catalog_value' => 37.50,
                'needs_review' => true,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
            'condition_id' => null,
            'old_values' => [
                'catalog_number' => 'B-4100',
            ],
            'new_values' => [
                'catalog_number' => 'B-4200',
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Postfris->id,
            'condition_id' => $this->postfris->id,
            'old_values' => [
                'catalog_value' => 75.75,
            ],
            'new_values' => [
                'catalog_value' => 85.75,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Gestempeld->id,
            'condition_id' => $this->gestempeld->id,
            'old_values' => [
                'catalog_value' => 37.50,
            ],
            'new_values' => [
                'catalog_value' => 42.50,
            ],
        ],
    ];

    // -------------------------------------------------
    // Normaliseer decimale en booleaanse logwaarden.
    // -------------------------------------------------

    $normalizeLogValues = function (array $attributes): array {
        if (array_key_exists('catalog_value', $attributes)) {
            $attributes['catalog_value'] =
                (float) $attributes['catalog_value'];
        }

        if (array_key_exists('needs_review', $attributes)) {
            $attributes['needs_review'] =
                (bool) $attributes['needs_review'];
        }

        ksort($attributes);

        return $attributes;
    };

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout in route D na verwerking van routes A B en C';

    $listenerCalls = 0;

    $observedConditionIds = [];

    $batchIdsByEdition = [];

    $createdLogIds = [];

    /** @var int|null $createdEntryId */
    $createdEntryId = null;

    /** @var int|null $createdValueId */
    $createdValueId = null;

    /** @var int|null $activeBatchId */
    $activeBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    StampCatalogValue::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer de tijdelijke Eloquent-listener.
        // -------------------------------------------------

        StampCatalogValue::creating(function (
            StampCatalogValue $value
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $deletedEntries,
            $expectedBatches,
            $expectedUpdateLogs,
            $normalizeLogValues,
            $exceptionMessage,
            &$listenerCalls,
            &$observedConditionIds,
            &$batchIdsByEdition,
            &$createdLogIds,
            &$createdEntryId,
            &$createdValueId,
            &$activeBatchId,
            &$capturedHistory
        ) {
            $entry = StampCatalogEntry::query()
                ->findOrFail($value->stamp_catalog_entry_id);

            if (
                $entry->catalog_edition_id
                !== $this->editionC1->id
            ) {
                return;
            }

            $listenerCalls++;

            $observedConditionIds[] = $value->condition_id;

            expect($listenerCalls)
                ->toBeLessThanOrEqual(2);

            // -------------------------------------------------
            // Controleer de nieuwe catalogusvermelding.
            // -------------------------------------------------

            expect($entry->stamp_id)
                ->toBe($this->stamp->id);

            expect($entry->catalog_number)
                ->toBe('C-4300');

            expect($entry->catalog_edition_id)
                ->toBe($this->editionC1->id);

            expect($entry->exists)
                ->toBeTrue();

            if ($createdEntryId === null) {
                $createdEntryId = $entry->id;
            }

            expect($entry->id)
                ->toBe($createdEntryId);

            expect($value->exists)
                ->toBeFalse();

            expect($value->getKey())
                ->toBeNull();

            expect($value->currency)
                ->toBe('GBP');

            // -------------------------------------------------
            // Controleer de actieve geschiedeniscontext.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($activeBatch->catalog_edition_id)
                ->toBe($this->editionC1->id);

            if ($activeBatchId === null) {
                $activeBatchId = $activeBatch->id;
            }

            expect($activeBatch->id)
                ->toBe($activeBatchId);

            $this->assertDatabaseCount(
                'catalog_change_batches',
                4
            );

            // -------------------------------------------------
            // Bewijs dat route A volledig is uitgevoerd.
            // -------------------------------------------------

            foreach ($deletedEntries as $entryId => $entryData) {
                $this->assertDatabaseMissing('stamp_catalog_entries', [
                    'id' => $entryId,
                ]);

                expect(
                    StampCatalogEntry::query()->find($entryId)
                )->toBeNull();

                foreach ($entryData['value_ids'] as $valueId) {
                    $this->assertDatabaseMissing('stamp_catalog_values', [
                        'id' => $valueId,
                    ]);

                    expect(
                        StampCatalogValue::query()->find($valueId)
                    )->toBeNull();
                }
            }

            // -------------------------------------------------
            // Bewijs dat routes B en C zijn uitgevoerd.
            // -------------------------------------------------

            expect($this->entryB1->fresh()->catalog_number)
                ->toBe('B-4200');

            expect($this->valueB1Postfris->fresh()->catalog_value)
                ->toBe('85.75');

            expect($this->valueB1Gestempeld->fresh()->catalog_value)
                ->toBe('42.50');

            expect($this->valueB1Postfris->fresh()->needs_review)
                ->toBeTrue();

            expect($this->valueB1Gestempeld->fresh()->needs_review)
                ->toBeTrue();

            expect($this->valueB1Postfris->fresh()->currency)
                ->toBe('USD');

            expect($this->valueB1Gestempeld->fresh()->currency)
                ->toBe('USD');

            expect(
                CatalogChangeLog::query()
                    ->where('action', 'deleted')
                    ->count()
            )->toBe(6);

            expect(
                CatalogChangeLog::query()
                    ->where('action', 'updated')
                    ->count()
            )->toBe(6);

            // -------------------------------------------------
            // Laat de eerste nieuwe waarde normaal opslaan.
            // -------------------------------------------------

            if ($listenerCalls === 1) {
                expect($value->condition_id)
                    ->toBe($this->postfris->id);

                expect($value->catalog_value)
                    ->toBe('95.75');

                expect($entry->values()->count())
                    ->toBe(0);

                expect($activeBatch->logs()->count())
                    ->toBe(1);

                $this->assertDatabaseCount(
                    'stamp_catalog_entries',
                    2
                );

                $this->assertDatabaseCount(
                    'stamp_catalog_values',
                    2
                );

                $this->assertDatabaseCount(
                    'catalog_change_logs',
                    13
                );

                return;
            }

            // -------------------------------------------------
            // De tweede nieuwe waarde veroorzaakt de fout.
            // -------------------------------------------------

            expect($value->condition_id)
                ->toBe($this->gestempeld->id);

            expect($value->catalog_value)
                ->toBe('47.50');

            expect($entry->values()->count())
                ->toBe(1);

            $this->assertDatabaseMissing('stamp_catalog_values', [
                'stamp_catalog_entry_id' => $createdEntryId,
                'condition_id' => $this->gestempeld->id,
            ]);

            // -------------------------------------------------
            // Bewijs dat de eerste nieuwe waarde is opgeslagen.
            // -------------------------------------------------

            $createdValue = $entry->values()
                ->where('condition_id', $this->postfris->id)
                ->sole();

            $createdValueId = $createdValue->id;

            expect($createdValue->catalog_value)
                ->toBe('95.75');

            expect($createdValue->currency)
                ->toBe('GBP');

            expect($createdValue->needs_review)
                ->toBeFalse();

            expect($createdValue->catalogEntry->id)
                ->toBe($createdEntryId);

            expect($createdValue->condition->id)
                ->toBe($this->postfris->id);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $createdEntryId,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $this->editionC1->id,
                'catalog_number' => 'C-4300',
            ]);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $createdValueId,
                'stamp_catalog_entry_id' => $createdEntryId,
                'condition_id' => $this->postfris->id,
                'catalog_value' => 95.75,
                'currency' => 'GBP',
                'needs_review' => false,
            ]);

            // -------------------------------------------------
            // Controleer de gewijzigde postzegelgegevens.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->otherCountry->id);

            expect($changedStamp->designer)
                ->toBe($payload['designer']);

            expect($changedStamp->printing_method)
                ->toBe($payload['printing_method']);

            expect($changedStamp->notes)
                ->toBe($payload['notes']);

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            // -------------------------------------------------
            // Controleer beide vertalingen.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Controleer de gewijzigde themakoppeling.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            // -------------------------------------------------
            // Controleer de databaseaantallen tijdens de fout.
            // -------------------------------------------------

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 2);

            $this->assertDatabaseCount('stamp_catalog_values', 3);

            $this->assertDatabaseCount('catalog_change_batches', 4);

            $this->assertDatabaseCount('catalog_change_logs', 14);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(2);

            expect($changedStamp->catalogValues()->count())
                ->toBe(3);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(2);

            // -------------------------------------------------
            // Controleer alle vier geschiedenisbatches.
            // -------------------------------------------------

            $batches = CatalogChangeBatch::query()
                ->orderBy('id')
                ->get();

            expect($batches)
                ->toHaveCount(4);

            foreach ($expectedBatches as $editionId => $batchData) {
                $batch = $batches
                    ->where('catalog_edition_id', $editionId)
                    ->sole();

                $batchIdsByEdition[$editionId] = $batch->id;

                expect($batch->catalog_edition_id)
                    ->toBe($editionId);

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
                    ->toBe($batchData['catalog']->name);

                expect($batch->catalog_edition_name)
                    ->toBe($batchData['edition']->edition_name);

                expect($batch->catalogEdition->id)
                    ->toBe($editionId);

                expect($batch->catalogEdition->catalog->id)
                    ->toBe($batchData['catalog']->id);

                expect($batch->user->id)
                    ->toBe($this->user->id);

                expect($batch->logs()->count())
                    ->toBe($batchData['log_count']);
            }

            expect(array_unique($batchIdsByEdition))
                ->toHaveCount(4);

            expect($activeBatchId)
                ->toBe(
                    $batchIdsByEdition[$this->editionC1->id]
                );

            expect($batches->last()->id)
                ->toBe($activeBatchId);

            // -------------------------------------------------
            // Controleer alle veertien wijzigingslogs.
            // -------------------------------------------------

            $logs = CatalogChangeLog::query()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs)
                ->toHaveCount(14);

            expect($logs->pluck('action')->all())
                ->toBe(array_merge(
                    array_fill(0, 6, 'deleted'),
                    array_fill(0, 6, 'updated'),
                    array_fill(0, 2, 'created')
                ));

            // -------------------------------------------------
            // Controleer de zes verwijderingslogs van route A.
            // -------------------------------------------------

            foreach ($deletedEntries as $entryId => $entryData) {
                $batchId =
                    $batchIdsByEdition[$entryData['edition_id']];

                $editionLogs = $logs
                    ->where('catalog_change_batch_id', $batchId)
                    ->values();

                expect($editionLogs)
                    ->toHaveCount(3);

                expect($editionLogs->pluck('record_type')->all())
                    ->toBe([
                        'stamp_catalog_value',
                        'stamp_catalog_value',
                        'stamp_catalog_entry',
                    ]);

                expect($editionLogs->pluck('action')->all())
                    ->toBe(array_fill(0, 3, 'deleted'));

                $entryLog = $editionLogs->last();

                expect($entryLog->record_id)
                    ->toBe($entryId);

                expect($entryLog->condition_id)
                    ->toBeNull();

                expect(
                    $normalizeLogValues($entryLog->old_values)
                )->toBe(
                    $normalizeLogValues($originalEntries[$entryId])
                );

                expect($entryLog->new_values)
                    ->toBeNull();

                foreach ($entryData['value_ids'] as $valueId) {
                    $valueLog = $editionLogs
                        ->where('record_type', 'stamp_catalog_value')
                        ->where('record_id', $valueId)
                        ->sole();

                    expect($valueLog->condition_id)
                        ->toBe(
                            $originalValues[$valueId]['condition_id']
                        );

                    expect(
                        $normalizeLogValues($valueLog->old_values)
                    )->toBe(
                        $normalizeLogValues($originalValues[$valueId])
                    );

                    expect($valueLog->new_values)
                        ->toBeNull();

                    expect($valueLog->id)
                        ->toBeLessThan($entryLog->id);
                }
            }

            // -------------------------------------------------
            // Controleer de zes wijzigingslogs van B en C.
            // Beide routes moeten dezelfde batch gebruiken.
            // -------------------------------------------------

            $routeBCLogs = $logs
                ->where(
                    'catalog_change_batch_id',
                    $batchIdsByEdition[$this->editionB1->id]
                )
                ->values();

            expect($routeBCLogs)
                ->toHaveCount(6);

            expect($routeBCLogs->pluck('id')->all())
                ->toBe(
                    $logs->slice(6, 6)->pluck('id')->all()
                );

            foreach ($routeBCLogs as $index => $log) {
                $expected = $expectedUpdateLogs[$index];

                expect($log->record_type)
                    ->toBe($expected['record_type']);

                expect($log->record_id)
                    ->toBe($expected['record_id']);

                expect($log->condition_id)
                    ->toBe($expected['condition_id']);

                expect($log->action)
                    ->toBe('updated');

                expect(
                    $normalizeLogValues($log->old_values)
                )->toBe(
                    $normalizeLogValues($expected['old_values'])
                );

                expect(
                    $normalizeLogValues($log->new_values)
                )->toBe(
                    $normalizeLogValues($expected['new_values'])
                );

                expect($log->old_values)
                    ->not->toHaveKey('updated_at');

                expect($log->new_values)
                    ->not->toHaveKey('updated_at');
            }

            // -------------------------------------------------
            // Controleer de twee aanmaaklogs van route D.
            // -------------------------------------------------

            $routeDLogs = $logs
                ->where('catalog_change_batch_id', $activeBatchId)
                ->values();

            expect($routeDLogs)
                ->toHaveCount(2);

            expect($routeDLogs->pluck('id')->all())
                ->toBe(
                    $logs->slice(12)->pluck('id')->all()
                );

            expect($routeDLogs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                ]);

            $entryLog = $routeDLogs[0];

            expect($entryLog->record_id)
                ->toBe($createdEntryId);

            expect($entryLog->condition_id)
                ->toBeNull();

            expect($entryLog->action)
                ->toBe('created');

            expect($entryLog->old_values)
                ->toBeNull();

            expect($entryLog->new_values)
                ->toBeArray();

            expect($entryLog->new_values['catalog_number'])
                ->toBe('C-4300');

            expect($entryLog->new_values['stamp_id'])
                ->toBe($this->stamp->id);

            expect($entryLog->new_values['catalog_edition_id'])
                ->toBe($this->editionC1->id);

            $valueLog = $routeDLogs[1];

            expect($valueLog->record_id)
                ->toBe($createdValueId);

            expect($valueLog->condition_id)
                ->toBe($this->postfris->id);

            expect($valueLog->action)
                ->toBe('created');

            expect($valueLog->old_values)
                ->toBeNull();

            expect($valueLog->new_values)
                ->toBeArray();

            expect(
                (float) $valueLog->new_values['catalog_value']
            )->toBe(95.75);

            expect($valueLog->new_values['currency'])
                ->toBe('GBP');

            expect($valueLog->new_values['condition_id'])
                ->toBe($this->postfris->id);

            expect($valueLog->new_values['stamp_catalog_entry_id'])
                ->toBe($createdEntryId);

            // -------------------------------------------------
            // Vergelijk de gelogde aanmaakattributen met
            // de daadwerkelijk opgeslagen databasegegevens.
            // -------------------------------------------------

            foreach ([
                [$entryLog, $entry],
                [$valueLog, $createdValue],
            ] as [$log, $record]) {
                $storedAttributes = $normalizeLogValues(
                    $record->getAttributes()
                );

                foreach (
                    $normalizeLogValues($log->new_values) as $attribute => $attributeValue
                ) {
                    expect($storedAttributes)
                        ->toHaveKey($attribute);

                    expect($storedAttributes[$attribute])
                        ->toBe($attributeValue);
                }
            }

            // -------------------------------------------------
            // Controleer de relaties van alle veertien logs.
            // -------------------------------------------------

            foreach ($logs as $log) {
                expect($log->stamp_id)
                    ->toBe($this->stamp->id);

                expect($log->stamp_series_id)
                    ->toBeNull();

                expect($log->catalog_change_batch_id)
                    ->toBe(
                        $batchIdsByEdition[$log->catalog_edition_id]
                    );

                expect($log->batch->id)
                    ->toBe($log->catalog_change_batch_id);

                expect($log->batch->catalog_edition_id)
                    ->toBe($log->catalog_edition_id);

                expect($log->catalogEdition->id)
                    ->toBe($log->catalog_edition_id);

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

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $log->id,
                    'catalog_change_batch_id' => $log->catalog_change_batch_id,
                    'catalog_edition_id' => $log->catalog_edition_id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $log->condition_id,
                    'record_type' => $log->record_type,
                    'record_id' => $log->record_id,
                    'action' => $log->action,
                ]);
            }

            // -------------------------------------------------
            // De mislukte tweede waarde heeft nog geen log.
            // -------------------------------------------------

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $activeBatchId,
                'record_type' => 'stamp_catalog_value',
                'condition_id' => $this->gestempeld->id,
                'action' => 'created',
            ]);

            expect($activeBatch->logs()->count())
                ->toBe(2);

            // -------------------------------------------------
            // Veroorzaak bewust een onverwachte uitzondering.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de gecombineerde aanvraag.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de foutinjectie is bereikt.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(2);

        expect($observedConditionIds)
            ->toBe([
                $this->postfris->id,
                $this->gestempeld->id,
            ]);

        expect($createdEntryId)
            ->toBeInt();

        expect($createdValueId)
            ->toBeInt();

        expect($activeBatchId)
            ->toBeInt();

        expect($batchIdsByEdition)
            ->toHaveCount(4);

        expect(array_unique($batchIdsByEdition))
            ->toHaveCount(4);

        expect($createdLogIds)
            ->toHaveCount(14);

        expect(array_unique($createdLogIds))
            ->toHaveCount(14);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->country_id)
            ->toBe($this->country->id);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer het herstel van alle catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $restoredEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($restoredEntry->getAttributes())
                ->toBe($attributes);

            expect($restoredEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($restoredEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $entryId,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $attributes['catalog_edition_id'],
                'catalog_number' => $attributes['catalog_number'],
            ]);
        }

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer het herstel van alle cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($restoredValue->getAttributes())
                ->toBe($attributes);

            expect($restoredValue->needs_review)
                ->toBeFalse();

            expect($restoredValue->catalogEntry->id)
                ->toBe($attributes['stamp_catalog_entry_id']);

            expect($restoredValue->condition->id)
                ->toBe($attributes['condition_id']);

            expect($restoredValue->getRawOriginal('created_at'))
                ->toBe($attributes['created_at']);

            expect($restoredValue->getRawOriginal('updated_at'))
                ->toBe($attributes['updated_at']);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $valueId,
                'stamp_catalog_entry_id' => $attributes['stamp_catalog_entry_id'],
                'condition_id' => $attributes['condition_id'],
                'catalog_value' => (float) $attributes['catalog_value'],
                'currency' => $attributes['currency'],
                'needs_review' => false,
            ]);
        }

        expect($this->valueB1Postfris->fresh()->catalog_value)
            ->toBe('25.00');

        expect($this->valueB1Gestempeld->fresh()->catalog_value)
            ->toBe('12.50');

        foreach (['B-4100', 'B-4200'] as $number) {
            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_number' => $number,
            ]);
        }

        foreach ([75.75, 85.75] as $amount) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'catalog_value' => $amount,
            ]);
        }

        foreach ([37.50, 42.50] as $amount) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => $amount,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van de nieuwe vermelding.
        //
        // Controleer de combinatie van ID en editie.
        // Een database kan verwijderde ID's hergebruiken.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $createdEntryId,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_number' => 'C-4300',
        ]);

        // -------------------------------------------------
        // Controleer de rollback van de nieuwe cataloguswaarde.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_values', [
            'id' => $createdValueId,
            'catalog_value' => 95.75,
            'currency' => 'GBP',
        ]);

        expect(
            StampCatalogValue::query()
                ->where('currency', 'GBP')
                ->count()
        )->toBe(0);

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van alle vier batches.
        // -------------------------------------------------

        foreach ($batchIdsByEdition as $editionId => $batchId) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'id' => $batchId,
            ]);

            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $editionId,
            ]);

            expect(
                CatalogChangeBatch::query()->find($batchId)
            )->toBeNull();

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $batchId,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van alle veertien logs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        StampCatalogValue::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});

test('de backend draait gecombineerde routes volledig terug bij een fout na het opslaan van de laatste wijzigingslog', function () {
    // -------------------------------------------------
    // Bereid aanvullende testgegevens voor.
    // -------------------------------------------------

    $originalTheme = Theme::create([
        'name' => 'Oorspronkelijk thema rollback G5',
        'slug' => 'oorspronkelijk-thema-rollback-g5',
    ]);

    $replacementTheme = Theme::create([
        'name' => 'Vervangend thema rollback G5',
        'slug' => 'vervangend-thema-rollback-g5',
    ]);

    $this->stamp->themes()->sync([
        $originalTheme->id,
    ]);

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'nl'],
        [
            'name' => 'Oorspronkelijke Nederlandse naam G5',
            'description' => 'Oorspronkelijke Nederlandse beschrijving G5',
        ]
    );

    $this->stamp->translations()->updateOrCreate(
        ['locale' => 'en'],
        [
            'name' => 'Original English name G5',
            'description' => 'Original English description G5',
        ]
    );

    // -------------------------------------------------
    // Bewaar de oorspronkelijke databasegegevens.
    // -------------------------------------------------

    $snapshot = function () {
        $models = [
            User::class,
            Country::class,
            Stamp::class,
            Catalog::class,
            CatalogEdition::class,
            Condition::class,
            CatalogConditionRule::class,
            StampCatalogEntry::class,
            StampCatalogValue::class,
            StampImage::class,
            Theme::class,
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

        $data['translations'] = $this->stamp->fresh()
            ->translations()
            ->orderBy('id')
            ->get()
            ->map(fn ($translation) => $translation->getAttributes())
            ->all();

        $data['themes'] = $this->stamp->fresh()
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        foreach ([
            $this->catalogA,
            $this->catalogB,
            $this->catalogC,
        ] as $catalog) {
            $data['catalog_countries'][$catalog->id] =
                $catalog->countries()
                    ->orderBy('countries.id')
                    ->pluck('countries.id')
                    ->all();
        }

        return $data;
    };

    $originalData = $snapshot();

    $originalStamp = $this->stamp->fresh()->getAttributes();

    $originalDutchTranslation = $this->stamp
        ->translations()
        ->where('locale', 'nl')
        ->firstOrFail()
        ->getAttributes();

    $originalEnglishTranslation = $this->stamp
        ->translations()
        ->where('locale', 'en')
        ->firstOrFail()
        ->getAttributes();

    $originalEntries = StampCatalogEntry::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($entry) => $entry->getAttributes())
        ->all();

    $originalValues = StampCatalogValue::query()
        ->get()
        ->keyBy('id')
        ->map(fn ($value) => $value->getAttributes())
        ->all();

    // -------------------------------------------------
    // Definieer de door route A te verwijderen records.
    // -------------------------------------------------

    $deletedEntries = [
        $this->entryA1->id => [
            'edition_id' => $this->editionA1->id,
            'value_ids' => [
                $this->valueA1Postfris->id,
                $this->valueA1Gestempeld->id,
            ],
        ],
        $this->entryA2->id => [
            'edition_id' => $this->editionA2->id,
            'value_ids' => [
                $this->valueA2Postfris->id,
                $this->valueA2Gestempeld->id,
            ],
        ],
    ];

    // -------------------------------------------------
    // Controleer de uitgangssituatie.
    // -------------------------------------------------

    $this->assertAuthenticatedAs($this->user);

    expect($this->stamp->fresh()->country_id)
        ->toBe($this->country->id);

    expect($this->otherCountry->id)
        ->not->toBe($this->country->id);

    expect(
        $this->catalogA->countries()
            ->where('countries.id', $this->otherCountry->id)
            ->exists()
    )->toBeFalse();

    foreach ([$this->catalogB, $this->catalogC] as $catalog) {
        expect(
            $catalog->countries()
                ->where('countries.id', $this->otherCountry->id)
                ->exists()
        )->toBeTrue();
    }

    $this->assertDatabaseCount('stamps', 1);

    $this->assertDatabaseCount('stamp_catalog_entries', 3);

    $this->assertDatabaseCount('stamp_catalog_values', 6);

    $this->assertDatabaseCount('catalog_change_batches', 0);

    $this->assertDatabaseCount('catalog_change_logs', 0);

    $this->assertDatabaseMissing('stamp_catalog_entries', [
        'stamp_id' => $this->stamp->id,
        'catalog_edition_id' => $this->editionC1->id,
    ]);

    expect($originalData['themes'])
        ->toBe([$originalTheme->id]);

    expect($this->history->getActiveBatch())
        ->toBeNull();

    expect(
        StampCatalogValue::query()
            ->where('needs_review', true)
            ->count()
    )->toBe(0);

    // -------------------------------------------------
    // Algemene formuliergegevens en route A.
    // -------------------------------------------------

    $payload = $this->updatePayload;

    $payload['country_id'] = $this->otherCountry->id;

    $payload['designer'] = 'Gewijzigde ontwerper rollbacktest G5';

    $payload['printing_method'] = 'Gewijzigde drukmethode G5';

    $payload['notes'] = 'Rollback na opgeslagen wijzigingslog G5';

    $payload['translations']['nl'] = [
        'name' => 'Gewijzigde Nederlandse naam G5',
        'description' => 'Gewijzigde Nederlandse beschrijving G5',
    ];

    $payload['theme_ids'] = [
        $replacementTheme->id,
    ];

    // -------------------------------------------------
    // Route B: wijzig cataloguseditie B1.
    // -------------------------------------------------

    $payload['catalog_edition_id'] = $this->editionB1->id;

    $payload['catalog_number'] = 'B-5100';

    $payload['catalog_values'] = [
        'postfris' => '95.75',
        'gestempeld' => '47.50',
    ];

    // -------------------------------------------------
    // Route C: wijzig dezelfde catalogusvermelding opnieuw.
    // -------------------------------------------------

    $payload['catalog_entries'] = [
        $this->entryB1->id => [
            'catalog_number' => 'B-5200',
            'values' => [
                'postfris' => '105.75',
                'gestempeld' => '52.50',
            ],
        ],
    ];

    // -------------------------------------------------
    // Route D: maak catalogusvermelding C1 aan.
    // -------------------------------------------------

    $payload['catalog_editions'] = [
        $this->editionC1->id => [
            'catalog_number' => 'C-5300',
            'values' => [
                'postfris' => '115.75',
                'gestempeld' => '57.50',
            ],
        ],
    ];

    // -------------------------------------------------
    // Definieer de verwachte geschiedenisbatches.
    // -------------------------------------------------

    $expectedBatches = [
        $this->editionA1->id => [
            'edition' => $this->editionA1,
            'catalog' => $this->catalogA,
            'log_count' => 3,
        ],
        $this->editionA2->id => [
            'edition' => $this->editionA2,
            'catalog' => $this->catalogA,
            'log_count' => 3,
        ],
        $this->editionB1->id => [
            'edition' => $this->editionB1,
            'catalog' => $this->catalogB,
            'log_count' => 6,
        ],
        $this->editionC1->id => [
            'edition' => $this->editionC1,
            'catalog' => $this->catalogC,
            'log_count' => 3,
        ],
    ];

    // -------------------------------------------------
    // Definieer de verwachte wijzigingslogs van B en C.
    // -------------------------------------------------

    $expectedUpdateLogs = [
        [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
            'condition_id' => null,
            'old_values' => [
                'catalog_number' => 'B-100',
            ],
            'new_values' => [
                'catalog_number' => 'B-5100',
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Postfris->id,
            'condition_id' => $this->postfris->id,
            'old_values' => [
                'catalog_value' => 25.00,
                'needs_review' => false,
            ],
            'new_values' => [
                'catalog_value' => 95.75,
                'needs_review' => true,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Gestempeld->id,
            'condition_id' => $this->gestempeld->id,
            'old_values' => [
                'catalog_value' => 12.50,
                'needs_review' => false,
            ],
            'new_values' => [
                'catalog_value' => 47.50,
                'needs_review' => true,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_entry',
            'record_id' => $this->entryB1->id,
            'condition_id' => null,
            'old_values' => [
                'catalog_number' => 'B-5100',
            ],
            'new_values' => [
                'catalog_number' => 'B-5200',
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Postfris->id,
            'condition_id' => $this->postfris->id,
            'old_values' => [
                'catalog_value' => 95.75,
            ],
            'new_values' => [
                'catalog_value' => 105.75,
            ],
        ],
        [
            'record_type' => 'stamp_catalog_value',
            'record_id' => $this->valueB1Gestempeld->id,
            'condition_id' => $this->gestempeld->id,
            'old_values' => [
                'catalog_value' => 47.50,
            ],
            'new_values' => [
                'catalog_value' => 52.50,
            ],
        ],
    ];

    // -------------------------------------------------
    // Normaliseer decimale en booleaanse logwaarden.
    // -------------------------------------------------

    $normalizeLogValues = function (array $attributes): array {
        if (array_key_exists('catalog_value', $attributes)) {
            $attributes['catalog_value'] =
                (float) $attributes['catalog_value'];
        }

        if (array_key_exists('needs_review', $attributes)) {
            $attributes['needs_review'] =
                (bool) $attributes['needs_review'];
        }

        ksort($attributes);

        return $attributes;
    };

    // -------------------------------------------------
    // Bereid de tijdelijke foutinjectie voor.
    // -------------------------------------------------

    $exceptionMessage =
        'Gesimuleerde fout na het opslaan van de laatste wijzigingslog G5';

    $listenerCalls = 0;

    $failureCalls = 0;

    $observedLogIds = [];

    $observedBatchIds = [];

    $batchIdsByEdition = [];

    $createdLogIds = [];

    $createdValueIds = [];

    /** @var int|null $createdEntryId */
    $createdEntryId = null;

    /** @var int|null $failedLogId */
    $failedLogId = null;

    /** @var int|null $activeBatchId */
    $activeBatchId = null;

    /** @var CatalogChangeHistoryService|null $capturedHistory */
    $capturedHistory = null;

    $originalDispatcher = CatalogChangeLog::getEventDispatcher();

    expect($originalDispatcher)
        ->not->toBeNull();

    $testDispatcher = clone $originalDispatcher;

    CatalogChangeLog::setEventDispatcher($testDispatcher);

    $this->withoutExceptionHandling();

    $this->travel(1)->minutes();

    try {
        // -------------------------------------------------
        // Registreer een listener op het created-event.
        //
        // Dit event wordt uitgevoerd nadat het logrecord
        // daadwerkelijk in de database is opgeslagen.
        // -------------------------------------------------

        CatalogChangeLog::created(function (
            CatalogChangeLog $log
        ) use (
            $payload,
            $originalStamp,
            $originalDutchTranslation,
            $originalEnglishTranslation,
            $originalEntries,
            $originalValues,
            $originalTheme,
            $replacementTheme,
            $deletedEntries,
            $expectedBatches,
            $expectedUpdateLogs,
            $normalizeLogValues,
            $exceptionMessage,
            &$listenerCalls,
            &$failureCalls,
            &$observedLogIds,
            &$observedBatchIds,
            &$batchIdsByEdition,
            &$createdLogIds,
            &$createdValueIds,
            &$createdEntryId,
            &$failedLogId,
            &$activeBatchId,
            &$capturedHistory
        ) {
            if ($log->stamp_id !== $this->stamp->id) {
                return;
            }

            $listenerCalls++;

            $observedLogIds[] = $log->id;

            expect($listenerCalls)
                ->toBeLessThanOrEqual(15);

            // -------------------------------------------------
            // Controleer bij iedere log de actieve batch.
            // -------------------------------------------------

            $capturedHistory = app(
                CatalogChangeHistoryService::class
            );

            expect($capturedHistory)
                ->toBe($this->history);

            $activeBatch = $capturedHistory->getActiveBatch();

            expect($activeBatch)
                ->toBeInstanceOf(CatalogChangeBatch::class);

            expect($activeBatch->id)
                ->toBe($log->catalog_change_batch_id);

            expect($activeBatch->catalog_edition_id)
                ->toBe($log->catalog_edition_id);

            $observedBatchIds[] = $activeBatch->id;

            // -------------------------------------------------
            // Bewijs dat iedere waargenomen log al bestaat.
            // -------------------------------------------------

            expect($log->exists)
                ->toBeTrue();

            expect($log->wasRecentlyCreated)
                ->toBeTrue();

            expect($log->getKey())
                ->toBeInt();

            $storedLog = CatalogChangeLog::query()
                ->findOrFail($log->id);

            expect($storedLog->getAttributes())
                ->toBe($log->fresh()->getAttributes());

            expect($storedLog->action)
                ->toBe($log->action);

            expect($storedLog->record_id)
                ->toBe($log->record_id);

            expect($storedLog->old_values)
                ->toBe($log->old_values);

            expect($storedLog->new_values)
                ->toBe($log->new_values);

            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $log->id,
                'catalog_change_batch_id' => $activeBatch->id,
                'catalog_edition_id' => $log->catalog_edition_id,
                'record_type' => $log->record_type,
                'record_id' => $log->record_id,
                'action' => $log->action,
            ]);

            $this->assertDatabaseCount(
                'catalog_change_logs',
                $listenerCalls
            );

            // -------------------------------------------------
            // Laat alle logs normaal opslaan, behalve dat
            // we na de laatste aanmaaklog een fout veroorzaken.
            // -------------------------------------------------

            if (
                $log->catalog_edition_id !== $this->editionC1->id
                || $log->record_type !== 'stamp_catalog_value'
                || $log->condition_id !== $this->gestempeld->id
                || $log->action !== 'created'
            ) {
                return;
            }

            $failureCalls++;

            $failedLogId = $log->id;

            $activeBatchId = $activeBatch->id;

            expect($listenerCalls)
                ->toBe(15);

            expect($failureCalls)
                ->toBe(1);

            // -------------------------------------------------
            // Bewijs dat route A volledig is uitgevoerd.
            // -------------------------------------------------

            foreach ($deletedEntries as $entryId => $entryData) {
                $this->assertDatabaseMissing('stamp_catalog_entries', [
                    'id' => $entryId,
                ]);

                expect(
                    StampCatalogEntry::query()->find($entryId)
                )->toBeNull();

                foreach ($entryData['value_ids'] as $valueId) {
                    $this->assertDatabaseMissing('stamp_catalog_values', [
                        'id' => $valueId,
                    ]);

                    expect(
                        StampCatalogValue::query()->find($valueId)
                    )->toBeNull();
                }
            }

            // -------------------------------------------------
            // Bewijs dat routes B en C volledig zijn uitgevoerd.
            // -------------------------------------------------

            $changedEntryB1 = $this->entryB1->fresh();

            expect($changedEntryB1->catalog_number)
                ->toBe('B-5200');

            expect($changedEntryB1->getRawOriginal('updated_at'))
                ->not->toBe(
                    $originalEntries[$this->entryB1->id]['updated_at']
                );

            foreach ([
                [$this->valueB1Postfris, '105.75'],
                [$this->valueB1Gestempeld, '52.50'],
            ] as [$originalValue, $expectedAmount]) {
                $changedValue = $originalValue->fresh();

                expect($changedValue->catalog_value)
                    ->toBe($expectedAmount);

                expect($changedValue->currency)
                    ->toBe('USD');

                expect($changedValue->needs_review)
                    ->toBeTrue();

                expect($changedValue->getRawOriginal('updated_at'))
                    ->not->toBe(
                        $originalValues[$originalValue->id]['updated_at']
                    );

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $originalValue->id,
                    'catalog_value' => (float) $expectedAmount,
                    'currency' => 'USD',
                    'needs_review' => true,
                ]);
            }

            // -------------------------------------------------
            // Bewijs dat route D beide waarden heeft opgeslagen.
            // -------------------------------------------------

            $createdEntry = StampCatalogEntry::query()
                ->where('stamp_id', $this->stamp->id)
                ->where('catalog_edition_id', $this->editionC1->id)
                ->sole();

            $createdEntryId = $createdEntry->id;

            expect($createdEntry->catalog_number)
                ->toBe('C-5300');

            expect($createdEntry->values()->count())
                ->toBe(2);

            $createdPostfris = $createdEntry->values()
                ->where('condition_id', $this->postfris->id)
                ->sole();

            $createdGestempeld = $createdEntry->values()
                ->where('condition_id', $this->gestempeld->id)
                ->sole();

            $createdValueIds = [
                $createdPostfris->id,
                $createdGestempeld->id,
            ];

            expect($createdPostfris->catalog_value)
                ->toBe('115.75');

            expect($createdGestempeld->catalog_value)
                ->toBe('57.50');

            foreach ([
                $createdPostfris,
                $createdGestempeld,
            ] as $createdValue) {
                expect($createdValue->currency)
                    ->toBe('GBP');

                expect($createdValue->needs_review)
                    ->toBeFalse();

                expect($createdValue->catalogEntry->id)
                    ->toBe($createdEntryId);

                expect($createdValue->exists)
                    ->toBeTrue();

                $this->assertDatabaseHas('stamp_catalog_values', [
                    'id' => $createdValue->id,
                    'stamp_catalog_entry_id' => $createdEntryId,
                    'condition_id' => $createdValue->condition_id,
                    'catalog_value' => (float) $createdValue->catalog_value,
                    'currency' => 'GBP',
                    'needs_review' => false,
                ]);
            }

            // -------------------------------------------------
            // Bewijs dat de laatste log daadwerkelijk bij
            // de opgeslagen gestempelde waarde hoort.
            // -------------------------------------------------

            expect($log->record_id)
                ->toBe($createdGestempeld->id);

            expect($log->condition_id)
                ->toBe($this->gestempeld->id);

            expect($log->old_values)
                ->toBeNull();

            expect($log->new_values)
                ->toBeArray();

            expect((float) $log->new_values['catalog_value'])
                ->toBe(57.50);

            expect($log->new_values['currency'])
                ->toBe('GBP');

            expect($log->new_values['stamp_catalog_entry_id'])
                ->toBe($createdEntryId);

            expect($log->new_values['condition_id'])
                ->toBe($this->gestempeld->id);

            $this->assertDatabaseHas('catalog_change_logs', [
                'id' => $failedLogId,
                'catalog_change_batch_id' => $activeBatchId,
                'catalog_edition_id' => $this->editionC1->id,
                'record_type' => 'stamp_catalog_value',
                'record_id' => $createdGestempeld->id,
                'condition_id' => $this->gestempeld->id,
                'action' => 'created',
            ]);

            // -------------------------------------------------
            // Controleer de gewijzigde postzegelgegevens.
            // -------------------------------------------------

            $changedStamp = Stamp::query()
                ->findOrFail($this->stamp->id);

            expect($changedStamp->country_id)
                ->toBe($this->otherCountry->id);

            foreach ([
                'designer',
                'printing_method',
                'notes',
            ] as $attribute) {
                expect($changedStamp->{$attribute})
                    ->toBe($payload[$attribute]);
            }

            expect($changedStamp->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedStamp->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedStamp->getRawOriginal('updated_at'))
                ->not->toBe($originalStamp['updated_at']);

            // -------------------------------------------------
            // Controleer beide vertalingen.
            // -------------------------------------------------

            $changedDutchTranslation = $changedStamp
                ->translations()
                ->where('locale', 'nl')
                ->firstOrFail();

            expect($changedDutchTranslation->id)
                ->toBe($originalDutchTranslation['id']);

            expect($changedDutchTranslation->name)
                ->toBe($payload['translations']['nl']['name']);

            expect($changedDutchTranslation->description)
                ->toBe($payload['translations']['nl']['description']);

            expect($changedDutchTranslation->getRawOriginal('updated_at'))
                ->not->toBe($originalDutchTranslation['updated_at']);

            expect(
                $changedStamp->translations()
                    ->where('locale', 'en')
                    ->firstOrFail()
                    ->getAttributes()
            )->toBe($originalEnglishTranslation);

            // -------------------------------------------------
            // Controleer de gewijzigde themakoppeling.
            // -------------------------------------------------

            $changedThemeIds = $changedStamp
                ->themes()
                ->orderBy('themes.id')
                ->pluck('themes.id')
                ->all();

            expect($changedThemeIds)
                ->toBe([$replacementTheme->id]);

            expect($changedThemeIds)
                ->not->toContain($originalTheme->id);

            // -------------------------------------------------
            // Controleer de databaseaantallen.
            // -------------------------------------------------

            $this->assertDatabaseCount('stamps', 1);

            $this->assertDatabaseCount('stamp_catalog_entries', 2);

            $this->assertDatabaseCount('stamp_catalog_values', 4);

            $this->assertDatabaseCount('catalog_change_batches', 4);

            $this->assertDatabaseCount('catalog_change_logs', 15);

            expect($changedStamp->catalogEntries()->count())
                ->toBe(2);

            expect($changedStamp->catalogValues()->count())
                ->toBe(4);

            expect(
                StampCatalogValue::query()
                    ->where('needs_review', true)
                    ->count()
            )->toBe(2);

            // -------------------------------------------------
            // Controleer alle vier geschiedenisbatches.
            // -------------------------------------------------

            $batches = CatalogChangeBatch::query()
                ->orderBy('id')
                ->get();

            expect($batches)
                ->toHaveCount(4);

            foreach ($expectedBatches as $editionId => $batchData) {
                $batch = $batches
                    ->where('catalog_edition_id', $editionId)
                    ->sole();

                $batchIdsByEdition[$editionId] = $batch->id;

                expect($batch->catalog_edition_id)
                    ->toBe($editionId);

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
                    ->toBe($batchData['catalog']->name);

                expect($batch->catalog_edition_name)
                    ->toBe($batchData['edition']->edition_name);

                expect($batch->catalogEdition->id)
                    ->toBe($editionId);

                expect($batch->catalogEdition->catalog->id)
                    ->toBe($batchData['catalog']->id);

                expect($batch->user->id)
                    ->toBe($this->user->id);

                expect($batch->logs()->count())
                    ->toBe($batchData['log_count']);
            }

            expect(array_unique($batchIdsByEdition))
                ->toHaveCount(4);

            expect($activeBatchId)
                ->toBe(
                    $batchIdsByEdition[$this->editionC1->id]
                );

            expect($batches->last()->id)
                ->toBe($activeBatchId);

            // -------------------------------------------------
            // Controleer alle vijftien opgeslagen logs.
            // -------------------------------------------------

            $logs = CatalogChangeLog::query()
                ->orderBy('id')
                ->get();

            $createdLogIds = $logs->pluck('id')->all();

            expect($logs)
                ->toHaveCount(15);

            expect($createdLogIds)
                ->toBe($observedLogIds);

            expect($logs->pluck('catalog_change_batch_id')->all())
                ->toBe($observedBatchIds);

            expect($logs->last()->id)
                ->toBe($failedLogId);

            expect($logs->pluck('action')->all())
                ->toBe(array_merge(
                    array_fill(0, 6, 'deleted'),
                    array_fill(0, 6, 'updated'),
                    array_fill(0, 3, 'created')
                ));

            // -------------------------------------------------
            // Controleer de zes verwijderingslogs van route A.
            // -------------------------------------------------

            foreach ($deletedEntries as $entryId => $entryData) {
                $batchId =
                    $batchIdsByEdition[$entryData['edition_id']];

                $editionLogs = $logs
                    ->where('catalog_change_batch_id', $batchId)
                    ->values();

                expect($editionLogs)
                    ->toHaveCount(3);

                expect($editionLogs->pluck('record_type')->all())
                    ->toBe([
                        'stamp_catalog_value',
                        'stamp_catalog_value',
                        'stamp_catalog_entry',
                    ]);

                expect($editionLogs->pluck('action')->all())
                    ->toBe(array_fill(0, 3, 'deleted'));

                $entryLog = $editionLogs->last();

                expect($entryLog->record_id)
                    ->toBe($entryId);

                expect($entryLog->condition_id)
                    ->toBeNull();

                expect(
                    $normalizeLogValues($entryLog->old_values)
                )->toBe(
                    $normalizeLogValues($originalEntries[$entryId])
                );

                expect($entryLog->new_values)
                    ->toBeNull();

                foreach ($entryData['value_ids'] as $valueId) {
                    $valueLog = $editionLogs
                        ->where('record_type', 'stamp_catalog_value')
                        ->where('record_id', $valueId)
                        ->sole();

                    expect($valueLog->condition_id)
                        ->toBe(
                            $originalValues[$valueId]['condition_id']
                        );

                    expect(
                        $normalizeLogValues($valueLog->old_values)
                    )->toBe(
                        $normalizeLogValues($originalValues[$valueId])
                    );

                    expect($valueLog->new_values)
                        ->toBeNull();

                    expect($valueLog->id)
                        ->toBeLessThan($entryLog->id);
                }
            }

            // -------------------------------------------------
            // Controleer de zes wijzigingslogs van B en C.
            // -------------------------------------------------

            $routeBCLogs = $logs
                ->where(
                    'catalog_change_batch_id',
                    $batchIdsByEdition[$this->editionB1->id]
                )
                ->values();

            expect($routeBCLogs)
                ->toHaveCount(6);

            expect($routeBCLogs->pluck('id')->all())
                ->toBe(
                    $logs->slice(6, 6)->pluck('id')->all()
                );

            foreach ($routeBCLogs as $index => $updateLog) {
                $expected = $expectedUpdateLogs[$index];

                expect($updateLog->record_type)
                    ->toBe($expected['record_type']);

                expect($updateLog->record_id)
                    ->toBe($expected['record_id']);

                expect($updateLog->condition_id)
                    ->toBe($expected['condition_id']);

                expect($updateLog->action)
                    ->toBe('updated');

                expect(
                    $normalizeLogValues($updateLog->old_values)
                )->toBe(
                    $normalizeLogValues($expected['old_values'])
                );

                expect(
                    $normalizeLogValues($updateLog->new_values)
                )->toBe(
                    $normalizeLogValues($expected['new_values'])
                );

                expect($updateLog->old_values)
                    ->not->toHaveKey('updated_at');

                expect($updateLog->new_values)
                    ->not->toHaveKey('updated_at');
            }

            // -------------------------------------------------
            // Controleer de drie aanmaaklogs van route D.
            // -------------------------------------------------

            $routeDLogs = $logs
                ->where('catalog_change_batch_id', $activeBatchId)
                ->values();

            expect($routeDLogs)
                ->toHaveCount(3);

            expect($routeDLogs->pluck('id')->all())
                ->toBe(
                    $logs->slice(12)->pluck('id')->all()
                );

            expect($routeDLogs->pluck('record_type')->all())
                ->toBe([
                    'stamp_catalog_entry',
                    'stamp_catalog_value',
                    'stamp_catalog_value',
                ]);

            expect($routeDLogs->pluck('record_id')->all())
                ->toBe([
                    $createdEntryId,
                    $createdPostfris->id,
                    $createdGestempeld->id,
                ]);

            expect($routeDLogs->pluck('condition_id')->all())
                ->toBe([
                    null,
                    $this->postfris->id,
                    $this->gestempeld->id,
                ]);

            expect($routeDLogs->last()->id)
                ->toBe($failedLogId);

            $createdRecords = [
                $createdEntry,
                $createdPostfris,
                $createdGestempeld,
            ];

            foreach ($routeDLogs as $index => $createdLog) {
                expect($createdLog->action)
                    ->toBe('created');

                expect($createdLog->old_values)
                    ->toBeNull();

                expect($createdLog->new_values)
                    ->toBeArray();

                $storedAttributes = $normalizeLogValues(
                    $createdRecords[$index]->getAttributes()
                );

                foreach (
                    $normalizeLogValues($createdLog->new_values) as $attribute => $attributeValue
                ) {
                    expect($storedAttributes)
                        ->toHaveKey($attribute);

                    expect($storedAttributes[$attribute])
                        ->toBe($attributeValue);
                }
            }

            expect($routeDLogs[0]->new_values['catalog_number'])
                ->toBe('C-5300');

            expect($routeDLogs[0]->new_values['stamp_id'])
                ->toBe($this->stamp->id);

            expect($routeDLogs[0]->new_values['catalog_edition_id'])
                ->toBe($this->editionC1->id);

            expect(
                (float) $routeDLogs[1]->new_values['catalog_value']
            )->toBe(115.75);

            expect(
                (float) $routeDLogs[2]->new_values['catalog_value']
            )->toBe(57.50);

            // -------------------------------------------------
            // Controleer de relaties van alle vijftien logs.
            // -------------------------------------------------

            foreach ($logs as $historyLog) {
                expect($historyLog->stamp_id)
                    ->toBe($this->stamp->id);

                expect($historyLog->stamp_series_id)
                    ->toBeNull();

                expect($historyLog->catalog_change_batch_id)
                    ->toBe(
                        $batchIdsByEdition[$historyLog->catalog_edition_id]
                    );

                expect($historyLog->batch->id)
                    ->toBe($historyLog->catalog_change_batch_id);

                expect($historyLog->batch->catalog_edition_id)
                    ->toBe($historyLog->catalog_edition_id);

                expect($historyLog->catalogEdition->id)
                    ->toBe($historyLog->catalog_edition_id);

                expect($historyLog->stamp->id)
                    ->toBe($this->stamp->id);

                expect($historyLog->stampSeries)
                    ->toBeNull();

                if ($historyLog->condition_id === null) {
                    expect($historyLog->condition)
                        ->toBeNull();
                } else {
                    expect($historyLog->condition->id)
                        ->toBe($historyLog->condition_id);
                }

                $this->assertDatabaseHas('catalog_change_logs', [
                    'id' => $historyLog->id,
                    'catalog_change_batch_id' => $historyLog->catalog_change_batch_id,
                    'catalog_edition_id' => $historyLog->catalog_edition_id,
                    'stamp_id' => $this->stamp->id,
                    'condition_id' => $historyLog->condition_id,
                    'record_type' => $historyLog->record_type,
                    'record_id' => $historyLog->record_id,
                    'action' => $historyLog->action,
                ]);
            }

            expect($activeBatch->logs()->count())
                ->toBe(3);

            // -------------------------------------------------
            // Veroorzaak de uitzondering nadat alle vijftien
            // logs en beide nieuwe waarden zijn opgeslagen.
            // -------------------------------------------------

            throw new RuntimeException($exceptionMessage);
        });

        // -------------------------------------------------
        // Verstuur de gecombineerde aanvraag.
        // -------------------------------------------------

        expect(fn () => $this->put(
            route('backend.stamps.update', $this->stamp),
            $payload
        ))->toThrow(
            RuntimeException::class,
            $exceptionMessage
        );

        // -------------------------------------------------
        // Controleer dat de foutinjectie is bereikt.
        // -------------------------------------------------

        expect($listenerCalls)
            ->toBe(15);

        expect($failureCalls)
            ->toBe(1);

        expect($observedLogIds)
            ->toHaveCount(15);

        expect(array_unique($observedLogIds))
            ->toHaveCount(15);

        expect($observedBatchIds)
            ->toHaveCount(15);

        expect(array_unique($observedBatchIds))
            ->toHaveCount(4);

        expect($createdLogIds)
            ->toBe($observedLogIds);

        expect($failedLogId)
            ->toBeInt();

        expect($failedLogId)
            ->toBe($createdLogIds[14]);

        expect($createdEntryId)
            ->toBeInt();

        expect($createdValueIds)
            ->toHaveCount(2);

        expect(array_unique($createdValueIds))
            ->toHaveCount(2);

        expect($activeBatchId)
            ->toBeInt();

        expect($batchIdsByEdition)
            ->toHaveCount(4);

        expect(array_unique($batchIdsByEdition))
            ->toHaveCount(4);

        expect($capturedHistory)
            ->toBeInstanceOf(CatalogChangeHistoryService::class);

        // -------------------------------------------------
        // Controleer het herstel van de postzegel.
        // -------------------------------------------------

        $restoredStamp = $this->stamp->fresh();

        expect($restoredStamp->getAttributes())
            ->toBe($originalStamp);

        expect($restoredStamp->country_id)
            ->toBe($this->country->id);

        expect($restoredStamp->getRawOriginal('updated_at'))
            ->toBe($originalStamp['updated_at']);

        $this->assertDatabaseMissing('stamps', [
            'id' => $this->stamp->id,
            'country_id' => $this->otherCountry->id,
        ]);

        // -------------------------------------------------
        // Controleer het herstel van beide vertalingen.
        // -------------------------------------------------

        expect(
            $restoredStamp->translations()
                ->where('locale', 'nl')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalDutchTranslation);

        expect(
            $restoredStamp->translations()
                ->where('locale', 'en')
                ->firstOrFail()
                ->getAttributes()
        )->toBe($originalEnglishTranslation);

        $this->assertDatabaseMissing('stamp_translations', [
            'stamp_id' => $this->stamp->id,
            'locale' => 'nl',
            'name' => $payload['translations']['nl']['name'],
        ]);

        // -------------------------------------------------
        // Controleer het herstel van de themakoppeling.
        // -------------------------------------------------

        $restoredThemeIds = $restoredStamp
            ->themes()
            ->orderBy('themes.id')
            ->pluck('themes.id')
            ->all();

        expect($restoredThemeIds)
            ->toBe([$originalTheme->id]);

        expect($restoredThemeIds)
            ->not->toContain($replacementTheme->id);

        expect($originalTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        expect($replacementTheme->fresh())
            ->toBeInstanceOf(Theme::class);

        // -------------------------------------------------
        // Controleer alle oorspronkelijke catalogusvermeldingen.
        // -------------------------------------------------

        foreach ($originalEntries as $entryId => $attributes) {
            $restoredEntry = StampCatalogEntry::query()
                ->findOrFail($entryId);

            expect($restoredEntry->getAttributes())
                ->toBe($attributes);

            expect($restoredEntry->stamp_id)
                ->toBe($this->stamp->id);

            expect($restoredEntry->values()->count())
                ->toBe(2);

            $this->assertDatabaseHas('stamp_catalog_entries', [
                'id' => $entryId,
                'stamp_id' => $this->stamp->id,
                'catalog_edition_id' => $attributes['catalog_edition_id'],
                'catalog_number' => $attributes['catalog_number'],
            ]);
        }

        expect($this->entryA1->fresh()->catalog_number)
            ->toBe('A-100');

        expect($this->entryA2->fresh()->catalog_number)
            ->toBe('A-200');

        expect($this->entryB1->fresh()->catalog_number)
            ->toBe('B-100');

        // -------------------------------------------------
        // Controleer alle oorspronkelijke cataloguswaarden.
        // -------------------------------------------------

        foreach ($originalValues as $valueId => $attributes) {
            $restoredValue = StampCatalogValue::query()
                ->findOrFail($valueId);

            expect($restoredValue->getAttributes())
                ->toBe($attributes);

            expect($restoredValue->needs_review)
                ->toBeFalse();

            expect($restoredValue->catalogEntry->id)
                ->toBe($attributes['stamp_catalog_entry_id']);

            expect($restoredValue->condition->id)
                ->toBe($attributes['condition_id']);

            expect($restoredValue->getRawOriginal('created_at'))
                ->toBe($attributes['created_at']);

            expect($restoredValue->getRawOriginal('updated_at'))
                ->toBe($attributes['updated_at']);

            $this->assertDatabaseHas('stamp_catalog_values', [
                'id' => $valueId,
                'stamp_catalog_entry_id' => $attributes['stamp_catalog_entry_id'],
                'condition_id' => $attributes['condition_id'],
                'catalog_value' => (float) $attributes['catalog_value'],
                'currency' => $attributes['currency'],
                'needs_review' => false,
            ]);
        }

        expect($this->valueB1Postfris->fresh()->catalog_value)
            ->toBe('25.00');

        expect($this->valueB1Gestempeld->fresh()->catalog_value)
            ->toBe('12.50');

        foreach (['B-5100', 'B-5200'] as $number) {
            $this->assertDatabaseMissing('stamp_catalog_entries', [
                'id' => $this->entryB1->id,
                'catalog_number' => $number,
            ]);
        }

        foreach ([95.75, 105.75] as $amount) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Postfris->id,
                'catalog_value' => $amount,
            ]);
        }

        foreach ([47.50, 52.50] as $amount) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $this->valueB1Gestempeld->id,
                'catalog_value' => $amount,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van de nieuwe vermelding.
        // -------------------------------------------------

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'id' => $createdEntryId,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_edition_id' => $this->editionC1->id,
        ]);

        $this->assertDatabaseMissing('stamp_catalog_entries', [
            'stamp_id' => $this->stamp->id,
            'catalog_number' => 'C-5300',
        ]);

        // -------------------------------------------------
        // Controleer de rollback van beide nieuwe waarden.
        // -------------------------------------------------

        foreach ($createdValueIds as $valueId) {
            $this->assertDatabaseMissing('stamp_catalog_values', [
                'id' => $valueId,
                'currency' => 'GBP',
            ]);
        }

        expect(
            StampCatalogValue::query()
                ->where('currency', 'GBP')
                ->count()
        )->toBe(0);

        expect($restoredStamp->catalogEntries()->count())
            ->toBe(3);

        expect($restoredStamp->catalogValues()->count())
            ->toBe(6);

        // -------------------------------------------------
        // Controleer de rollback van alle vier batches.
        // -------------------------------------------------

        foreach ($batchIdsByEdition as $editionId => $batchId) {
            $this->assertDatabaseMissing('catalog_change_batches', [
                'id' => $batchId,
            ]);

            $this->assertDatabaseMissing('catalog_change_batches', [
                'catalog_edition_id' => $editionId,
            ]);

            expect(
                CatalogChangeBatch::query()->find($batchId)
            )->toBeNull();

            $this->assertDatabaseMissing('catalog_change_logs', [
                'catalog_change_batch_id' => $batchId,
            ]);
        }

        // -------------------------------------------------
        // Controleer de rollback van alle vijftien logs.
        // -------------------------------------------------

        foreach ($createdLogIds as $logId) {
            $this->assertDatabaseMissing('catalog_change_logs', [
                'id' => $logId,
            ]);

            expect(
                CatalogChangeLog::query()->find($logId)
            )->toBeNull();
        }

        // -------------------------------------------------
        // Controleer expliciet de log die de fout veroorzaakte.
        // -------------------------------------------------

        $this->assertDatabaseMissing('catalog_change_logs', [
            'id' => $failedLogId,
            'catalog_edition_id' => $this->editionC1->id,
            'record_type' => 'stamp_catalog_value',
            'condition_id' => $this->gestempeld->id,
            'action' => 'created',
        ]);

        expect(
            CatalogChangeLog::query()->find($failedLogId)
        )->toBeNull();

        $this->assertDatabaseMissing('catalog_change_logs', [
            'stamp_id' => $this->stamp->id,
        ]);

        // -------------------------------------------------
        // Vergelijk alle oorspronkelijke databasegegevens.
        // -------------------------------------------------

        $restoredData = $snapshot();

        foreach ($originalData as $key => $originalRecords) {
            expect($restoredData)
                ->toHaveKey($key);

            expect($restoredData[$key])
                ->toBe($originalRecords);
        }

        // -------------------------------------------------
        // Controleer de definitieve databaseaantallen.
        // -------------------------------------------------

        $this->assertDatabaseCount('users', 2);

        $this->assertDatabaseCount('countries', 2);

        $this->assertDatabaseCount('stamps', 1);

        $this->assertDatabaseCount('catalogs', 3);

        $this->assertDatabaseCount('catalog_editions', 4);

        $this->assertDatabaseCount('conditions', 2);

        $this->assertDatabaseCount('catalog_condition_rules', 6);

        $this->assertDatabaseCount('stamp_catalog_entries', 3);

        $this->assertDatabaseCount('stamp_catalog_values', 6);

        $this->assertDatabaseCount('stamp_images', 0);

        $this->assertDatabaseCount('catalog_change_batches', 0);

        $this->assertDatabaseCount('catalog_change_logs', 0);

        expect(
            StampCatalogValue::query()
                ->where('needs_review', true)
                ->count()
        )->toBe(0);

        // -------------------------------------------------
        // Controleer de opschoning van de geschiedeniscontext.
        // -------------------------------------------------

        expect($this->history->getActiveBatch())
            ->toBeNull();

        expect($capturedHistory->getActiveBatch())
            ->toBeNull();

        expect(
            app(CatalogChangeHistoryService::class)
                ->getActiveBatch()
        )->toBeNull();

        $this->assertAuthenticatedAs($this->user);
    } finally {
        // -------------------------------------------------
        // Herstel altijd de oorspronkelijke testomgeving.
        // -------------------------------------------------

        CatalogChangeLog::setEventDispatcher(
            $originalDispatcher
        );

        $this->withExceptionHandling();

        $this->travelBack();
    }
});
