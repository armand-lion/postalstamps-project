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
use App\Services\CatalogEditionDuplicationService;

beforeEach(function () {
    app()->setLocale('nl');

    // Maak een administrator aan.
    $this->admin = User::factory()->create([
        'name' => 'Administrator Duplicatiegeschiedenis',
        'is_admin' => true,
    ]);

    // Maak een gewone gebruiker aan.
    $this->user = User::factory()->create([
        'name' => 'Gebruiker Duplicatiegeschiedenis',
        'is_admin' => false,
    ]);

    // Maak een land aan voor de postzegels.
    $this->country = Country::create([
        'name' => 'Testland Duplicatiegeschiedenis',
        'iso_code' => 'DG',
        'entity_type' => 'country',
        'slug' => 'testland-duplicatiegeschiedenis',
        'is_active' => true,
    ]);

    // Maak een catalogus aan.
    $this->catalog = Catalog::create([
        'name' => 'Testcatalogus Duplicatiegeschiedenis',
        'abbreviation' => 'TDG',
    ]);

    // Koppel het land aan de catalogus.
    $this->catalog->countries()->attach($this->country->id);

    // Maak de broneditie aan.
    $this->sourceEdition = CatalogEdition::create([
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2026',
        'edition_year' => 2026,
        'currency' => 'EUR',
    ]);

    // Maak de doeleditie aan.
    $this->targetEdition = CatalogEdition::create([
        'catalog_id' => $this->catalog->id,
        'edition_name' => 'Editie 2027',
        'edition_year' => 2027,
        'currency' => 'EUR',
    ]);

    // Maak de waarderingscategorie postfris aan.
    $this->postfris = Condition::create([
        'slug' => 'postfris',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    // Maak de waarderingscategorie gestempeld aan.
    $this->gestempeld = Condition::create([
        'slug' => 'gestempeld',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    // Maak de geschiedenisservice beschikbaar.
    $this->history = app(CatalogChangeHistoryService::class);

    // Maak de duplicatieservice beschikbaar.
    $this->service = app(CatalogEditionDuplicationService::class);
});

test('een succesvolle duplicatie maakt precies een geschiedenisbatch aan', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '100a',
        'notes' => 'Oorspronkelijke bronvermelding.',
    ]);

    // Bewaar de oorspronkelijke gegevens van de bronvermelding.
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull()
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(0);

    // Voer de duplicatiebewerking uit als administrator.
    $result = $this->service->copyStampEntries(
        $this->sourceEdition,
        $this->targetEdition,
        false,
        $this->admin
    );

    // Controleer de teruggegeven aantallen.
    expect($result)->toBe([
        'copied' => 1,
        'skipped' => 0,
    ]);

    // Controleer de aantallen na duplicatie.
    expect($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(1)
        ->and(StampCatalogEntry::count())->toBe(2)
        ->and(StampCatalogValue::count())->toBe(0)
        ->and(CatalogChangeBatch::count())->toBe(1)
        ->and(CatalogChangeLog::count())->toBe(1);

    // Haal de aangemaakte geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de belangrijkste gegevens van de geschiedenisbatch.
    expect($batch->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($batch->user_id)->toBe($this->admin->id)
        ->and($batch->operation)->toBe('duplicate')
        ->and($batch->logs()->count())->toBe(1);

    // Haal de gekopieerde catalogusvermelding op.
    $targetEntry = $this->targetEdition->stampEntries()
        ->where('stamp_id', $stamp->id)
        ->sole();

    // Controleer de gekopieerde catalogusvermelding.
    expect($targetEntry->id)->not->toBe($sourceEntry->id)
        ->and($targetEntry->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($targetEntry->stamp_id)->toBe($stamp->id)
        ->and($targetEntry->catalog_number)->toBe('100a')
        ->and($targetEntry->notes)->toBe('Oorspronkelijke bronvermelding.');

    // Haal de geregistreerde wijzigingslog op.
    $log = $batch->logs()->sole();

    // Controleer de koppeling tussen de batch en de wijzigingslog.
    expect($log->catalog_change_batch_id)->toBe($batch->id)
        ->and($log->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($log->stamp_id)->toBe($stamp->id)
        ->and($log->record_id)->toBe($targetEntry->id)
        ->and($log->action)->toBe('created')
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values)->toBeArray();

    // Controleer dat er geen geschiedenisbatch voor de broneditie is.
    expect(CatalogChangeBatch::query()
        ->where('catalog_edition_id', $this->sourceEdition->id)
        ->count())->toBe(0);

    // Controleer dat er geen wijzigingslogs voor de broneditie zijn.
    expect(CatalogChangeLog::query()
        ->where('catalog_edition_id', $this->sourceEdition->id)
        ->count())->toBe(0);

    // Controleer dat de oorspronkelijke bronvermelding ongewijzigd is.
    expect($sourceEntry->fresh()->getAttributes())
        ->toBe($originalSourceEntry);

    // Controleer dat de actieve geschiedenisbatch is opgeruimd.
    expect($this->history->getActiveBatch())->toBeNull();
});

test('de uitvoerende gebruiker wordt correct geregistreerd bij duplicatie', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '200b',
        'notes' => 'Bronvermelding voor gebruikersregistratie.',
    ]);

    // Bewaar de oorspronkelijke gegevens.
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalUser = $this->user->fresh()->getAttributes();
    $originalAdmin = $this->admin->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect($this->user->is_admin)->toBeFalse()
        ->and($this->admin->is_admin)->toBeTrue()
        ->and($this->user->id)->not->toBe($this->admin->id)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull()
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0);

    // Voer de duplicatiebewerking uit als gewone gebruiker.
    $result = $this->service->copyStampEntries(
        $this->sourceEdition,
        $this->targetEdition,
        false,
        $this->user
    );

    // Controleer het resultaat van de duplicatie.
    expect($result)->toBe([
        'copied' => 1,
        'skipped' => 0,
    ]);

    // Controleer de databaseaantallen.
    expect(CatalogChangeBatch::count())->toBe(1)
        ->and(CatalogChangeLog::count())->toBe(1)
        ->and(StampCatalogEntry::count())->toBe(2)
        ->and(StampCatalogValue::count())->toBe(0)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(1);

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de geregistreerde gebruikersgegevens.
    expect($batch->user_id)->toBe($this->user->id)
        ->and($batch->user_name)->toBe($this->user->name)
        ->and($batch->user_name)->toBe('Gebruiker Duplicatiegeschiedenis')
        ->and($batch->user_id)->not->toBe($this->admin->id)
        ->and($batch->user_name)->not->toBe($this->admin->name)
        ->and($batch->operation)->toBe('duplicate')
        ->and($batch->catalog_edition_id)->toBe($this->targetEdition->id);

    // Controleer de gebruikersrelatie.
    expect($batch->user)->toBeInstanceOf(User::class)
        ->and($batch->user->id)->toBe($this->user->id)
        ->and($batch->user->name)->toBe($this->user->name)
        ->and($batch->user->is_admin)->toBeFalse();

    // Controleer dat de administrator geen geschiedenisbatch heeft gekregen.
    expect(CatalogChangeBatch::query()
        ->where('user_id', $this->admin->id)
        ->count())->toBe(0);

    // Controleer dat de gewone gebruiker precies één geschiedenisbatch heeft.
    expect(CatalogChangeBatch::query()
        ->where('user_id', $this->user->id)
        ->count())->toBe(1);

    // Controleer de gekopieerde catalogusvermelding.
    $targetEntry = $this->targetEdition->stampEntries()
        ->where('stamp_id', $stamp->id)
        ->sole();

    expect($targetEntry->id)->not->toBe($sourceEntry->id)
        ->and($targetEntry->catalog_number)->toBe('200b')
        ->and($targetEntry->notes)->toBe('Bronvermelding voor gebruikersregistratie.');

    // Controleer de wijzigingslog.
    $log = $batch->logs()->sole();

    expect($log->catalog_change_batch_id)->toBe($batch->id)
        ->and($log->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($log->record_id)->toBe($targetEntry->id)
        ->and($log->action)->toBe('created');

    // Controleer dat de oorspronkelijke gegevens ongewijzigd zijn.
    expect($sourceEntry->fresh()->getAttributes())->toBe($originalSourceEntry)
        ->and($this->user->fresh()->getAttributes())->toBe($originalUser)
        ->and($this->admin->fresh()->getAttributes())->toBe($originalAdmin)
        ->and(User::count())->toBe(2);

    // Controleer dat de actieve geschiedenisbatch is opgeruimd.
    expect($this->history->getActiveBatch())->toBeNull();
});

test('duplicatie zonder opgegeven gebruiker registreert geschiedenis zonder gebruikersgegevens', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '300c',
        'notes' => 'Bronvermelding voor duplicatie zonder gebruiker.',
    ]);

    // Bewaar de oorspronkelijke gegevens.
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalUser = $this->user->fresh()->getAttributes();
    $originalAdmin = $this->admin->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(auth()->check())->toBeFalse()
        ->and(User::count())->toBe(2)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull()
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(0);

    // Voer de duplicatie uit zonder een gebruiker mee te geven.
    $result = $this->service->copyStampEntries(
        $this->sourceEdition,
        $this->targetEdition
    );

    // Controleer het resultaat van de duplicatie.
    expect($result)->toBe([
        'copied' => 1,
        'skipped' => 0,
    ]);

    // Controleer de databaseaantallen.
    expect(CatalogChangeBatch::count())->toBe(1)
        ->and(CatalogChangeLog::count())->toBe(1)
        ->and(StampCatalogEntry::count())->toBe(2)
        ->and(StampCatalogValue::count())->toBe(0)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(1);

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer dat er geen gebruiker is geregistreerd.
    expect($batch->user_id)->toBeNull()
        ->and($batch->user_name)->toBeNull()
        ->and($batch->user)->toBeNull()
        ->and($batch->operation)->toBe('duplicate')
        ->and($batch->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($batch->logs()->count())->toBe(1);

    // Controleer dat geen bestaande gebruiker aan de batch is gekoppeld.
    expect(CatalogChangeBatch::query()
        ->whereNotNull('user_id')
        ->count())->toBe(0);

    expect(CatalogChangeBatch::query()
        ->whereNotNull('user_name')
        ->count())->toBe(0);

    // Haal de gekopieerde catalogusvermelding op.
    $targetEntry = $this->targetEdition->stampEntries()
        ->where('stamp_id', $stamp->id)
        ->sole();

    // Controleer de gekopieerde catalogusvermelding.
    expect($targetEntry->id)->not->toBe($sourceEntry->id)
        ->and($targetEntry->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($targetEntry->stamp_id)->toBe($stamp->id)
        ->and($targetEntry->catalog_number)->toBe('300c')
        ->and($targetEntry->notes)->toBe('Bronvermelding voor duplicatie zonder gebruiker.');

    // Haal de geregistreerde wijzigingslog op.
    $log = $batch->logs()->sole();

    // Controleer dat de wijziging ondanks het ontbreken van een gebruiker is geregistreerd.
    expect($log->catalog_change_batch_id)->toBe($batch->id)
        ->and($log->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($log->stamp_id)->toBe($stamp->id)
        ->and($log->record_id)->toBe($targetEntry->id)
        ->and($log->action)->toBe('created')
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values)->toBeArray();

    // Controleer dat de oorspronkelijke gegevens ongewijzigd zijn.
    expect($sourceEntry->fresh()->getAttributes())->toBe($originalSourceEntry)
        ->and($this->user->fresh()->getAttributes())->toBe($originalUser)
        ->and($this->admin->fresh()->getAttributes())->toBe($originalAdmin)
        ->and(User::count())->toBe(2);

    // Controleer dat er geen gebruiker is ingelogd.
    expect(auth()->check())->toBeFalse();

    // Controleer dat de actieve geschiedenisbatch is opgeruimd.
    expect($this->history->getActiveBatch())->toBeNull();
});

test('catalogus- en editiemetadata worden correct geregistreerd bij duplicatie', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '400d',
        'notes' => 'Bronvermelding voor controle van metadata.',
    ]);

    // Bewaar de oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1)
        ->and(CatalogEdition::count())->toBe(2)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull()
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0);

    // Voer de duplicatiebewerking uit.
    $result = $this->service->copyStampEntries(
        $this->sourceEdition,
        $this->targetEdition,
        false,
        $this->admin
    );

    // Controleer het resultaat van de duplicatie.
    expect($result)->toBe([
        'copied' => 1,
        'skipped' => 0,
    ]);

    // Controleer de databaseaantallen.
    expect(Catalog::count())->toBe(1)
        ->and(CatalogEdition::count())->toBe(2)
        ->and(CatalogChangeBatch::count())->toBe(1)
        ->and(CatalogChangeLog::count())->toBe(1)
        ->and(StampCatalogEntry::count())->toBe(2)
        ->and(StampCatalogValue::count())->toBe(0);

    // Haal de geschiedenisbatch op.
    $batch = CatalogChangeBatch::query()->sole();

    // Controleer de geregistreerde catalogus- en editiemetadata.
    expect($batch->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($batch->catalog_edition_id)->not->toBe($this->sourceEdition->id)
        ->and($batch->catalog_name)->toBe($this->catalog->name)
        ->and($batch->catalog_name)->toBe('Testcatalogus Duplicatiegeschiedenis')
        ->and($batch->catalog_edition_name)->toBe($this->targetEdition->edition_name)
        ->and($batch->catalog_edition_name)->toBe('Editie 2027')
        ->and($batch->catalog_edition_name)->not->toBe($this->sourceEdition->edition_name)
        ->and($batch->operation)->toBe('duplicate');

    // Controleer de beschrijving van de duplicatiebewerking.
    expect($batch->description)
        ->toBe('Duplicatie van catalogusvermeldingen vanuit Editie 2026')
        ->toContain($this->sourceEdition->edition_name);

    // Controleer de relatie met de doeleditie.
    expect($batch->catalogEdition)->toBeInstanceOf(CatalogEdition::class)
        ->and($batch->catalogEdition->id)->toBe($this->targetEdition->id)
        ->and($batch->catalogEdition->edition_name)->toBe('Editie 2027')
        ->and($batch->catalogEdition->edition_year)->toBe(2027)
        ->and($batch->catalogEdition->catalog_id)->toBe($this->catalog->id);

    // Controleer de catalogusrelatie via de doeleditie.
    expect($batch->catalogEdition->catalog)->toBeInstanceOf(Catalog::class)
        ->and($batch->catalogEdition->catalog->id)->toBe($this->catalog->id)
        ->and($batch->catalogEdition->catalog->name)->toBe($this->catalog->name)
        ->and($batch->catalogEdition->catalog->abbreviation)->toBe('TDG');

    // Controleer de geregistreerde gebruiker.
    expect($batch->user_id)->toBe($this->admin->id)
        ->and($batch->user_name)->toBe($this->admin->name);

    // Controleer de tijdstempels van de geschiedenisbatch.
    expect($batch->created_at)->not->toBeNull()
        ->and($batch->updated_at)->not->toBeNull();

    // Haal de gekopieerde catalogusvermelding op.
    $targetEntry = $this->targetEdition->stampEntries()
        ->where('stamp_id', $stamp->id)
        ->sole();

    // Controleer de gekopieerde catalogusvermelding.
    expect($targetEntry->id)->not->toBe($sourceEntry->id)
        ->and($targetEntry->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($targetEntry->catalog_number)->toBe('400d')
        ->and($targetEntry->notes)->toBe('Bronvermelding voor controle van metadata.');

    // Controleer de wijzigingslog.
    $log = $batch->logs()->sole();

    expect($log->catalog_change_batch_id)->toBe($batch->id)
        ->and($log->catalog_edition_id)->toBe($this->targetEdition->id)
        ->and($log->record_id)->toBe($targetEntry->id)
        ->and($log->action)->toBe('created');

    // Controleer dat de broneditie geen geschiedenisregistratie heeft.
    expect(CatalogChangeBatch::query()
        ->where('catalog_edition_id', $this->sourceEdition->id)
        ->count())->toBe(0);

    expect(CatalogChangeLog::query()
        ->where('catalog_edition_id', $this->sourceEdition->id)
        ->count())->toBe(0);

    // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
    expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
        ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
        ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition)
        ->and($sourceEntry->fresh()->getAttributes())->toBe($originalSourceEntry);

    // Controleer dat de actieve geschiedenisbatch is opgeruimd.
    expect($this->history->getActiveBatch())->toBeNull();
});

test('nieuwe catalogusvermeldingen worden volledig geregistreerd in de wijzigingsgeschiedenis', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '00500e',
        'notes' => "Oorspronkelijke catalogusvermelding.\nBijzondere uitgifte – België.",
    ]);

    // Bewaar de oorspronkelijke gegevens.
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(0)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg voor verschillende tijdstempels tussen bron- en doelvermelding.
    $this->travel(1)->minutes();

    try {
        // Voer de duplicatiebewerking uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            false,
            $this->admin
        );

        // Controleer het resultaat van de duplicatie.
        expect($result)->toBe([
            'copied' => 1,
            'skipped' => 0,
        ]);

        // Controleer de databaseaantallen.
        expect(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(1)
            ->and(StampCatalogEntry::count())->toBe(2)
            ->and(StampCatalogValue::count())->toBe(0)
            ->and(Stamp::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2);

        // Haal de geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Haal de gekopieerde catalogusvermelding op.
        $targetEntry = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stamp->id)
            ->sole();

        // Controleer de gegevens van de nieuwe catalogusvermelding.
        expect($targetEntry->id)->not->toBe($sourceEntry->id)
            ->and($targetEntry->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($targetEntry->stamp_id)->toBe($stamp->id)
            ->and($targetEntry->catalog_number)->toBe('00500e')
            ->and($targetEntry->notes)->toBe($sourceEntry->notes)
            ->and($targetEntry->created_at->greaterThan($sourceEntry->created_at))->toBeTrue()
            ->and($targetEntry->values()->count())->toBe(0);

        // Haal de geregistreerde wijzigingslog op.
        $log = $batch->logs()->sole();

        // Controleer alle identificerende gegevens van de wijzigingslog.
        expect($log->catalog_change_batch_id)->toBe($batch->id)
            ->and($log->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($log->stamp_id)->toBe($stamp->id)
            ->and($log->stamp_series_id)->toBeNull()
            ->and($log->condition_id)->toBeNull()
            ->and($log->record_id)->toBe($targetEntry->id)
            ->and($log->record_type)->toBe('stamp_catalog_entry')
            ->and($log->action)->toBe('created');

        // Controleer de oude waarden.
        expect($log->old_values)->toBeNull();

        // Controleer de nieuwe waarden.
        expect($log->new_values)->toBeArray()
            ->and($log->new_values)->toHaveKeys([
                'id',
                'catalog_edition_id',
                'stamp_id',
                'catalog_number',
                'notes',
                'created_at',
                'updated_at',
            ]);

        // Controleer de volledige inhoud van de geregistreerde gegevens.
        expect($log->new_values['id'])->toBe($targetEntry->id)
            ->and($log->new_values['catalog_edition_id'])->toBe($this->targetEdition->id)
            ->and($log->new_values['stamp_id'])->toBe($stamp->id)
            ->and($log->new_values['catalog_number'])->toBe('00500e')
            ->and($log->new_values['notes'])->toBe($sourceEntry->notes)
            ->and($log->new_values['created_at'])->toBe($targetEntry->getRawOriginal('created_at'))
            ->and($log->new_values['updated_at'])->toBe($targetEntry->getRawOriginal('updated_at'));

        // Vergelijk alle geregistreerde attributen met de opgeslagen doelvermelding.
        expect($log->new_values)->toEqual($targetEntry->getAttributes());

        // Controleer dat de nieuwe waarden niet naar de bronvermelding verwijzen.
        expect($log->new_values['id'])->not->toBe($sourceEntry->id)
            ->and($log->new_values['catalog_edition_id'])->not->toBe($this->sourceEdition->id)
            ->and($log->new_values['created_at'])->not->toBe($sourceEntry->getRawOriginal('created_at'));

        // Controleer de relatie met de geschiedenisbatch.
        expect($log->batch)->toBeInstanceOf(CatalogChangeBatch::class)
            ->and($log->batch->id)->toBe($batch->id);

        // Controleer de relatie met de cataloguseditie.
        expect($log->catalogEdition)->toBeInstanceOf(CatalogEdition::class)
            ->and($log->catalogEdition->id)->toBe($this->targetEdition->id);

        // Controleer de relatie met de postzegel.
        expect($log->stamp)->toBeInstanceOf(Stamp::class)
            ->and($log->stamp->id)->toBe($stamp->id);

        // Controleer de niet-toepasselijke relaties.
        expect($log->stampSeries)->toBeNull()
            ->and($log->condition)->toBeNull();

        // Controleer de tijdstempels van de wijzigingslog.
        expect($log->created_at)->not->toBeNull()
            ->and($log->updated_at)->not->toBeNull();

        // Controleer dat er geen wijzigingslogs voor de broneditie zijn.
        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
        expect($sourceEntry->fresh()->getAttributes())->toBe($originalSourceEntry)
            ->and($stamp->fresh()->getAttributes())->toBe($originalStamp)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('nieuwe cataloguswaarden worden volledig geregistreerd in de wijzigingsgeschiedenis', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = StampCatalogEntry::create([
        'catalog_edition_id' => $this->sourceEdition->id,
        'stamp_id' => $stamp->id,
        'catalog_number' => '600f',
        'notes' => 'Bronvermelding voor geschiedenis van cataloguswaarden.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $sourcePostfrisValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '125.75',
        'currency' => 'EUR',
        'notes' => "Postfrisse cataloguswaarde.\nBijzondere kwaliteit – België.",
        'needs_review' => false,
    ]);

    // Maak een gestempelde cataloguswaarde met nulwaarde aan.
    $sourceGestempeldValue = $sourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => null,
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke gegevens.
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalPostfrisValue = $sourcePostfrisValue->fresh()->getAttributes();
    $originalGestempeldValue = $sourceGestempeldValue->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(2)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->sourceEdition->stampValues()->count())->toBe(2)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg voor verschillende tijdstempels tussen bron- en doelgegevens.
    $this->travel(1)->minutes();

    try {
        // Voer de duplicatie inclusief cataloguswaarden uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        );

        // Controleer het resultaat van de duplicatie.
        expect($result)->toBe([
            'copied' => 1,
            'skipped' => 0,
        ]);

        // Controleer de databaseaantallen.
        expect(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(3)
            ->and(StampCatalogEntry::count())->toBe(2)
            ->and(StampCatalogValue::count())->toBe(4)
            ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
            ->and($this->targetEdition->stampEntries()->count())->toBe(1)
            ->and($this->sourceEdition->stampValues()->count())->toBe(2)
            ->and($this->targetEdition->stampValues()->count())->toBe(2)
            ->and(Stamp::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2);

        // Haal de geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($batch->user_id)->toBe($this->admin->id)
            ->and($batch->operation)->toBe('duplicate')
            ->and($batch->logs()->count())->toBe(3);

        // Haal de gekopieerde catalogusvermelding op.
        $targetEntry = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stamp->id)
            ->sole();

        // Controleer de gekopieerde catalogusvermelding.
        expect($targetEntry->id)->not->toBe($sourceEntry->id)
            ->and($targetEntry->catalog_number)->toBe('600f')
            ->and($targetEntry->notes)->toBe($sourceEntry->notes)
            ->and($targetEntry->values()->count())->toBe(2);

        // Haal de wijzigingslog van de catalogusvermelding op.
        $entryLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->sole();

        expect($entryLog->record_id)->toBe($targetEntry->id)
            ->and($entryLog->action)->toBe('created')
            ->and($entryLog->old_values)->toBeNull()
            ->and($entryLog->new_values)->toEqual($targetEntry->getAttributes());

        // Controleer dat precies twee cataloguswaardelogs bestaan.
        expect($batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count())->toBe(2);

        // Controleer beide gekopieerde cataloguswaarden.
        foreach ([$sourcePostfrisValue, $sourceGestempeldValue] as $sourceValue) {
            $targetValue = $targetEntry->values()
                ->where('condition_id', $sourceValue->condition_id)
                ->sole();

            // Controleer de opgeslagen cataloguswaarde.
            expect($targetValue->id)->not->toBe($sourceValue->id)
                ->and($targetValue->stamp_catalog_entry_id)->toBe($targetEntry->id)
                ->and($targetValue->condition_id)->toBe($sourceValue->condition_id)
                ->and($targetValue->catalog_value)->toBe($sourceValue->catalog_value)
                ->and($targetValue->currency)->toBe($sourceValue->currency)
                ->and($targetValue->notes)->toBe($sourceValue->notes)
                ->and($targetValue->needs_review)->toBeTrue()
                ->and($targetValue->created_at->greaterThan($sourceValue->created_at))->toBeTrue();

            // Haal de bijbehorende wijzigingslog op.
            $log = $batch->logs()
                ->where('record_type', 'stamp_catalog_value')
                ->where('record_id', $targetValue->id)
                ->sole();

            // Controleer alle identificerende gegevens.
            expect($log->catalog_change_batch_id)->toBe($batch->id)
                ->and($log->catalog_edition_id)->toBe($this->targetEdition->id)
                ->and($log->stamp_id)->toBe($stamp->id)
                ->and($log->stamp_series_id)->toBeNull()
                ->and($log->condition_id)->toBe($sourceValue->condition_id)
                ->and($log->record_type)->toBe('stamp_catalog_value')
                ->and($log->record_id)->toBe($targetValue->id)
                ->and($log->action)->toBe('created');

            // Controleer de oude en nieuwe waarden.
            expect($log->old_values)->toBeNull()
                ->and($log->new_values)->toBeArray()
                ->and($log->new_values)->toHaveKeys([
                    'id',
                    'stamp_catalog_entry_id',
                    'condition_id',
                    'catalog_value',
                    'currency',
                    'notes',
                    'needs_review',
                    'created_at',
                    'updated_at',
                ]);

            // Controleer de inhoud van de geregistreerde gegevens.
            expect($log->new_values['id'])->toBe($targetValue->id)
                ->and($log->new_values['stamp_catalog_entry_id'])->toBe($targetEntry->id)
                ->and($log->new_values['condition_id'])->toBe($sourceValue->condition_id)
                ->and((string) $log->new_values['currency'])->toBe($targetValue->currency)
                ->and($log->new_values['notes'])->toBe($targetValue->notes)
                ->and($log->new_values['created_at'])->toBe($targetValue->getRawOriginal('created_at'))
                ->and($log->new_values['updated_at'])->toBe($targetValue->getRawOriginal('updated_at'));

            // Controleer de numerieke cataloguswaarde.
            expect((float) $log->new_values['catalog_value'])
                ->toBe((float) $targetValue->catalog_value);

            // Controleer de controlemarkering.
            expect(in_array($log->new_values['needs_review'], [true, 1], true))
                ->toBeTrue();

            // Controleer alle geregistreerde attributen gezamenlijk.
            expect($log->new_values)->toEqual($targetValue->getAttributes());

            // Controleer dat de log niet naar de bronwaarde verwijst.
            expect($log->new_values['id'])->not->toBe($sourceValue->id)
                ->and($log->new_values['stamp_catalog_entry_id'])->not->toBe($sourceEntry->id);

            // Controleer de Eloquent-relaties.
            expect($log->batch)->toBeInstanceOf(CatalogChangeBatch::class)
                ->and($log->batch->id)->toBe($batch->id)
                ->and($log->catalogEdition)->toBeInstanceOf(CatalogEdition::class)
                ->and($log->catalogEdition->id)->toBe($this->targetEdition->id)
                ->and($log->stamp)->toBeInstanceOf(Stamp::class)
                ->and($log->stamp->id)->toBe($stamp->id)
                ->and($log->condition)->toBeInstanceOf(Condition::class)
                ->and($log->condition->id)->toBe($sourceValue->condition_id)
                ->and($log->stampSeries)->toBeNull();

            // Controleer de tijdstempels van de wijzigingslog.
            expect($log->created_at)->not->toBeNull()
                ->and($log->updated_at)->not->toBeNull();
        }

        // Controleer de exacte cataloguswaarden in de doeleditie.
        expect($targetEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole()->catalog_value)->toBe('125.75');

        expect($targetEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole()->catalog_value)->toBe('0.00');

        // Controleer dat alle wijzigingslogs nieuwe records betreffen.
        expect($batch->logs()
            ->where('action', 'created')
            ->count())->toBe(3);

        // Controleer dat er geen geschiedenis voor de broneditie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
        expect($sourceEntry->fresh()->getAttributes())->toBe($originalSourceEntry)
            ->and($sourcePostfrisValue->fresh()->getAttributes())->toBe($originalPostfrisValue)
            ->and($sourceGestempeldValue->fresh()->getAttributes())->toBe($originalGestempeldValue)
            ->and($stamp->fresh()->getAttributes())->toBe($originalStamp)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer dat de oorspronkelijke controlemarkeringen behouden zijn.
        expect($sourcePostfrisValue->fresh()->needs_review)->toBeFalse()
            ->and($sourceGestempeldValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('meerdere catalogusvermeldingen inclusief cataloguswaarden worden binnen een geschiedenisbatch geregistreerd', function () {
    // Maak drie postzegels aan.
    $firstStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2023,
    ]);

    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $thirdStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak drie catalogusvermeldingen aan in de broneditie.
    $firstSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => '100a',
        'notes' => 'Bronvermelding eerste postzegel.',
    ]);

    $secondSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => '200b',
        'notes' => 'Bronvermelding tweede postzegel.',
    ]);

    $thirdSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $thirdStamp->id,
        'catalog_number' => '300c',
        'notes' => 'Bronvermelding derde postzegel.',
    ]);

    // Maak twee cataloguswaarden aan voor de eerste postzegel.
    $firstSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '15.75',
        'currency' => 'EUR',
        'notes' => 'Eerste postzegel postfris.',
        'needs_review' => false,
    ]);

    $firstSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '5.25',
        'currency' => 'EUR',
        'notes' => 'Eerste postzegel gestempeld.',
        'needs_review' => false,
    ]);

    // Maak twee cataloguswaarden aan voor de tweede postzegel.
    $secondSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '22.50',
        'currency' => 'EUR',
        'notes' => 'Tweede postzegel postfris.',
        'needs_review' => false,
    ]);

    $secondSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Tweede postzegel gestempeld.',
        'needs_review' => true,
    ]);

    // De derde postzegel heeft bewust geen cataloguswaarden.

    // Bewaar alle oorspronkelijke bronvermeldingen.
    $originalSourceEntries = $this->sourceEdition->stampEntries()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalSourceValues = $this->sourceEdition->stampValues()
        ->orderBy('stamp_catalog_values.id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogus- en editiegegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and(Stamp::count())->toBe(3)
        ->and(StampCatalogEntry::count())->toBe(3)
        ->and(StampCatalogValue::count())->toBe(4)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(3)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->sourceEdition->stampValues()->count())->toBe(4)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg voor verschillende tijdstempels tussen bron- en doelgegevens.
    $this->travel(1)->minutes();

    try {
        // Voer alle duplicaties binnen één bewerking uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        );

        // Controleer het resultaat van de duplicatie.
        expect($result)->toBe([
            'copied' => 3,
            'skipped' => 0,
        ]);

        // Controleer de databaseaantallen.
        expect(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(7)
            ->and(Stamp::count())->toBe(3)
            ->and(StampCatalogEntry::count())->toBe(6)
            ->and(StampCatalogValue::count())->toBe(8)
            ->and(Catalog::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2)
            ->and($this->sourceEdition->stampEntries()->count())->toBe(3)
            ->and($this->targetEdition->stampEntries()->count())->toBe(3)
            ->and($this->sourceEdition->stampValues()->count())->toBe(4)
            ->and($this->targetEdition->stampValues()->count())->toBe(4);

        // Haal de enige geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($batch->user_id)->toBe($this->admin->id)
            ->and($batch->user_name)->toBe($this->admin->name)
            ->and($batch->operation)->toBe('duplicate')
            ->and($batch->catalog_name)->toBe($this->catalog->name)
            ->and($batch->catalog_edition_name)->toBe($this->targetEdition->edition_name)
            ->and($batch->logs()->count())->toBe(7);

        // Controleer de verdeling van de wijzigingslogs.
        expect($batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->count())->toBe(3);

        expect($batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count())->toBe(4);

        expect($batch->logs()
            ->where('action', 'created')
            ->count())->toBe(7);

        // Controleer alle gekopieerde catalogusvermeldingen.
        foreach ([
            $firstSourceEntry,
            $secondSourceEntry,
            $thirdSourceEntry,
        ] as $sourceEntry) {
            $targetEntry = $this->targetEdition->stampEntries()
                ->where('stamp_id', $sourceEntry->stamp_id)
                ->sole();

            // Controleer de nieuwe catalogusvermelding.
            expect($targetEntry->id)->not->toBe($sourceEntry->id)
                ->and($targetEntry->catalog_edition_id)->toBe($this->targetEdition->id)
                ->and($targetEntry->stamp_id)->toBe($sourceEntry->stamp_id)
                ->and($targetEntry->catalog_number)->toBe($sourceEntry->catalog_number)
                ->and($targetEntry->notes)->toBe($sourceEntry->notes)
                ->and($targetEntry->values()->count())->toBe($sourceEntry->values()->count());

            // Haal de bijbehorende wijzigingslog op.
            $entryLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->where('record_id', $targetEntry->id)
                ->sole();

            // Controleer de wijzigingslog van de catalogusvermelding.
            expect($entryLog->catalog_change_batch_id)->toBe($batch->id)
                ->and($entryLog->catalog_edition_id)->toBe($this->targetEdition->id)
                ->and($entryLog->stamp_id)->toBe($sourceEntry->stamp_id)
                ->and($entryLog->stamp_series_id)->toBeNull()
                ->and($entryLog->condition_id)->toBeNull()
                ->and($entryLog->action)->toBe('created')
                ->and($entryLog->old_values)->toBeNull()
                ->and($entryLog->new_values)->toEqual($targetEntry->getAttributes());

            // Controleer alle bijbehorende cataloguswaarden.
            foreach ($sourceEntry->values()->get() as $sourceValue) {
                $targetValue = $targetEntry->values()
                    ->where('condition_id', $sourceValue->condition_id)
                    ->sole();

                // Controleer de gekopieerde cataloguswaarde.
                expect($targetValue->id)->not->toBe($sourceValue->id)
                    ->and($targetValue->stamp_catalog_entry_id)->toBe($targetEntry->id)
                    ->and($targetValue->condition_id)->toBe($sourceValue->condition_id)
                    ->and($targetValue->catalog_value)->toBe($sourceValue->catalog_value)
                    ->and($targetValue->currency)->toBe($sourceValue->currency)
                    ->and($targetValue->notes)->toBe($sourceValue->notes)
                    ->and($targetValue->needs_review)->toBeTrue();

                // Haal de bijbehorende wijzigingslog op.
                $valueLog = $batch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $targetValue->id)
                    ->sole();

                // Controleer de wijzigingslog van de cataloguswaarde.
                expect($valueLog->catalog_change_batch_id)->toBe($batch->id)
                    ->and($valueLog->catalog_edition_id)->toBe($this->targetEdition->id)
                    ->and($valueLog->stamp_id)->toBe($sourceEntry->stamp_id)
                    ->and($valueLog->stamp_series_id)->toBeNull()
                    ->and($valueLog->condition_id)->toBe($sourceValue->condition_id)
                    ->and($valueLog->action)->toBe('created')
                    ->and($valueLog->old_values)->toBeNull()
                    ->and($valueLog->new_values)->toEqual($targetValue->getAttributes());
            }
        }

        // Controleer het aantal wijzigingslogs per postzegel.
        expect($batch->logs()
            ->where('stamp_id', $firstStamp->id)
            ->count())->toBe(3);

        expect($batch->logs()
            ->where('stamp_id', $secondStamp->id)
            ->count())->toBe(3);

        expect($batch->logs()
            ->where('stamp_id', $thirdStamp->id)
            ->count())->toBe(1);

        // Controleer dat de derde postzegel geen cataloguswaardelogs heeft.
        expect($batch->logs()
            ->where('stamp_id', $thirdStamp->id)
            ->where('record_type', 'stamp_catalog_value')
            ->count())->toBe(0);

        // Controleer dat alle wijzigingslogs bij dezelfde batch horen.
        expect(CatalogChangeLog::query()
            ->where('catalog_change_batch_id', $batch->id)
            ->count())->toBe(7);

        // Controleer dat alle wijzigingslogs bij de doeleditie horen.
        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->count())->toBe(7);

        // Controleer dat de broneditie geen geschiedenisregistratie heeft.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        // Controleer dat alle oorspronkelijke bronvermeldingen ongewijzigd zijn.
        expect($this->sourceEdition->stampEntries()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all())->toBe($originalSourceEntries);

        // Controleer dat alle oorspronkelijke cataloguswaarden ongewijzigd zijn.
        expect($this->sourceEdition->stampValues()
            ->orderBy('stamp_catalog_values.id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all())->toBe($originalSourceValues);

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('bestaande catalogusvermeldingen worden overgeslagen zonder onterechte geschiedenisregistratie', function () {
    // Maak drie postzegels aan.
    $firstStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2023,
    ]);

    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $thirdStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak drie catalogusvermeldingen aan in de broneditie.
    $firstSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => '100a',
        'notes' => 'Bronvermelding eerste postzegel.',
    ]);

    $secondSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => '200b',
        'notes' => 'Bronvermelding tweede postzegel.',
    ]);

    $thirdSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $thirdStamp->id,
        'catalog_number' => '300c',
        'notes' => 'Bronvermelding derde postzegel.',
    ]);

    // Maak cataloguswaarden aan voor de eerste bronvermelding.
    $firstSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '25.00',
        'currency' => 'EUR',
        'notes' => 'Postfrisse bronwaarde eerste postzegel.',
        'needs_review' => false,
    ]);

    $firstSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '7.50',
        'currency' => 'EUR',
        'notes' => 'Gestempelde bronwaarde eerste postzegel.',
        'needs_review' => false,
    ]);

    // Maak cataloguswaarden aan voor de tweede bronvermelding.
    $secondSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '40.00',
        'currency' => 'EUR',
        'notes' => 'Postfrisse bronwaarde tweede postzegel.',
        'needs_review' => false,
    ]);

    $secondSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '12.50',
        'currency' => 'EUR',
        'notes' => 'Gestempelde bronwaarde tweede postzegel.',
        'needs_review' => false,
    ]);

    // Maak een cataloguswaarde aan voor de derde bronvermelding.
    $thirdSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Postfrisse bronwaarde derde postzegel.',
        'needs_review' => false,
    ]);

    // Maak een bestaande catalogusvermelding aan in de doeleditie.
    $existingTargetEntry = $this->targetEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => 'BESTAAND-100',
        'notes' => 'Bestaande doelvermelding die behouden moet blijven.',
    ]);

    // Maak een afwijkende bestaande cataloguswaarde aan.
    $existingTargetValue = $existingTargetEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '99.95',
        'currency' => 'USD',
        'notes' => 'Bestaande doelwaarde die behouden moet blijven.',
        'needs_review' => false,
    ]);

    // De bestaande doelvermelding heeft bewust geen gestempelde waarde.

    // Bewaar alle oorspronkelijke bronvermeldingen.
    $originalSourceEntries = $this->sourceEdition->stampEntries()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke bronwaarden.
    $originalSourceValues = $this->sourceEdition->stampValues()
        ->orderBy('stamp_catalog_values.id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Bewaar de bestaande doelgegevens.
    $originalTargetEntry = $existingTargetEntry->fresh()->getAttributes();
    $originalTargetValue = $existingTargetValue->fresh()->getAttributes();

    // Bewaar de oorspronkelijke catalogus- en editiegegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and(Stamp::count())->toBe(3)
        ->and(StampCatalogEntry::count())->toBe(4)
        ->and(StampCatalogValue::count())->toBe(6)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(3)
        ->and($this->targetEdition->stampEntries()->count())->toBe(1)
        ->and($this->sourceEdition->stampValues()->count())->toBe(5)
        ->and($this->targetEdition->stampValues()->count())->toBe(1)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg dat eventuele wijzigingen aan tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Voer de gemengde duplicatiebewerking uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        );

        // Controleer het resultaat van de duplicatie.
        expect($result)->toBe([
            'copied' => 2,
            'skipped' => 1,
        ]);

        // Controleer de databaseaantallen.
        expect(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(5)
            ->and(Stamp::count())->toBe(3)
            ->and(StampCatalogEntry::count())->toBe(6)
            ->and(StampCatalogValue::count())->toBe(9)
            ->and($this->sourceEdition->stampEntries()->count())->toBe(3)
            ->and($this->targetEdition->stampEntries()->count())->toBe(3)
            ->and($this->sourceEdition->stampValues()->count())->toBe(5)
            ->and($this->targetEdition->stampValues()->count())->toBe(4);

        // Haal de enige geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($batch->user_id)->toBe($this->admin->id)
            ->and($batch->user_name)->toBe($this->admin->name)
            ->and($batch->operation)->toBe('duplicate')
            ->and($batch->logs()->count())->toBe(5);

        // Controleer de verdeling van de wijzigingslogs.
        expect($batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->count())->toBe(2);

        expect($batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count())->toBe(3);

        expect($batch->logs()
            ->where('action', 'created')
            ->count())->toBe(5);

        // Controleer dat de bestaande doelvermelding volledig behouden is.
        expect($existingTargetEntry->fresh()->getAttributes())
            ->toBe($originalTargetEntry);

        expect($existingTargetEntry->fresh()->catalog_number)
            ->toBe('BESTAAND-100');

        expect($existingTargetEntry->fresh()->notes)
            ->toBe('Bestaande doelvermelding die behouden moet blijven.');

        // Controleer dat de bestaande doelwaarde volledig behouden is.
        expect($existingTargetValue->fresh()->getAttributes())
            ->toBe($originalTargetValue);

        expect($existingTargetValue->fresh()->catalog_value)->toBe('99.95')
            ->and($existingTargetValue->fresh()->currency)->toBe('USD')
            ->and($existingTargetValue->fresh()->notes)
            ->toBe('Bestaande doelwaarde die behouden moet blijven.')
            ->and($existingTargetValue->fresh()->needs_review)->toBeFalse();

        // Controleer dat de ontbrekende gestempelde waarde niet is toegevoegd.
        expect($existingTargetEntry->values()->count())->toBe(1);

        expect($existingTargetEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen wijzigingslogs voor de eerste postzegel bestaan.
        expect(CatalogChangeLog::query()
            ->where('stamp_id', $firstStamp->id)
            ->count())->toBe(0);

        // Controleer dat de bestaande doelvermelding geen wijzigingslog heeft.
        expect(CatalogChangeLog::query()
            ->where('record_type', 'stamp_catalog_entry')
            ->where('record_id', $existingTargetEntry->id)
            ->count())->toBe(0);

        // Controleer dat de bestaande doelwaarde geen wijzigingslog heeft.
        expect(CatalogChangeLog::query()
            ->where('record_type', 'stamp_catalog_value')
            ->where('record_id', $existingTargetValue->id)
            ->count())->toBe(0);

        // Controleer beide nieuw aangemaakte doelvermeldingen.
        foreach ([$secondSourceEntry, $thirdSourceEntry] as $sourceEntry) {
            $targetEntry = $this->targetEdition->stampEntries()
                ->where('stamp_id', $sourceEntry->stamp_id)
                ->sole();

            // Controleer de nieuwe catalogusvermelding.
            expect($targetEntry->id)->not->toBe($sourceEntry->id)
                ->and($targetEntry->catalog_edition_id)->toBe($this->targetEdition->id)
                ->and($targetEntry->stamp_id)->toBe($sourceEntry->stamp_id)
                ->and($targetEntry->catalog_number)->toBe($sourceEntry->catalog_number)
                ->and($targetEntry->notes)->toBe($sourceEntry->notes)
                ->and($targetEntry->values()->count())->toBe($sourceEntry->values()->count());

            // Haal de wijzigingslog van de nieuwe catalogusvermelding op.
            $entryLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->where('record_id', $targetEntry->id)
                ->sole();

            // Controleer de wijzigingslog.
            expect($entryLog->catalog_change_batch_id)->toBe($batch->id)
                ->and($entryLog->catalog_edition_id)->toBe($this->targetEdition->id)
                ->and($entryLog->stamp_id)->toBe($sourceEntry->stamp_id)
                ->and($entryLog->stamp_series_id)->toBeNull()
                ->and($entryLog->condition_id)->toBeNull()
                ->and($entryLog->action)->toBe('created')
                ->and($entryLog->old_values)->toBeNull()
                ->and($entryLog->new_values)->toEqual($targetEntry->getAttributes());

            // Controleer alle nieuw aangemaakte cataloguswaarden.
            foreach ($sourceEntry->values()->get() as $sourceValue) {
                $targetValue = $targetEntry->values()
                    ->where('condition_id', $sourceValue->condition_id)
                    ->sole();

                // Controleer de nieuwe cataloguswaarde.
                expect($targetValue->id)->not->toBe($sourceValue->id)
                    ->and($targetValue->stamp_catalog_entry_id)->toBe($targetEntry->id)
                    ->and($targetValue->condition_id)->toBe($sourceValue->condition_id)
                    ->and($targetValue->catalog_value)->toBe($sourceValue->catalog_value)
                    ->and($targetValue->currency)->toBe($sourceValue->currency)
                    ->and($targetValue->notes)->toBe($sourceValue->notes)
                    ->and($targetValue->needs_review)->toBeTrue();

                // Haal de wijzigingslog van de nieuwe cataloguswaarde op.
                $valueLog = $batch->logs()
                    ->where('record_type', 'stamp_catalog_value')
                    ->where('record_id', $targetValue->id)
                    ->sole();

                // Controleer de wijzigingslog.
                expect($valueLog->catalog_change_batch_id)->toBe($batch->id)
                    ->and($valueLog->catalog_edition_id)->toBe($this->targetEdition->id)
                    ->and($valueLog->stamp_id)->toBe($sourceEntry->stamp_id)
                    ->and($valueLog->stamp_series_id)->toBeNull()
                    ->and($valueLog->condition_id)->toBe($sourceValue->condition_id)
                    ->and($valueLog->action)->toBe('created')
                    ->and($valueLog->old_values)->toBeNull()
                    ->and($valueLog->new_values)->toEqual($targetValue->getAttributes());
            }
        }

        // Controleer het aantal wijzigingslogs per postzegel.
        expect($batch->logs()
            ->where('stamp_id', $firstStamp->id)
            ->count())->toBe(0);

        expect($batch->logs()
            ->where('stamp_id', $secondStamp->id)
            ->count())->toBe(3);

        expect($batch->logs()
            ->where('stamp_id', $thirdStamp->id)
            ->count())->toBe(2);

        // Controleer dat geen update- of verwijderingslogs zijn aangemaakt.
        expect($batch->logs()
            ->whereIn('action', ['updated', 'deleted'])
            ->count())->toBe(0);

        // Controleer dat alle wijzigingslogs bij de doeleditie horen.
        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->count())->toBe(5);

        // Controleer dat de broneditie geen geschiedenisregistratie heeft.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        // Controleer dat alle oorspronkelijke bronvermeldingen ongewijzigd zijn.
        expect($this->sourceEdition->stampEntries()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all())->toBe($originalSourceEntries);

        // Controleer dat alle oorspronkelijke bronwaarden ongewijzigd zijn.
        expect($this->sourceEdition->stampValues()
            ->orderBy('stamp_catalog_values.id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all())->toBe($originalSourceValues);

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('niet toegestane waarderingscategorieen worden uitgesloten van de wijzigingsgeschiedenis', function () {
    // Maak een algemene waarderingsregel aan die alleen postfris toestaat.
    $rule = CatalogConditionRule::create([
        'catalog_id' => $this->catalog->id,
        'country_id' => null,
        'condition_id' => $this->postfris->id,
        'issue_year_from' => null,
        'issue_year_to' => null,
        'sort_order' => 1,
    ]);

    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => '900i',
        'notes' => 'Bronvermelding voor controle van waarderingsregels.',
    ]);

    // Maak een toegestane postfrisse cataloguswaarde aan.
    $allowedSourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '45.75',
        'currency' => 'EUR',
        'notes' => 'Toegestane postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een niet-toegestane gestempelde cataloguswaarde aan.
    $disallowedSourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '18.50',
        'currency' => 'EUR',
        'notes' => 'Niet-toegestane gestempelde cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke gegevens.
    $originalRule = $rule->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalAllowedValue = $allowedSourceValue->fresh()->getAttributes();
    $originalDisallowedValue = $disallowedSourceValue->fresh()->getAttributes();
    $originalPostfris = $this->postfris->fresh()->getAttributes();
    $originalGestempeld = $this->gestempeld->fresh()->getAttributes();
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogConditionRule::count())->toBe(1)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(2)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->sourceEdition->stampValues()->count())->toBe(2)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg voor verschillende tijdstempels tussen bron- en doelgegevens.
    $this->travel(1)->minutes();

    try {
        // Voer de duplicatie inclusief cataloguswaarden uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        );

        // Controleer het resultaat van de duplicatie.
        expect($result)->toBe([
            'copied' => 1,
            'skipped' => 0,
        ]);

        // Controleer de databaseaantallen.
        expect(CatalogConditionRule::count())->toBe(1)
            ->and(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(2)
            ->and(StampCatalogValue::count())->toBe(3)
            ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
            ->and($this->targetEdition->stampEntries()->count())->toBe(1)
            ->and($this->sourceEdition->stampValues()->count())->toBe(2)
            ->and($this->targetEdition->stampValues()->count())->toBe(1);

        // Haal de enige geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($batch->user_id)->toBe($this->admin->id)
            ->and($batch->user_name)->toBe($this->admin->name)
            ->and($batch->operation)->toBe('duplicate')
            ->and($batch->logs()->count())->toBe(2);

        // Haal de gekopieerde catalogusvermelding op.
        $targetEntry = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stamp->id)
            ->sole();

        // Controleer de gekopieerde catalogusvermelding.
        expect($targetEntry->id)->not->toBe($sourceEntry->id)
            ->and($targetEntry->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($targetEntry->stamp_id)->toBe($stamp->id)
            ->and($targetEntry->catalog_number)->toBe('900i')
            ->and($targetEntry->notes)->toBe($sourceEntry->notes)
            ->and($targetEntry->values()->count())->toBe(1);

        // Haal de toegestane cataloguswaarde op.
        $allowedTargetValue = $targetEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Controleer de toegestane cataloguswaarde.
        expect($allowedTargetValue->id)->not->toBe($allowedSourceValue->id)
            ->and($allowedTargetValue->stamp_catalog_entry_id)->toBe($targetEntry->id)
            ->and($allowedTargetValue->condition_id)->toBe($this->postfris->id)
            ->and($allowedTargetValue->catalog_value)->toBe('45.75')
            ->and($allowedTargetValue->currency)->toBe('EUR')
            ->and($allowedTargetValue->notes)->toBe($allowedSourceValue->notes)
            ->and($allowedTargetValue->needs_review)->toBeTrue();

        // Controleer dat de niet-toegestane cataloguswaarde ontbreekt.
        expect($targetEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->exists())->toBeFalse();

        // Haal de wijzigingslog van de catalogusvermelding op.
        $entryLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->sole();

        // Controleer de wijzigingslog van de catalogusvermelding.
        expect($entryLog->catalog_change_batch_id)->toBe($batch->id)
            ->and($entryLog->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($entryLog->stamp_id)->toBe($stamp->id)
            ->and($entryLog->stamp_series_id)->toBeNull()
            ->and($entryLog->condition_id)->toBeNull()
            ->and($entryLog->record_id)->toBe($targetEntry->id)
            ->and($entryLog->action)->toBe('created')
            ->and($entryLog->old_values)->toBeNull()
            ->and($entryLog->new_values)->toEqual($targetEntry->getAttributes());

        // Haal de wijzigingslog van de toegestane cataloguswaarde op.
        $valueLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->sole();

        // Controleer de wijzigingslog van de toegestane cataloguswaarde.
        expect($valueLog->catalog_change_batch_id)->toBe($batch->id)
            ->and($valueLog->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($valueLog->stamp_id)->toBe($stamp->id)
            ->and($valueLog->stamp_series_id)->toBeNull()
            ->and($valueLog->condition_id)->toBe($this->postfris->id)
            ->and($valueLog->record_id)->toBe($allowedTargetValue->id)
            ->and($valueLog->action)->toBe('created')
            ->and($valueLog->old_values)->toBeNull()
            ->and($valueLog->new_values)->toEqual($allowedTargetValue->getAttributes());

        // Controleer de geregistreerde cataloguswaarde.
        expect((float) $valueLog->new_values['catalog_value'])->toBe(45.75)
            ->and($valueLog->new_values['condition_id'])->toBe($this->postfris->id);

        // Controleer de geregistreerde controlemarkering.
        expect(in_array($valueLog->new_values['needs_review'], [true, 1], true))
            ->toBeTrue();

        // Controleer de waarderingscategorierelatie.
        expect($valueLog->condition)->toBeInstanceOf(Condition::class)
            ->and($valueLog->condition->id)->toBe($this->postfris->id);

        // Controleer dat er geen log voor de niet-toegestane categorie bestaat.
        expect(CatalogChangeLog::query()
            ->where('condition_id', $this->gestempeld->id)
            ->count())->toBe(0);

        // Controleer dat precies één cataloguswaardelog bestaat.
        expect($batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count())->toBe(1);

        // Controleer dat beide wijzigingslogs nieuwe records betreffen.
        expect($batch->logs()
            ->where('action', 'created')
            ->count())->toBe(2);

        // Controleer dat de broneditie geen geschiedenisregistratie heeft.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        // Controleer dat de oorspronkelijke waarderingsregel behouden is.
        expect($rule->fresh()->getAttributes())->toBe($originalRule);

        // Controleer dat beide oorspronkelijke waarderingscategorieën behouden zijn.
        expect($this->postfris->fresh()->getAttributes())->toBe($originalPostfris)
            ->and($this->gestempeld->fresh()->getAttributes())->toBe($originalGestempeld);

        // Controleer dat alle oorspronkelijke brongegevens ongewijzigd zijn.
        expect($sourceEntry->fresh()->getAttributes())->toBe($originalSourceEntry)
            ->and($allowedSourceValue->fresh()->getAttributes())->toBe($originalAllowedValue)
            ->and($disallowedSourceValue->fresh()->getAttributes())->toBe($originalDisallowedValue);

        // Controleer dat de niet-toegestane bronwaarde behouden is.
        expect($disallowedSourceValue->fresh()->catalog_value)->toBe('18.50')
            ->and($disallowedSourceValue->fresh()->needs_review)->toBeFalse();

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('duplicatie van een lege broneditie laat geen geschiedenisbatch achter', function () {
    // Maak een postzegel aan die uitsluitend in de doeleditie voorkomt.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een bestaande catalogusvermelding aan in de doeleditie.
    $existingTargetEntry = $this->targetEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => 'BESTAAND-1000',
        'notes' => 'Bestaande doelvermelding bij een lege broneditie.',
    ]);

    // Maak een bestaande cataloguswaarde aan in de doeleditie.
    $existingTargetValue = $existingTargetEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '37.50',
        'currency' => 'EUR',
        'notes' => 'Bestaande cataloguswaarde die behouden moet blijven.',
        'needs_review' => false,
    ]);

    // Bewaar alle oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalTargetEntry = $existingTargetEntry->fresh()->getAttributes();
    $originalTargetValue = $existingTargetValue->fresh()->getAttributes();
    $originalAdmin = $this->admin->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1)
        ->and(CatalogEdition::count())->toBe(2)
        ->and(Stamp::count())->toBe(1)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(1)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(0)
        ->and($this->sourceEdition->stampValues()->count())->toBe(0)
        ->and($this->targetEdition->stampEntries()->count())->toBe(1)
        ->and($this->targetEdition->stampValues()->count())->toBe(1)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg dat eventuele wijzigingen aan tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Voer de duplicatie van de lege broneditie uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        );

        // Controleer dat er niets is gekopieerd of overgeslagen.
        expect($result)->toBe([
            'copied' => 0,
            'skipped' => 0,
        ]);

        // Controleer dat alle databaseaantallen ongewijzigd zijn.
        expect(Catalog::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2)
            ->and(Stamp::count())->toBe(1)
            ->and(StampCatalogEntry::count())->toBe(1)
            ->and(StampCatalogValue::count())->toBe(1)
            ->and(CatalogChangeBatch::count())->toBe(0)
            ->and(CatalogChangeLog::count())->toBe(0);

        // Controleer dat de broneditie nog steeds leeg is.
        expect($this->sourceEdition->stampEntries()->count())->toBe(0)
            ->and($this->sourceEdition->stampValues()->count())->toBe(0);

        // Controleer dat de doeleditie ongewijzigd is.
        expect($this->targetEdition->stampEntries()->count())->toBe(1)
            ->and($this->targetEdition->stampValues()->count())->toBe(1);

        // Haal de bestaande catalogusvermelding opnieuw op.
        $targetEntry = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stamp->id)
            ->sole();

        // Controleer dat dezelfde catalogusvermelding behouden is.
        expect($targetEntry->id)->toBe($existingTargetEntry->id)
            ->and($targetEntry->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($targetEntry->stamp_id)->toBe($stamp->id)
            ->and($targetEntry->catalog_number)->toBe('BESTAAND-1000')
            ->and($targetEntry->notes)
            ->toBe('Bestaande doelvermelding bij een lege broneditie.')
            ->and($targetEntry->values()->count())->toBe(1);

        // Haal de bestaande cataloguswaarde opnieuw op.
        $targetValue = $targetEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Controleer dat dezelfde cataloguswaarde behouden is.
        expect($targetValue->id)->toBe($existingTargetValue->id)
            ->and($targetValue->stamp_catalog_entry_id)->toBe($targetEntry->id)
            ->and($targetValue->condition_id)->toBe($this->postfris->id)
            ->and($targetValue->catalog_value)->toBe('37.50')
            ->and($targetValue->currency)->toBe('EUR')
            ->and($targetValue->notes)
            ->toBe('Bestaande cataloguswaarde die behouden moet blijven.')
            ->and($targetValue->needs_review)->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de broneditie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de doeleditie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de gebruiker bestaat.
        expect(CatalogChangeBatch::query()
            ->where('user_id', $this->admin->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen duplicatiebatch is achtergebleven.
        expect(CatalogChangeBatch::query()
            ->where('operation', 'duplicate')
            ->exists())->toBeFalse();

        // Controleer dat er geen wijzigingslogs voor de postzegel bestaan.
        expect(CatalogChangeLog::query()
            ->where('stamp_id', $stamp->id)
            ->exists())->toBeFalse();

        // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition)
            ->and($stamp->fresh()->getAttributes())->toBe($originalStamp)
            ->and($existingTargetEntry->fresh()->getAttributes())->toBe($originalTargetEntry)
            ->and($existingTargetValue->fresh()->getAttributes())->toBe($originalTargetValue)
            ->and($this->admin->fresh()->getAttributes())->toBe($originalAdmin);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('alle bestaande catalogusvermeldingen worden overgeslagen zonder achterblijvende geschiedenisbatch', function () {
    // Maak twee postzegels aan.
    $firstStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak twee catalogusvermeldingen aan in de broneditie.
    $firstSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => '1100a',
        'notes' => 'Oorspronkelijke eerste bronvermelding.',
    ]);

    $secondSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => '1100b',
        'notes' => 'Oorspronkelijke tweede bronvermelding.',
    ]);

    // Maak twee cataloguswaarden aan voor de eerste bronvermelding.
    $firstSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '25.00',
        'currency' => 'EUR',
        'notes' => 'Postfrisse bronwaarde.',
        'needs_review' => false,
    ]);

    $firstSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '7.50',
        'currency' => 'EUR',
        'notes' => 'Gestempelde bronwaarde.',
        'needs_review' => false,
    ]);

    // Maak een cataloguswaarde aan voor de tweede bronvermelding.
    $secondSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Postfrisse bronwaarde met nulwaarde.',
        'needs_review' => true,
    ]);

    // Maak beide catalogusvermeldingen ook aan in de doeleditie.
    $firstTargetEntry = $this->targetEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => 'BESTAAND-1100A',
        'notes' => 'Bestaande eerste doelvermelding.',
    ]);

    $secondTargetEntry = $this->targetEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => 'BESTAAND-1100B',
        'notes' => 'Bestaande tweede doelvermelding.',
    ]);

    // Maak een afwijkende cataloguswaarde aan voor de eerste doelvermelding.
    $firstTargetValue = $firstTargetEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '89.95',
        'currency' => 'USD',
        'notes' => 'Bestaande doelwaarde die behouden moet blijven.',
        'needs_review' => false,
    ]);

    // De eerste doelvermelding heeft bewust geen gestempelde waarde.
    // De tweede doelvermelding heeft bewust helemaal geen cataloguswaarden.

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
    $originalEntries = StampCatalogEntry::query()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = StampCatalogValue::query()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogus- en editiegegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Bewaar de oorspronkelijke postzegelgegevens.
    $originalFirstStamp = $firstStamp->fresh()->getAttributes();
    $originalSecondStamp = $secondStamp->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1)
        ->and(CatalogEdition::count())->toBe(2)
        ->and(Stamp::count())->toBe(2)
        ->and(StampCatalogEntry::count())->toBe(4)
        ->and(StampCatalogValue::count())->toBe(4)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(2)
        ->and($this->targetEdition->stampEntries()->count())->toBe(2)
        ->and($this->sourceEdition->stampValues()->count())->toBe(3)
        ->and($this->targetEdition->stampValues()->count())->toBe(1)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg dat eventuele wijzigingen aan tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Voer de duplicatie inclusief cataloguswaarden uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        );

        // Controleer dat alle catalogusvermeldingen zijn overgeslagen.
        expect($result)->toBe([
            'copied' => 0,
            'skipped' => 2,
        ]);

        // Controleer dat alle databaseaantallen ongewijzigd zijn.
        expect(Catalog::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2)
            ->and(Stamp::count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(4)
            ->and(StampCatalogValue::count())->toBe(4)
            ->and(CatalogChangeBatch::count())->toBe(0)
            ->and(CatalogChangeLog::count())->toBe(0);

        // Controleer de aantallen per cataloguseditie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->targetEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(3)
            ->and($this->targetEdition->stampValues()->count())->toBe(1);

        // Haal de eerste bestaande doelvermelding opnieuw op.
        $firstExistingEntry = $this->targetEdition->stampEntries()
            ->where('stamp_id', $firstStamp->id)
            ->sole();

        // Controleer dat de eerste doelvermelding behouden is.
        expect($firstExistingEntry->id)->toBe($firstTargetEntry->id)
            ->and($firstExistingEntry->catalog_number)->toBe('BESTAAND-1100A')
            ->and($firstExistingEntry->notes)->toBe('Bestaande eerste doelvermelding.')
            ->and($firstExistingEntry->values()->count())->toBe(1);

        // Haal de bestaande cataloguswaarde opnieuw op.
        $existingValue = $firstExistingEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Controleer dat de bestaande cataloguswaarde behouden is.
        expect($existingValue->id)->toBe($firstTargetValue->id)
            ->and($existingValue->stamp_catalog_entry_id)->toBe($firstTargetEntry->id)
            ->and($existingValue->condition_id)->toBe($this->postfris->id)
            ->and($existingValue->catalog_value)->toBe('89.95')
            ->and($existingValue->currency)->toBe('USD')
            ->and($existingValue->notes)
            ->toBe('Bestaande doelwaarde die behouden moet blijven.')
            ->and($existingValue->needs_review)->toBeFalse();

        // Controleer dat de ontbrekende gestempelde waarde niet is toegevoegd.
        expect($firstExistingEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->exists())->toBeFalse();

        // Haal de tweede bestaande doelvermelding opnieuw op.
        $secondExistingEntry = $this->targetEdition->stampEntries()
            ->where('stamp_id', $secondStamp->id)
            ->sole();

        // Controleer dat de tweede doelvermelding behouden is.
        expect($secondExistingEntry->id)->toBe($secondTargetEntry->id)
            ->and($secondExistingEntry->catalog_number)->toBe('BESTAAND-1100B')
            ->and($secondExistingEntry->notes)->toBe('Bestaande tweede doelvermelding.')
            ->and($secondExistingEntry->values()->count())->toBe(0);

        // Controleer dat de ontbrekende postfrisse nulwaarde niet is toegevoegd.
        expect($secondExistingEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de broneditie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de doeleditie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de gebruiker bestaat.
        expect(CatalogChangeBatch::query()
            ->where('user_id', $this->admin->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen duplicatiebatch is achtergebleven.
        expect(CatalogChangeBatch::query()
            ->where('operation', 'duplicate')
            ->exists())->toBeFalse();

        // Controleer dat er geen wijzigingslogs voor beide postzegels bestaan.
        expect(CatalogChangeLog::query()
            ->whereIn('stamp_id', [$firstStamp->id, $secondStamp->id])
            ->exists())->toBeFalse();

        // Controleer dat alle oorspronkelijke catalogusvermeldingen ongewijzigd zijn.
        expect(StampCatalogEntry::query()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all())->toBe($originalEntries);

        // Controleer dat alle oorspronkelijke cataloguswaarden ongewijzigd zijn.
        expect(StampCatalogValue::query()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all())->toBe($originalValues);

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer de oorspronkelijke postzegelgegevens.
        expect($firstStamp->fresh()->getAttributes())->toBe($originalFirstStamp)
            ->and($secondStamp->fresh()->getAttributes())->toBe($originalSecondStamp);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('herhaalde duplicatie veroorzaakt geen dubbele geschiedenisregistratie en behoudt bestaande geschiedenis', function () {
    // Maak twee postzegels aan.
    $firstStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak twee catalogusvermeldingen aan in de broneditie.
    $firstSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => '1200a',
        'notes' => 'Eerste bronvermelding voor herhaalde duplicatie.',
    ]);

    $secondSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => '1200b',
        'notes' => 'Tweede bronvermelding voor herhaalde duplicatie.',
    ]);

    // Maak twee cataloguswaarden aan voor de eerste bronvermelding.
    $firstSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '35.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    $firstSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '8.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een cataloguswaarde aan voor de tweede bronvermelding.
    $secondSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke cataloguswaarde met nulwaarde.',
        'needs_review' => true,
    ]);

    // Bewaar de oorspronkelijke bronvermeldingen.
    $originalSourceEntries = $this->sourceEdition->stampEntries()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke bronwaarden.
    $originalSourceValues = $this->sourceEdition->stampValues()
        ->orderBy('stamp_catalog_values.id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogus- en editiegegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Bewaar de oorspronkelijke gebruikersgegevens.
    $originalAdmin = $this->admin->fresh()->getAttributes();
    $originalUser = $this->user->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and(Stamp::count())->toBe(2)
        ->and(StampCatalogEntry::count())->toBe(2)
        ->and(StampCatalogValue::count())->toBe(3)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(2)
        ->and($this->sourceEdition->stampValues()->count())->toBe(3)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg voor verschillende tijdstempels.
    $this->travel(1)->minutes();

    try {
        // Voer de eerste duplicatie uit als administrator.
        $firstResult = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        );

        // Controleer het resultaat van de eerste duplicatie.
        expect($firstResult)->toBe([
            'copied' => 2,
            'skipped' => 0,
        ]);

        // Controleer de databaseaantallen na de eerste duplicatie.
        expect(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(5)
            ->and(StampCatalogEntry::count())->toBe(4)
            ->and(StampCatalogValue::count())->toBe(6)
            ->and($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(3)
            ->and($this->targetEdition->stampEntries()->count())->toBe(2)
            ->and($this->targetEdition->stampValues()->count())->toBe(3)
            ->and($this->history->getActiveBatch())->toBeNull();

        // Haal de oorspronkelijke geschiedenisbatch op.
        $firstBatch = CatalogChangeBatch::query()->sole();

        // Controleer de oorspronkelijke geschiedenisbatch.
        expect($firstBatch->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($firstBatch->user_id)->toBe($this->admin->id)
            ->and($firstBatch->user_name)->toBe($this->admin->name)
            ->and($firstBatch->operation)->toBe('duplicate')
            ->and($firstBatch->logs()->count())->toBe(5);

        // Controleer de verdeling van de oorspronkelijke wijzigingslogs.
        expect($firstBatch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->count())->toBe(2);

        expect($firstBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count())->toBe(3);

        expect($firstBatch->logs()
            ->where('action', 'created')
            ->count())->toBe(5);

        // Bewaar alle gegevens van de oorspronkelijke geschiedenisbatch.
        $originalBatch = $firstBatch->fresh()->getAttributes();

        // Bewaar alle oorspronkelijke wijzigingslogs.
        $originalLogs = CatalogChangeLog::query()
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
            ->all();

        // Bewaar alle aangemaakte doelvermeldingen.
        $originalTargetEntries = $this->targetEdition->stampEntries()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all();

        // Bewaar alle aangemaakte doelwaarden.
        $originalTargetValues = $this->targetEdition->stampValues()
            ->orderBy('stamp_catalog_values.id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all();

        // Verplaats de testtijd opnieuw.
        $this->travel(1)->minutes();

        // Voer dezelfde duplicatie uit als gewone gebruiker.
        $secondResult = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->user
        );

        // Controleer dat alle catalogusvermeldingen zijn overgeslagen.
        expect($secondResult)->toBe([
            'copied' => 0,
            'skipped' => 2,
        ]);

        // Controleer dat alle databaseaantallen ongewijzigd zijn.
        expect(Catalog::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2)
            ->and(Stamp::count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(4)
            ->and(StampCatalogValue::count())->toBe(6)
            ->and(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(5);

        // Controleer de aantallen per cataloguseditie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(3)
            ->and($this->targetEdition->stampEntries()->count())->toBe(2)
            ->and($this->targetEdition->stampValues()->count())->toBe(3);

        // Haal de enige overgebleven geschiedenisbatch op.
        $remainingBatch = CatalogChangeBatch::query()->sole();

        // Controleer dat de oorspronkelijke geschiedenisbatch behouden is.
        expect($remainingBatch->id)->toBe($firstBatch->id)
            ->and($remainingBatch->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($remainingBatch->user_id)->toBe($this->admin->id)
            ->and($remainingBatch->user_name)->toBe($this->admin->name)
            ->and($remainingBatch->operation)->toBe('duplicate')
            ->and($remainingBatch->logs()->count())->toBe(5);

        // Controleer alle oorspronkelijke batchgegevens.
        expect($remainingBatch->getAttributes())->toBe($originalBatch);

        // Controleer dat alle oorspronkelijke wijzigingslogs behouden zijn.
        expect(CatalogChangeLog::query()
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
            ->all())->toBe($originalLogs);

        // Controleer dat alle wijzigingslogs bij de oorspronkelijke batch horen.
        expect(CatalogChangeLog::query()
            ->where('catalog_change_batch_id', $firstBatch->id)
            ->count())->toBe(5);

        // Controleer dat de administrator precies één geschiedenisbatch heeft.
        expect(CatalogChangeBatch::query()
            ->where('user_id', $this->admin->id)
            ->count())->toBe(1);

        // Controleer dat de tweede gebruiker geen geschiedenisbatch heeft.
        expect(CatalogChangeBatch::query()
            ->where('user_id', $this->user->id)
            ->count())->toBe(0);

        // Controleer dat er precies één duplicatiebatch bestaat.
        expect(CatalogChangeBatch::query()
            ->where('operation', 'duplicate')
            ->count())->toBe(1);

        // Controleer de verdeling van de behouden wijzigingslogs.
        expect($remainingBatch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->count())->toBe(2);

        expect($remainingBatch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count())->toBe(3);

        expect($remainingBatch->logs()
            ->where('action', 'created')
            ->count())->toBe(5);

        // Controleer dat er geen update- of verwijderingslogs bestaan.
        expect($remainingBatch->logs()
            ->whereIn('action', ['updated', 'deleted'])
            ->count())->toBe(0);

        // Controleer het aantal wijzigingslogs per postzegel.
        expect($remainingBatch->logs()
            ->where('stamp_id', $firstStamp->id)
            ->count())->toBe(3);

        expect($remainingBatch->logs()
            ->where('stamp_id', $secondStamp->id)
            ->count())->toBe(2);

        // Controleer dat alle wijzigingslogs bij de doeleditie horen.
        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->count())->toBe(5);

        // Controleer dat de broneditie geen geschiedenisregistratie heeft.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        // Controleer dat alle doelvermeldingen ongewijzigd zijn.
        expect($this->targetEdition->stampEntries()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all())->toBe($originalTargetEntries);

        // Controleer dat alle doelwaarden ongewijzigd zijn.
        expect($this->targetEdition->stampValues()
            ->orderBy('stamp_catalog_values.id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all())->toBe($originalTargetValues);

        // Controleer dat alle oorspronkelijke bronvermeldingen ongewijzigd zijn.
        expect($this->sourceEdition->stampEntries()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all())->toBe($originalSourceEntries);

        // Controleer dat alle oorspronkelijke bronwaarden ongewijzigd zijn.
        expect($this->sourceEdition->stampValues()
            ->orderBy('stamp_catalog_values.id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all())->toBe($originalSourceValues);

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer de oorspronkelijke gebruikersgegevens.
        expect($this->admin->fresh()->getAttributes())->toBe($originalAdmin)
            ->and($this->user->fresh()->getAttributes())->toBe($originalUser);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('duplicatie zonder cataloguswaarden registreert uitsluitend nieuwe catalogusvermeldingen', function () {
    // Maak twee postzegels aan.
    $firstStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak twee catalogusvermeldingen aan in de broneditie.
    $firstSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => '1300a',
        'notes' => 'Eerste bronvermelding zonder waardeduplicatie.',
    ]);

    $secondSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => '1300b',
        'notes' => 'Tweede bronvermelding zonder waardeduplicatie.',
    ]);

    // Maak twee cataloguswaarden aan voor de eerste bronvermelding.
    $firstSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '65.75',
        'currency' => 'EUR',
        'notes' => 'Postfrisse cataloguswaarde die niet gekopieerd mag worden.',
        'needs_review' => false,
    ]);

    $firstSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '14.50',
        'currency' => 'EUR',
        'notes' => 'Gestempelde cataloguswaarde die niet gekopieerd mag worden.',
        'needs_review' => true,
    ]);

    // Maak twee cataloguswaarden aan voor de tweede bronvermelding.
    $secondSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Postfrisse nulwaarde die niet gekopieerd mag worden.',
        'needs_review' => false,
    ]);

    $secondSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '22.95',
        'currency' => 'USD',
        'notes' => null,
        'needs_review' => true,
    ]);

    // Bewaar alle oorspronkelijke bronvermeldingen.
    $originalSourceEntries = $this->sourceEdition->stampEntries()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke bronwaarden.
    $originalSourceValues = $this->sourceEdition->stampValues()
        ->orderBy('stamp_catalog_values.id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogus- en editiegegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Bewaar de oorspronkelijke postzegelgegevens.
    $originalFirstStamp = $firstStamp->fresh()->getAttributes();
    $originalSecondStamp = $secondStamp->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and(Stamp::count())->toBe(2)
        ->and(StampCatalogEntry::count())->toBe(2)
        ->and(StampCatalogValue::count())->toBe(4)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(2)
        ->and($this->sourceEdition->stampValues()->count())->toBe(4)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg voor verschillende tijdstempels tussen bron- en doelgegevens.
    $this->travel(1)->minutes();

    try {
        // Voer de duplicatie bewust zonder cataloguswaarden uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            false,
            $this->admin
        );

        // Controleer het resultaat van de duplicatie.
        expect($result)->toBe([
            'copied' => 2,
            'skipped' => 0,
        ]);

        // Controleer de databaseaantallen.
        expect(Catalog::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2)
            ->and(Stamp::count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(4)
            ->and(StampCatalogValue::count())->toBe(4)
            ->and(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(2);

        // Controleer de aantallen per cataloguseditie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(4)
            ->and($this->targetEdition->stampEntries()->count())->toBe(2)
            ->and($this->targetEdition->stampValues()->count())->toBe(0);

        // Haal de enige geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer de geschiedenisbatch.
        expect($batch->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($batch->user_id)->toBe($this->admin->id)
            ->and($batch->user_name)->toBe($this->admin->name)
            ->and($batch->operation)->toBe('duplicate')
            ->and($batch->catalog_name)->toBe($this->catalog->name)
            ->and($batch->catalog_edition_name)->toBe($this->targetEdition->edition_name)
            ->and($batch->logs()->count())->toBe(2);

        // Controleer de verdeling van de wijzigingslogs.
        expect($batch->logs()
            ->where('record_type', 'stamp_catalog_entry')
            ->count())->toBe(2);

        expect($batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->count())->toBe(0);

        // Controleer beide gekopieerde catalogusvermeldingen.
        foreach ([$firstSourceEntry, $secondSourceEntry] as $sourceEntry) {
            // Haal de nieuwe doelvermelding op.
            $targetEntry = $this->targetEdition->stampEntries()
                ->where('stamp_id', $sourceEntry->stamp_id)
                ->sole();

            // Controleer de gekopieerde catalogusvermelding.
            expect($targetEntry->id)->not->toBe($sourceEntry->id)
                ->and($targetEntry->catalog_edition_id)->toBe($this->targetEdition->id)
                ->and($targetEntry->stamp_id)->toBe($sourceEntry->stamp_id)
                ->and($targetEntry->catalog_number)->toBe($sourceEntry->catalog_number)
                ->and($targetEntry->notes)->toBe($sourceEntry->notes)
                ->and($targetEntry->created_at->gt($sourceEntry->created_at))->toBeTrue();

            // Controleer dat de bronvermelding cataloguswaarden bevat.
            expect($sourceEntry->values()->count())->toBe(2);

            // Controleer dat geen cataloguswaarden zijn gekopieerd.
            expect($targetEntry->values()->count())->toBe(0);

            // Controleer dat geen postfrisse cataloguswaarde is toegevoegd.
            expect($targetEntry->values()
                ->where('condition_id', $this->postfris->id)
                ->exists())->toBeFalse();

            // Controleer dat geen gestempelde cataloguswaarde is toegevoegd.
            expect($targetEntry->values()
                ->where('condition_id', $this->gestempeld->id)
                ->exists())->toBeFalse();

            // Haal de wijzigingslog van de catalogusvermelding op.
            $entryLog = $batch->logs()
                ->where('record_type', 'stamp_catalog_entry')
                ->where('record_id', $targetEntry->id)
                ->sole();

            // Controleer de wijzigingslog.
            expect($entryLog->catalog_change_batch_id)->toBe($batch->id)
                ->and($entryLog->catalog_edition_id)->toBe($this->targetEdition->id)
                ->and($entryLog->stamp_id)->toBe($sourceEntry->stamp_id)
                ->and($entryLog->stamp_series_id)->toBeNull()
                ->and($entryLog->condition_id)->toBeNull()
                ->and($entryLog->record_id)->toBe($targetEntry->id)
                ->and($entryLog->action)->toBe('created')
                ->and($entryLog->old_values)->toBeNull()
                ->and($entryLog->new_values)->toEqual($targetEntry->getAttributes());

            // Controleer dat geen cataloguswaardelogs voor deze postzegel bestaan.
            expect($batch->logs()
                ->where('stamp_id', $sourceEntry->stamp_id)
                ->where('record_type', 'stamp_catalog_value')
                ->count())->toBe(0);
        }

        // Controleer dat geen cataloguswaardelogs zijn aangemaakt.
        expect(CatalogChangeLog::query()
            ->where('record_type', 'stamp_catalog_value')
            ->count())->toBe(0);

        // Controleer dat geen logs aan waarderingscategorieën gekoppeld zijn.
        expect(CatalogChangeLog::query()
            ->whereNotNull('condition_id')
            ->count())->toBe(0);

        // Controleer dat beide wijzigingslogs nieuwe records betreffen.
        expect($batch->logs()
            ->where('action', 'created')
            ->count())->toBe(2);

        // Controleer dat geen update- of verwijderingslogs bestaan.
        expect($batch->logs()
            ->whereIn('action', ['updated', 'deleted'])
            ->count())->toBe(0);

        // Controleer het aantal wijzigingslogs per postzegel.
        expect($batch->logs()
            ->where('stamp_id', $firstStamp->id)
            ->count())->toBe(1);

        expect($batch->logs()
            ->where('stamp_id', $secondStamp->id)
            ->count())->toBe(1);

        // Controleer dat alle wijzigingslogs bij de doeleditie horen.
        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->count())->toBe(2);

        // Controleer dat de broneditie geen geschiedenisregistratie heeft.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->count())->toBe(0);

        // Controleer dat alle oorspronkelijke bronvermeldingen ongewijzigd zijn.
        expect($this->sourceEdition->stampEntries()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all())->toBe($originalSourceEntries);

        // Controleer dat alle oorspronkelijke bronwaarden ongewijzigd zijn.
        expect($this->sourceEdition->stampValues()
            ->orderBy('stamp_catalog_values.id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all())->toBe($originalSourceValues);

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer de oorspronkelijke postzegelgegevens.
        expect($firstStamp->fresh()->getAttributes())->toBe($originalFirstStamp)
            ->and($secondStamp->fresh()->getAttributes())->toBe($originalSecondStamp);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('ongeldige duplicatie naar dezelfde cataloguseditie veroorzaakt geen geschiedenisregistratie', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => '1400a',
        'notes' => 'Bronvermelding voor ongeldige duplicatie.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $postfrisValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '75.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een gestempelde cataloguswaarde aan.
    $gestempeldValue = $sourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '15.25',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde cataloguswaarde.',
        'needs_review' => true,
    ]);

    // Bewaar alle oorspronkelijke gegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalPostfrisValue = $postfrisValue->fresh()->getAttributes();
    $originalGestempeldValue = $gestempeldValue->fresh()->getAttributes();
    $originalAdmin = $this->admin->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1)
        ->and(CatalogEdition::count())->toBe(2)
        ->and(Stamp::count())->toBe(1)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(2)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->sourceEdition->stampValues()->count())->toBe(2)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg dat eventuele wijzigingen aan tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Probeer de broneditie naar zichzelf te dupliceren.
        expect(fn () => $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->sourceEdition,
            true,
            $this->admin
        ))->toThrow(
            InvalidArgumentException::class,
            'Een cataloguseditie kan niet naar zichzelf worden gekopieerd.'
        );

        // Controleer dat alle databaseaantallen ongewijzigd zijn.
        expect(Catalog::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2)
            ->and(Stamp::count())->toBe(1)
            ->and(StampCatalogEntry::count())->toBe(1)
            ->and(StampCatalogValue::count())->toBe(2)
            ->and(CatalogChangeBatch::count())->toBe(0)
            ->and(CatalogChangeLog::count())->toBe(0);

        // Controleer de aantallen per cataloguseditie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(1)
            ->and($this->sourceEdition->stampValues()->count())->toBe(2)
            ->and($this->targetEdition->stampEntries()->count())->toBe(0)
            ->and($this->targetEdition->stampValues()->count())->toBe(0);

        // Haal de oorspronkelijke catalogusvermelding opnieuw op.
        $remainingEntry = $this->sourceEdition->stampEntries()
            ->where('stamp_id', $stamp->id)
            ->sole();

        // Controleer dat de oorspronkelijke catalogusvermelding behouden is.
        expect($remainingEntry->id)->toBe($sourceEntry->id)
            ->and($remainingEntry->catalog_edition_id)->toBe($this->sourceEdition->id)
            ->and($remainingEntry->stamp_id)->toBe($stamp->id)
            ->and($remainingEntry->catalog_number)->toBe('1400a')
            ->and($remainingEntry->notes)
            ->toBe('Bronvermelding voor ongeldige duplicatie.')
            ->and($remainingEntry->values()->count())->toBe(2);

        // Haal de oorspronkelijke postfrisse cataloguswaarde opnieuw op.
        $remainingPostfrisValue = $remainingEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Controleer de oorspronkelijke postfrisse cataloguswaarde.
        expect($remainingPostfrisValue->id)->toBe($postfrisValue->id)
            ->and($remainingPostfrisValue->stamp_catalog_entry_id)->toBe($sourceEntry->id)
            ->and($remainingPostfrisValue->condition_id)->toBe($this->postfris->id)
            ->and($remainingPostfrisValue->catalog_value)->toBe('75.50')
            ->and($remainingPostfrisValue->currency)->toBe('EUR')
            ->and($remainingPostfrisValue->needs_review)->toBeFalse();

        // Haal de oorspronkelijke gestempelde cataloguswaarde opnieuw op.
        $remainingGestempeldValue = $remainingEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->sole();

        // Controleer de oorspronkelijke gestempelde cataloguswaarde.
        expect($remainingGestempeldValue->id)->toBe($gestempeldValue->id)
            ->and($remainingGestempeldValue->stamp_catalog_entry_id)->toBe($sourceEntry->id)
            ->and($remainingGestempeldValue->condition_id)->toBe($this->gestempeld->id)
            ->and($remainingGestempeldValue->catalog_value)->toBe('15.25')
            ->and($remainingGestempeldValue->currency)->toBe('EUR')
            ->and($remainingGestempeldValue->needs_review)->toBeTrue();

        // Controleer dat er geen geschiedenisbatch voor de broneditie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de andere editie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de gebruiker bestaat.
        expect(CatalogChangeBatch::query()
            ->where('user_id', $this->admin->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen duplicatiebatch bestaat.
        expect(CatalogChangeBatch::query()
            ->where('operation', 'duplicate')
            ->exists())->toBeFalse();

        // Controleer dat er geen wijzigingslogs voor de postzegel bestaan.
        expect(CatalogChangeLog::query()
            ->where('stamp_id', $stamp->id)
            ->exists())->toBeFalse();

        // Controleer dat alle oorspronkelijke gegevens ongewijzigd zijn.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition)
            ->and($stamp->fresh()->getAttributes())->toBe($originalStamp)
            ->and($sourceEntry->fresh()->getAttributes())->toBe($originalSourceEntry)
            ->and($postfrisValue->fresh()->getAttributes())->toBe($originalPostfrisValue)
            ->and($gestempeldValue->fresh()->getAttributes())->toBe($originalGestempeldValue)
            ->and($this->admin->fresh()->getAttributes())->toBe($originalAdmin);

        // Controleer dat er geen actieve geschiedenisbatch bestaat.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('ongeldige duplicatie tussen verschillende catalogussen veroorzaakt geen geschiedenisregistratie', function () {
    // Maak een tweede catalogus aan.
    $otherCatalog = Catalog::create([
        'name' => 'Andere Testcatalogus Duplicatiegeschiedenis',
        'abbreviation' => 'ATDG',
    ]);

    // Koppel hetzelfde land aan de tweede catalogus.
    $otherCatalog->countries()->attach($this->country->id);

    // Maak een cataloguseditie aan binnen de tweede catalogus.
    $otherEdition = CatalogEdition::create([
        'catalog_id' => $otherCatalog->id,
        'edition_name' => 'Andere Editie 2027',
        'edition_year' => 2027,
        'currency' => 'EUR',
    ]);

    // Maak twee postzegels aan.
    $firstStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2024,
    ]);

    $secondStamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak twee catalogusvermeldingen aan in de broneditie.
    $firstSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => '1500a',
        'notes' => 'Eerste bronvermelding voor ongeldige catalogusduplicatie.',
    ]);

    $secondSourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $secondStamp->id,
        'catalog_number' => '1500b',
        'notes' => 'Tweede bronvermelding voor ongeldige catalogusduplicatie.',
    ]);

    // Maak cataloguswaarden aan voor de eerste bronvermelding.
    $firstSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '85.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse bronwaarde.',
        'needs_review' => false,
    ]);

    $firstSourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '19.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde bronwaarde.',
        'needs_review' => true,
    ]);

    // Maak een cataloguswaarde aan voor de tweede bronvermelding.
    $secondSourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '0.00',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse nulwaarde.',
        'needs_review' => false,
    ]);

    // Maak een bestaande vermelding aan in de andere catalogus.
    $otherEntry = $otherEdition->stampEntries()->create([
        'stamp_id' => $firstStamp->id,
        'catalog_number' => 'ANDERS-1500A',
        'notes' => 'Bestaande vermelding binnen de andere catalogus.',
    ]);

    // Maak een afwijkende cataloguswaarde aan binnen de andere catalogus.
    $otherValue = $otherEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '125.95',
        'currency' => 'USD',
        'notes' => 'Bestaande waarde binnen de andere catalogus.',
        'needs_review' => false,
    ]);

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
    $originalEntries = StampCatalogEntry::query()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = StampCatalogValue::query()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogusgegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalOtherCatalog = $otherCatalog->fresh()->getAttributes();

    // Bewaar de oorspronkelijke editiegegevens.
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();
    $originalOtherEdition = $otherEdition->fresh()->getAttributes();

    // Bewaar de oorspronkelijke postzegelgegevens.
    $originalFirstStamp = $firstStamp->fresh()->getAttributes();
    $originalSecondStamp = $secondStamp->fresh()->getAttributes();

    // Bewaar de oorspronkelijke gebruikersgegevens.
    $originalAdmin = $this->admin->fresh()->getAttributes();

    // Controleer dat de catalogusedities verschillend zijn.
    expect($this->sourceEdition->id)->not->toBe($otherEdition->id);

    // Controleer dat de catalogusedities bij verschillende catalogussen horen.
    expect($this->sourceEdition->catalog_id)
        ->not->toBe($otherEdition->catalog_id);

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(2)
        ->and(CatalogEdition::count())->toBe(3)
        ->and(Stamp::count())->toBe(2)
        ->and(StampCatalogEntry::count())->toBe(3)
        ->and(StampCatalogValue::count())->toBe(4)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(2)
        ->and($this->sourceEdition->stampValues()->count())->toBe(3)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($otherEdition->stampEntries()->count())->toBe(1)
        ->and($otherEdition->stampValues()->count())->toBe(1)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Zorg dat eventuele wijzigingen aan tijdstempels zichtbaar worden.
    $this->travel(1)->minutes();

    try {
        // Probeer naar een editie van een andere catalogus te dupliceren.
        expect(fn () => $this->service->copyStampEntries(
            $this->sourceEdition,
            $otherEdition,
            true,
            $this->admin
        ))->toThrow(
            InvalidArgumentException::class,
            'De bron- en doeleditie moeten bij dezelfde catalogus horen.'
        );

        // Controleer dat alle databaseaantallen ongewijzigd zijn.
        expect(Catalog::count())->toBe(2)
            ->and(CatalogEdition::count())->toBe(3)
            ->and(Stamp::count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(3)
            ->and(StampCatalogValue::count())->toBe(4)
            ->and(CatalogChangeBatch::count())->toBe(0)
            ->and(CatalogChangeLog::count())->toBe(0);

        // Controleer de aantallen per cataloguseditie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(2)
            ->and($this->sourceEdition->stampValues()->count())->toBe(3)
            ->and($this->targetEdition->stampEntries()->count())->toBe(0)
            ->and($this->targetEdition->stampValues()->count())->toBe(0)
            ->and($otherEdition->stampEntries()->count())->toBe(1)
            ->and($otherEdition->stampValues()->count())->toBe(1);

        // Haal de bestaande vermelding van de andere catalogus opnieuw op.
        $remainingOtherEntry = $otherEdition->stampEntries()
            ->where('stamp_id', $firstStamp->id)
            ->sole();

        // Controleer dat de bestaande vermelding behouden is.
        expect($remainingOtherEntry->id)->toBe($otherEntry->id)
            ->and($remainingOtherEntry->catalog_edition_id)->toBe($otherEdition->id)
            ->and($remainingOtherEntry->stamp_id)->toBe($firstStamp->id)
            ->and($remainingOtherEntry->catalog_number)->toBe('ANDERS-1500A')
            ->and($remainingOtherEntry->notes)
            ->toBe('Bestaande vermelding binnen de andere catalogus.')
            ->and($remainingOtherEntry->values()->count())->toBe(1);

        // Haal de bestaande cataloguswaarde opnieuw op.
        $remainingOtherValue = $remainingOtherEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Controleer dat de bestaande cataloguswaarde behouden is.
        expect($remainingOtherValue->id)->toBe($otherValue->id)
            ->and($remainingOtherValue->stamp_catalog_entry_id)->toBe($otherEntry->id)
            ->and($remainingOtherValue->condition_id)->toBe($this->postfris->id)
            ->and($remainingOtherValue->catalog_value)->toBe('125.95')
            ->and($remainingOtherValue->currency)->toBe('USD')
            ->and($remainingOtherValue->notes)
            ->toBe('Bestaande waarde binnen de andere catalogus.')
            ->and($remainingOtherValue->needs_review)->toBeFalse();

        // Controleer dat de ontbrekende gestempelde waarde niet is toegevoegd.
        expect($remainingOtherEntry->values()
            ->where('condition_id', $this->gestempeld->id)
            ->exists())->toBeFalse();

        // Controleer dat de tweede postzegel niet is gekopieerd.
        expect($otherEdition->stampEntries()
            ->where('stamp_id', $secondStamp->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de broneditie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->sourceEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de reguliere doeleditie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de andere editie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $otherEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de gebruiker bestaat.
        expect(CatalogChangeBatch::query()
            ->where('user_id', $this->admin->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen duplicatiebatch bestaat.
        expect(CatalogChangeBatch::query()
            ->where('operation', 'duplicate')
            ->exists())->toBeFalse();

        // Controleer dat er geen wijzigingslogs voor beide postzegels bestaan.
        expect(CatalogChangeLog::query()
            ->whereIn('stamp_id', [$firstStamp->id, $secondStamp->id])
            ->exists())->toBeFalse();

        // Controleer dat alle oorspronkelijke catalogusvermeldingen ongewijzigd zijn.
        expect(StampCatalogEntry::query()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all())->toBe($originalEntries);

        // Controleer dat alle oorspronkelijke cataloguswaarden ongewijzigd zijn.
        expect(StampCatalogValue::query()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all())->toBe($originalValues);

        // Controleer de oorspronkelijke catalogusgegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($otherCatalog->fresh()->getAttributes())->toBe($originalOtherCatalog);

        // Controleer de oorspronkelijke editiegegevens.
        expect($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition)
            ->and($otherEdition->fresh()->getAttributes())->toBe($originalOtherEdition);

        // Controleer de oorspronkelijke postzegelgegevens.
        expect($firstStamp->fresh()->getAttributes())->toBe($originalFirstStamp)
            ->and($secondStamp->fresh()->getAttributes())->toBe($originalSecondStamp);

        // Controleer de oorspronkelijke gebruikersgegevens.
        expect($this->admin->fresh()->getAttributes())->toBe($originalAdmin);

        // Controleer dat er geen actieve geschiedenisbatch bestaat.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een fout tijdens het dupliceren van catalogusgegevens draait alle wijzigingen en geschiedenisregistratie terug', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => '1600a',
        'notes' => 'Bronvermelding voor transactionele rollback.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $postfrisValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '95.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een gestempelde cataloguswaarde aan.
    $gestempeldValue = $sourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '24.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde cataloguswaarde.',
        'needs_review' => true,
    ]);

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
    $originalEntries = StampCatalogEntry::query()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = StampCatalogValue::query()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogus- en editiegegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Bewaar de oorspronkelijke postzegelgegevens.
    $originalStamp = $stamp->fresh()->getAttributes();

    // Bewaar de oorspronkelijke gebruikersgegevens.
    $originalAdmin = $this->admin->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1)
        ->and(CatalogEdition::count())->toBe(2)
        ->and(Stamp::count())->toBe(1)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(2)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->sourceEdition->stampValues()->count())->toBe(2)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Bewaar de oorspronkelijke Eloquent-eventdispatcher.
    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    // Gebruik een tijdelijke kopie van de eventdispatcher.
    StampCatalogValue::setEventDispatcher(clone $originalDispatcher);

    // Houd het aantal pogingen tot het aanmaken van doelwaarden bij.
    $targetValueCreationAttempts = 0;

    // Houd bij of de gesimuleerde fout daadwerkelijk is bereikt.
    $failureTriggered = false;

    // Bewaar de tijdelijke databasetoestand vlak voor de fout.
    $stateBeforeFailure = [];

    // Zorg voor verschillende tijdstempels.
    $this->travel(1)->minutes();

    try {
        // Registreer een tijdelijke eventlistener.
        StampCatalogValue::creating(function (StampCatalogValue $value) use (
            &$targetValueCreationAttempts,
            &$failureTriggered,
            &$stateBeforeFailure
        ) {
            // Haal de catalogusvermelding van de nieuwe waarde op.
            $entry = StampCatalogEntry::query()
                ->findOrFail($value->stamp_catalog_entry_id);

            // Reageer uitsluitend op waarden binnen de doeleditie.
            if ($entry->catalog_edition_id !== $this->targetEdition->id) {
                return;
            }

            // Verhoog het aantal aanmaakpogingen.
            $targetValueCreationAttempts++;

            // Laat de eerste cataloguswaarde normaal aanmaken.
            if ($targetValueCreationAttempts !== 2) {
                return;
            }

            // Markeer dat de gesimuleerde fout is bereikt.
            $failureTriggered = true;

            // Haal de actieve geschiedenisbatch op.
            $activeBatch = $this->history->getActiveBatch();

            // Bewaar de tijdelijke toestand binnen de transactie.
            $stateBeforeFailure = [
                'batches' => CatalogChangeBatch::count(),
                'logs' => CatalogChangeLog::count(),
                'entries' => StampCatalogEntry::count(),
                'values' => StampCatalogValue::count(),
                'source_entries' => $this->sourceEdition->stampEntries()->count(),
                'source_values' => $this->sourceEdition->stampValues()->count(),
                'target_entries' => $this->targetEdition->stampEntries()->count(),
                'target_values' => $this->targetEdition->stampValues()->count(),
                'entry_logs' => CatalogChangeLog::query()
                    ->where('record_type', 'stamp_catalog_entry')
                    ->count(),
                'value_logs' => CatalogChangeLog::query()
                    ->where('record_type', 'stamp_catalog_value')
                    ->count(),
                'active_batch_id' => $activeBatch?->id,
                'active_batch_edition_id' => $activeBatch?->catalog_edition_id,
                'active_batch_user_id' => $activeBatch?->user_id,
                'active_batch_operation' => $activeBatch?->operation,
                'active_batch_logs' => $activeBatch?->logs()->count(),
            ];

            // Simuleer een fout voordat de tweede waarde wordt opgeslagen.
            throw new RuntimeException(
                'Gesimuleerde fout tijdens het dupliceren van cataloguswaarden.'
            );
        });

        // Voer de duplicatie uit en verwacht de gesimuleerde fout.
        expect(fn () => $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        ))->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens het dupliceren van cataloguswaarden.'
        );

        // Controleer dat de gesimuleerde fout daadwerkelijk is bereikt.
        expect($failureTriggered)->toBeTrue()
            ->and($targetValueCreationAttempts)->toBe(2);

        // Controleer dat er vóór de fout al wijzigingen waren uitgevoerd.
        expect($stateBeforeFailure['batches'])->toBe(1)
            ->and($stateBeforeFailure['logs'])->toBe(2)
            ->and($stateBeforeFailure['entries'])->toBe(2)
            ->and($stateBeforeFailure['values'])->toBe(3)
            ->and($stateBeforeFailure['source_entries'])->toBe(1)
            ->and($stateBeforeFailure['source_values'])->toBe(2)
            ->and($stateBeforeFailure['target_entries'])->toBe(1)
            ->and($stateBeforeFailure['target_values'])->toBe(1)
            ->and($stateBeforeFailure['entry_logs'])->toBe(1)
            ->and($stateBeforeFailure['value_logs'])->toBe(1);

        // Controleer de actieve geschiedenisbatch vlak voor de fout.
        expect($stateBeforeFailure['active_batch_id'])->not->toBeNull()
            ->and($stateBeforeFailure['active_batch_edition_id'])
            ->toBe($this->targetEdition->id)
            ->and($stateBeforeFailure['active_batch_user_id'])
            ->toBe($this->admin->id)
            ->and($stateBeforeFailure['active_batch_operation'])
            ->toBe('duplicate')
            ->and($stateBeforeFailure['active_batch_logs'])->toBe(2);

        // Controleer dat alle databasewijzigingen zijn teruggedraaid.
        expect(Catalog::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2)
            ->and(Stamp::count())->toBe(1)
            ->and(StampCatalogEntry::count())->toBe(1)
            ->and(StampCatalogValue::count())->toBe(2)
            ->and(CatalogChangeBatch::count())->toBe(0)
            ->and(CatalogChangeLog::count())->toBe(0);

        // Controleer de aantallen per cataloguseditie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(1)
            ->and($this->sourceEdition->stampValues()->count())->toBe(2)
            ->and($this->targetEdition->stampEntries()->count())->toBe(0)
            ->and($this->targetEdition->stampValues()->count())->toBe(0);

        // Controleer dat de tijdelijke geschiedenisbatch niet meer bestaat.
        expect(CatalogChangeBatch::query()
            ->whereKey($stateBeforeFailure['active_batch_id'])
            ->exists())->toBeFalse();

        // Controleer dat de tijdelijke wijzigingslogs niet meer bestaan.
        expect(CatalogChangeLog::query()
            ->where(
                'catalog_change_batch_id',
                $stateBeforeFailure['active_batch_id']
            )
            ->exists())->toBeFalse();

        // Controleer dat er geen geschiedenisbatch voor de doeleditie bestaat.
        expect(CatalogChangeBatch::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen wijzigingslogs voor de doeleditie bestaan.
        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat er geen wijzigingslogs voor de postzegel bestaan.
        expect(CatalogChangeLog::query()
            ->where('stamp_id', $stamp->id)
            ->exists())->toBeFalse();

        // Controleer dat alle oorspronkelijke catalogusvermeldingen behouden zijn.
        expect(StampCatalogEntry::query()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all())->toBe($originalEntries);

        // Controleer dat alle oorspronkelijke cataloguswaarden behouden zijn.
        expect(StampCatalogValue::query()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all())->toBe($originalValues);

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer de oorspronkelijke postzegelgegevens.
        expect($stamp->fresh()->getAttributes())->toBe($originalStamp);

        // Controleer de oorspronkelijke cataloguswaarden.
        expect($postfrisValue->fresh()->catalog_value)->toBe('95.75')
            ->and($postfrisValue->fresh()->needs_review)->toBeFalse()
            ->and($gestempeldValue->fresh()->catalog_value)->toBe('24.50')
            ->and($gestempeldValue->fresh()->needs_review)->toBeTrue();

        // Controleer de oorspronkelijke gebruikersgegevens.
        expect($this->admin->fresh()->getAttributes())->toBe($originalAdmin);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        StampCatalogValue::setEventDispatcher($originalDispatcher);

        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('een fout tijdens het opslaan van de wijzigingsgeschiedenis draait de volledige duplicatie terug', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => '1700a',
        'notes' => 'Bronvermelding voor rollback van geschiedenisregistratie.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $postfrisValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '115.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Maak een gestempelde cataloguswaarde aan.
    $gestempeldValue = $sourceEntry->values()->create([
        'condition_id' => $this->gestempeld->id,
        'catalog_value' => '32.50',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke gestempelde cataloguswaarde.',
        'needs_review' => true,
    ]);

    // Bewaar alle oorspronkelijke catalogusvermeldingen.
    $originalEntries = StampCatalogEntry::query()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
        ->all();

    // Bewaar alle oorspronkelijke cataloguswaarden.
    $originalValues = StampCatalogValue::query()
        ->orderBy('id')
        ->get()
        ->map(fn (StampCatalogValue $value) => $value->getAttributes())
        ->all();

    // Bewaar de oorspronkelijke catalogus- en editiegegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Bewaar de oorspronkelijke postzegel- en gebruikersgegevens.
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalAdmin = $this->admin->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(Catalog::count())->toBe(1)
        ->and(CatalogEdition::count())->toBe(2)
        ->and(Stamp::count())->toBe(1)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(2)
        ->and(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->sourceEdition->stampValues()->count())->toBe(2)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Bewaar de oorspronkelijke Eloquent-eventdispatcher.
    $originalDispatcher = CatalogChangeLog::getEventDispatcher();

    // Gebruik een tijdelijke kopie van de eventdispatcher.
    CatalogChangeLog::setEventDispatcher(clone $originalDispatcher);

    // Houd bij of de gesimuleerde fout daadwerkelijk is bereikt.
    $failureTriggered = false;

    // Bewaar de tijdelijke databasetoestand vlak voor de fout.
    $stateBeforeFailure = [];

    // Zorg voor verschillende tijdstempels.
    $this->travel(1)->minutes();

    try {
        // Registreer een tijdelijke eventlistener.
        CatalogChangeLog::created(function (CatalogChangeLog $log) use (
            &$failureTriggered,
            &$stateBeforeFailure
        ) {
            // Reageer uitsluitend op wijzigingslogs van de doeleditie.
            if ($log->catalog_edition_id !== $this->targetEdition->id) {
                return;
            }

            // Laat wijzigingslogs van catalogusvermeldingen normaal aanmaken.
            if ($log->record_type !== 'stamp_catalog_value') {
                return;
            }

            // Markeer dat de gesimuleerde fout is bereikt.
            $failureTriggered = true;

            // Haal de actieve geschiedenisbatch op.
            $activeBatch = $this->history->getActiveBatch();

            // Haal de zojuist opgeslagen cataloguswaarde op.
            $targetValue = StampCatalogValue::query()
                ->findOrFail($log->record_id);

            // Haal de bijbehorende catalogusvermelding op.
            $targetEntry = StampCatalogEntry::query()
                ->findOrFail($targetValue->stamp_catalog_entry_id);

            // Bewaar de tijdelijke toestand binnen de transactie.
            $stateBeforeFailure = [
                'batches' => CatalogChangeBatch::count(),
                'logs' => CatalogChangeLog::count(),
                'entries' => StampCatalogEntry::count(),
                'values' => StampCatalogValue::count(),
                'source_entries' => $this->sourceEdition->stampEntries()->count(),
                'source_values' => $this->sourceEdition->stampValues()->count(),
                'target_entries' => $this->targetEdition->stampEntries()->count(),
                'target_values' => $this->targetEdition->stampValues()->count(),
                'entry_logs' => CatalogChangeLog::query()
                    ->where('record_type', 'stamp_catalog_entry')
                    ->count(),
                'value_logs' => CatalogChangeLog::query()
                    ->where('record_type', 'stamp_catalog_value')
                    ->count(),
                'active_batch_id' => $activeBatch?->id,
                'active_batch_edition_id' => $activeBatch?->catalog_edition_id,
                'active_batch_user_id' => $activeBatch?->user_id,
                'active_batch_operation' => $activeBatch?->operation,
                'active_batch_logs' => $activeBatch?->logs()->count(),
                'log_id' => $log->id,
                'log_batch_id' => $log->catalog_change_batch_id,
                'log_record_type' => $log->record_type,
                'log_record_id' => $log->record_id,
                'log_action' => $log->action,
                'log_old_values' => $log->old_values,
                'log_new_values' => $log->new_values,
                'log_exists' => CatalogChangeLog::query()
                    ->whereKey($log->id)
                    ->exists(),
                'target_entry_id' => $targetEntry->id,
                'target_entry_edition_id' => $targetEntry->catalog_edition_id,
                'target_value_id' => $targetValue->id,
                'target_value_attributes' => $targetValue->getAttributes(),
                'target_value_needs_review' => $targetValue->needs_review,
            ];

            // Simuleer een fout nadat het wijzigingslog is opgeslagen.
            throw new RuntimeException(
                'Gesimuleerde fout tijdens het opslaan van de duplicatiegeschiedenis.'
            );
        });

        // Voer de duplicatie uit en verwacht de gesimuleerde fout.
        expect(fn () => $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        ))->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout tijdens het opslaan van de duplicatiegeschiedenis.'
        );

        // Controleer dat de gesimuleerde fout daadwerkelijk is bereikt.
        expect($failureTriggered)->toBeTrue();

        // Controleer dat er vóór de fout al wijzigingen waren uitgevoerd.
        expect($stateBeforeFailure['batches'])->toBe(1)
            ->and($stateBeforeFailure['logs'])->toBe(2)
            ->and($stateBeforeFailure['entries'])->toBe(2)
            ->and($stateBeforeFailure['values'])->toBe(3)
            ->and($stateBeforeFailure['source_entries'])->toBe(1)
            ->and($stateBeforeFailure['source_values'])->toBe(2)
            ->and($stateBeforeFailure['target_entries'])->toBe(1)
            ->and($stateBeforeFailure['target_values'])->toBe(1)
            ->and($stateBeforeFailure['entry_logs'])->toBe(1)
            ->and($stateBeforeFailure['value_logs'])->toBe(1);

        // Controleer de actieve geschiedenisbatch vlak voor de fout.
        expect($stateBeforeFailure['active_batch_id'])->not->toBeNull()
            ->and($stateBeforeFailure['active_batch_edition_id'])
            ->toBe($this->targetEdition->id)
            ->and($stateBeforeFailure['active_batch_user_id'])
            ->toBe($this->admin->id)
            ->and($stateBeforeFailure['active_batch_operation'])
            ->toBe('duplicate')
            ->and($stateBeforeFailure['active_batch_logs'])->toBe(2);

        // Controleer het daadwerkelijk opgeslagen wijzigingslog.
        expect($stateBeforeFailure['log_id'])->not->toBeNull()
            ->and($stateBeforeFailure['log_exists'])->toBeTrue()
            ->and($stateBeforeFailure['log_batch_id'])
            ->toBe($stateBeforeFailure['active_batch_id'])
            ->and($stateBeforeFailure['log_record_type'])
            ->toBe('stamp_catalog_value')
            ->and($stateBeforeFailure['log_record_id'])
            ->toBe($stateBeforeFailure['target_value_id'])
            ->and($stateBeforeFailure['log_action'])->toBe('created')
            ->and($stateBeforeFailure['log_old_values'])->toBeNull()
            ->and($stateBeforeFailure['log_new_values'])
            ->toEqual($stateBeforeFailure['target_value_attributes']);

        // Controleer de tijdelijk aangemaakte catalogusvermelding.
        expect($stateBeforeFailure['target_entry_id'])
            ->not->toBe($sourceEntry->id)
            ->and($stateBeforeFailure['target_entry_edition_id'])
            ->toBe($this->targetEdition->id);

        // Controleer de tijdelijk aangemaakte cataloguswaarde.
        expect($stateBeforeFailure['target_value_id'])
            ->not->toBe($postfrisValue->id)
            ->and($stateBeforeFailure['target_value_id'])
            ->not->toBe($gestempeldValue->id)
            ->and($stateBeforeFailure['target_value_needs_review'])
            ->toBeTrue();

        // Controleer dat alle databasewijzigingen zijn teruggedraaid.
        expect(Catalog::count())->toBe(1)
            ->and(CatalogEdition::count())->toBe(2)
            ->and(Stamp::count())->toBe(1)
            ->and(StampCatalogEntry::count())->toBe(1)
            ->and(StampCatalogValue::count())->toBe(2)
            ->and(CatalogChangeBatch::count())->toBe(0)
            ->and(CatalogChangeLog::count())->toBe(0);

        // Controleer de aantallen per cataloguseditie.
        expect($this->sourceEdition->stampEntries()->count())->toBe(1)
            ->and($this->sourceEdition->stampValues()->count())->toBe(2)
            ->and($this->targetEdition->stampEntries()->count())->toBe(0)
            ->and($this->targetEdition->stampValues()->count())->toBe(0);

        // Controleer dat de tijdelijke catalogusvermelding niet meer bestaat.
        expect(StampCatalogEntry::query()
            ->whereKey($stateBeforeFailure['target_entry_id'])
            ->exists())->toBeFalse();

        // Controleer dat de tijdelijke cataloguswaarde niet meer bestaat.
        expect(StampCatalogValue::query()
            ->whereKey($stateBeforeFailure['target_value_id'])
            ->exists())->toBeFalse();

        // Controleer dat het tijdelijke wijzigingslog niet meer bestaat.
        expect(CatalogChangeLog::query()
            ->whereKey($stateBeforeFailure['log_id'])
            ->exists())->toBeFalse();

        // Controleer dat de tijdelijke geschiedenisbatch niet meer bestaat.
        expect(CatalogChangeBatch::query()
            ->whereKey($stateBeforeFailure['active_batch_id'])
            ->exists())->toBeFalse();

        // Controleer dat alle wijzigingslogs van de batch zijn teruggedraaid.
        expect(CatalogChangeLog::query()
            ->where(
                'catalog_change_batch_id',
                $stateBeforeFailure['active_batch_id']
            )
            ->exists())->toBeFalse();

        // Controleer dat er geen wijzigingslogs voor de doeleditie bestaan.
        expect(CatalogChangeLog::query()
            ->where('catalog_edition_id', $this->targetEdition->id)
            ->exists())->toBeFalse();

        // Controleer dat alle oorspronkelijke catalogusvermeldingen behouden zijn.
        expect(StampCatalogEntry::query()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogEntry $entry) => $entry->getAttributes())
            ->all())->toBe($originalEntries);

        // Controleer dat alle oorspronkelijke cataloguswaarden behouden zijn.
        expect(StampCatalogValue::query()
            ->orderBy('id')
            ->get()
            ->map(fn (StampCatalogValue $value) => $value->getAttributes())
            ->all())->toBe($originalValues);

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer de oorspronkelijke postzegelgegevens.
        expect($stamp->fresh()->getAttributes())->toBe($originalStamp);

        // Controleer de oorspronkelijke cataloguswaarden.
        expect($postfrisValue->fresh()->catalog_value)->toBe('115.75')
            ->and($postfrisValue->fresh()->needs_review)->toBeFalse()
            ->and($gestempeldValue->fresh()->catalog_value)->toBe('32.50')
            ->and($gestempeldValue->fresh()->needs_review)->toBeTrue();

        // Controleer de oorspronkelijke gebruikersgegevens.
        expect($this->admin->fresh()->getAttributes())->toBe($originalAdmin);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        CatalogChangeLog::setEventDispatcher($originalDispatcher);

        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('de actieve geschiedenisbatch wordt na een succesvolle duplicatie opgeruimd en niet opnieuw gebruikt', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => '1800a',
        'notes' => 'Bronvermelding voor controle van batchopruiming.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '45.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke brongegevens.
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Bewaar de oorspronkelijke catalogus- en editiegegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Bewaar de oorspronkelijke postzegel- en gebruikersgegevens.
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalAdmin = $this->admin->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Bewaar de oorspronkelijke Eloquent-eventdispatcher.
    $originalDispatcher = StampCatalogValue::getEventDispatcher();

    // Gebruik een tijdelijke kopie van de eventdispatcher.
    StampCatalogValue::setEventDispatcher(clone $originalDispatcher);

    // Bewaar de geschiedenisservice die tijdens duplicatie wordt gebruikt.
    /** @var CatalogChangeHistoryService|null $historyDuringDuplication */
    $historyDuringDuplication = null;

    // Bewaar de actieve geschiedenisbatch tijdens duplicatie.
    /** @var CatalogChangeBatch|null $batchDuringDuplication */
    $batchDuringDuplication = null;

    // Houd bij of de tijdelijke eventlistener is uitgevoerd.
    $listenerTriggered = false;

    // Zorg voor verschillende tijdstempels.
    $this->travel(1)->minutes();

    try {
        // Registreer een tijdelijke eventlistener.
        StampCatalogValue::creating(function (StampCatalogValue $value) use (
            &$historyDuringDuplication,
            &$batchDuringDuplication,
            &$listenerTriggered
        ) {
            // Haal de bijbehorende catalogusvermelding op.
            $entry = StampCatalogEntry::query()
                ->findOrFail($value->stamp_catalog_entry_id);

            // Reageer uitsluitend op waarden binnen de doeleditie.
            if ($entry->catalog_edition_id !== $this->targetEdition->id) {
                return;
            }

            // Markeer dat de listener daadwerkelijk is uitgevoerd.
            $listenerTriggered = true;

            // Haal de geschiedenisservice uit de servicecontainer op.
            $historyDuringDuplication = app(
                CatalogChangeHistoryService::class
            );

            // Bewaar de actieve geschiedenisbatch.
            $batchDuringDuplication = $historyDuringDuplication
                ->getActiveBatch();
        });

        // Voer een succesvolle duplicatie uit.
        $result = $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        );

        // Controleer het resultaat van de duplicatie.
        expect($result)->toBe([
            'copied' => 1,
            'skipped' => 0,
        ]);

        // Controleer dat de tijdelijke listener is uitgevoerd.
        expect($listenerTriggered)->toBeTrue();

        // Controleer de geschiedenisservice tijdens duplicatie.
        expect($historyDuringDuplication)
            ->toBeInstanceOf(CatalogChangeHistoryService::class)
            ->toBe($this->history);

        // Controleer de actieve batch tijdens duplicatie.
        expect($batchDuringDuplication)
            ->toBeInstanceOf(CatalogChangeBatch::class);

        // Controleer de databaseaantallen na duplicatie.
        expect(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(2)
            ->and(StampCatalogValue::count())->toBe(2)
            ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
            ->and($this->sourceEdition->stampValues()->count())->toBe(1)
            ->and($this->targetEdition->stampEntries()->count())->toBe(1)
            ->and($this->targetEdition->stampValues()->count())->toBe(1);

        // Haal de opgeslagen geschiedenisbatch op.
        $batch = CatalogChangeBatch::query()->sole();

        // Controleer dat dit dezelfde batch is als tijdens duplicatie.
        expect($batchDuringDuplication?->id)->toBe($batch->id)
            ->and($batch->catalog_edition_id)->toBe($this->targetEdition->id)
            ->and($batch->user_id)->toBe($this->admin->id)
            ->and($batch->operation)->toBe('duplicate')
            ->and($batch->logs()->count())->toBe(2);

        // Controleer dat dezelfde service-instantie wordt gebruikt.
        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history)
            ->toBe($historyDuringDuplication);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull()
            ->and($historyDuringDuplication?->getActiveBatch())->toBeNull();

        // Haal de gekopieerde catalogusvermelding op.
        $targetEntry = $this->targetEdition->stampEntries()
            ->where('stamp_id', $stamp->id)
            ->sole();

        // Haal de gekopieerde cataloguswaarde op.
        $targetValue = $targetEntry->values()
            ->where('condition_id', $this->postfris->id)
            ->sole();

        // Controleer de gekopieerde cataloguswaarde.
        expect($targetValue->id)->not->toBe($sourceValue->id)
            ->and($targetValue->catalog_value)->toBe('45.75')
            ->and($targetValue->currency)->toBe('EUR')
            ->and($targetValue->needs_review)->toBeTrue();

        // Bewaar de oorspronkelijke geschiedenisbatch.
        $originalBatch = $batch->fresh()->getAttributes();

        // Bewaar alle oorspronkelijke wijzigingslogs.
        $originalLogs = CatalogChangeLog::query()
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
            ->all();

        // Bewaar de gekopieerde catalogusvermelding.
        $originalTargetEntry = $targetEntry->fresh()->getAttributes();

        // Bewaar de gekopieerde cataloguswaarde.
        $originalTargetValue = $targetValue->fresh()->getAttributes();

        // Haal het oorspronkelijke wijzigingslog van de waarde op.
        $valueLog = $batch->logs()
            ->where('record_type', 'stamp_catalog_value')
            ->sole();

        // Controleer de oorspronkelijke geregistreerde cataloguswaarde.
        expect($valueLog->record_id)->toBe($targetValue->id)
            ->and($valueLog->action)->toBe('created')
            ->and($valueLog->old_values)->toBeNull()
            ->and($valueLog->new_values)->toEqual($originalTargetValue);

        // Verplaats de testtijd opnieuw.
        $this->travel(1)->minutes();

        // Voer bewust een onafhankelijke modelwijziging uit.
        $targetValue->update([
            'catalog_value' => '62.50',
        ]);

        // Haal de gewijzigde cataloguswaarde opnieuw op.
        $updatedTargetValue = $targetValue->fresh();

        // Controleer dat de onafhankelijke wijziging is opgeslagen.
        expect($updatedTargetValue->id)->toBe($targetValue->id)
            ->and($updatedTargetValue->catalog_value)->toBe('62.50')
            ->and($updatedTargetValue->currency)->toBe('EUR')
            ->and($updatedTargetValue->needs_review)->toBeTrue()
            ->and($updatedTargetValue->getRawOriginal('updated_at'))
            ->not->toBe($originalTargetValue['updated_at']);

        // Controleer dat uitsluitend de waarde en tijdstempel zijn gewijzigd.
        $expectedTargetValue = $originalTargetValue;

        $expectedTargetValue['catalog_value'] =
            $updatedTargetValue->getRawOriginal('catalog_value');

        $expectedTargetValue['updated_at'] =
            $updatedTargetValue->getRawOriginal('updated_at');

        expect($updatedTargetValue->getAttributes())
            ->toBe($expectedTargetValue);

        // Controleer dat er geen nieuwe geschiedenisregistratie bestaat.
        expect(CatalogChangeBatch::count())->toBe(1)
            ->and(CatalogChangeLog::count())->toBe(2)
            ->and($batch->logs()->count())->toBe(2)
            ->and(StampCatalogEntry::count())->toBe(2)
            ->and(StampCatalogValue::count())->toBe(2);

        // Controleer dat er geen update-log is aangemaakt.
        expect(CatalogChangeLog::query()
            ->where('action', 'updated')
            ->exists())->toBeFalse();

        // Controleer dat de oorspronkelijke batch ongewijzigd is.
        expect($batch->fresh()->getAttributes())->toBe($originalBatch);

        // Controleer dat alle oorspronkelijke wijzigingslogs behouden zijn.
        expect(CatalogChangeLog::query()
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogChangeLog $log) => $log->getAttributes())
            ->all())->toBe($originalLogs);

        // Controleer dat het oorspronkelijke waardelog niet is aangepast.
        expect($valueLog->fresh()->new_values)
            ->toEqual($originalTargetValue);

        // Controleer de oorspronkelijke brongegevens.
        expect($sourceEntry->fresh()->getAttributes())
            ->toBe($originalSourceEntry)
            ->and($sourceValue->fresh()->getAttributes())
            ->toBe($originalSourceValue);

        // Controleer dat de gekopieerde vermelding ongewijzigd is.
        expect($targetEntry->fresh()->getAttributes())
            ->toBe($originalTargetEntry);

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer de oorspronkelijke postzegel- en gebruikersgegevens.
        expect($stamp->fresh()->getAttributes())->toBe($originalStamp)
            ->and($this->admin->fresh()->getAttributes())->toBe($originalAdmin);

        // Controleer opnieuw dat dezelfde service-instantie wordt gebruikt.
        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history)
            ->toBe($historyDuringDuplication);

        // Controleer dat er nog steeds geen actieve geschiedenisbatch bestaat.
        expect($this->history->getActiveBatch())->toBeNull()
            ->and($historyDuringDuplication?->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        StampCatalogValue::setEventDispatcher($originalDispatcher);

        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});

test('de actieve geschiedenisbatch wordt na een mislukte duplicatie opgeruimd en niet opnieuw gebruikt', function () {
    // Maak een postzegel aan.
    $stamp = Stamp::factory()->create([
        'country_id' => $this->country->id,
        'issue_year' => 2025,
    ]);

    // Maak een catalogusvermelding aan in de broneditie.
    $sourceEntry = $this->sourceEdition->stampEntries()->create([
        'stamp_id' => $stamp->id,
        'catalog_number' => '1900a',
        'notes' => 'Bronvermelding voor batchopruiming na rollback.',
    ]);

    // Maak een postfrisse cataloguswaarde aan.
    $sourceValue = $sourceEntry->values()->create([
        'condition_id' => $this->postfris->id,
        'catalog_value' => '55.75',
        'currency' => 'EUR',
        'notes' => 'Oorspronkelijke postfrisse cataloguswaarde.',
        'needs_review' => false,
    ]);

    // Bewaar de oorspronkelijke brongegevens.
    $originalSourceEntry = $sourceEntry->fresh()->getAttributes();
    $originalSourceValue = $sourceValue->fresh()->getAttributes();

    // Bewaar de oorspronkelijke catalogus- en editiegegevens.
    $originalCatalog = $this->catalog->fresh()->getAttributes();
    $originalSourceEdition = $this->sourceEdition->fresh()->getAttributes();
    $originalTargetEdition = $this->targetEdition->fresh()->getAttributes();

    // Bewaar de oorspronkelijke postzegel- en gebruikersgegevens.
    $originalStamp = $stamp->fresh()->getAttributes();
    $originalAdmin = $this->admin->fresh()->getAttributes();

    // Controleer de uitgangssituatie.
    expect(CatalogChangeBatch::count())->toBe(0)
        ->and(CatalogChangeLog::count())->toBe(0)
        ->and(StampCatalogEntry::count())->toBe(1)
        ->and(StampCatalogValue::count())->toBe(1)
        ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
        ->and($this->sourceEdition->stampValues()->count())->toBe(1)
        ->and($this->targetEdition->stampEntries()->count())->toBe(0)
        ->and($this->targetEdition->stampValues()->count())->toBe(0)
        ->and($this->history->getActiveBatch())->toBeNull();

    // Bewaar de oorspronkelijke Eloquent-eventdispatcher.
    $originalDispatcher = CatalogChangeLog::getEventDispatcher();

    // Gebruik een tijdelijke kopie van de eventdispatcher.
    CatalogChangeLog::setEventDispatcher(clone $originalDispatcher);

    // Bewaar de geschiedenisservice die tijdens duplicatie wordt gebruikt.
    /** @var CatalogChangeHistoryService|null $historyDuringDuplication */
    $historyDuringDuplication = null;

    // Bewaar de actieve geschiedenisbatch tijdens duplicatie.
    /** @var CatalogChangeBatch|null $batchDuringDuplication */
    $batchDuringDuplication = null;

    // Houd bij of de gesimuleerde fout daadwerkelijk is bereikt.
    $failureTriggered = false;

    // Bewaar de tijdelijke databasetoestand vlak voor de fout.
    $stateBeforeFailure = [];

    // Zorg voor verschillende tijdstempels.
    $this->travel(1)->minutes();

    try {
        // Registreer een tijdelijke eventlistener.
        CatalogChangeLog::created(function (CatalogChangeLog $log) use (
            &$historyDuringDuplication,
            &$batchDuringDuplication,
            &$failureTriggered,
            &$stateBeforeFailure
        ) {
            // Reageer uitsluitend op wijzigingslogs van de doeleditie.
            if ($log->catalog_edition_id !== $this->targetEdition->id) {
                return;
            }

            // Reageer uitsluitend op wijzigingslogs van cataloguswaarden.
            if ($log->record_type !== 'stamp_catalog_value') {
                return;
            }

            // Markeer dat de gesimuleerde fout is bereikt.
            $failureTriggered = true;

            // Haal de geschiedenisservice uit de servicecontainer op.
            $historyDuringDuplication = app(
                CatalogChangeHistoryService::class
            );

            // Bewaar de actieve geschiedenisbatch.
            $batchDuringDuplication = $historyDuringDuplication
                ->getActiveBatch();

            // Bewaar de tijdelijke toestand binnen de transactie.
            $stateBeforeFailure = [
                'batches' => CatalogChangeBatch::count(),
                'logs' => CatalogChangeLog::count(),
                'entries' => StampCatalogEntry::count(),
                'values' => StampCatalogValue::count(),
                'target_entries' => $this->targetEdition->stampEntries()->count(),
                'target_values' => $this->targetEdition->stampValues()->count(),
                'active_batch_id' => $batchDuringDuplication?->id,
                'active_batch_edition_id' => $batchDuringDuplication?->catalog_edition_id,
                'active_batch_user_id' => $batchDuringDuplication?->user_id,
                'active_batch_operation' => $batchDuringDuplication?->operation,
                'active_batch_logs' => $batchDuringDuplication?->logs()->count(),
                'log_id' => $log->id,
                'log_batch_id' => $log->catalog_change_batch_id,
                'target_value_id' => $log->record_id,
            ];

            // Simuleer een fout nadat het wijzigingslog is opgeslagen.
            throw new RuntimeException(
                'Gesimuleerde fout voor batchopruiming na mislukte duplicatie.'
            );
        });

        // Voer de duplicatie uit en verwacht de gesimuleerde fout.
        expect(fn () => $this->service->copyStampEntries(
            $this->sourceEdition,
            $this->targetEdition,
            true,
            $this->admin
        ))->toThrow(
            RuntimeException::class,
            'Gesimuleerde fout voor batchopruiming na mislukte duplicatie.'
        );

        // Controleer dat de gesimuleerde fout daadwerkelijk is bereikt.
        expect($failureTriggered)->toBeTrue();

        // Controleer de geschiedenisservice tijdens duplicatie.
        expect($historyDuringDuplication)
            ->toBeInstanceOf(CatalogChangeHistoryService::class)
            ->toBe($this->history);

        // Controleer de actieve batch tijdens duplicatie.
        expect($batchDuringDuplication)
            ->toBeInstanceOf(CatalogChangeBatch::class);

        // Controleer dat er vóór de fout daadwerkelijk wijzigingen waren.
        expect($stateBeforeFailure['batches'])->toBe(1)
            ->and($stateBeforeFailure['logs'])->toBe(2)
            ->and($stateBeforeFailure['entries'])->toBe(2)
            ->and($stateBeforeFailure['values'])->toBe(2)
            ->and($stateBeforeFailure['target_entries'])->toBe(1)
            ->and($stateBeforeFailure['target_values'])->toBe(1);

        // Controleer de tijdelijke geschiedenisbatch.
        expect($stateBeforeFailure['active_batch_id'])->not->toBeNull()
            ->and($stateBeforeFailure['active_batch_id'])
            ->toBe($batchDuringDuplication?->id)
            ->and($stateBeforeFailure['active_batch_edition_id'])
            ->toBe($this->targetEdition->id)
            ->and($stateBeforeFailure['active_batch_user_id'])
            ->toBe($this->admin->id)
            ->and($stateBeforeFailure['active_batch_operation'])
            ->toBe('duplicate')
            ->and($stateBeforeFailure['active_batch_logs'])->toBe(2)
            ->and($stateBeforeFailure['log_batch_id'])
            ->toBe($stateBeforeFailure['active_batch_id']);

        // Controleer dat alle databasewijzigingen zijn teruggedraaid.
        expect(CatalogChangeBatch::count())->toBe(0)
            ->and(CatalogChangeLog::count())->toBe(0)
            ->and(StampCatalogEntry::count())->toBe(1)
            ->and(StampCatalogValue::count())->toBe(1)
            ->and($this->sourceEdition->stampEntries()->count())->toBe(1)
            ->and($this->sourceEdition->stampValues()->count())->toBe(1)
            ->and($this->targetEdition->stampEntries()->count())->toBe(0)
            ->and($this->targetEdition->stampValues()->count())->toBe(0);

        // Controleer dat de tijdelijke geschiedenisbatch niet meer bestaat.
        expect(CatalogChangeBatch::query()
            ->whereKey($stateBeforeFailure['active_batch_id'])
            ->exists())->toBeFalse();

        // Controleer dat het tijdelijke wijzigingslog niet meer bestaat.
        expect(CatalogChangeLog::query()
            ->whereKey($stateBeforeFailure['log_id'])
            ->exists())->toBeFalse();

        // Controleer dat de tijdelijke cataloguswaarde niet meer bestaat.
        expect(StampCatalogValue::query()
            ->whereKey($stateBeforeFailure['target_value_id'])
            ->exists())->toBeFalse();

        // Controleer dat dezelfde service-instantie wordt gebruikt.
        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history)
            ->toBe($historyDuringDuplication);

        // Controleer dat de actieve geschiedenisbatch is opgeruimd.
        expect($this->history->getActiveBatch())->toBeNull()
            ->and($historyDuringDuplication?->getActiveBatch())->toBeNull();

        // Controleer dat de oorspronkelijke brongegevens behouden zijn.
        expect($sourceEntry->fresh()->getAttributes())
            ->toBe($originalSourceEntry)
            ->and($sourceValue->fresh()->getAttributes())
            ->toBe($originalSourceValue);

        // Herstel de dispatcher voordat de onafhankelijke wijziging plaatsvindt.
        CatalogChangeLog::setEventDispatcher($originalDispatcher);

        // Verplaats de testtijd opnieuw.
        $this->travel(1)->minutes();

        // Voer bewust een onafhankelijke modelwijziging uit.
        $sourceValue->update([
            'catalog_value' => '72.50',
        ]);

        // Haal de gewijzigde cataloguswaarde opnieuw op.
        $updatedSourceValue = $sourceValue->fresh();

        // Controleer dat de onafhankelijke wijziging is opgeslagen.
        expect($updatedSourceValue->id)->toBe($sourceValue->id)
            ->and($updatedSourceValue->catalog_value)->toBe('72.50')
            ->and($updatedSourceValue->currency)->toBe('EUR')
            ->and($updatedSourceValue->needs_review)->toBeTrue()
            ->and($updatedSourceValue->getRawOriginal('updated_at'))
            ->not->toBe($originalSourceValue['updated_at']);

        // Controleer dat uitsluitend de verwachte velden zijn gewijzigd.
        $expectedSourceValue = $originalSourceValue;

        $expectedSourceValue['catalog_value'] =
            $updatedSourceValue->getRawOriginal('catalog_value');

        $expectedSourceValue['needs_review'] =
            $updatedSourceValue->getRawOriginal('needs_review');

        $expectedSourceValue['updated_at'] =
            $updatedSourceValue->getRawOriginal('updated_at');

        expect($updatedSourceValue->getAttributes())
            ->toBe($expectedSourceValue);

        // Controleer dat de teruggedraaide batch niet opnieuw is gebruikt.
        expect(CatalogChangeBatch::count())->toBe(0)
            ->and(CatalogChangeLog::count())->toBe(0)
            ->and(StampCatalogEntry::count())->toBe(1)
            ->and(StampCatalogValue::count())->toBe(1);

        // Controleer dat er geen update-log is aangemaakt.
        expect(CatalogChangeLog::query()
            ->where('action', 'updated')
            ->exists())->toBeFalse();

        // Controleer dat de teruggedraaide geschiedenisbatch afwezig blijft.
        expect(CatalogChangeBatch::query()
            ->whereKey($stateBeforeFailure['active_batch_id'])
            ->exists())->toBeFalse();

        // Controleer dat er geen wijzigingslogs voor de batch bestaan.
        expect(CatalogChangeLog::query()
            ->where(
                'catalog_change_batch_id',
                $stateBeforeFailure['active_batch_id']
            )
            ->exists())->toBeFalse();

        // Controleer dat de doeleditie leeg is gebleven.
        expect($this->targetEdition->stampEntries()->count())->toBe(0)
            ->and($this->targetEdition->stampValues()->count())->toBe(0);

        // Controleer dat de oorspronkelijke catalogusvermelding behouden is.
        expect($sourceEntry->fresh()->getAttributes())
            ->toBe($originalSourceEntry);

        // Controleer de oorspronkelijke catalogus- en editiegegevens.
        expect($this->catalog->fresh()->getAttributes())->toBe($originalCatalog)
            ->and($this->sourceEdition->fresh()->getAttributes())->toBe($originalSourceEdition)
            ->and($this->targetEdition->fresh()->getAttributes())->toBe($originalTargetEdition);

        // Controleer de oorspronkelijke postzegel- en gebruikersgegevens.
        expect($stamp->fresh()->getAttributes())->toBe($originalStamp)
            ->and($this->admin->fresh()->getAttributes())->toBe($originalAdmin);

        // Controleer opnieuw dat dezelfde service-instantie wordt gebruikt.
        expect(app(CatalogChangeHistoryService::class))
            ->toBe($this->history)
            ->toBe($historyDuringDuplication);

        // Controleer dat er nog steeds geen actieve geschiedenisbatch bestaat.
        expect($this->history->getActiveBatch())->toBeNull()
            ->and($historyDuringDuplication?->getActiveBatch())->toBeNull();
    } finally {
        // Herstel altijd de oorspronkelijke Eloquent-eventdispatcher.
        CatalogChangeLog::setEventDispatcher($originalDispatcher);

        // Herstel altijd de oorspronkelijke testtijd.
        $this->travelBack();
    }
});
